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

class GiftReward extends Model
{
    public function giftInfo()
    {
        return $this->belongsTo('Gift', 'id', 'gift_id');
    }
}
