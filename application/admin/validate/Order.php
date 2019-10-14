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
        ['consignee', 'require', '收货人称必须填写'],
        ['address', 'require', '地址必须填写'],
    ];
}
