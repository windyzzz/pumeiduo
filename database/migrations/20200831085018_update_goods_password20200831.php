<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGoodsPassword20200831 extends Migrator
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
        $this->table('goods_password')
            ->addColumn('is_official', 'integer', ['default' => 0, 'comment' => '是否是官方账号', 'after' => 'user_id'])
            ->addColumn('source', 'integer', ['comment' => '来源：1商品详情 2社区文章'])
            ->addColumn('creator_id', 'integer', ['comment' => '生成者ID'])
            ->update();
    }
}
