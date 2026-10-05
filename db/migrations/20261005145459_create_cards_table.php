<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateCardsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('cards');
        $table->addColumn('column_id', 'integer', ['signed' => false])
              ->addColumn('title', 'string', ['limit' => 200])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('position', 'integer', ['default' => 0])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->addForeignKey('column_id', 'columns', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addIndex(['column_id', 'position'])
              ->create();
    }
}