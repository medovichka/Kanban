<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateBoardsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('boards');
        $table->addColumn('group_id', 'integer', ['signed' => false])
              ->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('title', 'string', ['limit' => 150])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->addForeignKey('group_id', 'groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addIndex(['group_id', 'user_id'], ['unique' => true])
              ->create();
    }
}
