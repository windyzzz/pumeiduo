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

class Task extends Model
{
    public function setStartTimeAttr($value)
    {
        return strtotime($value);
    }

    public function setEndTimeAttr($value)
    {
        return strtotime($value);
    }

    public function setUseStartTimeAttr($value)
    {
        return strtotime($value);
    }

    public function setUseEndTimeAttr($value)
    {
        return strtotime($value);
    }

    public function taskReward()
    {
        return $this->hasMany('task_reward', 'task_id', 'id');
    }
}
