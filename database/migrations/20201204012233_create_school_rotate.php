<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolRotate extends Migrator
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
        $this->table('school_rotate', ['comment' => '商学院轮播图表'])
            ->addColumn('module_id', 'integer', ['default' => 0, 'comment' => '模块ID 0为最外层的轮播图'])
            ->addColumn('url', 'string', ['comment' => '图片路径'])
            ->addColumn('module_type', 'string', ['default' => '', 'comment' => '跳转模块标识'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'comment' => '是否开启'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
