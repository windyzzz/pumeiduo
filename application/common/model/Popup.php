<?php

namespace app\common\model;

use think\Model;

class Popup extends Model
{
    protected $type = [
        '1' => '无跳转',
        '2' => 'VIP申请',
        '3' => '促销商品',
        '4' => '优惠团购',
        '5' => '超值套装',
        '6' => '领券中心',
        '7' => '我的礼券',
        '8' => '任务中心',
    ];

    public function getType()
    {
        return $this->type;
    }

    public function getTypeDescAttr($value, $data)
    {
        return $this->type[$data['type']];
    }

    public function getShowLimitDescAttr($value, $data)
    {
        $showLimit = ['1' => '每天一次', '2' => '活动期间一次'];
        return $showLimit[$data['show_limit']];
    }
}
