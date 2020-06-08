<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAbroadConfig extends Migrator
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
        $this->table('abroad_config')
            ->addColumn('type', 'string', ['limit' => 20, 'comment' => '类型'])
            ->addColumn('title', 'string', ['null' => true, 'comment' => '标题'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->create();
    }
}
