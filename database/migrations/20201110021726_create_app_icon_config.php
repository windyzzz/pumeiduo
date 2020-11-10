<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAppIconConfig extends Migrator
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
        $this->table('app_icon_config')
            ->addColumn('type', 'string', ['comment' => '所属地方'])
            ->addColumn('row_num', 'integer', ['comment' => '一行个数'])
            ->create();
    }
}
