<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UserReferrerLog extends Migrator
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
        $table = $this->table('user_referrer_log')->setComment("会员推荐关系变更记录表");
        $table->addColumn('user_id','integer',['limit'=>11,'comment'=>'会员ID','default'=>0])
            ->addColumn('user_level','integer',['limit'=>2,'comment'=>'会员等级','default'=>0])
            ->addColumn('user_referrer_chain','text',['limit'=>\Phinx\Db\Adapter\MysqlAdapter::TEXT_REGULAR,'comment'=>'变更前会员推荐关系链接'])
            ->addColumn('old_referrer','integer',['limit'=>11,'comment'=>'旧推荐人ID','default'=>0])
            ->addColumn('new_referrer','integer',['limit'=>11,'comment'=>'新推荐人ID','default'=>0])
            ->addColumn('add_time','integer',['limit'=>11,'comment'=>'添加时间','default'=>0])
            ->create();
    }
}
