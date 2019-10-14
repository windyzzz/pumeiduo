<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateCart extends Migrator
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
        $this->table('cart')
            ->addColumn('token', 'string', ['limit' => 64, 'null' => true, 'comment' => '用户token', 'after' => 'user_id'])
            ->update();
    }
}
