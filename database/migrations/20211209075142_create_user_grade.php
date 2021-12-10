<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserGrade extends Migrator
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
        $this->table('user_grade', ['comment' => '会员职级'])
            ->setId('level_id')
            ->addColumn('level_name', 'string', ['limit' => 30, 'comment' => '等级名称'])
            ->addColumn('pv_from', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '月度累计业绩达标资格开始'])
            ->addColumn('pv_to', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '月度累计业绩达标资格结束'])
            ->addColumn('supply_rate', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '供货率（%）'])
            ->addColumn('purchase_rate', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '进货率（%）'])
            ->addColumn('retail_rate', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '代零售返点（%）'])
            ->addColumn('wholesale_rate', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '代批发返点（%）'])
            ->addColumn('status', 'integer', ['default' => 1])
            ->create();
    }
}
