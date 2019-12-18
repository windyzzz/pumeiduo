<?php

use think\migration\Seeder;

class ArticleData extends Seeder
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $data = [
            'article_id' => 106,
            'cat_id' => 80,
            'title' => '申请金卡-协议',
            'content' => '内容补充',
            'app_content' => '内容补充',
            'add_time' => time(),
            'publish_time' => time(),
        ];
//        $this->table('article')->insert($data)->save();
    }
}