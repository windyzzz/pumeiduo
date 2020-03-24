<?php

use think\migration\Seeder;

class AddressTab extends Seeder
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
                'name' => '家',
                'is_default' => 1,
                'user_id' => 0
            ],
            [
                'id' => 2,
                'name' => '公司',
                'is_default' => 1,
                'user_id' => 0
            ],
            [
                'id' => 3,
                'name' => '学校',
                'is_default' => 1,
                'user_id' => 0
            ]
        ];
        $this->table('ad_position')->insert($data)->save();
    }
}