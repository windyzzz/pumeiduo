<?php

use think\migration\Seeder;

class SvipLevel extends Seeder
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
            [
                'id' => 1,
                'app_level' => 3,
                'agent_level' => 1,
                'name' => 'SVIP'
            ],
            [
                'id' => 2,
                'app_level' => 4,
                'agent_level' => 2,
                'name' => '经理'
            ],
            [
                'id' => 3,
                'app_level' => 5,
                'agent_level' => 3,
                'name' => '高级经理'
            ],
            [
                'id' => 4,
                'app_level' => 6,
                'agent_level' => 4,
                'name' => '总监'
            ],
            [
                'id' => 5,
                'app_level' => 7,
                'agent_level' => 5,
                'name' => '高级总监'
            ],
            [
                'id' => 6,
                'app_level' => 8,
                'agent_level' => 6,
                'name' => '董事'
            ],
            [
                'id' => 7,
                'app_level' => 9,
                'agent_level' => 7,
                'name' => '一星董事'
            ],
            [
                'id' => 8,
                'app_level' => 10,
                'agent_level' => 8,
                'name' => '二星董事'
            ],
            [
                'id' => 9,
                'app_level' => 11,
                'agent_level' => 9,
                'name' => '三星董事'
            ],
        ];
        $this->table('svip_level')->insert($data)->save();
    }
}
