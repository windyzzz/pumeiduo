<?php

namespace app\home\validate;


use think\Validate;

class Community extends Validate
{
    protected $rule = [
        'content' => 'require',
        'cate_id' => 'require',
        'goods_id' => 'require',
    ];

    protected $msg = [
        'content' => '请输入内容',
        'cate_id' => '请选择社区分类',
        'goods_id' => '请选择商品'
    ];

    protected $scene = [
        'add' => ['content', 'cate_id', 'goods_id']
    ];
}