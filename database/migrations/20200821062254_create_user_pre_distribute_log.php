<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserPreDistributeLog extends Migrator
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
        $this->table('user_pre_distribute_log', ['comment' => '用户预备升级记录'])
            ->addColumn('user_id' , 'integer', ['comment' => '用户ID'])
            ->addColumn('old_level', 'integer', ['comment' => '用户当前等级'])
            ->addColumn('new_level', 'integer', ['comment' => '用户预备升级等级'])
            ->addColumn('upgrade_type', 'integer', ['comment' =>'升级途径：1购买升级套餐 2购买设置金额升级'])
            ->addColumn('order_id', 'integer', ['default' => 0, 'comment' => '订单ID'])
            ->addColumn('upgrade_money', 'decimal', ['default' => '0', 'comment' => '升级金额', 'precision' => 10, 'scale' => 2])
            ->addColumn('add_time', 'integer')
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '0未处理 1已完成 -1已取消'])
            ->create();
    }
}
