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
class Gift extends Validate
{
    //验证规则
    protected $rule = [
        'title' => 'require|checkEmpty',
        'goods_id' => 'require|checkEmpty|checkExist',
    ];

    //错误消息
    protected $message = [
        'title' => '标题不能为空',
        'goods_id.require' => '设置商品不能为空',
        'goods_id.checkEmpty' => '设置商品不能为空',
        'goods_id.checkExist' => '设置商品已经有活动在进行中，请勿重复设置',
    ];

    protected function checkExist($value, $data1, $data)
    {
        if ($data['item_id']) {
            $exist = M('gift')->where([
                'goods_id' => $value,
                'is_open' => 1,
                'item_id' => $data['item_id'],
            ])->find();
        } else {
            $exist = M('gift')->where([
                'goods_id' => $value,
                'is_open' => 1,
            ])->find();
        }

        if ($exist) {
            return false;
        }

        return true;
    }

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
