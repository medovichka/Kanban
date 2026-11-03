<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateSessionsTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('sessions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 64])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'biginteger', ['signed' => false])
            ->addColumn('last_activity_at', 'biginteger', ['signed' => false])
            ->addColumn('expires_at', 'biginteger', ['signed' => false])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['user_id'])
            ->addIndex(['expires_at'])
            ->create();

        $this->table('session_data', ['id' => false, 'primary_key' => ['session_id', 'data_key']])
            ->addColumn('session_id', 'string', ['limit' => 64])
            ->addColumn('data_key', 'string', ['limit' => 100])
            ->addColumn('data_value', 'text')
            ->addForeignKey('session_id', 'sessions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
