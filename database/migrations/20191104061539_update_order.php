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

        $this->table('order')
            ->addColumn('cancel_time', 'integer', ['default' => '0', 'comment' => '取消订单时间'])
            ->update();

        $this->table('order')
            ->changeColumn('pay_status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '支付状态 0未支付 1已支付 2部分支付 3已退款 4拒绝退款'])
            ->changeColumn('shipping_status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '发货状态 0未发货 1已发货 2部分发货 3不需发货'])
            ->addColumn('send_type', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '发货方式 0自填快递 1在线预约 2电子面单 3无需物流'])
            ->addColumn('delivery_type', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '发货类型：1统一发货 2分开发货'])
            ->update();
    }
}
