<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePromQrcodeLog extends Migrator
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
        $this->table('prom_qrcode_log', ['comment' => '扫码优惠活动记录'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('prom_id', 'integer', ['comment' => '活动ID'])
            ->addColumn('reward_type', 'integer', ['comment' => '赠送类型：1优惠券 2电子币'])
            ->addColumn('reward_content', 'string', ['comment' => '赠送内容'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
