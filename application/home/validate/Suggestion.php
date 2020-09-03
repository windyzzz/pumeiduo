<?php

namespace app\home\validate;


use think\Validate;

class Suggestion extends Validate
{
    protected $rule = [
        'phone' => 'require',
        'cate_id' => 'require',
        'content' => 'require',
    ];

    protected $message = [
        'phone' => '请输入正确的手机号码',
        'cate_id' => '类型错误',
        'content' => '请输入反馈内容'
    ];
}