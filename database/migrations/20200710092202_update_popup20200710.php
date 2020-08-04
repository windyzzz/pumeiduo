<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdatePopup20200710 extends Migrator
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
            ->addColumn('type_id', 'integer', ['default' => 0, 'comment' => '关联ID（根据类型关联', 'after' => 'type'])
            ->addColumn('item_id', 'integer', ['default' => 0, 'comment' => '商品规格ID', 'after' => 'type_id'])
            ->update();
    }
}
