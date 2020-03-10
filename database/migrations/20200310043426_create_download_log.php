<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateDownloadLog extends Migrator
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
        $this->table('download_log')
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '类型：1苹果 2安卓'])
            ->addColumn('down_ip', 'string', ['limit' => 30, 'comment' => 'ip'])
            ->addColumn('down_time', 'integer', ['comment' => '时间'])
            ->create();
    }
}
