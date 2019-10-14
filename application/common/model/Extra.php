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

class Extra extends Model
{
    protected $autoWriteTimestamp = 'datetime';
    protected $insert = ['status' => 0];
    public $status = ['未付款', '已付款', '已取消'];

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

    public function extraReward()
    {
        return $this->hasMany('extra_reward', 'extra_id', 'id');
    }
}
