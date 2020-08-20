<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserIdCardInfo extends Migrator
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
        $this->table('user_id_card_info', ['comment' => '用户身份证信息'])
            ->addColumn('user_id', 'integer', ['default' => 0, 'comment' => '用户ID'])
            ->addColumn('id_card', 'string', ['limit' => 20, 'comment' => '身份证'])
            ->addColumn('real_name', 'string', ['comment' => '真实姓名'])
            ->create();
    }
}
