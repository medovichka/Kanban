<?php

declare(strict_types=1);

namespace App\Cron;

use App\Metrics;
use DateTimeImmutable;
use Monolog\Logger;
use Throwable;

final class CronScheduler
{
    public function __construct(
        private readonly CronJobRegistry $registry,
        private readonly CronHistory $history,
        private readonly Logger $logger,
    ) {
    }

    public function tick(?DateTimeImmutable $now = null, ?array $only = null, bool $force = false): array
    {
        $now ??= new DateTimeImmutable();
        $results = [];

        foreach ($this->registry->all() as $name => $job) {
            if ($only !== null && !in_array($name, $only, true)) {
                continue;
            }

            $expression = new CronExpression($job::schedule());

            if (!$force && !$expression->isDue($now)) {
                continue;
            }

            $results[$name] = $this->runOne($name, $job, new CronContext($now->getTimestamp()));
        }

        return $results;
    }

    public function run(string $name, ?int $now = null): CronResult
    {
        $job = $this->registry->get($name);

        if ($job === null) {
            return CronResult::error('Задача не найдена: ' . $name);
        }

        return $this->runOne($name, $job, new CronContext($now ?? time()));
    }

    public function describe(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $lastSuccess = $this->history->lastSuccess();
        $rows = [];

        foreach ($this->registry->all() as $name => $job) {
            $rows[] = [
                'name' => $name,
                'schedule' => $job::schedule(),
                'next_run' => (new CronExpression($job::schedule()))->nextRun($now)->format('Y-m-d H:i:s'),
                'last_success' => $lastSuccess[$name] ?? null,
                'running' => false,
            ];
        }

        return $rows;
    }

    private function runOne(string $name, JobInterface $job, CronContext $context): CronResult
    {
        $lock = new CronLock();
        $startedAt = time();

        if (!$lock->acquire($name)) {
            $result = CronResult::skip('Задача уже выполняется в другом процессе');
            $this->finish($name, $result, $startedAt, $context);

            return $result;
        }

        $start = hrtime(true);

        try {
            $result = $job->run($context);
        } catch (Throwable $e) {
            $this->logger->error('Cron job failed', ['job' => $name, 'exception' => $e]);
            $result = CronResult::error($e->getMessage());
        } finally {
            $lock->release($name);
        }

        $duration = (hrtime(true) - $start) / 1e9;
        $result = new CronResult($result->status, $result->message, $duration);

        $this->finish($name, $result, $startedAt, $context);

        return $result;
    }

    private function finish(string $name, CronResult $result, int $startedAt, CronContext $context): void
    {
        $this->history->record($result->toRow($name, $startedAt));

        Metrics::cronJobFinished($name, $result->status);

        if ($result->status === CronResult::OK) {
            Metrics::cronJobSucceeded($name);
        }

        $this->logger->info('Cron job finished', [
            'job' => $name,
            'status' => $result->status,
            'duration_ms' => (int) round($result->duration * 1000),
            'message' => $result->message,
        ]);
    }
}
