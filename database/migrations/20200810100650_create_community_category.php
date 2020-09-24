<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateCommunityCategory extends Migrator
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
        $this->table('community_category', ['comment' => '社区分类'])
            ->addColumn('cate_name', 'string', ['comment' => '分类名'])
            ->addColumn('parent_id', 'integer', ['comment' => '父级ID'])
            ->addColumn('level', 'integer', ['default' => 0, 'comment' => '等级'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '显示状态：1显示 0不显示'])
            ->addColumn('user_can_publish', 'integer', ['default' => 0, 'comment' => '分类下能否直接发布文章：0不能 1能够'])
            ->create();
    }
}
