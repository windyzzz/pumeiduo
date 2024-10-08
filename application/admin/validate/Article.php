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
class Article extends Validate
{
    //验证规则
    protected $rule = [
        'title' => 'require|checkEmpty',
//        'cat_id' => 'require|checkEmpty',
        'content' => 'require|checkContent',
        'message' => 'require|checkContent',
        'link' => 'url',
        'desc' => 'require|checkEmpty',
        'push_time' => 'require',
    ];

    //错误消息
    protected $message = [
        'title' => '标题不能为空',
        'content' => '内容不能为空',
        'message' => '内容不能为空',
        'cat_id.require' => '所属分类缺少参数',
        'cat_id.checkEmpty' => '所属分类必须选择',
        'article_id.checkArticleId' => '系统预定义的文章不能删除',
        'link.url' => '链接格式错误',
        'desc' => '简介不能为空',
        'push_time' => '推送时间不能为空',
    ];

    //验证场景
    protected $scene = [
        'add' => ['title', 'cat_id', 'content'],
        'edit' => ['title', 'cat_id', 'content'],
        'del' => ['article_id'],
        'message' => ['title', 'message'],
        'push' => ['title', 'desc', 'push_time'],
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
