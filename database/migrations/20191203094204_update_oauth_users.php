<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateOauthUsers extends Migrator
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
        $this->table('oauth_users')
            ->changeColumn('user_id', 'integer', ['null' => true, 'comment' => '用户表ID（未绑定手机情况下为空）'])
            ->addColumn('oauth_data', 'text', ['null' => true, 'comment' => '授权的用户数据'])
            ->update();
    }
}
