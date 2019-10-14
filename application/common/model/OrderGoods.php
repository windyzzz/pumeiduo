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

class OrderGoods extends Model
{
    protected $table = '';

    //自定义初始化
    protected function initialize()
    {
        parent::initialize();
    }

    public function goods()
    {
        return $this->hasOne('goods', 'goods_id', 'goods_id');
    }
}
