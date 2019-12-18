<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateArticle extends Migrator
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
            ->addColumn('app_content', 'text', ['null' => true, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'comment' => 'APP显示的内容（与content一致）', 'after' => 'content'])
            ->addColumn('finish_time', 'integer', ['null' => true, 'comment' => '活动结束时间', 'after' => 'publish_time'])
            ->addColumn('extend_cate_id', 'integer', ['null' => true, 'comment' => '额外分类ID', 'after' => 'cat_id'])
            ->addColumn('extend_sort', 'integer', ['default' => 0, 'comment' => '额外排序', 'after' => 'extend_cate_id'])
            ->changeColumn('video', 'string', ['null' => 'true', 'comment' => '视频连接'])
            ->update();
    }
}
