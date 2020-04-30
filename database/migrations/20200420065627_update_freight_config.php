<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateFreightConfig extends Migrator
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
        $this->table('freight_config')
            ->addColumn('discount_type', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '满优惠类型：0无设置 1数量', 'after' => 'continue_money'])
            ->addColumn('discount_condition', 'decimal', ['default' => '0', 'comment' => '满优惠条件', 'precision' => 10, 'scale' => 2, 'after' => 'discount_type'])
            ->addColumn('discount_money', 'decimal', ['default' => '0', 'comment' => '优惠后的运费', 'precision' => 10, 'scale' => 2, 'after' => 'discount_condition'])
            ->update();
    }
}
