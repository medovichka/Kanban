<?php

declare(strict_types=1);

namespace App\Cron;

use App\Database\Connection;

final class CronLock
{
    private bool $acquired = false;

    public function acquire(string $job, int $ttl = 3600): bool
    {
        $now = time();
        $expired = $now - $ttl;

        try {
            $capsule = Connection::capsule();
            $pdo = $capsule->getConnection()->getPdo();
            $isSqlite = $capsule->getConnection()->getDriverName() === 'sqlite';

            if ($isSqlite) {
                $pdo->exec('BEGIN IMMEDIATE');
            }

            $row = $capsule->table('cron_locks')->where('job', $job)->first();

            if ($row !== null && (int) $row->locked_until > $now) {
                if ($isSqlite) {
                    $pdo->exec('ROLLBACK');
                }

                return false;
            }

            if ($row === null) {
                $capsule->table('cron_locks')->insert([
                    'job' => $job,
                    'locked_until' => $now + $ttl,
                    'locked_by' => (string) getmypid(),
                ]);
            } else {
                $capsule->table('cron_locks')->where('job', $job)->update([
                    'locked_until' => $now + $ttl,
                    'locked_by' => (string) getmypid(),
                ]);
            }

            if ($isSqlite) {
                $pdo->exec('COMMIT');
            }

            return $this->acquired = true;
        } catch (\Throwable $e) {
            error_log('cron lock: ' . $e->getMessage());

            return false;
        }
    }

    public function release(string $job): void
    {
        if (!$this->acquired) {
            return;
        }

        try {
            Connection::capsule()->table('cron_locks')->where('job', $job)->update(['locked_until' => 0]);
        } catch (\Throwable $e) {
            error_log('cron unlock: ' . $e->getMessage());
        }

        $this->acquired = false;
    }
}
