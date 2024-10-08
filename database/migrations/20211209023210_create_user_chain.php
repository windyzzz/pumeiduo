<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserChain extends Migrator
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
        $this->table('user_chain', ['comment' => '用户链记录'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('referee_ids', 'text', ['comment' => '推荐链'])
            ->addIndex(['user_id'])
            ->create();
    }
}
