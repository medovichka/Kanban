<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateColumnsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('columns');
        $table->addColumn('board_id', 'integer', ['signed' => false])
              ->addColumn('title', 'string', ['limit' => 100])
              ->addColumn('position', 'integer', ['default' => 0])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->addForeignKey('board_id', 'boards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addIndex(['board_id', 'position'])
              ->create();
    }
}