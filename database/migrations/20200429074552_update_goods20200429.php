<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGoods20200429 extends Migrator
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
        $this->table('goods')
            ->changeColumn('trade_type', 'integer', ['default' => 1, 'limit' =>\Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '交易条件：1.仓库自发 2.一件代发 3.供应链发货'])
            ->update();
    }
}
