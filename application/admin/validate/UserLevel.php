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

class UserLevel extends Validate
{
    // 验证规则
    protected $rule = [
        ['level_name', 'require|unique:distribut_level'],
        ['discount', 'number|egt:1|elt:100|unique:distribut_level'],
    ];
    //错误信息
    protected $message = [
        'level_name.require' => '名称必须',
        'level_name.unique' => '已存在相同等级名称',
        'discount.number' => '折扣率格式错误',
        'discount.egt' => '折扣率范围1~100',
        'discount.elt' => '折扣率范围1~100',
        'discount.unique' => '已存在相同折扣率',
    ];
    //验证场景
    protected $scene = [
        'edit' => [
            'level_name' => 'require|unique:distribut_level,level_name^level_id',
            'discount' => 'number|egt:1|elt:100|unique:distribut_level,discount^level_id',
        ],
    ];
}
