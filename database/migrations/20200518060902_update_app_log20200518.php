<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateAppLog20200518 extends Migrator
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
        $this->table('app_log')
            ->addColumn('not_show_version', 'string', ['default' => '', 'limit' => 10, 'comment' => '不显示第三方登录的版本号', 'after' => 'is_force'])
            ->update();
    }
}
