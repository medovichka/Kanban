<?php

declare(strict_types=1);

use App\Container;
use App\Cron\CronJobRegistry;
use App\Cron\CronHistory;
use App\Cron\CronScheduler;
use App\Cron\Jobs\CleanupJobs;
use App\Cron\Jobs\HeartbeatJob;

return [
    'factories' => [
        CronJobRegistry::class => static function (Container $container): CronJobRegistry {
            $registry = new CronJobRegistry();

            CleanupJobs::registerAll($registry, $container->get(App\Session\SessionDriver::class));

            $registry->add(new HeartbeatJob());

            return $registry;
        },

        CronScheduler::class => static fn (Container $container): CronScheduler => new CronScheduler(
            $container->get(CronJobRegistry::class),
            new CronHistory(),
            $container->logger(),
        ),
    ],
];
