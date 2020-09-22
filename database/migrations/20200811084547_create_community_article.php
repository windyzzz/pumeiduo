<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateCommunityArticle extends Migrator
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
        $this->table('community_article', ['comment' => '社区文章表'])
            ->addColumn('title', 'string', ['default' => '', 'comment' => '标题'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID/管理员ID'])
            ->addColumn('cate_id1', 'integer', ['comment' => '所属社区顶级分类'])
            ->addColumn('cate_id2', 'integer', ['comment' => '所属社区下级分类'])
            ->addColumn('goods_id', 'integer', ['default' => 0, 'comment' => '关联商品ID'])
            ->addColumn('item_id', 'integer', ['default' => 0, 'comment' => '关联商品规格ID'])
            ->addColumn('image', 'text', ['comment' => '图片'])
            ->addColumn('video', 'string', ['default' => '', 'comment' => '视频'])
            ->addColumn('video_cover', 'string', ['default' => '', 'comment' => '视频封面图'])
            ->addColumn('video_axis', 'integer', ['default' => 1, 'comment' => '视频轴方向：1横向型 2竖向型'])
            ->addColumn('share', 'integer', ['default' => 0, 'comment' => '分享次数'])
            ->addColumn('click', 'integer', ['default' => 0, 'comment' => '点击次数'])
            ->addColumn('status', 'integer', ['default' => 0, 'comment' => '状态：0未审核 1通过发布 -1审核失败 2预发布'])
            ->addColumn('reason', 'string', ['default' => '', 'comment' => '审核失败原因'])
            ->addColumn('add_time', 'integer')
            ->addColumn('up_time', 'integer', ['default' => 0, 'comment' => '更新时间'])
            ->addColumn('publish_time', 'integer', ['default' => 0, 'comment' => '审核发布时间'])
            ->addColumn('source', 'integer', ['comment' => '来源：1用户 2管理员'])
            ->addIndex(['cate_id1', 'cate_id2'], ['name' => 'cate_id'])
            ->create();
    }
}
