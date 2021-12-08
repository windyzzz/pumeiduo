<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UserOrderPvLog extends Migrator
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
        $table = $this->table('user_order_pv_log')->setComment("会员业绩记录表");
        $table->addColumn('order_id','integer',['limit'=>11,'comment'=>'订单ID','default'=>0])
            ->addColumn('order_sn','string',['limit'=>60,'comment'=>'订单号','default'=>''])
            ->addColumn('user_id','integer',['limit'=>10,'comment'=>'订单所属用户ID','default'=>0])
            ->addColumn('user_level','integer',['limit'=>2,'comment'=>'订单所属用户等级','default'=>0])
            ->addColumn('user_mobile','string',['limit'=>20,'comment'=>'订单所属用户手机号','default'=>''])
            ->addColumn('user_name','string',['limit'=>60,'comment'=>'订单所属用户关系人会员号','default'=>''])
            ->addColumn('chain_user_id','integer',['limit'=>10,'comment'=>'订单所属用户关系人ID','default'=>0])
            ->addColumn('chain_user_level','integer',['limit'=>2,'comment'=>'订单所属用户关系人等级','default'=>0])
            ->addColumn('chain_user_mobile','string',['limit'=>20,'comment'=>'订单所属用户关系人手机号','default'=>''])
            ->addColumn('chain_user_name','string',['limit'=>60,'comment'=>'订单所属用户关系人会员号','default'=>''])
            ->addColumn('pv','decimal',['precision'=>10,'scale'=>2,'default'=>0.00,'comment'=>'个人PV','null'=>FALSE])
            ->addColumn('team_pv','decimal',['precision'=>10,'scale'=>2,'default'=>0.00,'comment'=>'团队PV','null'=>FALSE])
            ->addColumn('chain_user_generation','integer',['limit'=>2,'default'=>0,'comment'=>'关系人代数'])
            ->addColumn('parent_chain','string',['limit'=>2000,'default'=>"",'comment'=>'关系链条'])
            ->addColumn('first_leader','integer',['limit'=>10,'default'=>0,'comment'=>'推荐上级'])
            ->addColumn('second_leader','integer',['limit'=>10,'default'=>0,'comment'=>'推荐上上级'])
            ->addColumn('third_leader','integer',['limit'=>10,'default'=>0,'comment'=>'推荐上上上级'])
            ->addColumn('order_price','decimal',['precision'=>10,'scale'=>2,'default'=>0.00,'comment'=>'订单金额','null'=>FALSE])
            ->addColumn('year','string',['limit'=>4,'default'=>"",'comment'=>'支付时间：年份'])
            ->addColumn('month','string',['limit'=>8,'default'=>"",'comment'=>'支付时间：月份'])
            ->addColumn('day','string',['limit'=>12,'default'=>"",'comment'=>'支付时间：日期'])
            ->addColumn('add_time','integer',['limit'=>11,'comment'=>'添加时间','default'=>0])
            ->create();
    }
}
