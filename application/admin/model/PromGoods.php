<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\model;

use think\Model;

class PromGoods extends Model
{
    public function getPromDetailAttr($value, $data)
    {
        switch ($data['type']) {
            case 1:
                $title = '优惠￥'.$data['expression'];
                break;
            case 2:
                $title = '促销价￥'.$data['expression'];
                break;
            case 3:
                $title = '买就送优惠券';
                break;
            case 4:
                $title = '满' . $data['goods_num'] . '打' . $data['expression'] . '折';
                break;
            case 5:
                $title = '满' . $data['goods_price'] . '减' . $data['expression'] . '元';
                break;
            default:
                $discount = $data['expression'] / 10;
                $title = $discount.'折';
        }

        return $title;
    }

    public function getPromDescAttr($value, $data)
    {
        $parse_type = ['0' => '直接打折', '1' => '减价优惠', '2' => '固定金额出售', '3' => '买就赠优惠券', '4' => '满打折', '5' => '满减价'];

        return $parse_type[$data['type']];
    }
}
