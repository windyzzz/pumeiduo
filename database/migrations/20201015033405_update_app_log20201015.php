<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateAppLog20201015 extends Migrator
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
            ->renameColumn('not_show_version', 'not_show_login_version')
            ->addColumn('show_version', 'text', ['comment' => '提示更新版本', 'after' => 'is_force'])
            ->addColumn('update_version', 'text', ['comment' => '强制更新版本', 'after' => 'show_version'])
            ->update();
    }
}
