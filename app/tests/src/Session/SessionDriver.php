<?php

declare(strict_types=1);

namespace App\Session;

interface SessionDriver
{
    public function read(string $id): ?SessionData;

    public function write(string $id, int $userId, array $payload, int $lifetime): void;

    public function destroy(string $id): void;

    public function destroyAllForUser(int $userId): int;

    public function gc(int $now): int;

    public function generateId(): string;

    public function healthy(): bool;

    public function name(): string;
}
