<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateCoupon20210615 extends Migrator
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
            ->changeColumn('type_value', 'string', ['default' => '0', 'comment' => '发放对象 0：所有人，1：注册会员，2：普卡会员，3：网店会员，4：新用户，5：新VIP，6：SVIP(首次登陆APP)'])
            ->changeColumn('image_url', 'string', ['default' => ''])
            ->update();
    }
}
