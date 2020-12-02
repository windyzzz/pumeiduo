<?php

namespace app\admin\validate;

use think\Validate;

class School extends Validate
{
    //验证规则
    protected $rule = [
        'title' => 'require|checkEmpty',
        'subtitle' => 'require|checkEmpty',
        'cover' => 'require|checkEmpty',
        'publish_time' => 'require|checkEmpty',
        'content' => 'require|checkContent',
    ];

    //错误消息
    protected $message = [
        'title' => '标题不能为空',
        'subtitle' => '副标题不能为空',
        'cover' => '封面图不能为空',
        'publish_time' => '发布时间不能为空',
        'content' => '内容不能为空',
    ];

    //验证场景
    protected $scene = [
        'article_add' => ['title', 'subtitle', 'cover', 'publish_time', 'content'],
        'article_edit' => ['title', 'subtitle', 'cover', 'publish_time', 'content'],
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

    protected function checkContent($value)
    {
        $value = strip_tags($value);
        $value = str_replace('&nbsp;', '', $value);
        $value = trim($value);
        if (empty($value)) {
            return false;
        }
        return true;
    }
}
