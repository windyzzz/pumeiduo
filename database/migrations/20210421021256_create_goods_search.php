<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateGoodsSearch extends Migrator
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
        $this->table('goods_search', ['comment' => '商品搜索词记录'])
            ->addColumn('keyword', 'string', ['comment' => '搜索词'])
            ->addColumn('search_num', 'integer', ['default' => 1, 'comment' => '搜索次数'])
            ->addIndex(['keyword'], ['name' => 'keyword'])
            ->create();
    }
}
