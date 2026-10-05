<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateGroupUserTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('group_user', ['id' => false, 'primary_key' => ['group_id', 'user_id']]);
        $table->addColumn('group_id', 'integer', ['signed' => false])
              ->addColumn('user_id', 'integer', ['signed' => false])
              ->addColumn('is_admin', 'boolean', ['default' => false])
              ->addColumn('joined_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addForeignKey('group_id', 'groups', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addIndex(['group_id', 'is_admin'])
              ->create();
    }
}