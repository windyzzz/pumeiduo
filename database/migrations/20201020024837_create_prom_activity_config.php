<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePromActivityConfig extends Migrator
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
        $this->table('prom_activity_config', ['comment' => '促销活动配置表'])
            ->addColumn('index_banner', 'string', ['comment' => '首页banner'])
            ->addColumn('inside_header', 'string', ['comment' => '内页页头'])
            ->addColumn('inside_banner', 'string', ['comment' => '内页banner'])
            ->addColumn('inside_bgcolor', 'string', ['default' => '', 'comment' => '内页背景颜色'])
            ->addColumn('is_open', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否开启'])
            ->create();
    }
}
