<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolArticleQuestionnaireCaption extends Migrator
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
        $this->table('school_article_questionnaire_caption', ['comment' => '商学院文章问卷调查主体表'])
            ->addColumn('title', 'string', ['comment' => '标题'])
            ->addColumn('type', 'integer', ['default' => 1, 'comment' => '类型：1评分 2评价 3单选 4多选'])
            ->addColumn('is_open', 'integer', ['default' => 1, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'comment' => '是否开启'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->create();
    }
}
