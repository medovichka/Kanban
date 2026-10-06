<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateBoardSettingsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('board_settings');
        $table->addColumn('board_id', 'integer', ['signed' => false])
              ->addColumn('setting_key', 'string', ['limit' => 100])
              ->addColumn('setting_value', 'text', ['null' => true])
              ->addColumn('updated_by', 'integer', ['signed' => false, 'null' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->addForeignKey('board_id', 'boards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addForeignKey('updated_by', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
              ->addIndex(['board_id', 'setting_key'], ['unique' => true])
              ->create();
    }
}