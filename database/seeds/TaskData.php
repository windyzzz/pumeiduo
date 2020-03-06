<?php

use think\migration\Seeder;

class TaskData extends Seeder
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
            'id' => '4',
            'title' => '登录红包',
            'is_open' => '0',
            'start_time' => '1581316298',
            'end_time' => '1585670399',
            'use_start_time' => '1581316304',
            'use_end_time' => '1585670399',
            'goods_id_list' => '',
            'icon' => '',
        ];
        $this->table('task')->insert($data)->save();
    }
}