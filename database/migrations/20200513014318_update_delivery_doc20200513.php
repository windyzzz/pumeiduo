<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateDeliveryDoc20200513 extends Migrator
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
            ->addColumn('htns_status', 'string', ['default' => '000', 'limit' => 10, 'comment' => 'HTNS的物流状态（文档5.1.2 配送状态编号）'])
            ->update();
    }
}
