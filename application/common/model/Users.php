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

class Users extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    /**
     * 用户账号状态
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusDescAttr($value, $data)
    {
        if ($data['is_lock'] == 1) {
            return '已冻结';
        }
        if ($data['is_cancel'] == 1) {
            return '已注销';
        }
//        return '正常';
    }
}
