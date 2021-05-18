<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateUserSchoolArticle20210518 extends Migrator
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
        $this->table('user_school_article')
            ->addColumn('times', 'integer', ['default' => 0, 'comment' => '学习完成次数', 'after' => 'finish_time'])
            ->update();
    }
}
