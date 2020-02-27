<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePushLog extends Migrator
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
        $this->table('push_log')
            ->addColumn('push_id', 'integer', ['comment' => '推送消息ID'])
            ->addColumn('user_push_ids', 'string', ['null' => true, 'limit' => 50, 'comment' => '用户推送ID'])
            ->addColumn('user_push_tags', 'string', ['null' => true, 'limit' => 50, 'comment' => '用户推送TAGS'])
            ->addColumn('error_msg', 'text', ['comment' => '错误内容'])
            ->addColumn('error_response', 'text', ['null' => true])
            ->addColumn('create_time', 'integer')
            ->create();
    }
}
