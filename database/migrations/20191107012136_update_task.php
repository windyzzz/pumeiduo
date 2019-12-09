<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateTask extends Migrator
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
        $this->table('task')
            ->addColumn('use_start_time', 'integer', ['null' => true, 'comment' => '使用开始时间', 'after' => 'end_time'])
            ->addColumn('use_end_time', 'integer', ['null' => true, 'comment' => '使用结束时间', 'after' => 'use_start_time'])
            ->update();
    }
}
