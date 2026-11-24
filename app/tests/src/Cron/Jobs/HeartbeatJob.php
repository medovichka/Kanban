<?php

declare(strict_types=1);

namespace App\Cron\Jobs;

use App\Cron\CronContext;
use App\Cron\CronResult;
use App\Cron\JobInterface;

final class HeartbeatJob implements JobInterface
{
    public static function name(): string
    {
        return 'demo.heartbeat';
    }

    public static function schedule(): string
    {
        return '* * * * *';
    }

    public function run(CronContext $context): CronResult
    {
        if ($context->verbose) {
            fwrite(STDOUT, sprintf("[heartbeat] %s\n", date('Y-m-d H:i:s', $context->now)));
        }

        return CronResult::ok('ping');
    }
}
