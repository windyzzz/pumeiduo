<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateCoupon extends Migrator
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
        $this->table('coupon')
            ->changeColumn('type_value', 'string', ['default' => '', 'comment' => '放对象（0：所有人，1：注册会员，2：普卡会员，3：网店会员，4：新用户）', 'after' => 'type'])
            ->changeColumn('use_type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0,
                'comment' => '使用范围：0全店通用 1指定商品可用 2指定分类可用 4折扣商品 5兑换商品', 'after' => 'type_value'])
            ->addColumn('is_usual', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0,
                'comment' => '是否可叠加优惠（不与商品优惠促销、订单优惠促销冲突）', 'after' => 'status'])
            ->addColumn('nature', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1,
                'comment' => '优惠券种类：1普通 2任务', 'after' => 'id'])
            ->update();
    }
}
