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

class System
{
    public function __construct()
    {
        // header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Method:POST,GET');
    }

    function happy_day(){
        $time = NOW_TIME;
        $field = 'top1,top2,top3,top4,top5,top6,top7,top8,bg1';
        $icon = M('icon')->field($field)->where(array('from_time'=>array('elt',$time),'to_time'=>array('egt',$time)))->find();
        if(!$icon){
            $icon = M('icon')->field($field)->where(array('id'=>1))->find();
        }
        return json(['status' => 1, 'msg' => 'success', 'result' => $icon]);
    }

    function footer_icon(){
        $time = NOW_TIME;
        $field = 'footer1,footer2,footer3,footer4,footer5,footer6,footer7,footer8';
        $icon = M('icon')->field($field)->where(array('from_time'=>array('elt',$time),'to_time'=>array('egt',$time)))->find();
        if(!$icon){
            $icon = M('icon')->field($field)->where(array('id'=>1))->find();
        }
        return json(['status' => 1, 'msg' => 'success', 'result' => $icon]);
    }
}