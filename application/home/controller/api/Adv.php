<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller\api;

class Adv
{
    public function __construct()
    {
        // header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Method:POST,GET');
    }

    public function index()
    {
        $position_id = I('position_id');
        $position_id_arr = explode(',', $position_id);
        $now_time = time();
        $arr = array();
        foreach ($position_id_arr as $k => $position_id) {
            $arr[$position_id] = M('ad')->field('ad_code, ad_link, ad_name, target_type, target_type_id')
                ->where('pid', $position_id)
                ->where('enabled', 1)
                ->where('start_time', 'elt', $now_time)
                ->where('end_time', 'egt', $now_time)
                ->order('orderby')
                ->select();
        }
        if (count($arr) == 1) {
            $adList = $arr[$position_id];
            foreach ($adList as $k => $item) {
                if (in_array($item['target_type'], [3, 4])) {
                    $adList[$k]['need_login'] = 1;
                } else {
                    $adList[$k]['need_login'] = 0;
                }
            }
            return json(['status' => 1, 'msg' => 'success', 'result' => $adList]);
        } else {
            return json(['status' => 1, 'msg' => 'success', 'result' => $arr]);
        }
    }
}
