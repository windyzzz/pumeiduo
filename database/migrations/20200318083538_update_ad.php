<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateAd extends Migrator
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
        $this->table('ad')
            ->addColumn('target_type', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => 'APP跳转类型：1商品详情 2优惠促销 3领券中心 4任务中心'])
            ->addColumn('target_type_id', 'integer', ['default' => 0, 'comment' => 'APP跳转类型参数ID'])
            ->update();
    }
}
