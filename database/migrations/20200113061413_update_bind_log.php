<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateBindLog extends Migrator
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
        $this->table('bind_log')
            ->changeColumn('type', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '类型：1绑定 2解绑'])
            ->addColumn('way', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '合并方式：0未确定 1前端绑定 2后台绑定 3扫码绑定', 'after' => 'type'])
            ->changeColumn('openid', 'string', ['null' => true])
            ->changeColumn('unionid', 'string', ['null' => true])
            ->changeColumn('oauth', 'string', ['null' => true])
            ->update();
    }
}
