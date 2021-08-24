<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateBrandConfig extends Migrator
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
        $this->table('brand_config', ['comment' => '品牌设置'])
            ->addColumn('type', 'string', ['limit' => 20, 'comment' => '类型'])
            ->addColumn('name', 'string', ['comment' => '配置名'])
            ->addColumn('url', 'string')
            ->addColumn('content', 'text', ['null' => true])
            ->create();
    }
}
