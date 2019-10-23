<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdatePromGoods extends Migrator
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
        $this->table('prom_goods')
            ->changeColumn('type', 'integer', ['limit' => 2, 'default' => null, 'comment' => '促销类型，0直接打折 1减价优惠 2固定金额出售 3赠送代金券 4满打折'])
            ->addColumn('goods_num', 'integer', ['default' => null, 'comment' => '商品数量（满多少促销）', 'after' => 'goods_id'])
            ->update();
    }
}
