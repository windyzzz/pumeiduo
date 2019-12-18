<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAnnounce extends Migrator
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
        $this->table('announce')
            ->addColumn('title', 'string', ['comment' => '标题'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '类型：1商品促销'])
            ->addColumn('goods_id', 'integer', ['comment' => '商品ID'])
            ->addColumn('item_id', 'integer', ['null' => true, 'comment' => '商品规格ID'])
            ->addColumn('goods_num', 'string', ['comment' => '商品名称'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY])
            ->addColumn('create_time', 'integer')
            ->create();
    }
}
