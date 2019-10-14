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

class WxKeyword extends Model
{
    //关键字类型
    const TYPE_AUTO_REPLY = 'auto_reply';

    public function wxReply()
    {
        return $this->belongsTo('WxReply', 'pid', 'id');
    }
}
