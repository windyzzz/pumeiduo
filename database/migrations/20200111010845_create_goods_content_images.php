<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateGoodsContentImages extends Migrator
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
        $this->table('goods_content_images')
            ->addColumn('goods_id', 'integer', ['comment' => '商品ID'])
            ->addColumn('image_identify', 'string', ['limit' => 50, 'comment' => '图标标识（md5加密）'])
            ->addColumn('width', 'string', ['limit' => 10, 'comment' => '宽度'])
            ->addColumn('height', 'string', ['limit' => 10, 'comment' => '高度'])
            ->addColumn('type', 'string', ['limit' => 20, 'comment' => '类型'])
            ->create();
    }
}
