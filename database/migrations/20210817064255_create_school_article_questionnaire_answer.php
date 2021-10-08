<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolArticleQuestionnaireAnswer extends Migrator
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
        $this->table('school_article_questionnaire_answer', ['comment' => '商学院文章问卷调查回答表'])
            ->addColumn('article_id', 'integer', ['comment' => '文章ID'])
            ->addColumn('user_id', 'integer', ['comment' => '用户ID'])
            ->addColumn('caption_id', 'integer', ['comment' => '问卷调查主体ID'])
            ->addColumn('score', 'integer', ['default' => 0, 'comment' => '分数'])
            ->addColumn('option_ids', 'string', ['default' => '', 'comment' => '问卷调查选项关联组合'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('add_time', 'integer')
            ->create();
    }
}
