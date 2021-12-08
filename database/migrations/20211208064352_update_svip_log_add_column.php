<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateSvipLogAddColumn extends Migrator
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
        $this->table('svip_info')
            ->addColumn('account_money', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '奖金账户'])
            ->addColumn('customs_money', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '电子币'])
            ->addColumn('xiaofei_money', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '乐活优选积分'])
            ->addColumn('carroom', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '旅游账户'])
            ->addColumn('point_money', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '税费'])
            ->addColumn('account_sync', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否资金同步'])
            ->update();
    }
}
