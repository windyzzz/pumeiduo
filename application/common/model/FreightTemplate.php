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

class FreightTemplate extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function freightConfig()
    {
        return $this->hasMany('FreightConfig', 'template_id', 'template_id');
    }

    public function getTypeDescAttr($value, $data)
    {
        $type = config('FREIGHT_TYPE');

        return $type[$data['type']];
    }

    public function getUnitDescAttr($value, $data)
    {
        if (0 == $data['type']) {
            return '件';
        } elseif (1 == $data['type']) {
            return '克';
        }

        return '立方米';
    }
}
