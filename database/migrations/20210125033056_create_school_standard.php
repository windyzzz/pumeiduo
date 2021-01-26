<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolStandard extends Migrator
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
        $this->table('school_standard', ['comment' => '商学院学习达标标准'])
            ->addColumn('level', 'string', ['comment' => '等级'])
            ->addColumn('course_percent', 'decimal', ['default' => '0', 'precision' => 10, 'scale' => 2, 'comment' => '课程数量百分比'])
            ->create();
    }
}
