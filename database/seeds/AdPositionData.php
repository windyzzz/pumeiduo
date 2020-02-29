<?php

use think\migration\Seeder;

class AdPositionData extends Seeder
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
            'position_id' => 61,
            'position_name' => '“我的分享”广告栏',
            'ad_width' => 343,
            'ad_height' => 96,
            'position_desc' => '',
            'position_style' => '',
            'is_open' => 1,
            'category_id' => 0,
        ];
        $this->table('article')->insert($data)->save();
    }
}