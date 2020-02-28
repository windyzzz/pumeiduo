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

class UserLoginLog extends Model
{
    /**
     * 登录来源
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getSourceDescAttr($value, $data)
    {
        $source = ['1' => '微信', '2' => 'PC', '3' => 'APP'];
        return $source[$data['source']];
    }
}
