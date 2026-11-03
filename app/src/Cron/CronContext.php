<?php

declare(strict_types=1);

namespace App\Cron;

final class CronContext
{
    public function __construct(
        public readonly int $now,
        public readonly array $options = [],
        public readonly bool $verbose = false,
    ) {
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }
}
