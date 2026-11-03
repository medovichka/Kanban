<?php

declare(strict_types=1);

namespace App\Session;

final class SessionData
{
    public function __construct(
        public readonly string $id,
        public readonly int $userId,
        public readonly array $payload,
        public readonly int $expiresAt,
        public readonly int $createdAt,
    ) {
    }

    public function isExpired(int $now): bool
    {
        return $this->expiresAt <= $now;
    }
}
