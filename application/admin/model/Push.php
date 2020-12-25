<?php

namespace app\admin\model;


use think\Model;

class Push extends Model
{
    public function getTypeDescAttr($value, $data)
    {
        $parse_type = [
            '1' => '公告',
            '2' => '活动消息',
            '3' => '领券中心',
            '4' => '商品',
            '5' => '首页',
            '6' => '促销商品区',
            '7' => 'SVIP专享',
            '8' => 'VIP申请区',
            '9' => '我的礼券',
            '10' => '商品列表',
            '11' => '超值套装',
            '12' => '分类跳转',
            '13' => '韩国购',
//            '14' => '用户社区文章列表',     // 针对用户单独发送
            '15' => '种草社区'
        ];

        return $parse_type[$data['type']];
    }

    public function getDistributeLevelDescAttr($value, $data)
    {
        $parse_type = ['0' => '全部用户', '1' => '普通用户', '2' => 'VIP', '3' => 'SVIP'];

        return $parse_type[$data['distribute_level']];
    }

    public function goods()
    {
        return $this->hasOne('goods', 'goods_id', 'type_id');
    }

    public function specGoodsPrice()
    {
        return $this->hasOne('SpecGoodsPrice', 'item_id', 'item_id');
    }
}
