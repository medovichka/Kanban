<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Database\Connection;
use App\Http\Controller;
use App\Http\InteractsWithSession;
use App\Http\Request;
use App\Http\Response;
use App\Metrics;
use App\Session\Session;
use App\Session\SessionManager;

final class SessionController extends Controller
{
    use InteractsWithSession;

    public function handle(Request $request): Response
    {
        return match ($request->method) {
            'GET' => $request->path === '/api/me' ? $this->me() : $this->listSessions(),
            'DELETE' => $this->destroy($request),
            'POST' => $this->destroyAll($request),
            default => $this->json(['error' => 'method_not_allowed'], 405),
        };
    }

    private function me(): Response
    {
        $session = $this->session();
        $user = $this->user();

        if ($session === null || $user === null) {
            return $this->json(['error' => 'unauthorized'], 401);
        }

        return $this->json([
            'user' => $user->toApi(),
            'session' => [
                'id' => self::shortId($session),
                'driver' => $this->sessions()->driver()->name(),
                'expires_at' => date(DATE_ATOM, $session->expiresAt),
            ],
            'csrf_token' => SessionManager::csrfToken($session),
        ]);
    }

    private function listSessions(): Response
    {
        $session = $this->session();

        if ($session === null) {
            return $this->json(['error' => 'unauthorized'], 401);
        }

        $rows = Connection::capsule()->table('sessions')
            ->where('user_id', $session->userId)
            ->where('expires_at', '>', time())
            ->orderByDesc('last_activity_at')
            ->get()
            ->all();

        $sessions = [];
        foreach ($rows as $row) {
            $sessions[] = [
                'id' => substr((string) $row->id, 0, 10) . '…',
                'current' => $row->id === $session->id,
                'ip' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'created_at' => date(DATE_ATOM, (int) $row->created_at),
                'last_activity_at' => date(DATE_ATOM, (int) $row->last_activity_at),
                'expires_at' => date(DATE_ATOM, (int) $row->expires_at),
            ];
        }

        return $this->json(['sessions' => $sessions]);
    }

    private function destroy(Request $request): Response
    {
        $session = $this->session();

        if ($session === null) {
            return $this->json(['error' => 'unauthorized'], 401);
        }

        if (!SessionManager::csrfValid($session, $request->header('X-CSRF-Token') ?? '')) {
            return $this->json(['error' => 'csrf_token_mismatch'], 419);
        }

        $prefix = (string) $this->parameter('id', '');

        foreach (Connection::capsule()->table('sessions')->where('user_id', $session->userId)->pluck('id')->all() as $id) {
            if (str_starts_with((string) $id, $prefix)) {
                $this->sessions()->driver()->destroy((string) $id);
                Metrics::sessionEvent($this->sessions()->driver()->name(), 'revoke');

                return $this->json(['deleted' => true]);
            }
        }

        return $this->json(['error' => 'not_found'], 404);
    }

    private function destroyAll(Request $request): Response
    {
        $session = $this->session();

        if ($session === null) {
            return $this->json(['error' => 'unauthorized'], 401);
        }

        if (!SessionManager::csrfValid($session, $request->header('X-CSRF-Token') ?? '')) {
            return $this->json(['error' => 'csrf_token_mismatch'], 419);
        }

        $deleted = Connection::capsule()->table('sessions')
            ->where('user_id', $session->userId)
            ->where('id', '!=', $session->id)
            ->delete();

        Metrics::sessionEvent($this->sessions()->driver()->name(), 'revoke_all');

        return $this->json(['deleted' => $deleted]);
    }

    private static function shortId(Session $session): string
    {
        return substr($session->id, 0, 10) . '…';
    }
}
