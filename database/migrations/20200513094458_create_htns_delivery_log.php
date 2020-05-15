<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateHtnsDeliveryLog extends Migrator
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
        $this->table('htns_delivery_log', ['comment' => 'HTNS物流配送记录'])
            ->addColumn('order_id', 'integer', ['comment' => '订单ID'])
            ->addColumn('rec_id', 'integer', ['comment' => '订单商品ID'])
            ->addColumn('goods_num', 'integer', ['null' => true, 'comment' => '商品数量'])
            ->addColumn('goods_name', 'string', ['null' => true, 'comment' => '商品名称'])
            ->addColumn('status', 'string', ['limit' => 10, 'comment' => '状态'])
            ->addColumn('create_time', 'integer', ['comment' => '时间'])
            ->addColumn('time_zone', 'string', ['limit' => 10, 'comment' => '时区'])
            ->create();
    }
}
