<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSuggestion extends Migrator
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
        $this->table('suggestion_cate')
            ->addColumn('name', 'string', ['comment' => '名称'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->create();

        $this->table('suggestion')
            ->addColumn('phone', 'string', ['limit' => 20, 'comment' => '手机号码'])
            ->addColumn('cate_id', 'integer', ['comment' => '类型ID'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('is_handled', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '内容'])
            ->addColumn('create_time', 'integer')
            ->create();
    }
}
