<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserSchoolConfig extends Migrator
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
        $this->table('user_school_config', ['comment' => '用户商学院配置记录表'])
            ->addColumn('type', 'string', ['limit' => 20, 'comment' => '配置类型'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
