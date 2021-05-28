<?php

namespace app\admin\model;

use think\Model;

class PromQrcode extends Model
{

    public function getRewardTypeDescAttr($value, $data)
    {
        $reward_type = ['1' => '优惠券', '2' => '电子币'];

        return $reward_type[$data['reward_type']];
    }
}
