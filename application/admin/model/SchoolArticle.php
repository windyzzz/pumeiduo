<?php

namespace app\admin\model;

use think\Model;

class SchoolArticle extends Model
{
    /**
     * 文章状态
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getStatusDescAttr($value, $data)
    {
        $status = [
            '-1' => '已删除',
            '1' => '发布中',
            '2' => '预发布',
            '3' => '不发布',
        ];
        return $status[$data['status']];
    }
}
