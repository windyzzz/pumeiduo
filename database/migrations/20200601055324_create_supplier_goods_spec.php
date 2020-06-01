<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSupplierGoodsSpec extends Migrator
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
        $this->table('supplier_goods_spec', ['comment' => '供应链商品规格标识'])
            ->addColumn('spec_id', 'integer', ['comment' => '规格标识ID'])
            ->addColumn('name', 'string', ['limit' => 20, 'comment' => '标识名称'])
            ->addColumn('supplier_id', 'integer', ['comment' => '供应商ID'])
            ->create();
    }
}
