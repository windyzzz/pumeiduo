<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolArticle extends Migrator
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
        $this->table('school_article', ['comment' => '商学院文章表'])
            ->addColumn('class_id', 'integer', ['comment' => '商学院模块分类ID'])
            ->addColumn('title', 'string', ['comment' => '标题'])
            ->addColumn('subtitle', 'string', ['default' => '', 'comment' => '副标题'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('cover', 'string', ['comment' => '封面图'])
            ->addColumn('learn', 'integer', ['default' => 0, 'comment' => '学习人数'])
            ->addColumn('share', 'integer', ['default' => 0, 'comment' => '分享人数'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：-1已删除 1发布 2预发布 3不发布'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('distribute_level', 'string', ['default' => '0', 'comment' => '允许查看的用户等级，0所有人'])
            ->addColumn('integral', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '学习需要积分'])
            ->addColumn('add_time', 'integer')
            ->addColumn('publish_time', 'integer', ['default' => 0])
            ->addColumn('update_time', 'integer', ['default' => 0])
            ->addColumn('delete_time', 'integer', ['default' => 0])
            ->create();
    }
}
