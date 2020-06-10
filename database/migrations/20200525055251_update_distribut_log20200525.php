<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateDistributLog20200525 extends Migrator
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
        $this->table('distribut_log')
            ->addColumn('upgrade_money', 'decimal', ['default' => '0', 'comment' => '升级金额', 'precision' => 10, 'scale' => 2])
            ->addColumn('note_status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '提示状态：0未提示 1已提示'])
            ->update();
    }
}
