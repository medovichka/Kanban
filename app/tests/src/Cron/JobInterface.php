<?php

declare(strict_types=1);

namespace App\Cron;

interface JobInterface
{
    public static function name(): string;

    public static function schedule(): string;

    public function run(CronContext $context): CronResult;
}
