<?php

use think\migration\Seeder;

class TaskConfigData extends Seeder
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
            'banner' => '',
            'config_value' => serialize(C('TASK_CATE'))
        ];
        $this->table('task_config')->insert($data)->save();
    }
}