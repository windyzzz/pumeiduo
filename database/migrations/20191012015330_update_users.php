<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateUsers extends Migrator
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
        $this->table('users')
            ->changeColumn('token', 'string', ['limit' => 64, 'null' => true, 'comment' => '用户token', 'after' => 'third_leader'])
            ->addColumn('time_out', 'integer', ['default' => 0, 'signed' => false, 'comment' => 'token过期时间', 'after' => 'token'])
            ->update();

        $this->table('users')
            ->changeColumn('is_consummate', 'integer', ['limit' =>  \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '完善个人信息已领取收益'])
            ->changeColumn('is_not_show_jk', 'integer', ['limit' =>  \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0, 'comment' => '完是否永久不提示 加入金卡弹窗 1是 0不是'])
            ->update();
    }
}
