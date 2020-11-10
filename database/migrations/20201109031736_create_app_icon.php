<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAppIcon extends Migrator
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
        $this->table('app_icon')
            ->addColumn('code', 'string', ['limit' => 20, 'comment' => '标识'])
            ->addColumn('name', 'string', ['limit' => 20, 'comment' => '名字'])
            ->addColumn('img', 'string', ['comment' => '图标路径'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'comment' => '是否开启'])
            ->addColumn('type', 'string', ['comment' => '所属地方'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->create();
    }
}
