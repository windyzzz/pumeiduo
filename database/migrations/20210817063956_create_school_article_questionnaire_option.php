<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSchoolArticleQuestionnaireOption extends Migrator
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
        $this->table('school_article_questionnaire_option', ['comment' => '商学院文章问卷调查选项表'])
            ->addColumn('caption_id', 'integer', ['comment' => '主体ID'])
            ->addColumn('content', 'string', ['comment' => '内容'])
            ->create();
    }
}
