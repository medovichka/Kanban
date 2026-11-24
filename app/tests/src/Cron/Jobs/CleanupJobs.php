<?php

declare(strict_types=1);

namespace App\Cron\Jobs;

use App\Cron\CronContext;
use App\Cron\CronJobRegistry;
use App\Cron\CronResult;
use App\Cron\JobInterface;
use App\Database\Connection;
use App\Metrics;
use App\Session\SessionDriver;
use App\Session\SessionManager;

final class CleanupJobs
{
    public static function registerAll(CronJobRegistry $registry, SessionDriver $sessions): void
    {
        $registry->add(new SessionsGcJob($sessions));
        $registry->add(new CronRunsPruneJob());
        $registry->add(new HealthProbeJob());
    }
}

final class SessionsGcJob implements JobInterface
{
    public function __construct(private readonly SessionDriver $sessions)
    {
    }

    public static function name(): string
    {
        return 'sessions.gc';
    }

    public static function schedule(): string
    {
        return '*/10 * * * *';
    }

    public function run(CronContext $context): CronResult
    {
        $deleted = $this->sessions->gc($context->now);

        return CronResult::ok(sprintf('Удалено просроченных сессий: %d', $deleted));
    }
}

final class CronRunsPruneJob implements JobInterface
{
    public function __construct(private readonly int $keepDays = 30)
    {
    }

    public static function name(): string
    {
        return 'cron.history_prune';
    }

    public static function schedule(): string
    {
        return '0 3 * * *';
    }

    public function run(CronContext $context): CronResult
    {
        $threshold = date('Y-m-d H:i:s', $context->now - $this->keepDays * 86400);
        $deleted = Connection::capsule()->table('cron_runs')->where('finished_at', '<', $threshold)->delete();

        return CronResult::ok(sprintf('Удалено записей журнала старше %d дней: %d', $this->keepDays, $deleted));
    }
}

final class HealthProbeJob implements JobInterface
{
    public function __construct(private readonly SessionManager $sessions)
    {
    }

    public static function name(): string
    {
        return 'app.health_probe';
    }

    public static function schedule(): string
    {
        return '*/5 * * * *';
    }

    public function run(CronContext $context): CronResult
    {
        $healthy = $this->sessions->driver()->healthy();
        Metrics::healthCheck('session_table', $healthy ? 'ok' : 'fail');

        if (!$healthy) {
            return CronResult::error('Таблица sessions недоступна. Проверьте миграции: phinx migrate.');
        }

        $active = Connection::capsule()->table('sessions')->where('expires_at', '>', $context->now)->count();

        Metrics::safely(static fn () => Metrics::registry()
            ->getOrRegisterGauge('app', 'active_sessions', 'Живые (неистёкшие) сессии')
            ->set($active));

        return CronResult::ok(sprintf('Таблица сессий доступна, живых сессий: %d', $active));
    }
}
