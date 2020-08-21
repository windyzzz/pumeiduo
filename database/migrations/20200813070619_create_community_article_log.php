<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateCommunityArticleLog extends Migrator
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
        $this->table('community_article_verify_log', ['comment' => '社区文章审核记录'])
            ->addColumn('article_id', 'integer', ['comment' => '社区文章ID'])
            ->addColumn('status', 'integer', ['comment' => '审核状态：1通过 -1拒绝'])
            ->addColumn('reason', 'string', ['default' => '', 'comment' => '拒绝原因'])
            ->addColumn('admin_id', 'integer', ['comment' => '管理员ID'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
