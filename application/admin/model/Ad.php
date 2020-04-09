<?php

namespace app\admin\model;

use think\Model;

class Ad extends Model
{
    /**
     * APP跳转页面
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getTargetTypeDescAttr($value, $data)
    {
        $targetType = ['1' => '商品详情页', '2' => '优惠促销页', '3' => '领券中心页', '4' => '任务中心页'];
        return $targetType[$data['target_type']] ?? '无';
    }
}
