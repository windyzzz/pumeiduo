<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateShareBg extends Migrator
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
        $this->table('share_bg', ['comment' => '用户分享图背景'])
            ->addColumn('type', 'string', ['comment' => '类型：goods商品；user个人'])
            ->addColumn('image', 'string', ['comment' => '图片路径'])
            ->create();
    }
}
