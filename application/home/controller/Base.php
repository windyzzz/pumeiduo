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

use app\common\logic\Token as TokenLogic;
use think\Controller;
use think\Request;
use think\Session;

class Base extends Controller
{
    public $session_id;
    public $cateTrre = [];
    protected $user;
    protected $user_id;
    protected $userToken;

    /*
     * 初始化操作
     */
    public function _initialize()
    {
        if (input('unique_id')) {           // 兼容手机app
            session_id(input('unique_id'));
            Session::start();
        }
        header('Cache-control: private');  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881

        // 判断当前用户是否手机
        if (isMobile()) {
            cookie('is_mobile', '1', 3600);
        } else {
            cookie('is_mobile', '0', 3600);
        }

        $this->public_assign();

        $isApp = Request::instance()->header('is-app', null);
        if ($isApp == 1) {
            // APP请求
            session_start();
            session_destroy();
            $token = Request::instance()->header('user-token', null);
            // 处理url
            $url = self::getUrl();
            if (in_array($url, self::whiteListPath()) || !$token) {
                $this->userToken = TokenLogic::setToken();
                return true;
            }
            if (in_array($url, self::specialListPath()) && $token) {
                $user = TokenLogic::getValue('user', $token);
                $this->user = $user;
                $this->user_id = !empty($user) ? $user['user_id'] : '';
                $this->userToken = $token;
                return true;
            }
            // 验证token
            $res = TokenLogic::checkToken($token);
            if ($res['status'] !== 1) die(json_encode(['status' => $res['status'], 'msg' => $res['msg']]));
            $this->user = $res['user'];
            $this->user_id = $res['user']['user_id'];
            $this->userToken = $token;
        } else {
            // 网页请求
            $this->session_id = session_id(); // 当前的 session_id
            define('SESSION_ID', $this->session_id); //将当前的session_id保存为常量，供其它方法调用
            $this->userToken = session_id();
        }
    }

    /**
     * 保存公告变量到 smarty中 比如 导航.
     */
    public function public_assign()
    {
        $tpshop_config = [];
        $tp_config = M('config')->cache(true, TPSHOP_CACHE_TIME)->select();
        foreach ($tp_config as $k => $v) {
            if ('hot_keywords' == $v['name']) {
                $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        }

        $goods_category_tree = get_goods_category_tree();
        $this->cateTrre = $goods_category_tree;
        $this->assign('goods_category_tree', $goods_category_tree);
        $brand_list = M('brand')->cache(true)->field('id,name,parent_cat_id,logo,is_hot')->where('parent_cat_id>0')->select();
        $this->assign('brand_list', $brand_list);
        $this->assign('tpshop_config', $tpshop_config);
        $user = session('user');
        $this->assign('username', $user['nickname']);

        //PC端首页"手机端、APP二维码"
        $store_logo = tpCache('shop_info.shop_info_store_logo');
        $store_logo ? $head_pic = $store_logo : $head_pic = '/public/static/images/logo/pc_home_logo_default.png';
        $mobile_url = "http://{$_SERVER['HTTP_HOST']}".U('Mobile/index/app_down');
        $this->assign('head_pic', "http://{$_SERVER['HTTP_HOST']}/".$head_pic);
        $this->assign('mobile_url', $mobile_url);
    }

    /*
     *
     */
    public function ajaxReturn($data)
    {
        exit(json_encode($data));
    }

    /**
     * 获取请求路径
     * @return string
     */
    private function getUrl()
    {
        $url = $this->request->url();
        $urlArr = explode('&', $url);
        return $urlArr[1] . '&' . $urlArr[2];
    }

    /**
     * 白名单路径
     * @return array
     */
    private function whiteListPath()
    {
        return [];
    }

    /**
     * 特殊路径
     * @return array
     */
    private function specialListPath()
    {
        return [];
    }
}
