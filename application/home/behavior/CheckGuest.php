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

use think\cache\driver\Redis;

class CheckGuest
{
    public function run(&$params)
    {
        if (session('user')) {
            $user = session('user');
        } elseif ((new Redis())->has('user_' . $params['user_token'])) {
            $user = (new Redis())->get('user_' . $params['user_token']);
        } else {
            return true;
        }
        if ($user) {
            exit(json_encode(['status' => -1, 'msg' => '登录状态下，不能进行该操作', 'result' => null]));
        }
    }
}
