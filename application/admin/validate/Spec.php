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

class Spec extends Validate
{
    // 验证规则
    protected $rule = [
        ['name', 'require', '规格名称必须填写'],
        ['type_id', 'require', '商品类型必须选择'],
        ['items', 'require', '规格项不能为空'],
        ['order', 'number', '排序必须为数字'],
    ];
    protected $scene = [
        'edit' => ['name', 'type_id', 'order'],
    ];
}
