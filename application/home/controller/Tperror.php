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

use think\Controller;

class Tperror extends Controller
{
    public function tp404($msg = '', $url = '')
    {
        $msg = empty($msg) ? '您可能输入了错误的网址，或者该页面已经不存在了哦。' : $msg;
        $this->assign('error', $msg);
        $tpshop_config = [];
        $tp_config = M('config')->cache(true, TPSHOP_CACHE_TIME)->select();
        foreach ($tp_config as $k => $v) {
            if ('hot_keywords' == $v['name']) {
                $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        }
        $this->assign('goods_category_tree', get_goods_category_tree());
        $brand_list = M('brand')->cache(true, TPSHOP_CACHE_TIME)->field('id,parent_cat_id,logo,is_hot')->where('parent_cat_id>0')->select();
        $this->assign('brand_list', $brand_list);
        $this->assign('tpshop_config', $tpshop_config);

        return $this->fetch('public/tp404');
    }
}
