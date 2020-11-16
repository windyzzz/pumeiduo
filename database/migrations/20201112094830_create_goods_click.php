<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateGoodsClick extends Migrator
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
        $this->table('goods_click', ['comment' => '商品点击记录表'])
            ->addColumn('goods_id', 'integer')
            ->addColumn('user_id', 'integer', ['default' => 0])
            ->addColumn('add_time', 'integer')
            ->addColumn('is_first', 'integer', ['default' => 0, 'comment' => '是否是初始化数据'])
            ->create();
    }
}
