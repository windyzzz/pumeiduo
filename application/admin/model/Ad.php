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
        $targetType = [
            '1' => '商品详情页',
            '2' => '优惠促销页',
            '3' => '领券中心页',
            '4' => '任务中心页',
            '5' => '所有商品',
            '6' => '促销商品',
            '7' => '我的礼券',
            '8' => '韩国购',
            '9' => 'VIP申请',
            '10' => 'SVIP专享',
            '11' => '商品分类跳转',
        ];
        return $targetType[$data['target_type']] ?? '无';
    }
}
