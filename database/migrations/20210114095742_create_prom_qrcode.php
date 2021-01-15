<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePromQrcode extends Migrator
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
        $this->table('prom_qrcode', ['comment' => '扫码优惠活动'])
            ->addColumn('reward_type', 'integer', ['comment' => '赠送类型：1优惠券 2电子币'])
            ->addColumn('reward_content', 'string', ['comment' => '赠送内容'])
            ->addColumn('code', 'string', ['limit' => 8, 'comment' => '兑换码'])
            ->addColumn('qrcode', 'string', ['comment' => '兑换码二维码图片路径'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'comment' => '是否开启'])
            ->addColumn('start_time', 'integer', ['comment' => '开始时间'])
            ->addColumn('end_time', 'integer', ['comment' => '结束时间'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
