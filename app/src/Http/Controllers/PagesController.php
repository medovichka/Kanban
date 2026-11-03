<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Config;
use App\Http\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;

final class PagesController extends Controller
{
    public function handle(Request $request): Response
    {
        $body = View::nav(null)
            . '<h1>О проекте</h1>'
            . '<p>' . $this->h((string) Config::get('app.name', 'Kanban')) . ' — учебный канбан-борд.</p>'
            . '<p>Домашнее задание: сессии в БД, kрон-задачи и абстрактный контроллер с маршрутизацией.</p>'
            . '<ul>'
            . '<li><code>/api/status</code> — состояние сервиса и метрики</li>'
            . '<li><code>/cron/status</code> — расписание и последние запуски cron-задач</li>'
            . '<li><code>/api/me</code> — текущая сессия в JSON (нужна авторизация)</li>'
            . '</ul>'
            . '<p><a href="/">На главную</a></p>';

        return $this->view(View::page('О проекте', $body));
    }
}
