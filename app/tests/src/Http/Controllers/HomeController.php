<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Config;
use App\Database\Connection;
use App\Http\Controller;
use App\Http\InteractsWithSession;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Models\User;

class HomeController extends Controller
{
    use InteractsWithSession;

    public function handle(Request $request): Response
    {
        $session = $this->session();
        $user = $this->user();
        $flashes = [];

        if ($session !== null) {
            [$flashes] = $this->flashes($session);
        }

        $dbError = null;
        $version = null;
        $users = [];
        $tables = [];
        $counts = [];

        try {
            $version = (string) Connection::pdo()->query('SELECT sqlite_version()')->fetchColumn();
            $tables = Connection::capsule()->getConnection()->getSchemaBuilder()->getTableListing();
            sort($tables);

            foreach ($tables as $table) {
                $counts[$table] = Connection::capsule()->table($table)->count();
            }

            $users = User::query()->orderByDesc('id')->limit(50)->get()->all();
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
            $this->logger()->error('Home: failed to read tables', ['exception' => $e]);
        }

        $body = View::nav($user?->displayName());
        $body .= '<h1>' . $this->h((string) Config::get('app.name', 'Kanban')) . '</h1>';

        if (isset($flashes['success'])) {
            $body .= '<p class="notice notice-ok">' . $this->h((string) $flashes['success']) . '</p>';
        }

        if ($dbError !== null) {
            $body .= '<p class="notice notice-error">БД недоступна: ' . $this->h($dbError) . '</p>';
        }

        $body .= '<dl>'
            . '<dt>Версия PHP</dt><dd>' . $this->h(PHP_VERSION) . '</dd>'
            . '<dt>pdo_sqlite</dt><dd class="' . (extension_loaded('pdo_sqlite') ? 'ok' : 'fail') . '">'
            . (extension_loaded('pdo_sqlite') ? 'подключено' : 'отсутствует') . '</dd>'
            . '<dt>Хост</dt><dd>' . $this->h((string) gethostname()) . '</dd>'
            . '<dt>БД</dt><dd class="' . ($dbError === null ? 'ok' : 'fail') . '">'
            . ($dbError === null ? 'SQLite ' . $this->h($version) : 'ошибка') . '</dd>'
            . '<dt>Драйвер сессий</dt><dd>' . $this->h($this->sessions()->driver()->name()) . '</dd>'
            . '<dt>Текущая сессия</dt><dd>' . ($session === null
                ? 'нет — <a href="/login">войти</a> или <a href="/register">зарегистрироваться</a>'
                : $this->h(substr($session->id, 0, 12)) . '…, истекает ' . $this->h($session->expiresAtDate()->format('Y-m-d H:i')))
            . '</dd>'
            . '</dl>';

        $body .= '<h2>Пользователи</h2>';
        if ($users === []) {
            $body .= '<p>Нет данных (таблица пуста или БД недоступна).</p>';
        } else {
            $body .= '<table><tr><th>id</th><th>имя</th><th>email</th><th>создан</th></tr>';
            foreach ($users as $row) {
                $body .= '<tr><td>' . $this->h((string) $row->id) . '</td>'
                    . '<td>' . $this->h((string) $row->name) . '</td>'
                    . '<td>' . $this->h((string) $row->email) . '</td>'
                    . '<td>' . $this->h((string) $row->created_at) . '</td></tr>';
            }
            $body .= '</table>';
        }

        $body .= '<h2>Таблицы в БД</h2><table><tr><th>таблица</th><th>строк</th></tr>';
        foreach ($tables as $table) {
            $body .= '<tr><td>' . $this->h($table) . '</td><td>' . $this->h((string) ($counts[$table] ?? 0)) . '</td></tr>';
        }
        $body .= '</table>';

        return $this->view(View::page('Главная', $body));
    }
}
