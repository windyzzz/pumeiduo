<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateOrderGoods20200323 extends Migrator
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
        $this->table('order_goods')
            ->addColumn('goods_pv', 'decimal', ['default' => '0', 'comment' => '订单商品pv', 'precision' => 10, 'scale' => 2])
            ->addColumn('pv_send', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否已将pv数值发送到代理商系统'])
            ->addColumn('pay_type', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '支付类型：0赠品或者未知（之前没有记录）1积分+现金 2现金'])
            ->update();
    }
}
