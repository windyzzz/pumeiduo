<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSpecialRegion extends Migrator
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
        $this->table('special_region', ['comment' => '临时保存的本地存在但供应链不存在的地区信息'])
            ->addColumn('name', 'string', ['limit' => 20])
            ->addColumn('parent_id', 'integer')
            ->addColumn('level', 'integer')
            ->addColumn('first_money', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2])
            ->addColumn('next_money', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2])
            ->create();
    }
}
