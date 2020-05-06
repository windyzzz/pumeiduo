<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateGoodspvUpdateLog extends Migrator
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
        $this->table('goodspv_update_log', ['comment' => '外部批量更新商品pv记录表'])
            ->addColumn('goods_id', 'integer', ['default' => '0','comment' => '商品ID'])
            ->addColumn('goods_sn', 'string', ['limit' => 20, 'comment' => '商品编号'])
            ->addColumn('goods_name', 'string', ['comment' => '商品名称'])
            ->addColumn('status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '状态：0失败 1成功'])
            ->addColumn('old_retail_pv', 'decimal', ['default' => '0', 'comment' => '旧零售价pv', 'precision' => 10, 'scale' => 2])
            ->addColumn('new_retail_pv', 'decimal', ['default' => '0', 'comment' => '新零售价pv', 'precision' => 10, 'scale' => 2])
            ->addColumn('old_integral_pv', 'decimal', ['default' => '0', 'comment' => '旧积分价pv', 'precision' => 10, 'scale' => 2])
            ->addColumn('new_integral_pv', 'decimal', ['default' => '0', 'comment' => '新积分价pv', 'precision' => 10, 'scale' => 2])
            ->addColumn('note', 'string', ['null' => true, 'comment' => '备注'])
            ->addColumn('create_time', 'integer')
            ->create();
    }
}
