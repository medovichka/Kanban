<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controller;
use App\Http\InteractsWithSession;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Models\User;
use App\Metrics;
use App\Session\SessionManager;

final class LoginController extends Controller
{
    use InteractsWithSession;

    public function handle(Request $request): Response
    {
        return $request->isPost() ? $this->attempt($request) : $this->form();
    }

    private function form(): Response
    {
        $session = $this->sessionOrCreate();
        [$flashes, $session] = $this->flashes($session);

        $body = View::nav(null) . '<h1>Вход</h1>';

        if (isset($flashes['success'])) {
            $body .= '<p class="notice notice-ok">' . $this->h((string) $flashes['success']) . '</p>';
        }

        if (isset($flashes['error'])) {
            $body .= '<p class="notice notice-error">' . $this->h((string) $flashes['error']) . '</p>';
        }

        $body .= '<form class="stack" method="post" action="/login">'
            . '<input type="email" name="email" placeholder="Email" required maxlength="150">'
            . '<input type="password" name="password" placeholder="Пароль" required>'
            . '<input type="hidden" name="_csrf" value="' . $this->h(SessionManager::csrfToken($session)) . '">'
            . '<input type="submit" value="Войти">'
            . '</form>'
            . '<p>Нет аккаунта? <a href="/register">Зарегистрироваться</a></p>';

        return $this->attachSession($this->view(View::page('Вход', $body)), $session);
    }

    private function attempt(Request $request): Response
    {
        $session = $this->session();

        if ($session === null || !SessionManager::csrfValid($session, $request->input('_csrf'))) {
            Metrics::loginFailed('password');

            return $this->error(419, 'Форма устарела. Обновите страницу и попробуйте ещё раз.');
        }

        $email = mb_strtolower(trim($request->input('email')));
        $password = $request->input('password');

        $user = User::query()->where('email', $email)->first();
        $hash = $user?->password;

        if ($user === null || !is_string($hash) || !password_verify($password, $hash)) {
            Metrics::loginFailed('password');

            $session = $session->flash('error', 'Неверный email или пароль.');

            return $this->attachSession(Response::redirect('/login'), $session);
        }

        $session->destroy();
        $session = $this->startSessionInternal($user, ['login_at' => time()]);

        Metrics::loginSucceeded('password');

        return $this->attachSession(Response::redirect('/dashboard'), $session);
    }
}
