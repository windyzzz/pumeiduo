<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateTaskLog extends Migrator
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
        $this->table('task_log')
            ->changeColumn('reward_coupon_id', 'string', ['limit' => 50, 'default' => '0', 'comment' => '奖励现金券ID，多张用 - 隔开'])
            ->changeColumn('reward_coupon_money', 'string', ['limit' => 100, 'default' => '0', 'comment' => '奖励现金券对应的价值，多张用 - 隔开'])
            ->changeColumn('reward_coupon_name', 'string', ['limit' => 255, 'default' => '0', 'comment' => '奖励现金券名，多张用 - 隔开'])
            ->changeColumn('status', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' =>'奖励的领取状态（0：未领取，1：已经领取，-1已取消）'])
            ->update();
    }
}
