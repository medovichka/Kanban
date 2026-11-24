<?php

declare(strict_types=1);

namespace App;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Storage\APCng;

final class Metrics
{
    private static ?CollectorRegistry $registry = null;

    public static function enabled(): bool
    {
        return getenv('APP_METRICS_ENABLED') === '1'
            && extension_loaded('apcu')
            && apcu_enabled()
            && class_exists(CollectorRegistry::class);
    }

    public static function registry(): CollectorRegistry
    {
        return self::$registry ??= new CollectorRegistry(new APCng(decimalPrecision: 6), false);
    }

    public static function userRegistered(): void
    {
        self::safely(static fn () => self::counter(
            'app',
            'users_registered_total',
            'Зарегистрированные пользователи'
        )->inc());
    }

    public static function loginSucceeded(string $method): void
    {
        self::loginAttempt($method, 'success');
    }

    public static function loginFailed(string $method): void
    {
        self::loginAttempt($method, 'fail');
    }

    public static function httpRequest(string $method, string $route, int $status, float $duration): void
    {
        self::safely(static function () use ($method, $route, $status, $duration): void {
            self::counter(
                '',
                'http_requests_total',
                'HTTP-запросы, обработанные приложением',
                ['method', 'route', 'status']
            )->inc([$method, $route, (string) $status]);

            self::registry()
                ->getOrRegisterHistogram(
                    '',
                    'http_request_duration_seconds',
                    'Время обработки запроса в PHP, секунды',
                    ['method', 'route'],
                    [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 0.75, 1.0, 1.5, 2.5, 5.0, 7.5, 10.0]
                )
                ->observe($duration, [$method, $route]);
        });
    }

    public static function sessionEvent(string $driver, string $event): void
    {
        self::safely(static fn () => self::counter(
            'app',
            'session_events_total',
            'События сессий по драйверу и типу',
            ['driver', 'event']
        )->inc([$driver, $event]));
    }

    public static function cronJobFinished(string $name, string $status): void
    {
        self::safely(static fn () => self::counter(
            'app',
            'cron_runs_total',
            'Запуски cron-задач по результату (ok/error/skip)',
            ['job', 'status']
        )->inc([$name, $status]));
    }

    public static function cronJobSucceeded(string $name): void
    {
        self::safely(static fn () => self::registry()
            ->getOrRegisterGauge(
                'app',
                'cron_last_success_timestamp_seconds',
                'Unix-время последнего успешного запуска cron-задачи',
                ['job']
            )
            ->set(time(), [$name]));
    }

    public static function healthCheck(string $check, string $state): void
    {
        self::safely(static fn () => self::registry()
            ->getOrRegisterGauge('app', 'health_check', '1 — проверка проходит, 0 — нет', ['check'])
            ->set($state === 'ok' ? 1 : 0, [$check]));
    }

    public static function safely(callable $fn): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            $fn();
        } catch (\Throwable $e) {
            error_log('metrics: ' . $e->getMessage());
        }
    }

    private static function counter(string $namespace, string $name, string $help, array $labels = []): Counter
    {
        return self::registry()->getOrRegisterCounter($namespace, $name, $help, $labels);
    }

    private static function loginAttempt(string $method, string $result): void
    {
        self::safely(static fn () => self::counter(
            'app',
            'auth_login_total',
            'Попытки входа по методу и результату',
            ['method', 'result']
        )->inc([$method, $result]));
    }
}
