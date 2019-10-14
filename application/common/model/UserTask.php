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

class UserTask extends Model
{
    public function taskLog()
    {
        return $this->hasMany('task_log', 'task_reward_id', 'task_reward_id');
    }
}
