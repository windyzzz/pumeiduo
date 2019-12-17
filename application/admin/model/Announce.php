<?php

namespace app\admin\model;


use think\Model;

class Announce extends Model
{
    public function goods()
    {
        return $this->hasOne('goods', 'goods_id', 'goods_id');
    }

    public function specGoodsPrice()
    {
        return $this->hasOne('SpecGoodsPrice', 'item_id', 'item_id');
    }

    public function getTypeDescAttr($value, $data)
    {
        $parse_type = ['1' => '商品促销'];

        return $parse_type[$data['type']];
    }
}