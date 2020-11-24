<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUserShareImage extends Migrator
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
        $this->table('user_share_image', ['comment' => '用户分享图片表'])
            ->addColumn('user_id', 'integer')
            ->addColumn('head_pic', 'string', ['comment' => '用户网络头像下载本地路径'])
            ->addColumn('share_pic', 'string', ['comment' => '用户分享图本地路径'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
