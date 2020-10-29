<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePromActivityItem extends Migrator
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
        $this->table('prom_activity_item', ['comment' => '促销活动内容表'])
            ->addColumn('activity_id', 'integer', ['comment' => '活动ID'])
            ->addColumn('coupon_id', 'integer', ['default' => 0, 'comment' => '优惠券ID'])
            ->addColumn('goods_id', 'integer', ['default' => 0 , 'comment' => '商品ID'])
            ->addColumn('item_id', 'integer', ['default' => 0 , 'comment' => '规格ID'])
            ->create();
    }
}
