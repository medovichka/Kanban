<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Cron\CronHistory;
use App\Cron\CronScheduler;
use App\Http\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;

final class CronPanelController extends Controller
{
    public function handle(Request $request): Response
    {
        $scheduler = $this->service(CronScheduler::class);

        $history = $this->service(CronHistory::class);

        try {
            $jobs = $scheduler->describe();
        } catch (\Throwable $e) {
            $this->logger()->error('Cron panel: cannot describe jobs', ['exception' => $e]);

            return $this->error(500, 'Cron-задачи не описаны: ' . $e->getMessage());
        }

        $body = View::nav(null) . '<h1>Cron-задачи</h1>'
            . '<p>Планировщик запускается тиком раз в минуту: <code>php bin/cron.php --tick</code>. '
            . 'Ручной запуск: <code>php bin/cron.php --job &lt;name&gt;</code>.</p>';

        $body .= '<table><tr><th>задача</th><th>расписание</th><th>следующий запуск</th><th>последний успех</th></tr>';
        foreach ($jobs as $job) {
            $body .= '<tr><td>' . $this->h($job['name']) . '</td>'
                . '<td><code>' . $this->h($job['schedule']) . '</code></td>'
                . '<td>' . $this->h($job['next_run']) . '</td>'
                . '<td>' . $this->h($job['last_success'] ?? '—') . '</td></tr>';
        }
        $body .= '</table>';

        $rows = $history->latest(50);

        $body .= '<h2>Последние запуски</h2>';
        if ($rows === []) {
            $body .= '<p>Запусков ещё не было (или таблица cron_runs не создана — выполните миграции).</p>';
        } else {
            $body .= '<table><tr><th>#</th><th>задача</th><th>статус</th><th>мс</th><th>старт</th><th>сообщение</th></tr>';
            foreach ($rows as $row) {
                $class = $row->status === 'ok' ? 'ok' : 'fail';
                $body .= '<tr><td>' . $this->h((string) $row->id) . '</td>'
                    . '<td>' . $this->h((string) $row->job) . '</td>'
                    . '<td class="' . $class . '">' . $this->h((string) $row->status) . '</td>'
                    . '<td>' . $this->h((string) $row->duration_ms) . '</td>'
                    . '<td>' . $this->h((string) $row->started_at) . '</td>'
                    . '<td>' . $this->h((string) ($row->message ?? '')) . '</td></tr>';
            }
            $body .= '</table>';
        }

        return $this->view(View::page('Cron', $body));
    }
}
