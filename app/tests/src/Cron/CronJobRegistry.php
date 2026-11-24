<?php

declare(strict_types=1);

namespace App\Cron;

final class CronJobRegistry
{
    private array $jobs = [];

    public function __construct(array $jobs = [])
    {
        foreach ($jobs as $job) {
            $this->add($job);
        }
    }

    public function add(JobInterface $job): void
    {
        $name = $job::name();

        if (preg_match('/^[a-z0-9_.-]{1,64}$/', $name) !== 1) {
            throw new \InvalidArgumentException('Имя cron-задачи должно быть [a-z0-9_.-]: ' . $name);
        }

        if (isset($this->jobs[$name])) {
            throw new \InvalidArgumentException('Cron-задача с именем "' . $name . '" уже зарегистрирована.');
        }

        $this->jobs[$name] = $job;
    }

    public function get(string $name): ?JobInterface
    {
        return $this->jobs[$name] ?? null;
    }

    public function all(): array
    {
        return $this->jobs;
    }

    public function has(string $name): bool
    {
        return isset($this->jobs[$name]);
    }
}
