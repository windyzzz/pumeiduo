<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateUsersDeleteColumn extends Migrator
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
        $this->table('users')
            ->removeColumn('svip_activate_time')
            ->removeColumn('svip_upgrade_time')
            ->removeColumn('svip_referee_number')
            ->removeColumn('grade_referee_num1')
            ->removeColumn('grade_referee_num2')
            ->removeColumn('grade_referee_num3')
            ->removeColumn('grade_referee_num4')
            ->update();
    }
}
