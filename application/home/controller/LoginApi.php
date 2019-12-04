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

use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;

class LoginApi
{
    public $config;
    public $oauth;
    public $class_obj;

    public function __construct()
    {
        session('?user');
        $this->oauth = I('get.oauth');
        if (!$this->oauth) {
            return json(['status' => 0, 'msg' => '非法操作', 'result' => null]);
        }
        //获取配置
        $data = M('Plugin')->where('code', $this->oauth)->where('type', 'login')->find();
        $this->config = unserialize($data['config_value']); // 配置反序列化
        include_once "plugins/login/{$this->oauth}/{$this->oauth}.class.php";
        $class = '\\' . $this->oauth;
        $this->class_obj = new $class($this->config); //实例化对应的登陆插件
    }

    public function login()
    {
        if (!$this->oauth) {
            return json(['status' => 0, 'msg' => '非法操作', 'result' => null]);
        }
        include_once "plugins/login/{$this->oauth}/{$this->oauth}.class.php";
        $url = $this->class_obj->login();
        $return['url'] = $url;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function callback()
    {
        $data = $this->class_obj->respon();

        $logic = new UsersLogic();
        $data = $logic->thirdLogin($data);

        if (1 != $data['status']) {
            return json(['status' => 0, 'msg' => $data['msg'], 'result' => null]);
        }
        session('user', $data['result']);
        setcookie('user_id', $data['result']['user_id'], null, '/');
        setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
        $nickname = empty($data['result']['nickname']) ? '第三方用户' : $data['result']['nickname'];
        setcookie('uname', urlencode($nickname), null, '/');
        setcookie('cn', 0, time() - 3600, '/');
        // 登录后将购物车的商品的 user_id 改为当前登录的id
        M('cart')->where('session_id', $this->session_id)->save(['user_id' => $data['result']['user_id']]);

        $cartLogic = new CartLogic();
        $cartLogic->doUserLoginHandle($this->session_id, $data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
        if (isMobile()) {
//            $this->success('登陆成功', U('Home/index/index'));
            echo "<script> top.location.href='" . url('/', '', true, true) . "'</script>";
            exit;
        }
        echo "<script> top.location.href='" . url('/', '', true, true) . "'</script>";
        exit;
//            $this->success('登陆成功', U('Home/index/index'));
    }
}
