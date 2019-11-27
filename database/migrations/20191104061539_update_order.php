<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateOrder extends Migrator
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
        $this->table('order')
            ->changeColumn('prom_type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'default' => 0, 'comment' => '订单类型：0普通订单 4预售订单 5虚拟订单 6拼团订单 7合购优惠'])
            ->update();

        $this->table('order_goods')
            ->changeColumn('prom_type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'default' => 0, 'comment' => '0普通订单 1限时抢购 2团购 3 促销优惠 4预售 7合购优惠'])
            ->update();
        $this->table('order')
            ->changeColumn('integral', 'decimal', ['default' => 0, 'comment' => '使用积分', 'precision' => 10, 'scale' => 2])
            ->update();
    }
}
