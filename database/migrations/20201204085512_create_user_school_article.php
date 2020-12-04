<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserSchoolArticle extends Migrator
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
        $this->table('user_school_article', ['comment' => '用户商学院文章记录表'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('article_id', 'integer', ['comment' => '文章ID'])
            ->addColumn('integral', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '学习需要积分'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
