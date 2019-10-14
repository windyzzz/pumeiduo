<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

class Block extends Base
{
    public function index()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    //自定义页面列表页
    public function pageList()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    public function ajaxGoodsList()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    //商品列表板块参数设置
    public function goods_list_block()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /*
    *保存编辑完成后的信息
    */
    public function add_data()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    //设置首页
    public function set_index()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    //删除页面
    public function delete()
    {
        $id = I('post.id');
        if ($id) {
            $r = D('mobile_template')->where('id', $id)->delete();
            exit(json_encode(1));
        }
    }

    //获取秒杀活动数据
    public function get_flash()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }
}
