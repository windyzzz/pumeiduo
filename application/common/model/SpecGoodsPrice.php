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

class SpecGoodsPrice extends Model
{
    public function promGoods()
    {
        return $this->hasOne('PromGoods', 'id', 'prom_id')->cache(true, 10);
    }

    public function goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id')->cache(true, 10);
    }
}
