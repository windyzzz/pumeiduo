<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateGoods20210311 extends Migrator
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
        $this->table('goods')
            ->addColumn('video_cover', 'string', ['default' => '', 'comment' => '视频封面图', 'after' => 'video'])
            ->addColumn('video_axis', 'integer', ['default' => 1, 'comment' => '视频轴方向：1横向型 2竖向型', 'after' => 'video_cover'])
            ->update();
    }
}
