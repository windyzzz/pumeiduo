<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateArticle20210712 extends Migrator
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
        $this->table('article')
            ->addColumn('nature', 'integer', ['default' => 1, 'comment' => '文章种类：1普通文章 2首页弹窗', 'after' => 'article_id'])
            ->update();

        $this->table('user_article')
            ->addColumn('add_time', 'integer', ['default' => 0, 'after' => 'status'])
            ->update();
    }
}
