<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Request;
use App\Metrics;
use App\Models\User;
use App\Session\Session;
use App\Session\SessionManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Monolog\Logger;
use Throwable;

final class Authenticator
{
    public function __construct(
        private readonly SessionManager $sessions,
        private readonly Logger $logger,
    ) {
    }

    public function manager(): SessionManager
    {
        return $this->sessions;
    }

    public function session(Request $request): ?Session
    {
        $id = (string) $request->cookie($this->sessions->cookieName(), '');

        return $id !== '' ? $this->sessions->find($id) : null;
    }

    public function sessionOrStart(Request $request): Session
    {
        return $this->session($request) ?? $this->sessions->start(0);
    }

    public function user(Request $request): ?User
    {
        $session = $this->session($request);

        if ($session === null || $session->userId <= 0) {
            return null;
        }

        return User::query()->find($session->userId);
    }

    public function check(Request $request): bool
    {
        return $this->user($request) !== null;
    }

    public function startSession(User $user, array $attributes = []): Session
    {
        return $this->sessions->start((int) $user->getKey(), $attributes);
    }

    public function register(Request $request, string $name, string $email, string $password): AuthResult
    {
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (UniqueConstraintViolationException) {
            return AuthResult::failure('Пользователь с таким email уже существует.');
        } catch (Throwable $e) {
            $this->logger->error('Register failed', ['exception' => $e]);

            return AuthResult::failure('Не удалось сохранить пользователя.');
        }

        Metrics::userRegistered();

        return $this->authenticate($request, $user, ['registered_at' => time()]);
    }

    public function login(Request $request, string $email, string $password): AuthResult
    {
        $user = User::query()->where('email', $email)->first();
        $hash = $user?->password;

        if ($user === null || !is_string($hash) || !password_verify($password, $hash)) {
            Metrics::loginFailed('password');

            return AuthResult::failure('Неверный email или пароль.');
        }

        Metrics::loginSucceeded('password');

        return $this->authenticate($request, $user, ['login_at' => time()]);
    }

    public function logout(Request $request): void
    {
        $this->session($request)?->destroy();
    }

    private function authenticate(Request $request, User $user, array $attributes): AuthResult
    {
        $this->session($request)?->destroy();
        $session = $this->startSession($user, $attributes);

        return AuthResult::success($user, $session);
    }
}
