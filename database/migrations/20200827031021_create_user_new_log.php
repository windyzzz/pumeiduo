<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserNewLog extends Migrator
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
        $this->table('user_new_log', ['comment' => '新用户奖励记录'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('coupon_id', 'string', ['default' => '', 'comment' => '优惠券ID列表'])
            ->addColumn('point', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '积分'])
            ->addColumn('electronic', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '电子币'])
            ->addColumn('add_time', 'integer')
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态：0未展示 1已展示 -1已删除'])
            ->create();
    }
}
