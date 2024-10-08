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

class GoodsCoupon extends Model
{
    public function goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id');
    }

    public function goodsCategory()
    {
        return $this->hasOne('GoodsCategory', 'id', 'goods_category_id');
    }
}
