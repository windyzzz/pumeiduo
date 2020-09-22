<?php

namespace app\admin\validate;

use think\Validate;

class Community extends Validate
{
    //验证规则
    protected $rule = [
        'cate_id1' => 'require|checkEmpty',
        'cate_id2' => 'require|checkEmpty',
        'content' => 'require|checkContent',
        'goods_id' => 'require|checkContent',
        'publish_time' => 'require|checkContent',
    ];

    //错误消息
    protected $message = [
        'cate_id1' => '一级分类不能为空',
        'cate_id2' => '二级分类不能为空',
        'content' => '内容不能为空',
        'goods_id' => '请选择商品',
        'publish_time' => '预发布时间不能为空',
    ];

    //验证场景
    protected $scene = [
        'article_add' => ['cate_id1', 'cate_id2', 'content', 'goods_id', 'publish_time'],
        'article_edit' => ['cate_id1', 'cate_id2', 'content', 'goods_id'],
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
