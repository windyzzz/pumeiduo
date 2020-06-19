<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateOrder20200615 extends Migrator
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
            ->changeColumn('order_type', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '订单类型：1圃美多 2海外购 3供应链', 'after' => 'order_sn'])
            ->addColumn('parent_id', 'integer', ['default' => 0, 'comment' => '父级订单ID', 'after' => 'order_id'])
            ->addColumn('supplier_order_sn', 'string', ['null' => true, 'default' => '', 'limit' => 20, 'comment' => '供应链订单编号'])
            ->addColumn('supplier_order_status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '供应链订单状态：0未同步 1同步成功 2同步失败 3已取消 4已完成 5已作废 6售后'])
            ->addColumn('supplier_pay_status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '供应链订单支付状态：0未支付 1已支付 2已退款'])
            ->addColumn('supplier_shipping_status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '供应链订单发货状态：0未发货 1已发货 2已退款'])
            ->addColumn('supplier_submit_time', 'integer', ['null' => true, 'comment' => '供应链系统同步时间'])
            ->addColumn('supplier_submit_remark', 'text', ['null' => true, 'comment' => '供应链系统同步记录'])
            ->update();
    }
}
