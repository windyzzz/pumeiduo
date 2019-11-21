<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateOrderProm extends Migrator
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
        $this->table('order_prom')
            ->addColumn('title', 'string', ['limit' => 50, 'comment' => '活动名称'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '类型：0赠品+满减 1满减价 2满赠送'])
            ->addColumn('order_price', 'decimal', ['comment' => '订单满足价格', 'precision' => 10, 'scale' => 2])
            ->addColumn('discount_price', 'decimal', ['null' => true, 'comment' => '订单优惠价格', 'precision' => 10, 'scale' => 2])
            ->addColumn('start_time', 'integer', ['comment' => '开始时间'])
            ->addColumn('end_time', 'integer', ['comment' => '结束时间'])
            ->addColumn('is_open', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否开启'])
            ->addColumn('is_end', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否已经结束'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '活动描述'])
            ->create();
    }
}
