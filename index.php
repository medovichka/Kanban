<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

$logger = new Logger('http');
$logger->pushHandler(new StreamHandler(
    __DIR__ . '/logs/app.log',
    Level::Debug
));

$capsule = new Capsule;
$capsule->addConnection([
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/db/database.sqlite3',
    'prefix'   => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->getConnection()->getPdo()->exec('PRAGMA foreign_keys = ON;');

$request = [
    'method'  => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'uri'     => $_SERVER['REQUEST_URI']    ?? '/',
    'query'   => $_GET,
    'body'    => file_get_contents('php://input'),
    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
];

$response = ['status' => 'ok', 'message' => 'Kanban skillet'];

try {
    $response['users'] = \App\Models\User::all()->toArray();
} catch (\Throwable $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
    $logger->error('Application error', ['exception' => $e]);
}

$responseBody = json_encode(
    $response,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);

$logger->info('Incoming HTTP request', [
    'request'  => $request,
    'response' => $responseBody,
]);

header('Content-Type: application/json; charset=utf-8');
echo $responseBody;