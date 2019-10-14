<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\model;

use think\Model;

class Gift extends Model
{
    public function setStartTimeAttr($value)
    {
        return strtotime($value);
    }

    public function setEndTimeAttr($value)
    {
        return strtotime($value);
    }

    public function setCatIdAttr($value)
    {
        return implode(',', $value);
    }

    public function setCatId_2Attr($value)
    {
        return implode(',', $value);
    }

    public function setCatId_3Attr($value)
    {
        return implode(',', $value);
    }

    public function reward()
    {
        return $this->hasMany('GiftReward', 'gift_id', 'id');
    }

    public function goods()
    {
        return $this->hasOne('goods', 'goods_id', 'goods_id');
    }

    public function specGoodsPrice()
    {
        return $this->hasOne('specGoodsPrice', 'item_id', 'item_id');
    }
}
