<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateDeliveryDoc20200427 extends Migrator
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
        $this->table('delivery_doc')
            ->addColumn('goods_num', 'integer', ['null' => true, 'comment' => '商品数量', 'after' => 'rec_id'])
            ->changeColumn('admin_id', 'integer', ['comment' => '（仓储系统）管理员ID'])
            ->update();
    }
}
