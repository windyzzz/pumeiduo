<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\validate;

use think\Validate;

/**
 * 用户地址验证器
 * Class UserAddress.
 */
class UserAppLogin extends Validate
{
    protected $rule = [
        'openid' => 'require',
        'unionid' => 'require',
        'oauth' => 'require',
    ];

    protected $msg = [
        'openid.require' => 'openid必须',
        'unionid.require' => 'unionid必须',
        'oauth.require' => '来源必须',
    ];
}
