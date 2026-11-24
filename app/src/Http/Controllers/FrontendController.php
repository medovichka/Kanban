<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controller;
use App\Http\InteractsWithSession;
use App\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Response;
use App\Http\View;
use App\Mvc\Request\AbstractRequest;
use App\Mvc\Response\ResponseModel;
use App\Session\SessionManager;

final class FrontendController extends Controller
{
    use InteractsWithSession;

    public function handle(Request $request): Response
    {
        return match ($this->action()) {
            'register' => $this->register($request),
            'login' => $this->login($request),
            'logout' => $this->logout($request),
            default => $this->notFound(),
        };
    }

    public function register(Request $request): Response
    {
        if (!$request->isPost()) {
            return $this->registerForm();
        }

        return $this->process(new RegisterRequest($request, $this->auth()), '/register');
    }

    public function login(Request $request): Response
    {
        if (!$request->isPost()) {
            return $this->loginForm();
        }

        return $this->process(new LoginRequest($request, $this->auth()), '/login');
    }

    public function logout(Request $request): Response
    {
        return $this->reply((new LogoutRequest($request, $this->auth()))->execute());
    }

    private function registerForm(): Response
    {
        $session = $this->sessionOrCreate();
        [$flashes, $session] = $this->flashes($session);
        $errors = is_array($flashes['errors'] ?? null) ? $flashes['errors'] : [];
        $old = is_array($flashes['old'] ?? null) ? $flashes['old'] : [];
        $csrf = SessionManager::csrfToken($session);

        $body = View::nav($this->user()?->displayName(), $csrf) . '<h1>Регистрация</h1>';

        if ($errors !== []) {
            $body .= '<p class="notice notice-error">' . $this->h(implode(' ', array_map('strval', $errors))) . '</p>';
        }

        $body .= '<form class="stack" method="post" action="/register">'
            . '<label>Имя <input type="text" name="name" required maxlength="100" value="'
            . $this->h((string) ($old['name'] ?? '')) . '"></label>'
            . '<label>Email <input type="email" name="email" required maxlength="150" value="'
            . $this->h((string) ($old['email'] ?? '')) . '"></label>'
            . '<label>Пароль <input type="password" name="password" required minlength="8" maxlength="255"></label>'
            . '<input type="hidden" name="_csrf" value="' . $this->h($csrf) . '">'
            . '<input type="submit" value="Зарегистрироваться">'
            . '</form>'
            . '<p>Уже есть аккаунт? <a href="/login">Войти</a></p>';

        return $this->attachSession($this->view(View::page('Регистрация', $body)), $session);
    }

    private function loginForm(): Response
    {
        $session = $this->sessionOrCreate();
        [$flashes, $session] = $this->flashes($session);
        $errors = is_array($flashes['errors'] ?? null) ? $flashes['errors'] : [];
        $old = is_array($flashes['old'] ?? null) ? $flashes['old'] : [];
        $csrf = SessionManager::csrfToken($session);

        $body = View::nav(null, $csrf) . '<h1>Вход</h1>';

        if ($errors !== []) {
            $body .= '<p class="notice notice-error">' . $this->h(implode(' ', array_map('strval', $errors))) . '</p>';
        }

        $body .= '<form class="stack" method="post" action="/login">'
            . '<input type="email" name="email" placeholder="Email" required maxlength="150" value="'
            . $this->h((string) ($old['email'] ?? '')) . '">'
            . '<input type="password" name="password" placeholder="Пароль" required minlength="8">'
            . '<input type="hidden" name="_csrf" value="' . $this->h($csrf) . '">'
            . '<input type="submit" value="Войти">'
            . '</form>'
            . '<p>Нет аккаунта? <a href="/register">Зарегистрироваться</a></p>';

        return $this->attachSession($this->view(View::page('Вход', $body)), $session);
    }

    private function process(AbstractRequest $request, string $redirectTo): Response
    {
        if ($request->isValid()) {
            return $this->reply($request->execute());
        }

        $old = array_intersect_key($request->values(), ['name' => true, 'email' => true]);
        $session = $this->auth()->sessionOrStart($this->request)
            ->flash('errors', $request->errors())
            ->flash('old', $old);

        return $this->attachSession(Response::redirect($redirectTo), $session);
    }

    private function reply(ResponseModel $model): Response
    {
        $response = $model->toResponse();

        if ($model->forgetsSession()) {
            return $this->detachSession($response);
        }

        $session = $model->session();

        return $session !== null ? $this->attachSession($response, $session) : $response;
    }
}
