<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolArticleTempResource extends Migrator
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
        $this->table('school_article_temp_resource', ['comment' => '商学院文章临时资源记录表（文章内容的视频、音频）'])
            ->addColumn('article_id', 'integer', ['comment' => '文章ID'])
            ->addColumn('local_path', 'string', ['comment' => '资源本地路径'])
            ->addColumn('oss_path', 'string', ['default' => '', 'comment' => '资源OSS路径'])
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '处理状态：0未处理 1处理完成 -1处理失败'])
            ->create();
    }
}
