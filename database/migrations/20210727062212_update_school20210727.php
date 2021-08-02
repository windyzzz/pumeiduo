<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateSchool20210727 extends Migrator
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
        $this->table('school')
            ->addColumn('distribute_grade', 'string', ['default' => '0', 'comment' => '允许查看的代理商等级，0所有人', 'after' => 'sort'])
            ->addColumn('app_grade', 'string', ['default' => '0', 'comment' => '允许查看的商城等级，0所有人', 'after' => 'sort'])
            ->changeColumn('distribute_level', 'string', ['default' => '0', 'comment' => '允许查看的代理商职级，0所有人'])
            ->update();

        $this->table('school_class')
            ->addColumn('distribute_grade', 'string', ['default' => '0', 'comment' => '允许查看的代理商等级，0所有人', 'after' => 'sort'])
            ->addColumn('app_grade', 'string', ['default' => '0', 'comment' => '允许查看的商城等级，0所有人', 'after' => 'sort'])
            ->changeColumn('distribute_level', 'string', ['default' => '0', 'comment' => '允许查看的代理商职级，0所有人'])
            ->update();

        $this->table('school_article')
            ->addColumn('distribute_grade', 'string', ['default' => '0', 'comment' => '允许查看的代理商等级，0所有人', 'after' => 'sort'])
            ->addColumn('app_grade', 'string', ['default' => '0', 'comment' => '允许查看的商城等级，0所有人', 'after' => 'sort'])
            ->changeColumn('distribute_level', 'string', ['default' => '0', 'comment' => '允许查看的代理商职级，0所有人'])
            ->update();

        $this->table('school_standard')
            ->addColumn('distribute_grade', 'string', ['default' => '0', 'comment' => '允许查看的代理商等级，0所有人', 'after' => 'type'])
            ->addColumn('app_grade', 'string', ['default' => '0', 'comment' => '允许查看的商城等级，0所有人', 'after' => 'type'])
            ->changeColumn('distribute_level', 'string', ['default' => '0', 'comment' => '允许查看的代理商职级，0所有人'])
            ->update();
    }
}
