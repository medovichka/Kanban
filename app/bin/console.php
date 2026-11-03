<?php

declare(strict_types=1);

use App\Bootstrap\Application;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$base = dirname(__DIR__);
require_once $base . '/vendor/autoload.php';

$command = $argv[1] ?? 'help';
$argument = $argv[2] ?? null;

$logger = new Logger('cli');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

$phinx = static function (string $subcommand) use ($base): int {
    $commandLine = sprintf('%s %s %s -c %s', escapeshellarg(PHP_BINARY), escapeshellarg($base . '/vendor/bin/phinx'), $subcommand, escapeshellarg($base . '/phinx.yml'));
    passthru($commandLine, $code);

    return (int) $code;
};

$container = null;
$boot = static function () use (&$container): void {
    if ($container === null) {
        $container = Application::boot();
    }
};

switch ($command) {
    case 'migrate':
        exit($phinx('migrate'));

    case 'migrate-status':
        exit($phinx('status'));

    case 'routes':
        $router = new App\Routing\Router();
        $routes = require $base . '/config/routes.php';
        $routes($router);

        foreach ($router->routes() as $route) {
            fwrite(STDOUT, sprintf("%-8s %-28s %s\n", implode('|', $route->methods), $route->pattern, $route->controller));
        }
        exit(0);

    case 'cron:list':
        $boot();

        $scheduler = $container->get(CronScheduler::class);
        foreach ($scheduler->describe() as $job) {
            fwrite(STDOUT, sprintf("%-22s %-14s следующий: %s\n", $job['name'], $job['schedule'], $job['next_run']));
        }
        exit(0);

    case 'cron:run':
        $boot();
        if ($argument === null) {
            fwrite(STDERR, 'Укажите имя задачи: php bin/console cron:run sessions.gc' . PHP_EOL);
            exit(1);
        }
        $result = $container->get(CronScheduler::class)->run($argument);
        fwrite(STDOUT, sprintf('[%s] %s' . PHP_EOL, $result->status, $result->message));
        exit($result->status === 'error' ? 1 : 0);

    case 'cron:tick':
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($base . '/bin/cron.php') . ' --tick', $code);
        exit((int) $code);

    case 'session:gc':
        $boot();

        $sessions = $container->get(App\Session\SessionDriver::class);
        $deleted = $sessions->gc(time());
        fwrite(STDOUT, 'Удалено просроченных сессий: ' . $deleted . PHP_EOL);
        exit(0);

    case 'test':
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($base . '/tests/smoke.php'), $code);
        exit((int) $code);

    case 'server':

        fwrite(STDOUT, 'Запустите: php -S 127.0.0.1:8000 -t public' . PHP_EOL);
        exit(0);

    default:
        fwrite(STDOUT, implode(PHP_EOL, [
            'php bin/console <команда>',
            '',
            '  migrate            применить миграции',
            '  migrate-status     что применено',
            '  cron:list          задачи и расписание',
            '  cron:run <name>    запустить задачу вручную',
            '  cron:tick          один тик планировщика',
            '  session:gc         удалить просроченные сессии',
            '  routes             таблица маршрутов',
            '  test               самопроверка (маршруты, cron, http)',
            '',
        ]) . PHP_EOL);
        exit($command === 'help' ? 0 : 1);
}
