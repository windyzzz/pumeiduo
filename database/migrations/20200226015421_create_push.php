<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePush extends Migrator
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
        $this->table('push')
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '类型：1公告 2活动消息 3优惠券 4商品 5首页'])
            ->addColumn('type_id', 'integer', ['default' => 0, 'comment' => '关联ID（根据类型关联）'])
            ->addColumn('item_id', 'integer', ['default' => 0, 'comment' => '商品规格ID'])
            ->addColumn('title', 'string', ['default' => '', 'comment' => '标题'])
            ->addColumn('desc', 'string', ['default' => '', 'comment' => '简介'])
            ->addColumn('distribute_level', 'integer', ['default' => 0, 'comment' => '推送范围（会员等级），0为全部'])
            ->addColumn('push_time', 'integer', ['default' => 0, 'comment' => '推送时间'])
            ->addColumn('status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '推送状态：0未推送 1推送成功 -1推送失败'])
            ->addColumn('create_time', 'integer', ['comment' => '创建时间'])
            ->create();
    }
}
