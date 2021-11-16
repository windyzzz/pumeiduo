<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSvipInfo extends Migrator
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
        $this->table('svip_info', ['comment' => 'SVIP（代理商）信息'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('user_name', 'string', ['default' => '', 'limit' => 20, 'comment' => '用户名'])
            ->addColumn('real_name', 'string', ['default' => '', 'limit' => 20, 'comment' => '真实姓名'])
            ->addColumn('svip_activate_time', 'integer', ['default' => 0, 'comment' => '代理商激活时间'])
            ->addColumn('svip_upgrade_time', 'integer', ['default' => 0, 'comment' => '升级到代理商的时间'])
            ->addColumn('svip_referee_number', 'integer', ['default' => 0, 'comment' => '代理商推荐总人数'])
            ->addColumn('grade_referee_num1', 'integer', ['default' => 0, 'comment' => '代理商推荐游客人数'])
            ->addColumn('grade_referee_num2', 'integer', ['default' => 0, 'comment' => '代理商推荐优享会员人数'])
            ->addColumn('grade_referee_num3', 'integer', ['default' => 0, 'comment' => '代理商推荐尊享会员人数'])
            ->addColumn('grade_referee_num4', 'integer', ['default' => 0, 'comment' => '代理商推荐代理商人数'])
            ->addColumn('network_parent_user_name', 'string', ['default' => '', 'comment' => '服务人用户名'])
            ->addColumn('network_parent_real_name', 'string', ['default' => '', 'comment' => '服务人真实姓名'])
            ->addColumn('customs_user_name', 'string', ['default' => '', 'comment' => '服务中心用户名'])
            ->addColumn('customs_real_name', 'string', ['default' => '', 'comment' => '服务中心真实姓名'])
            ->addIndex(['user_id'])
            ->addIndex(['user_name'])
            ->create();
    }
}
