<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controller;
use App\Http\InteractsWithSession;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Metrics;
use App\Models\User;
use App\Session\SessionManager;

final class RegisterController extends Controller
{
    use InteractsWithSession;

    public function handle(Request $request): Response
    {
        return $request->isPost() ? $this->store($request) : $this->show();
    }

    private function show(): Response
    {
        $session = $this->sessionOrCreate();
        [$flashes, $session] = $this->flashes($session);

        $body = View::nav($this->user()?->displayName()) . '<h1>Регистрация</h1>';

        if (isset($flashes['errors']) && is_array($flashes['errors'])) {
            $body .= '<p class="notice notice-error">' . $this->h(implode(' ', array_map('strval', $flashes['errors']))) . '</p>';
        }

        $body .= '<form class="stack" method="post" action="/register">'
            . '<label>Имя <input type="text" name="name" required maxlength="100" value="'
            . $this->h((string) ($flashes['old_name'] ?? '')) . '"></label>'
            . '<label>Email <input type="email" name="email" required maxlength="150" value="'
            . $this->h((string) ($flashes['old_email'] ?? '')) . '"></label>'
            . '<label>Пароль <input type="password" name="password" required minlength="8" maxlength="255"></label>'
            . '<input type="hidden" name="_csrf" value="' . $this->h(SessionManager::csrfToken($session)) . '">'
            . '<input type="submit" value="Зарегистрироваться">'
            . '</form>'
            . '<p>Уже есть аккаунт? <a href="/login">Войти</a></p>';

        return $this->attachSession($this->view(View::page('Регистрация', $body)), $session);
    }

    private function store(Request $request): Response
    {
        $session = $this->session();

        if ($session === null || !SessionManager::csrfValid($session, $request->input('_csrf'))) {
            return $this->error(419, 'Форма устарела, обновите страницу.');
        }

        $name = trim($request->input('name'));
        $email = mb_strtolower(trim($request->input('email')));
        $password = $request->input('password');
        $errors = [];

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Имя не может быть пустым или длиннее 100 символов.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Некорректный email.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Пароль должен быть хотя бы 8 символов.';
        }

        if ($errors === []) {
            try {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                Metrics::userRegistered();

                $session->destroy();
                $session = $this->startSessionInternal($user, ['registered_at' => time()]);

                return $this->attachSession(Response::redirect('/dashboard'), $session);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $errors[] = 'Пользователь с таким email уже существует.';
            } catch (\Throwable $e) {
                $this->logger()->error('Register failed', ['exception' => $e]);
                $errors[] = 'Не удалось сохранить пользователя.';
            }
        }

        $session = $session
            ->flash('errors', $errors)
            ->flash('old_name', $name)
            ->flash('old_email', $email);

        return $this->attachSession(Response::redirect('/register'), $session);
    }
}
