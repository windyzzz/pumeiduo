<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller;

use app\common\logic\KeyWordLogic;

class KeyWord
{
    public function getKeyWord()
    {
        $keyWordLogic = new KeyWordLogic();
        $list = $keyWordLogic->getActivityList();

        return json(['status' => 1, 'msg' => 'ok', 'result' => $list]);
    }
}
