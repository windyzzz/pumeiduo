<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateUserPopupLog extends Migrator
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
        $this->table('user_popup_log', ['comment' => '用户弹窗记录'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '类型：1活动 2文章', 'after' => 'id'])
            ->changeColumn('user_id', 'integer', ['default' => 0, 'comment' => '用户ID'])
            ->addColumn('equip_id', 'string', ['default' => '', 'comment' => '设备ID', 'after' => 'user_id'])
            ->changeColumn('popup_id', 'integer', ['comment' => '活动弹窗ID / 文章ID'])
            ->update();
    }
}
