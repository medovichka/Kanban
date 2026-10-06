<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$logger = new Logger('http');
$logger->pushHandler(new StreamHandler(
    __DIR__ . '/../logs/app.log',
    Level::Debug
));

$capsule = new Capsule;
$capsule->addConnection([
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/../db/database.sqlite3',
    'prefix'   => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$capsule->getConnection()->getPdo()->exec('PRAGMA foreign_keys = ON;');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$input = file_get_contents('php://input');
$location = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'CLI') === 'POST' && isset($_POST['name'], $_POST['email'])) {
    $name = trim((string) $_POST['name']);
    $email = trim((string) $_POST['email']);
    $password = (string) ($_POST['password'] ?? '');

    try {
        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password === ''
                ? null
                : password_hash($password, PASSWORD_DEFAULT),
        ]);
        $location = '/';
    } catch (\Throwable $e) {
        $logger->error('Failed to create user', ['exception' => $e]);
        $location = '/?error=1';
    }

    header('Location: ' . $location);
    $responseBody = '';
} else {
    $phpVersion = PHP_VERSION;
    $hasPdoSqlite = extension_loaded('pdo_sqlite');
    $hostname = (string) gethostname();

    $dbError = null;
    $sqliteVersion = null;
    $users = [];

    try {
        $sqliteVersion = (string) Capsule::connection()
            ->getPdo()
            ->query('SELECT sqlite_version()')
            ->fetchColumn();

        $users = User::orderByDesc('id')->get()->all();
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
        $logger->error('Failed to read users', ['exception' => $e]);
    }

    $isError = isset($_GET['error']);

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kanban</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; color: #1c1c1c; }
        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.1rem; margin-top: 2rem; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 0.3rem 1rem; background: #f6f6f6; padding: 1rem; border-radius: 6px; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        .ok { color: #0a7a2f; }
        .fail { color: #b00020; }
        .notice { padding: 0.6rem 1rem; border-radius: 6px; margin: 1rem 0; }
        .notice-error { background: #fde8ea; color: #b00020; }
        table { border-collapse: collapse; width: 100%; margin: 0.5rem 0 1rem; }
        th, td { border: 1px solid #ddd; padding: 0.4rem 0.6rem; text-align: left; font-size: 0.9rem; }
        th { background: #f0f0f0; }
        form { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0.5rem; }
        input[type=text], input[type=email], input[type=password] { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 0.5rem 1.2rem; border: 0; border-radius: 4px; background: #2563eb; color: #fff; cursor: pointer; }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <h1>Kanban</h1>

    <dl>
        <dt>Версия PHP</dt>
        <dd><?= h($phpVersion) ?></dd>

        <dt>Расширение pdo_sqlite</dt>
        <dd class="<?= $hasPdoSqlite ? 'ok' : 'fail' ?>"><?= $hasPdoSqlite ? 'подключено' : 'отсутствует' ?></dd>

        <dt>Хост контейнера</dt>
        <dd><?= h($hostname) ?></dd>

        <dt>Подключение к БД</dt>
        <dd class="<?= $dbError === null ? 'ok' : 'fail' ?>">
            <?= $dbError === null ? 'SQLite ' . h($sqliteVersion ?? '') : 'ошибка: ' . h($dbError) ?>
        </dd>
    </dl>

    <?php if ($isError): ?>
        <p class="notice notice-error">Не удалось сохранить запись. Проверьте, что имя и email не пустые, а email ещё не используется.</p>
    <?php endif; ?>

    <h2>Таблица users</h2>
    <?php if ($users): ?>
        <table>
            <tr><th>id</th><th>имя</th><th>email</th><th>создан</th></tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= h((string) $user->id) ?></td>
                    <td><?= h((string) $user->name) ?></td>
                    <td><?= h((string) $user->email) ?></td>
                    <td><?= h((string) $user->created_at) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Нет данных (таблица пуста или БД недоступна).</p>
    <?php endif; ?>

    <form method="post" action="/">
        <input type="text" name="name" placeholder="Имя" required maxlength="100">
        <input type="email" name="email" placeholder="Email" required maxlength="150">
        <input type="password" name="password" placeholder="Пароль" maxlength="255">
        <button type="submit">Добавить</button>
    </form>
</body>
</html>
    <?php
    $responseBody = (string) ob_get_clean();

    header('Content-Type: text/html; charset=utf-8');
}

$logger->info('Incoming HTTP request', [
    'request' => [
        'method'  => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'uri'     => $_SERVER['REQUEST_URI']    ?? '/',
        'query'   => $_GET,
        'body'    => $input,
        'headers' => array_filter(
            function_exists('getallheaders') ? getallheaders() : [],
            static fn (string $name): bool => !in_array(
                strtolower($name),
                ['cookie', 'authorization'],
                true
            ),
            ARRAY_FILTER_USE_KEY
        ),
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
    ],
    'response' => $responseBody,
]);

echo $responseBody;
