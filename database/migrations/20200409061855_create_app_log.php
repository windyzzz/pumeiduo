<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAppLog extends Migrator
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
        $this->table('app_log', ['comment' => 'APP更新记录表'])
            ->addColumn('type', 'string', ['limit' => 10, 'comment' => 'APP类型'])
            ->addColumn('app_version', 'string', ['limit' => 10, 'comment' => '版本号'])
            ->addColumn('app_log', 'text', ['comment' => '版本日志'])
            ->addColumn('app_path', 'string', ['comment' => '下载路径'])
            ->addColumn('is_update', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否需要更新'])
            ->addColumn('is_force', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'comment' => '是否强制更新'])
            ->addColumn('create_time', 'integer', ['comment' => '创建时间'])
            ->addColumn('update_time', 'integer', ['comment' => '更新时间'])
            ->create();
    }
}
