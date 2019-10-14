<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

use app\common\logic\Saas;
use think\Controller;

class Sso extends Controller
{
    public function logout()
    {
        $ssoToken = input('sso_token', '');

        $return = Saas::instance()->ssoLogout($ssoToken);

        ajaxReturn($return);
    }
}
