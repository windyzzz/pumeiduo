<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateOrderPromGoods extends Migrator
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
        $this->table('order_prom_goods')
            ->addColumn('order_prom_id', 'integer', ['comment' => '订单优惠促销ID'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '类别：1参与活动商品 2赠送商品'])
            ->addColumn('goods_id', 'integer', ['comment' => '商品ID'])
            ->addColumn('item_id', 'integer', ['null' => true, 'default' => 0, 'comment' => '规格属性ID'])
            ->addColumn('goods_num', 'integer', ['null' => true, 'default' => 0, 'comment' => '商品数量'])
            ->create();
    }
}
