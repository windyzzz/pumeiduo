<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateGoodsPassword extends Migrator
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
        $this->table('goods_password', ['comment' => '商品口令表'])
            ->addColumn('goods_id', 'integer', ['comment' => '商品ID'])
            ->addColumn('item_id', 'integer', ['default' => 0, 'comment' => '规格ID'])
            ->addColumn('password', 'string', ['limit' => 20, 'comment' => '口令'])
            ->addColumn('user_id', 'integer', ['default' => 0, 'comment' => '用户ID'])
            ->addColumn('add_time', 'integer')
            ->addColumn('dead_time', 'integer', ['comment' => '失效时间'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：1有效 0失效'])
            ->create();
    }
}
