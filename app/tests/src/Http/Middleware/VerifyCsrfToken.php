<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Session\SessionManager;

final class VerifyCsrfToken
{
    public static function handle(Request $request, callable $next, SessionManager $sessions): Response
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $id = (string) $request->cookie($sessions->cookieName(), '');
        $session = $id !== '' ? $sessions->find($id) : null;

        if ($session === null) {
            return Response::html('<h1>419</h1><p>Сессия истекла, войдите заново.</p>', 419);
        }

        $token = $request->input('_csrf', $request->header('X-CSRF-Token') ?? '');

        if (!SessionManager::csrfValid($session, $token)) {
            return Response::html('<h1>419</h1><p>CSRF-токен недействителен.</p>', 419);
        }

        return $next($request);
    }
}
