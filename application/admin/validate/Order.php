<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\validate;

use think\Validate;

class Order extends Validate
{
    // 验证规则
    protected $rule = [
        ['consignee', 'require'],
        ['mobile', 'require'],
        ['address', 'require'],
        ['province', 'number|gt:0'],
        ['city', 'number|gt:0'],
        ['district', 'number|gt:0'],
    ];

    // 错误信息
    protected $message = [
        'consignee.require' => '收货人称必须填写',
        'mobile.require' => '联系方式必须填写',
        'address.require' => '地址必须填写',
        'province.number' => '必须选择省',
        'province.gt' => '必须选择省',
        'city.number' => '必须选择市',
        'city.gt' => '必须选择市',
        'district.number' => '必须选择区',
        'district.gt' => '必须选择区',
    ];
}
