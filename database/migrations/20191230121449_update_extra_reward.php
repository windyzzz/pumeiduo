<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateExtraReward extends Migrator
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
        $this->table('extra_reward')
            ->changeColumn('order_num', 'integer', ['default' => 0, 'comment' => '订单数量'])
            ->addColumn('can_integral', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '能否使用积分', 'after' => 'goods_price'])
            ->addColumn('buy_limit', 'integer', ['default' => 0, 'comment' => '每人限购数量', 'after' => 'goods_num'])
            ->update();
    }
}
