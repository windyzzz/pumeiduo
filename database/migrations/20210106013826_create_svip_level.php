<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSvipLevel extends Migrator
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
        $this->table('svip_level', ['comment' => 'SVIP等级表'])
            ->addColumn('app_level', 'integer', ['comment' => 'APP的等级标识'])
            ->addColumn('agent_level', 'integer', ['comment' => '代理商的等级标识'])
            ->addColumn('name', 'string', ['comment' => '等级名称'])
            ->create();
    }
}
