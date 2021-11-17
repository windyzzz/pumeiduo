<?php

use think\migration\Migrator;
use think\migration\db\Column;

class UpdateSchoolAddIndex extends Migrator
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
            ->addIndex(['is_open'])
            ->addIndex(['is_allow'])
            ->addIndex(['is_top'])
            ->update();

        $this->table('school_class')
            ->addIndex(['is_open'])
            ->addIndex(['is_allow'])
            ->addIndex(['is_learn'])
            ->update();

        $this->table('school_article')
            ->addIndex(['learn_type'])
            ->addIndex(['status'])
            ->update();

        $this->table('user_school_config')
            ->addIndex(['type'])
            ->addIndex(['user_id'])
            ->update();

        $this->table('school_article_temp_resource')
            ->addIndex(['article_id'])
            ->update();

        $this->table('school_article_questionnaire_answer')
            ->addIndex(['article_id'])
            ->addIndex(['user_id'])
            ->addIndex(['caption_id'])
            ->update();
    }
}
