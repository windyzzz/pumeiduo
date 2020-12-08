<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolArticleResource extends Migrator
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
        $this->table('school_article_resource', ['comment' => '商学院文章素材表'])
            ->addColumn('article_id', 'integer', ['comment' => '商学院文章ID'])
            ->addColumn('image', 'string', ['default' => '', 'comment' => '图片'])
            ->addColumn('get_image_info', 'integer', ['default' => '0', 'comment' => '是否已记录图片信息'])
            ->addColumn('video', 'string', ['default' => '', 'comment' => '视频'])
            ->addColumn('video_cover', 'string', ['default' => '', 'comment' => '视频封面图'])
            ->addColumn('video_axis', 'integer', ['default' => '1', 'comment' => '视频轴方向：1横向型 2竖向型'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
