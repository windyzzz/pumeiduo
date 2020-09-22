<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateCommunityArticle20200904 extends Migrator
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
        $this->table('community_article')
            ->changeColumn('status', 'integer', ['default' => 0, 'comment' => '状态：-3已删除 -2已取消 -1审核失败 0未审核 1通过发布 2预发布'])
            ->addColumn('is_browse', 'integer', ['default' => 0, 'comment' => '用户自己是否已查看', 'after' => 'status'])
            ->addColumn('cancel_time', 'integer', ['default' => 0, 'comment' => '取消时间', 'after' => 'publish_time'])
            ->addColumn('delete_time', 'integer', ['default' => 0, 'comment' => '删除时间', 'after' => 'cancel_time'])
            ->update();
    }
}
