<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Cron\CronScheduler;
use App\Http\Controller;
use App\Http\Request;
use App\Http\Response;

final class CronController extends Controller
{
    public function handle(Request $request): Response
    {
        $scheduler = $this->service(CronScheduler::class);

        return $this->json([
            'jobs' => $scheduler->describe(),
            'tick_command' => 'php bin/cron.php --tick',
        ]);
    }
}
