<?php

namespace app\admin\model;


use think\Model;

class CommunityArticle extends Model
{
    public function getStatusDescAttr($value, $data)
    {
        $parse_type = [
            '0' => '未审核',
            '1' => '审核通过',
            '-1' => '拒绝通过',
        ];

        return $parse_type[$data['status']];
    }
}