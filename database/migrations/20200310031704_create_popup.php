<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePopup extends Migrator
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
        $this->table('popup')
            ->addColumn('type', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '跳转类型：1无跳转 2VIP申请 3促销商品 4优惠团购 5超值套装 6领券中心 7我的礼券 8任务中心'])
            ->addColumn('show_limit', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '弹出限制：1每天一次 2活动期间一次'])
            ->addColumn('show_path', 'string', ['comment' => '图片路径'])
            ->addColumn('start_time', 'integer', ['comment' => '开始时间'])
            ->addColumn('end_time', 'integer', ['comment' => '结束时间'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '是否开启：0否 1是'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->create();
    }
}
