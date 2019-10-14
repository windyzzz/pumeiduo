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

class ShippingArea extends Model
{
    public function plugin()
    {
        return $this->hasOne('plugin', 'code', 'shipping_code');
    }
}
