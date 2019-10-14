<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\behavior;

class CheckValid
{
    public function run(&$params)
    {
        $user = session('user');
        if (0 == $user['type']) {
            exit(json_encode(['status' => -1, 'msg' => '你还没选择你的用户类型呢，不能进行该操作', 'result' => $wxuser]));
        }
    }
}
