<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use Illuminate\Database\Capsule\Manager as Capsule;

final class Connection
{
    private static ?Capsule $capsule = null;

    public static function boot(array $settings): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        $driver = (string) ($settings['driver'] ?? Config::get('db.driver', 'sqlite'));
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => $driver,
            'database' => (string) ($settings['database'] ?? Config::get('db.database', '')),
            'prefix' => (string) ($settings['prefix'] ?? Config::get('db.prefix', '')),
            'foreign_key_constraints' => true,
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        if ($driver === 'sqlite') {
            $pdo = $capsule->getConnection()->getPdo();
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
        }

        return self::$capsule = $capsule;
    }

    public static function capsule(): Capsule
    {
        return self::$capsule ?? throw new \RuntimeException('Соединение с БД не инициализировано. Вызовите Connection::boot().');
    }

    public static function check(): array
    {
        try {
            self::capsule()->getConnection()->getPdo()->query('SELECT 1');

            $tables = self::capsule()->getConnection()->getSchemaBuilder()->getTableListing();
            foreach (['users', 'sessions', 'session_data', 'cron_runs'] as $table) {
                if (!in_array($table, $tables, true)) {
                    return ['ok' => false, 'error' => 'Не мигрирована схема: нет таблицы ' . $table];
                }
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
