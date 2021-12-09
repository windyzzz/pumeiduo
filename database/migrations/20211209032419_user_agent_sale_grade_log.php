<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UserAgentSaleGradeLog extends Migrator
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
        $table = $this->table("user_agent_sale_grade_log")->setComment("经销商返利定级记录表");
        $table->addColumn('user_id','integer',['limit'=>10,'default'=>0,'comment'=>"会员ID"])
            ->addColumn('grade','integer',['limit'=>1,'default'=>0,'comment'=>"经销商当月级别"])
            ->addColumn('user_level','integer',['limit'=>10,'default'=>0,'comment'=>"会员等级"])
            ->addColumn('grade_name','string',['limit'=>60,'default'=>0,'comment'=>"级别名称"])
            ->addColumn('effect_time','integer',['limit'=>11,'default'=>0,'comment'=>"经销商级别生效时间"])
            ->addColumn('expire_time','integer',['limit'=>11,'default'=>0,'comment'=>"经销商级别过期时间"])
            ->addColumn('status','integer',['limit'=>1,'default'=>0,'comment'=>"记录状态0=失效，1=有效"])
            ->addColumn('add_time','integer',['limit'=>11,'default'=>0,'comment'=>"记录添加时间"])
            ->create();
    }
}
