<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSpecialSmsLog extends Migrator
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
        $this->table('special_sms_log')
            ->addColumn('mobile', 'string', ['limit' => 20])
            ->addColumn('param', 'string')
            ->addColumn('is_send', 'integer', ['default' => 0])
            ->create();
    }
}
