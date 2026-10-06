<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateGroupsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('groups');
        $table->addColumn('name', 'string', ['limit' => 150])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->create();
    }
}