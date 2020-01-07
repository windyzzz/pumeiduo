<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateShareSetting extends Migrator
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
        $this->table('share_setting')
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '类型：1推荐码 2商品 3活动文章'])
            ->addColumn('title', 'string', ['comment' => '标题'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('image', 'string', ['null' => true, 'comment' => '图片'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否开启'])
            ->create();
    }
}
