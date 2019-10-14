<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

function send_sms($mobile, $content, $type = '')
{
}

/**
 * 面包屑导航  用于前台用户中心
 * 根据当前的控制器名称 和 action 方法.
 */
function navigate_user()
{
    // $navigate = include APP_PATH.'home/navigate.php';
    // $location = strtolower('Home/'.CONTROLLER_NAME);
    // $arr = array(
    //     '首页'=>'/',
    //     $navigate[$location]['name']=>U('/Home/'.CONTROLLER_NAME),
    //     $navigate[$location]['action'][ACTION_NAME]=>'javascript:void();',
    // );
    return [];
}

/**
 *  面包屑导航  用于前台商品
 *
 * @param type $id   商品id  或者是 商品分类id
 * @param type $type 默认0是传递商品分类id  id 也可以传递 商品id type则为1
 */
function navigate_goods($id, $type = 0)
{
    $cat_id = $id; //
    // 如果传递过来的是
    if (1 == $type) {
        $cat_id = M('goods')->where('goods_id', $id)->getField('cat_id');
    }
    $categoryList = M('GoodsCategory')->getField('id,name,parent_id');

    // 第一个先装起来
    $arr[$cat_id] = $categoryList[$cat_id]['name'];
    while (true) {
        $cat_id = $categoryList[$cat_id]['parent_id'];
        if ($cat_id > 0) {
            $arr[$cat_id] = $categoryList[$cat_id]['name'];
        } else {
            break;
        }
    }
    $arr = array_reverse($arr, true);

    return $arr;
}

function get_coupon_type()
{
    return [
        '全场通用',
        '指定商品可用',
        '指定分类商品可用',
    ];
}

function rebate_type($type)
{
    $rebate_type = [
        '分销提成',
        '商店提成',
    ];

    return $rebate_type[$type];
}
