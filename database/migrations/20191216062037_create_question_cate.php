<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateQuestionCate extends Migrator
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
        $this->table('question_cate')
            ->addColumn('name', 'string', ['comment' => '分类名称'])
            ->addColumn('desc', 'string', ['null' => true, 'comment' => '描述'])
            ->addColumn('sort', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL, 'comment' => '排序'])
            ->addColumn('is_show', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否显示'])
            ->create();
    }
}
