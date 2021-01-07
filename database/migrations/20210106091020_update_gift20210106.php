<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGift20210106 extends Migrator
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
        $this->table('gift')
            ->addColumn('goods_nature', 'integer', ['default' => 0, 'comment' => '商品种类，0全部 1圃美多 2韩国购 3供应链 4代理商', 'after' => 'remarks'])
            ->update();
    }
}
