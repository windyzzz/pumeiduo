<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGoods20200320 extends Migrator
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
            ->addColumn('retail_pv', 'decimal', ['default' => '0', 'comment' => '零售价pv', 'precision' => 10, 'scale' => 2])
            ->addColumn('integral_pv', 'decimal', ['default' => '0', 'comment' => '积分价pv', 'precision' => 10, 'scale' => 2])
            ->update();
    }
}
