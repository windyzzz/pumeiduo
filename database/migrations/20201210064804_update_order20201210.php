<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateOrder20201210 extends Migrator
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
                'comment' => '订单类型：1圃美多 2韩国购 3供应链 4直播 5商学院兑换'])
            ->addColumn('school_credit', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '商学院学分'])
            ->update();

        $this->table('order_goods')
            ->addColumn('school_credit', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '商学院学分'])
            ->update();
    }
}
