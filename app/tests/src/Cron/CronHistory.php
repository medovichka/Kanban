<?php

declare(strict_types=1);

namespace App\Cron;

use App\Database\Connection;

final class CronHistory
{
    public function record(array $row): void
    {
        try {
            Connection::capsule()->table('cron_runs')->insert($row);
        } catch (\Throwable $e) {
            error_log('cron: cannot write history: ' . $e->getMessage());
        }
    }

    public function latest(int $limit = 20): array
    {
        try {
            return Connection::capsule()->table('cron_runs')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            error_log('cron: cannot read history: ' . $e->getMessage());

            return [];
        }
    }

    public function lastSuccess(): array
    {
        $rows = [];
        foreach ($this->latest(200) as $row) {
            if ($row->status === CronResult::OK && !isset($rows[$row->job])) {
                $rows[$row->job] = (string) $row->finished_at;
            }
        }

        return $rows;
    }

    public function isAvailable(): bool
    {
        try {
            return Connection::capsule()->getConnection()->getSchemaBuilder()->hasTable('cron_runs');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
