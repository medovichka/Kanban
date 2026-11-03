<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Database\Connection;
use App\Metrics;
use App\Session\ArraySessionDriver;
use App\Session\DatabaseSessionDriver;

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'php' => 'ok',
    'database' => 'ok',
    'session_table' => 'ok',
];
$error = null;

try {
    Config::load(__DIR__ . '/../config/config.php');
    Connection::boot((array) Config::get('db', []));

    $db = Connection::check();
    if (!$db['ok']) {
        $checks['database'] = 'fail';
        $error = $db['error'];
    }

    $driver = (string) Config::get('session.driver', 'database');
    $sessions = $driver === 'database'
        ? new DatabaseSessionDriver((int) Config::get('session.lifetime', 86400))
        : new ArraySessionDriver();

    if (!$sessions->healthy()) {
        $checks['session_table'] = 'fail';
    }
} catch (\Throwable $e) {
    error_log('health: ' . $e->getMessage());
    $checks['database'] = 'fail';
    $checks['session_table'] = 'fail';
    $error = $e->getMessage();
}

foreach ($checks as $name => $state) {
    Metrics::healthCheck($name, $state);
}

$ok = !in_array('fail', $checks, true);

http_response_code($ok ? 200 : 503);

echo json_encode([
    'status' => $ok ? 'ok' : 'fail',
    'checks' => $checks,
    'error' => $error,
    'time' => date(DATE_ATOM),
], JSON_UNESCAPED_UNICODE);
