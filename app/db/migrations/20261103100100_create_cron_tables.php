<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateCronTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('cron_runs')
            ->addColumn('job', 'string', ['limit' => 64])
            ->addColumn('status', 'string', ['limit' => 16])
            ->addColumn('message', 'string', ['limit' => 1000, 'null' => true])
            ->addColumn('duration_ms', 'integer', ['default' => 0])
            ->addColumn('started_at', 'datetime')
            ->addColumn('finished_at', 'datetime')
            ->addIndex(['job', 'started_at'])
            ->addIndex(['finished_at'])
            ->create();

        $this->table('cron_locks', ['id' => false, 'primary_key' => ['job']])
            ->addColumn('job', 'string', ['limit' => 64])
            ->addColumn('locked_until', 'biginteger', ['signed' => false, 'default' => 0])
            ->addColumn('locked_by', 'string', ['limit' => 64, 'null' => true])
            ->create();
    }
}
