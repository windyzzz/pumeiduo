<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUsersEditLog extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $this->table('users_edit_log')
            ->addColumn('admin_id', 'integer', ['comment' => '管理员ID'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('old_data', 'text', ['comment' => '旧数据'])
            ->addColumn('new_data', 'text', ['comment' => '新数据'])
            ->addColumn('create_time', 'integer')
            ->create();
    }
}
