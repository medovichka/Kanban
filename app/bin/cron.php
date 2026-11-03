<?php

declare(strict_types=1);

use App\Bootstrap\Application;
use App\Cron\CronResult;
use App\Cron\CronScheduler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$base = dirname(__DIR__);

$usage = static function (): void {
    fwrite(STDOUT, implode(PHP_EOL, [
        'Использование: php bin/cron.php <команда> [опции]',
        '',
        '  --tick             запустить задачи, которым пора сейчас (вызывается раз в минуту)',
        '  --list             показать задачи, расписание и ближайший запуск',
        '  --job <name>       запустить одну задачу вручную, не глядя на расписание',
        '  --now "<время>"    подменить «сейчас»: 2026-01-01 03:00:00',
        '  -v                 подробный вывод',
        '',
    ]) . PHP_EOL);
};

$options = getopt('v', ['tick', 'list', 'job:', 'now:', 'help']);

if ($options === false || isset($options['help'])
    || (!isset($options['tick']) && !isset($options['list']) && !isset($options['job']))) {
    $usage();
    exit(isset($options['help']) ? 0 : 1);
}

require_once $base . '/vendor/autoload.php';

$logger = new Logger('cron');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

try {
    $container = Application::current() ?? Application::boot();
    $container->instance(Logger::class, $logger);

    $scheduler = $container->get(CronScheduler::class);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Не удалось собрать планировщик: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

try {
    $now = isset($options['now']) ? new DateTimeImmutable((string) $options['now']) : new DateTimeImmutable();
} catch (\Throwable) {
    fwrite(STDERR, 'Некорректное --now: ' . (string) $options['now'] . PHP_EOL);
    exit(1);
}

if (isset($options['list'])) {
    foreach ($scheduler->describe($now) as $job) {
        fwrite(STDOUT, sprintf(
            "%-22s %-14s следующий: %s  последний успех: %s\n",
            $job['name'],
            $job['schedule'],
            $job['next_run'],
            $job['last_success'] ?? '—',
        ));
    }
    exit(0);
}

if (isset($options['job'])) {
    $name = (string) $options['job'];
    $result = $scheduler->run($name, $now->getTimestamp());
    fwrite(STDOUT, sprintf('[%s] %s — %s' . PHP_EOL, $result->status, $name, $result->message));

    exit($result->status === CronResult::ERROR ? 1 : 0);
}

$results = $scheduler->tick($now);

if ($results === [] && isset($options['v'])) {
    fwrite(STDOUT, 'В эту минуту задач к запуску нет.' . PHP_EOL);
}

$exitCode = 0;

foreach ($results as $name => $result) {
    fwrite(STDOUT, sprintf('[%s] %-22s %6.0f мс  %s' . PHP_EOL, $result->status, $name, $result->duration * 1000, $result->message));

    if ($result->status === CronResult::ERROR) {
        $exitCode = 1;
    }
}

exit($exitCode);
