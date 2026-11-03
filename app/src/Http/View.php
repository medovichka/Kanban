<?php

declare(strict_types=1);

namespace App\Http;

final class View
{
    public static function page(string $title, string $body, string $appName = 'Kanban'): string
    {
        $escapedTitle = htmlspecialchars($title . ' — ' . $appName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>{$escapedTitle}</title>
                <style>
                    body { font-family: system-ui, sans-serif; max-width: 860px; margin: 2rem auto; padding: 0 1rem; color: #1c1c1c; }
                    h1 { font-size: 1.4rem; }
                    h2 { font-size: 1.1rem; margin-top: 2rem; }
                    nav a { margin-right: 1rem; }
                    dl { display: grid; grid-template-columns: max-content 1fr; gap: 0.3rem 1rem; background: #f6f6f6; padding: 1rem; border-radius: 6px; }
                    dt { font-weight: 600; }
                    dd { margin: 0; }
                    .ok { color: #0a7a2f; }
                    .fail { color: #b00020; }
                    .notice { padding: 0.6rem 1rem; border-radius: 6px; margin: 1rem 0; }
                    .notice-error { background: #fde8ea; color: #b00020; }
                    .notice-ok { background: #e6f7ec; color: #0a7a2f; }
                    table { border-collapse: collapse; width: 100%; margin: 0.5rem 0 1rem; }
                    th, td { border: 1px solid #ddd; padding: 0.4rem 0.6rem; text-align: left; font-size: 0.9rem; }
                    th { background: #f0f0f0; }
                    form { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 0.5rem; margin: 0.5rem 0 1rem; }
                    form.stack { display: flex; flex-direction: column; max-width: 380px; }
                    input[type=text], input[type=email], input[type=password] { padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
                    button, input[type=submit] { padding: 0.5rem 1.2rem; border: 0; border-radius: 4px; background: #2563eb; color: #fff; cursor: pointer; }
                    button:hover, input[type=submit]:hover { background: #1d4ed8; }
                    button.muted { background: #6b7280; }
                </style>
            </head>
            <body>
            HTML
            . "\n" . $body . "\n</body>\n</html>\n";
    }

    public static function nav(?string $userName): string
    {
        $links = '<a href="/">Главная</a><a href="/dashboard">Кабинет</a><a href="/cron/status">Cron</a>';

        return $userName === null
            ? '<nav>' . $links . '<a href="/login">Вход</a><a href="/register">Регистрация</a></nav>'
            : '<nav>' . $links . '<span>Вы: ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</span>'
                . '<form method="post" action="/logout" style="display:inline"><button class="muted" type="submit">Выйти</button></form></nav>';
    }
}
