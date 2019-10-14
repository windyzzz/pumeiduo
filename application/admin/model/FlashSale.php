<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\model;

use think\Model;

class FlashSale extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function specGoodsPrice()
    {
        return $this->hasOne('SpecGoodsPrice', 'item_id', 'item_id');
    }

    public function goods()
    {
        return $this->hasOne('goods', 'prom_id', 'id');
    }

    //状态描述
    public function getStatusDescAttr($value, $data)
    {
        if (1 == $data['is_end']) {
            return '已结束';
        }
        if ($data['buy_num'] >= $data['goods_num']) {
            return '已售罄';
        }
        if ($data['start_time'] > time()) {
            return '未开始';
        } elseif ($data['start_time'] < time() && $data['end_time'] > time()) {
            return '进行中';
        }

        return '已过期';
    }
}
