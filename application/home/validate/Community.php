<?php

namespace app\home\validate;


use think\Validate;

class Community extends Validate
{
    protected $rule = [
        'content' => 'require',
        'cate_id1' => 'require|gt:0',
        'cate_id2' => 'require|gt:0',
        'goods_id' => 'require|gt:0',
    ];

    protected $message = [
        'content' => '请输入内容',
        'cate_id1' => '请选择社区分类',
        'cate_id2' => '请选择社区分类',
        'goods_id' => '请选择商品'
    ];

    protected $scene = [
        'article_add' => ['content', 'cate_id1', 'cate_id2', 'goods_id']
    ];
}