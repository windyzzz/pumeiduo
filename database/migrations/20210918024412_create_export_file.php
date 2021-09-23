<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateExportFile extends Migrator
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
        $this->table('export_file', ['comment' => '导出文件记录'])
            ->addColumn('type', 'string', ['comment' => '导出类型'])
            ->addColumn('name', 'string', ['comment' => '文件名'])
            ->addColumn('path', 'string', ['comment' => '文件路径'])
            ->addColumn('url', 'string', ['default' => '', 'comment' => '外部下载地址'])
            ->addColumn('table', 'string', ['comment' => '数据查询所属表'])
            ->addColumn('join', 'string', ['default' => '', 'comment' => '数据查询join连接'])
            ->addColumn('condition', 'string', ['default' => '', 'comment' => '数据查询条件'])
            ->addColumn('group', 'string', ['default' => '', 'comment' => '数据查询group组合'])
            ->addColumn('order', 'string', ['default' => '', 'comment' => '数据查询排序'])
            ->addColumn('limit', 'string', ['default' => '', 'comment' => '数据查询限制'])
            ->addColumn('field', 'string', ['default' => '', 'comment' => '数据查询字段'])
            ->addColumn('status', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0,
                'comment' => '状态：0未成功 1成功 2正在导出 -1失败'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
