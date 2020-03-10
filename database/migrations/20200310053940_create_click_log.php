<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateClickLog extends Migrator
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
        $this->table('click_log')
            ->addColumn('position', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '点击地方：1H5下载页头'])
            ->addColumn('ip', 'string', ['limit' => 30, 'comment' => 'ip'])
            ->addColumn('time', 'integer', ['comment' => '时间'])
            ->create();
    }
}
