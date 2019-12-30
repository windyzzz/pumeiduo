<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateWithdrawals extends Migrator
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
        $this->table('withdrawals')
            ->addColumn('bank_code', 'string', ['limit' => 25, 'null' => true, 'comment' => '银行代码', 'after' => 'bank_name'])
            ->addColumn('bank_region', 'string', ['null' => true, 'comment' => '银行开户地区', 'after' => 'bank_code'])
            ->addColumn('bank_branch', 'string', ['null' => true, 'comment' => '银行开户支行', 'after' => 'bank_region'])
            ->update();
    }
}
