<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolExchange extends Migrator
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
        $this->table('school_exchange', ['comment' => '商学院商品兑换表'])
            ->addColumn('goods_id', 'integer', ['comment' => '商品ID'])
            ->addColumn('item_id', 'integer', ['default' => 0, 'comment' => '商品规格ID'])
            ->addColumn('credit', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '消费学分'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'comment' => '是否开启'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->create();
    }
}
