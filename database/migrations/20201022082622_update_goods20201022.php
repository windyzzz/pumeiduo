<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGoods20201022 extends Migrator
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
        $this->table('goods')
            ->addColumn('is_agent', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' =>'是否是代理商商品'])
            ->addColumn('buying_price', 'decimal', ['default' => '0', 'comment' => '进货价（代理商商品）', 'precision' => 10, 'scale' => 2])
            ->addColumn('retail_price', 'decimal', ['default' => '0', 'comment' => '零售价（代理商商品）', 'precision' => 10, 'scale' => 2])
            ->addColumn('buying_price_pv', 'decimal', ['default' => '0', 'comment' => '进货价pv（代理商商品）', 'precision' => 10, 'scale' => 2])
            ->addColumn('applet_on_sale', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' =>'小程序上架状态'])
            ->addColumn('applet_on_time', 'integer', ['default' => 0, 'comment' => '小程序上架时间'])
            ->addColumn('applet_out_time', 'integer', ['default' => 0, 'comment' => '小程序下架时间'])
            ->update();
    }
}
