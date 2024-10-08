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
class UserAddress extends Validate
{
    protected $rule = [
        'user_id' => 'require|number',
        'consignee' => 'require|max:25',
        'email' => 'email',
        'province' => 'require|number',
        'city' => 'require|number',
        'district' => 'require|number',
        'address' => 'require|max:100',
        'mobile' => ['regex' => '/^1[3|4|5|8][0-9]\d{4,8}$/'],
    ];

    protected $message = [
        'user_id.require' => '用户id必须',
        'user_id.number' => '用户id必须为数字',
        'consignee.require' => '收货人必须填写',
        'consignee.max' => '收货人名称最多不能超过25个字符',
        'email' => 'email格式错误',
        'province.require' => '省份必须选择',
        'province.number' => '省份iD必须为数字',
        'city.require' => '市必须选择',
        'city.number' => '市iD必须为数字',
        'district.require' => '镇区必须选择',
        'district.number' => '镇区iD必须为数字',
        'address.require' => '地址必须填写',
        'address.max' => '地址民称最多不能超过100个字符',
        'mobile.regex' => '手机号码格式错误',
    ];
}
