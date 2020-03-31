<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateInviteLog extends Migrator
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
        $this->table('invite_log')
            ->addColumn('invite_uid', 'integer', ['comment' => '邀请人id'])
            ->addColumn('user_id', 'integer', ['comment' => '用户id'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0,
                'comment' => '状态：0设置失败 1设置成功 -1设置不成功（用户已设置了推荐人）'])
            ->addColumn('create_time', 'integer')
            ->create();
    }
}
