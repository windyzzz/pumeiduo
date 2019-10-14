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

class GroupBuy extends Model
{
    public function goods()
    {
        return $this->hasOne('goods', 'goods_id', 'goods_id');
    }

    public function specGoodsPrice()
    {
        return $this->hasOne('specGoodsPrice', 'item_id', 'item_id');
    }

    public function groupDetail()
    {
        return $this->hasMany('group_detail', 'group_id', 'id');
    }

    //状态描述
    public function getStatusDescAttr($value, $data)
    {
        if (1 == $data['is_end']) {
            return '已结束';
        }
        if ($data['start_time'] > time()) {
            return '未开始';
        } elseif ($data['start_time'] < time() && $data['end_time'] > time()) {
            return '进行中';
        }

        return '已结束';
    }
}
