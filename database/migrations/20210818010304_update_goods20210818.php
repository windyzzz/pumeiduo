<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGoods20210818 extends Migrator
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
            ->changeColumn('exchange_integral', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '积分兑换：0不参与积分兑换，积分和现金的兑换比例见后台配置'])
            ->addColumn('exchange_integral_bak', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '积分兑换备份字段', 'after' => 'exchange_integral'])
            ->update();
    }
}
