<?php

declare(strict_types=1);

use App\Metrics;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    return ['register' => static fn (): null => null, 'finish' => static fn (string $route, float $duration): null => null];
}

require_once $autoload;

if (!Metrics::enabled()) {
    return ['register' => static fn (): null => null, 'finish' => static fn (string $route, float $duration): null => null];
}

$httpMethod = static function (): string {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));

    return in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true) ? $method : 'OTHER';
};

$register = static function (): void {
    Metrics::safely(static function (): void {
        $registry = Metrics::registry();

        $registry->getOrRegisterCounter('app', 'auth_login_total', 'Попытки входа по методу и результату', ['method', 'result'])
            ->incBy(0, ['password', 'success']);
        $registry->getOrRegisterCounter('app', 'users_registered_total', 'Зарегистрированные пользователи')
            ->incBy(0);
        $registry->getOrRegisterCounter('app', 'session_events_total', 'События сессий по драйверу и типу', ['driver', 'event'])
            ->incBy(0, ['database', 'create']);
        $registry->getOrRegisterCounter('app', 'cron_runs_total', 'Запуски cron-задач по результату (ok/error/skip)', ['job', 'status'])
            ->incBy(0, ['unknown', 'skip']);
        $registry->getOrRegisterGauge('app', 'health_check', '1 — проверка проходит, 0 — нет', ['check'])
            ->set(1, ['database']);
    });
};

$finish = static function (string $route, float $duration) use ($httpMethod): void {
    $status = http_response_code();
    $status = is_int($status) ? $status : 200;

    $lastError = error_get_last();
    if ($lastError !== null && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $status = 500;
    }

    Metrics::httpRequest($httpMethod(), $route, $status, $duration);
};

return ['register' => $register, 'finish' => $finish];
