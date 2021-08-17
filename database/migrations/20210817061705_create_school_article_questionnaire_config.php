<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolArticleQuestionnaireConfig extends Migrator
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
        $this->table('school_article_questionnaire_config', ['comment' => '商学院文章问卷调查配置表'])
            ->addColumn('title', 'string', ['default' => '', 'comment' => '标题'])
            ->addColumn('direction', 'string', ['default' => '', 'comment' => '说明'])
            ->addColumn('start_time', 'integer', ['default' => 0, 'comment' => '开始时间'])
            ->addColumn('end_time', 'integer', ['default' => 0, 'comment' => '结束时间'])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态：0关闭 1开启'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
