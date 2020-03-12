<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserLoginLog extends Migrator
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
        $this->table('user_login_log')
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('login_ip', 'string', ['limit' => 30, 'comment' => '登录ip'])
            ->addColumn('login_time', 'integer', ['comment' => '登录时间'])
            ->addColumn('login_date', 'string', ['limit' => 20, 'comment' => '登录日期'])
            ->addColumn('source', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '登录来源：1微信 2PC 3APP'])
            ->addColumn('tinyint', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '首次APP登陆，0不是 1是'])
            ->create();
    }
}
