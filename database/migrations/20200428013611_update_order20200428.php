<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateOrder20200428 extends Migrator
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
            ->addColumn('is_abroad', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '是否是海外购订单', 'after' => 'delivery_type'])
            ->addColumn('id_card', 'string', ['default' => 0, 'limit' => 20, 'comment' => '身份证', 'after' => 'consignee'])
            ->update();
    }
}
