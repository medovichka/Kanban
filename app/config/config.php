<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Kanban',
    ],

    'db' => [
        'driver'   => 'sqlite',
        'database' => __DIR__ . '/../db/database.sqlite3',
        'prefix'   => '',
    ],

    'logging' => [
        'name'  => 'http',
        'level' => 'debug',
        'path'  => __DIR__ . '/../logs/app.log',
    ],

    'session' => [
        'driver'   => 'database',
        'lifetime' => 86400,
        'cookie'   => 'kanban_session',
    ],

    'container' => [
        'lifetime' => 86400,
        'channel'  => 'app',
    ],

    'secrets' => [
        'demo' => 'enc:h0f4l/h7fJHbfP8Sfj9dH9ov/cj/QbiGFkLbi98Fmegyk+i1AqKf',
    ],
];
