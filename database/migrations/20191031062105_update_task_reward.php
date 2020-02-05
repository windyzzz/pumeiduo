<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateTaskReward extends Migrator
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
        $this->table('task_reward')
            ->changeColumn('reward_coupon_id', 'string', ['limit' => 50, 'default' => '0', 'comment' => '奖励现金券ID，多张用 - 隔开'])
            ->changeColumn('cycle', 'integer', ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '任务周期：0限一次 1每次 2每日 3每周 4每月'])
            ->addColumn('reward_times', 'integer', ['default' => 0, 'comment' => '奖励次数，0不限制', 'after' => 'reward_coupon_id'])
            ->update();
    }
}
