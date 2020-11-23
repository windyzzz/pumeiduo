<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolClass extends Migrator
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
        $this->table('school_class', ['comment' => '商学院模块分类表'])
            ->addColumn('module_id', 'integer', ['comment' => '模块ID'])
            ->addColumn('name', 'string', ['comment' => '名称'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'comment' => '是否开启'])
            ->addColumn('is_allow', 'integer', ['default' => 1, 'comment' => '是否允许访问'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('distribute_level', 'string', ['default' => '0', 'comment' => '允许查看的用户等级，0所有人'])
            ->create();
    }
}
