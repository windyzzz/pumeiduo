<?php

namespace app\admin\model;


use think\Model;

class Push extends Model
{
    public function getTypeDescAttr($value, $data)
    {
        $parse_type = ['1' => '公告', '2' => '活动消息', '3' => '优惠券', '4' => '商品', '5' => '首页'];

        return $parse_type[$data['type']];
    }

    public function getDistributeLevelDescAttr($value, $data)
    {
        $parse_type = ['0' => '全部用户', '1' => '普通用户', '2' => 'VIP', '3' => 'SVIP'];

        return $parse_type[$data['distribute_level']];
    }
}