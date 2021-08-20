<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGoods20210820 extends Migrator
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
            ->addColumn('retail_pv_bak', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '零售价pv备份字段', 'after' => 'retail_pv'])
            ->addColumn('integral_pv_bak', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '积分价pv备份字段', 'after' => 'integral_pv'])
            ->update();
    }
}
