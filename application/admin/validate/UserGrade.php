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

class UserGrade extends Validate
{
    // 验证规则
    protected $rule = [
        ['level_name', 'require|unique:user_grade'],
        ['supply_rate', 'number|egt:0|elt:100'],
        ['purchase_rate', 'number|egt:0|elt:100'],
        ['retail_rate', 'number|egt:0|elt:100'],
        ['wholesale_rate', 'number|egt:0|elt:100'],
    ];
    //错误信息
    protected $message = [
        'level_name.require' => '名称必须',
        'level_name.unique' => '已存在相同等级名称',
        'supply_rate.number' => '供货率格式错误',
        'supply_rate.egt' => '供货率范围0~100',
        'supply_rate.elt' => '供货率范围0~100',
        'purchase_rate.number' => '进货率格式错误',
        'purchase_rate.egt' => '进货率范围0~100',
        'purchase_rate.elt' => '进货率范围0~100',
        'retail_rate.number' => '代零售返点格式错误',
        'retail_rate.egt' => '代零售返点范围0~100',
        'retail_rate.elt' => '代零售返点范围0~100',
        'wholesale_rate.number' => '代批发返点格式错误',
        'wholesale_rate.egt' => '代批发返点范围0~100',
        'wholesale_rate.elt' => '代批发返点范围0~100',
    ];
    //验证场景
    protected $scene = [
        'edit' => [
            'level_name' => 'require|unique:user_grade,level_name^level_id',
            'supply_rate' => 'number|egt:0|elt:100',
            'purchase_rate' => 'number|egt:0|elt:100',
            'retail_rate' => 'number|egt:0|elt:100',
            'wholesale_rate' => 'number|egt:0|elt:100',
        ],
    ];
}
