<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateRebateLog20200821 extends Migrator
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
        $this->table('rebate_log')
            ->changeColumn('point', 'decimal', ['default' => '0', 'comment' => '获佣积分', 'precision' => 10, 'scale' => 2])
            ->addColumn('is_vip', 'integer', ['default' => 0, 'comment' => '升级VIP/SVIP套餐订单分成 1是 0否', 'after' => 'sale_service'])
            ->update();
    }
}
