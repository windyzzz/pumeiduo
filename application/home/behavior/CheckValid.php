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

use app\common\logic\Token as TokenLogic;

class CheckValid
{
    public function run(&$params)
    {
        $user = TokenLogic::getValue('user', $params['user_token']);
        if (!$user) exit(json_encode(['status' => -1, 'msg' => '请先登录', 'result' => null]));
        if (0 == $user['type']) {
            exit(json_encode(['status' => -1, 'msg' => '你还没选择你的用户类型呢，不能进行该操作', 'result' => null]));
        }
    }
}
