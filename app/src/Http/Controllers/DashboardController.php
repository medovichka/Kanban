<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Connection;
use App\Http\Controller;
use App\Http\InteractsWithSession;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Models\User;
use App\Session\Session;
use App\Session\SessionManager;

final class DashboardController extends Controller
{
    use InteractsWithSession;

    public function handle(Request $request): Response
    {
        $session = $this->session();
        $user = $this->user();

        if ($session === null || $user === null) {
            return $this->redirect('/login');
        }

        return $request->isPost()
            ? $this->logoutOthers($request, $session, $user)
            : $this->show($session, $user);
    }

    private function show(Session $session, User $user): Response
    {
        [$flashes, $session] = $this->flashes($session);

        $rows = Connection::capsule()->table('sessions')
            ->where('user_id', $session->userId)
            ->where('expires_at', '>', time())
            ->orderByDesc('last_activity_at')
            ->get()
            ->all();

        $body = View::nav($user->displayName()) . '<h1>Личный кабинет</h1>';

        if (isset($flashes['success'])) {
            $body .= '<p class="notice notice-ok">' . $this->h((string) $flashes['success']) . '</p>';
        }

        $body .= '<dl>'
            . '<dt>Имя</dt><dd>' . $this->h($user->displayName()) . '</dd>'
            . '<dt>Email</dt><dd>' . $this->h((string) $user->email) . '</dd>'
            . '<dt>Драйвер сессий</dt><dd>' . $this->h($this->sessions()->driver()->name()) . '</dd>'
            . '<dt>Текущая сессия</dt><dd>' . $this->h(substr($session->id, 0, 10)) . '… , истекает '
            . $this->h($session->expiresAtDate()->format('Y-m-d H:i')) . ' (TTL '
            . $this->h((string) $this->sessions()->lifetime()) . ' c)</dd>'
            . '</dl>'
            . '<h2>Активные сессии (' . count($rows) . ')</h2>'
            . '<table><tr><th>id</th><th>ip</th><th>user-agent</th><th>создана</th><th>активность</th></tr>';

        foreach ($rows as $row) {
            $body .= '<tr><td>' . $this->h(substr((string) $row->id, 0, 10)) . '…'
                . ($row->id === $session->id ? ' <b>(текущая)</b>' : '') . '</td>'
                . '<td>' . $this->h((string) ($row->ip_address ?? '')) . '</td>'
                . '<td>' . $this->h(mb_substr((string) ($row->user_agent ?? ''), 0, 60)) . '</td>'
                . '<td>' . $this->h(date('Y-m-d H:i', (int) $row->created_at)) . '</td>'
                . '<td>' . $this->h(date('Y-m-d H:i', (int) $row->last_activity_at)) . '</td></tr>';
        }

        $body .= '</table>'
            . '<form method="post" action="/dashboard">'
            . '<input type="hidden" name="action" value="logout_others">'
            . '<input type="hidden" name="_csrf" value="' . $this->h(SessionManager::csrfToken($session)) . '">'
            . '<input type="submit" value="Закрыть сессии на других устройствах">'
            . '</form>';

        return $this->attachSession($this->view(View::page('Кабинет', $body)), $session);
    }

    private function logoutOthers(Request $request, Session $session, User $user): Response
    {
        if (!SessionManager::csrfValid($session, $request->input('_csrf'))) {
            return $this->error(419, 'Форма устарела, обновите страницу.');
        }

        $deleted = Connection::capsule()->table('sessions')
            ->where('user_id', $session->userId)
            ->where('id', '!=', $session->id)
            ->delete();

        $session = $session->flash('success', 'Закрыто сессий на других устройствах: ' . (int) $deleted);

        return $this->attachSession(Response::redirect('/dashboard'), $session);
    }
}
