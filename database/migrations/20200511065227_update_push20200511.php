<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdatePush20200511 extends Migrator
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
            ->changeColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '类型：1公告 2活动消息 3领券中心 4商品 5首页 6促销商品区 7SVIP专享 8VIP申请区 9我的礼券'])
            ->update();
    }
}
