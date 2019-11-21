<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\model;

use think\Model;

class OrderProm extends Model
{

    public function getPromDetailAttr($value, $data)
    {
        switch ($data['type']) {
            case 0:
                $title = '赠品+满减';
                break;
            case 1:
                $title = '满减价';
                break;
            case 2:
                $title = '满赠送';
                break;
            default:
                $title = '赠品+满减';
        }

        return $title;
    }

    /*
     * 活动类型
     */
    public function getPromDescAttr($value, $data)
    {
        $parse_type = ['0' => '赠品+满减', '1' => '满减价', '2' => '满赠送'];
        return $parse_type[$data['type']];
    }

    /*
     * 状态描述
     */
    public function getStatusDescAttr($value, $data)
    {
        if ($data['is_end'] == 1) {
            return '已结束';
        }
        if ($data['is_open'] == 0) {
            return '已暂停';
        }
        if ($data['start_time'] > time()) {
            return '未开始';
        } elseif ($data['start_time'] < time() && $data['end_time'] > time()) {
            return '进行中';
        }

        return '已过期';
    }
}
