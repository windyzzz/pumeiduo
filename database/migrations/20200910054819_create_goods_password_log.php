<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateGoodsPasswordLog extends Migrator
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
        $this->table('goods_password_log', ['comment' => '商品口令记录表'])
            ->addColumn('password_id', 'integer', ['comment' => '口令ID'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
