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

class Task extends Validate
{
    // 验证规则
    protected $rule = [
        ['title', 'require', '标题必须填写'],
        ['start_time', 'require|checkStartTime', '开始时间必须填写'],
        ['end_time', 'require|checkEndTime', '结束时间必须填写'],
        ['reward', 'checkDescription', '描述文案字数超出限制'],
    ];

    public function checkStartTime($value, $rule, $data)
    {
        if ($value > $data['end_time']) {
            return '开始时间必须小于结束时间！';
        }

        return true;
    }

    public function checkEndTime($value, $rule, $data)
    {
        if ($value < $data['start_time']) {
            return '结束时间必须大于开始时间！';
        }

        return true;
    }

    public function checkDescription($value)
    {
        foreach ($value as $k => $v) {
            if (mb_strlen($v['description'], 'UTF-8') > 40) {
                return '描述文案中有字数超出40个字符';
            }
        }

        return true;
    }
}
