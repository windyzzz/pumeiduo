<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

use app\common\model\KeyWord;

class KeyWordLogic
{
    protected $model;

    public function __construct()
    {
        $this->model = new KeyWord();
    }

    public function getActivityList()
    {
        return $this->model->where([
            'status' => 1,
        ])->order('sort_order,click_num desc')
        ->select();
    }
}
