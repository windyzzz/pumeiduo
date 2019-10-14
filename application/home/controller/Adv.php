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

class Adv
{
    public function __construct()
    {
        // header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Method:POST,GET');
    }

    public function index()
    {
        $position_id = I('position_id', 0);
        $now_time = time();
        $list = M('ad')->field('ad_code,ad_link,ad_name')
        ->where('pid', $position_id)
        ->where('enabled', 1)
        ->where('start_time', 'elt', $now_time)
        ->where('end_time', 'egt', $now_time)
        ->order('orderby')
        ->select();

        return json(['status' => 1, 'msg' => 'success', 'result' => $list]);
    }
}
