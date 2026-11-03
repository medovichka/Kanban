<?php

declare(strict_types=1);

namespace App\Session;

use App\Metrics;

final class SessionManager
{
    public const COOKIE = 'kanban_session';

    public function __construct(
        private readonly SessionDriver $driver,
        private readonly int $lifetime = 86400,
        private readonly ?string $cookieName = null,
    ) {
    }

    public function driver(): SessionDriver
    {
        return $this->driver;
    }

    public function lifetime(): int
    {
        return $this->lifetime;
    }

    public function cookieName(): string
    {
        return $this->cookieName ?? self::COOKIE;
    }

    public function start(int $userId, array $attributes = []): Session
    {
        $id = $this->driver->generateId();
        $now = time();
        $payload = ['session' => $attributes, 'created' => $now];

        $this->driver->write($id, $userId, $payload, $this->lifetime);
        Metrics::sessionEvent($this->driver->name(), 'create');

        return new Session($id, $userId, $payload, $now + $this->lifetime, $now, $this->driver, $this->lifetime);
    }

    public function find(string $id): ?Session
    {
        if (!self::isValidId($id)) {
            return null;
        }

        $data = $this->driver->read($id);

        if ($data === null) {
            return null;
        }

        if ($data->isExpired(time())) {
            $this->driver->destroy($id);
            Metrics::sessionEvent($this->driver->name(), 'expired');

            return null;
        }

        $this->driver->write($id, $data->userId, $data->payload, $this->lifetime);

        return new Session($id, $data->userId, $data->payload, time() + $this->lifetime, $data->createdAt, $this->driver, $this->lifetime);
    }

    public function gc(): int
    {
        return $this->driver->gc(time());
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $id) === 1;
    }

    public static function csrfToken(Session $session): string
    {
        $token = $session->get('_csrf');

        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $session->set('_csrf', $token);
        }

        return $token;
    }

    public static function csrfValid(Session $session, string $token): bool
    {
        $expected = $session->get('_csrf');

        return is_string($expected) && $token !== '' && hash_equals($expected, $token);
    }
}
