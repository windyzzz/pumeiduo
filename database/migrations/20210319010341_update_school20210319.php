<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateSchool20210319 extends Migrator
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
        $this->table('school')
            ->addIndex(['type'], ['name' => 'type'])
            ->update();

        $this->table('school_class')
            ->addIndex(['module_id'], ['name' => 'module_id'])
            ->update();

        $this->table('school_rotate')
            ->addIndex(['module_id'], ['name' => 'module_id'])
            ->update();

        $this->table('school_article')
            ->addIndex(['class_id'], ['name' => 'class_id'])
            ->update();

        $this->table('school_article_resource')
            ->addIndex(['article_id'], ['name' => 'article_id'])
            ->update();

        $this->table('school_exchange')
            ->addIndex(['goods_id'], ['name' => 'goods_id'])
            ->addIndex(['item_id'], ['name' => 'item_id'])
            ->update();

        $this->table('user_school_article')
            ->addIndex(['user_id'], ['name' => 'user_id'])
            ->addIndex(['article_id'], ['name' => 'article_id'])
            ->update();

        $this->table('user_school_article_qrcode')
            ->addIndex(['user_id'], ['name' => 'user_id'])
            ->addIndex(['article_id'], ['name' => 'article_id'])
            ->update();
    }
}
