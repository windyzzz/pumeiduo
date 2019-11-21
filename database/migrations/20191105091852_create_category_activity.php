<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateCategoryActivity extends Migrator
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
        $this->table('cate_activity')
            ->addColumn('title', 'string', ['limit' => 50, 'comment' => '标题'])
            ->addColumn('slogan', 'string', ['limit' => 100, 'comment' => '标语'])
            ->addColumn('banner', 'string', ['comment' => '横幅'])
            ->addColumn('background', 'string', ['null' => true, 'comment' => '背景图'])
            ->addColumn('start_time', 'integer', ['comment' => '开始时间'])
            ->addColumn('end_time', 'integer', ['comment' => '结束时间'])
            ->addColumn('is_open', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否开启'])
            ->addColumn('is_end', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否结束'])
            ->create();
        $this->table('cate_activity_goods')
            ->addColumn('cate_act_id', 'integer', ['comment' => '活动ID'])
            ->addColumn('goods_id', 'integer', ['comment' => '商品ID'])
            ->addColumn('item_id', 'integer', ['default' => 0, 'comment' => '商品规格属性ID'])
            ->create();
    }
}
