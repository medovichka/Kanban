<?php

declare(strict_types=1);

namespace App\Session;

final class ArraySessionDriver implements SessionDriver
{
    private array $sessions = [];

    public function read(string $id): ?SessionData
    {
        return $this->sessions[$id] ?? null;
    }

    public function write(string $id, int $userId, array $payload, int $lifetime): void
    {
        $existing = $this->sessions[$id] ?? null;

        $this->sessions[$id] = new SessionData(
            $id,
            $userId,
            $payload,
            time() + max(60, $lifetime),
            $existing?->createdAt ?? time(),
        );
    }

    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }

    public function destroyAllForUser(int $userId): int
    {
        $count = 0;
        foreach ($this->sessions as $id => $session) {
            if ($session->userId === $userId) {
                unset($this->sessions[$id]);
                $count++;
            }
        }

        return $count;
    }

    public function gc(int $now): int
    {
        $count = 0;
        foreach ($this->sessions as $id => $session) {
            if ($session->isExpired($now)) {
                unset($this->sessions[$id]);
                $count++;
            }
        }

        return $count;
    }

    public function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function healthy(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'array';
    }
}
