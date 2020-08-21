<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateCommunityArticleEditLog extends Migrator
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
        $this->table('community_article_edit_log', ['comment' => '社区文章修改记录表'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '修改对象：1用户 2管理员'])
            ->addColumn('user_id', 'integer', ['comment' => '用户/管理员ID'])
            ->addColumn('data', 'text', ['comment' => '记录数据'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
