<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateSivpInfoAddColumnReferee extends Migrator
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
        $this->table('svip_info')
            ->addColumn('referee_user_name', 'string', ['default' => '', 'comment' => '推荐人用户名', 'after' => 'grade_referee_num4'])
            ->addColumn('referee_real_name', 'string', ['default' => '', 'comment' => '推荐人真实姓名', 'after' => 'referee_user_name'])
            ->update();
    }
}
