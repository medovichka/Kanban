<?php

declare(strict_types=1);

namespace App\Session;

use App\Metrics;
use DateTimeImmutable;

final class Session
{
    private const DATA_KEY = 'session';

    public function __construct(
        public readonly string $id,
        public readonly int $userId,
        private readonly array $payload,
        public readonly int $expiresAt,
        public readonly int $createdAt,
        private readonly SessionDriver $driver,
        private readonly int $lifetime,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[self::DATA_KEY][$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $payload = $this->payload;
        $payload[self::DATA_KEY][$key] = $value;

        return $this->persist($payload);
    }

    public function forget(string $key): self
    {
        $payload = $this->payload;
        unset($payload[self::DATA_KEY][$key]);

        return $this->persist($payload);
    }

    public function flashes(): array
    {
        return (array) ($this->payload['flashes'] ?? []);
    }

    public function flash(string $key, mixed $value): self
    {
        $payload = $this->payload;
        $payload['flashes'][$key] = $value;

        return $this->persist($payload);
    }

    public function pullFlashes(): array
    {
        $flashes = $this->flashes();

        if ($flashes === []) {
            return [$this, []];
        }

        $payload = $this->payload;
        unset($payload['flashes']);

        return [$this->persist($payload), $flashes];
    }

    public function regenerate(): self
    {
        $this->driver->destroy($this->id);

        $id = $this->driver->generateId();
        $this->driver->write($id, $this->userId, $this->payload, $this->lifetime);

        Metrics::sessionEvent($this->driver->name(), 'regenerate');

        return new self($id, $this->userId, $this->payload, time() + $this->lifetime, time(), $this->driver, $this->lifetime);
    }

    public function destroy(): void
    {
        $this->driver->destroy($this->id);
        Metrics::sessionEvent($this->driver->name(), 'destroy');
    }

    public function expiresAtDate(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp($this->expiresAt);
    }

    private function persist(array $payload): self
    {
        $this->driver->write($this->id, $this->userId, $payload, $this->lifetime);
        Metrics::sessionEvent($this->driver->name(), 'touch');

        return new self($this->id, $this->userId, $payload, $this->expiresAt, $this->createdAt, $this->driver, $this->lifetime);
    }
}
