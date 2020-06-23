<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateReturnGoods20200623 extends Migrator
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
        $this->table('return_goods')
            ->addColumn('supplier_sale_sn', 'string', ['limit' => 20, 'default' => '', 'comment' => '供应链售后订单号'])
            ->addColumn('supplier_sale_status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '供应链售后状态：0未确定 1同意 -1拒绝'])
            ->addColumn('supplier_sale_remark', 'string', ['default' => '', 'comment' => '供应链售后备注'])
            ->addColumn('supplier_receive_info', 'text', ['null' => true, 'comment' => '供应链收货信息'])
            ->update();
    }
}
