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

class GoodsCategory extends Validate
{
    // 验证规则
    protected $rule = [
        ['name', 'require', '分类名称必须填写'],
        ['sort_order', 'number', '排序必须为数字'],
    ];
}
