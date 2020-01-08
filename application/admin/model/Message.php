<?php

namespace app\admin\model;


use think\Model;

class Message extends Model
{
    public function getCategoryDescAttr($value, $data)
    {
        $parse_type = ['0' => '系统消息', '1' => '活动消息'];

        return $parse_type[$data['category']];
    }

    public function getTypeDescAttr($value, $data)
    {
        $parse_type = ['0' => '个体消息', '1' => '全体消息'];

        return $parse_type[$data['type']];
    }

    public function getDistributeLevelAttr($value, $data)
    {
        $parse_type = ['0' => '全部用户', '1' => '普通用户', '2' => 'VIP', '3' => 'VIP'];

        return $parse_type[$data['distribut_level']];
    }
}