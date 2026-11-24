<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Database\Connection;
use App\Http\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Metrics;
use App\Session\SessionDriver;

final class StatusController extends Controller
{
    public function handle(Request $request): Response
    {
        $sessions = $this->service(SessionDriver::class);

        $checks = [
            'php' => 'ok',
            'database' => Connection::check()['ok'] ? 'ok' : 'fail',
            'session_table' => $sessions->healthy() ? 'ok' : 'fail',
        ];

        foreach ($checks as $name => $state) {
            Metrics::healthCheck($name, $state);
        }

        $ok = !in_array('fail', $checks, true);

        return $this->json([
            'status' => $ok ? 'ok' : 'fail',
            'checks' => $checks,
            'session_driver' => $sessions->name(),
            'php' => PHP_VERSION,
            'host' => (string) gethostname(),
            'metrics_enabled' => Metrics::enabled(),
        ], $ok ? 200 : 503);
    }
}
