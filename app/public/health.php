<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'php' => 'ok',
    'database' => 'ok',
];
$httpStatus = 200;

try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/../db/database.sqlite3', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $usersTable = $pdo
        ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'")
        ->fetchColumn();

    if ($usersTable !== 'users') {
        throw new \RuntimeException('schema is not migrated');
    }
} catch (\Throwable $e) {
    $checks['database'] = 'fail';
    $httpStatus = 503;
}

http_response_code($httpStatus);

echo json_encode([
    'status' => $httpStatus === 200 ? 'ok' : 'fail',
    'checks' => $checks,
], JSON_UNESCAPED_UNICODE);
