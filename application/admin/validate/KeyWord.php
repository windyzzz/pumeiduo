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

/**
 * Description of Article.
 *
 * @author Administrator
 */
class KeyWord extends Validate
{
    //验证规则
    protected $rule = [
        'name' => 'require|checkEmpty|unique:key_word',
        'sort_order' => 'number',
    ];

    //错误消息
    protected $message = [
        'name' => '关键词不能为空',
        'name.unique' => '关键词已存在不能重复添加',
        'sort_order' => '排序必须是数字',
    ];

    protected function checkEmpty($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (empty($value)) {
            return false;
        }

        return true;
    }
}
