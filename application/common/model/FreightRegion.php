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

class FreightRegion extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function region()
    {
        return $this->hasOne('region', 'id', 'region_id');
    }

    public function freightConfig()
    {
        return $this->hasOne('FreightConfig', 'config_id', 'config_id');
    }
}
