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

use app\common\model\Extra as ExtraModel;

class ExtraLogic
{
    protected $model;

    public function __construct()
    {
        $this->model = new ExtraModel();
    }

    public function getAvailable()
    {
        $now = time();

        return $this->model->with(['extra_reward'])->where([
            'status' => 1,
            'start_time' => ['lt', $now],
            'end_time' => ['gt', $now],
        ])->select();
    }
}
