<?php

namespace app\admin\model;


use think\Model;

class CommunityArticle extends Model
{

    public function getSourceDescAttr($value, $data)
    {
        $parse_type = [
            '1' => '会员用户',
            '2' => '后台管理',
        ];

        return $parse_type[$data['source']];
    }

    public function getStatusDescAttr($value, $data)
    {
        $parse_type = [
            '0' => '未审核',
            '1' => '审核通过',
            '-1' => '拒绝通过',
            '2' => '预发布状态',
        ];

        return $parse_type[$data['status']];
    }
}