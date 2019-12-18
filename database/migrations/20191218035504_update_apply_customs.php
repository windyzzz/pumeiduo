<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateApplyCustoms extends Migrator
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
        $this->table('apply_customs')
            ->changeColumn('id_card', 'string', ['limit' => 18, 'comment' => '身份证'])
            ->changeColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '状态：0审核中 1审核完成 2撤销'])
            ->changeColumn('add_time', 'integer', ['comment' => '申请时间', 'after' => 'status'])
            ->changeColumn('success_time', 'integer', ['null' => true, 'comment' => '成功时间'])
            ->changeColumn('cancel_time', 'integer', ['null' => true, 'comment' => '撤销时间'])
            ->update();
    }
}
