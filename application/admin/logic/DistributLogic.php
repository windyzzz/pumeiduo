<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

class DistributLogic
{
    public function getRebateCount($where = '')
    {
        M('rebate_log')->where($where)->count();
    }
}
