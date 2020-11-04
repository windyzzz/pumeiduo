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
use app\common\logic\OrderLogic;
use app\common\logic\UsersLogic;
use app\common\logic\wechat\WechatUtil;
use think\Hook;
use think\Request;
use think\Url;

class Login
{
    public $user_id = 0;
    public $user = [];

    public function __construct()
    {
        // header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Method:POST,GET');
        $user = session('user');
        $this->user = $user;
        $this->user_id = $user ? $user['user_id'] : 0;
    }

    public function getWeChatConfig()
    {
        // require_once(PLUGIN_PATH."payment/weixin/example/WxPay.NativePay.php");
        // require_once(PLUGIN_PATH."payment/weixin/example/WxPay.JsApiPay.php");
        // $paymentPlugin = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
        // $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化

        // $jsapi = new \WxPayJsApiPay();
        // $jsapi->SetAppid($config_value['appid']);
        // $timeStamp = time();
        // $jsapi->SetTimeStamp("$timeStamp");
        // $jsapi->SetNonceStr(\WxPayApi::getNonceStr());

        // $jsapi->SetPaySign($jsapi->MakeSign());
        // $parameters = $jsapi->GetValues();
        // $parameters['signature'] = $parameters['paySign'];
        // unset($parameters['paySign']);
        $url = I('url', '');
        $WechatUtil = new WechatUtil();
        $res = $WechatUtil->getSignPackage($url);

        return json(['status' => 1, 'msg' => 'ok', 'result' => $res]);
    }

    /**
     * 登录.
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        //验证码验证
        // if (isset($_POST['verify_code'])) {
        //     $verify_code = I('post.verify_code');
        //     $verify = new Verify();
        //     if (!$verify->check($verify_code, 'user_login')) {
        //         $res = array('status' => 0, 'msg' => '验证码错误');
        //         exit(json_encode($res));
        //     }
        // }
        $logic = new UsersLogic();
        $res = $logic->login($username, $password, '', 2);
        if (1 == $res['status']) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle(); // 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']); //登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }

        return json($res);
    }

    /**
     * 登录.
     */
    public function do_login_id()
    {
        $user_id = trim(I('post.user_id'));
        $password = trim(I('post.password'));
        $logic = new UsersLogic();
        $res = $logic->login_ip($user_id, $password);
        if (1 == $res['status']) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle(); // 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']); //登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }

        return json($res);
    }

    /**
     *  检查登录.
     */
    public function checkLogin()
    {
        // session_destroy();
        $params = I('get.');
        // 1. 检查登陆
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);
        Url::root('/');
        $return['baseUrl'] = url('/', '', '', true);
        session('invite', I('invite', 0));

        return json(['status' => 1, 'msg' => '已经登录', 'result' => $return]);
    }

    /**
     *  检查是否新注册账号.
     */
    public function checkIsNew()
    {
        $result = session('?is_new') ? 1 : 0;

        return json(['status' => 1, 'msg' => 'success', 'result' => $result]);
    }

    /**
     *  删除新注册账号标识.
     */
    public function clearNew()
    {
        $result = session('is_new', null);

        return json(['status' => 1, 'msg' => 'success', 'result' => $result]);
    }

    public function callback()
    {
        // 1. 检查登陆
        $params = I('get.');
        $params['web'] = 'weixin';
        $file = 'invite.txt';
        file_put_contents($file, 'invite'.json_encode($params), FILE_APPEND | LOCK_EX);
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);
    }

    /**
     *  注册.
     */
    public function reg(Request $request)
    {
        if ($this->user_id > 0) {
            // return json(['status'=>0, 'msg'=>'你已经登录过了', 'result'=>null]);
        }

        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        if (!$request->isPost()) {
            return json(['status' => 0, 'msg' => '请求方式出错', 'result' => null]);
        }

        $logic = new UsersLogic();
        $username = I('post.username', '');
        $password = I('post.password', '');
        $password2 = I('post.password2', '');
        $code = I('post.code', '');
        $scene = I('post.scene', 1);
        $session_id = session_id();

        // 手机/邮箱验证码检查，如果没以上两种功能默认是图片验证码检查
        if (check_mobile($username)) {
            if ($reg_sms_enable) {   //是否开启注册验证码机制
                //手机功能没关闭
                $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                if (1 != $check_code['status']) {
                    return json($check_code);
                }
            } else {
                if (!$this->verifyHandle('user_reg')) {
                    return json(['status' => -1, 'msg' => '图像验证码错误']);
                }
            }
        } else {
            return json(['status' => -1, 'msg' => '手机号码不合格式']);
        }

        // if(check_email($username)){
        //     if($reg_smtp_enable){        //是否开启注册邮箱验证码机制
        //         //邮件功能未关闭
        //         $check_code = $logic->check_validate_code($code, $username);
        //         if($check_code['status'] != 1){
        //             return json($check_code);
        //         }
        //     }else{
        //         if(!$this->verifyHandle('user_reg')){
        //             return json(['status'=>-1,'msg'=>'图像验证码错误']);
        //         };
        //     }
        // }

        $invite = I('invite');
        if (!empty($invite)) {
            $invite = get_user_info($invite); //根据user_id查找邀请人
        }
        $data = $logic->reg($username, $password, $password2, 0, $invite, '', '', '', null, 2);
        if (1 != $data['status']) {
            return json($data);
        }
        session('user', $data['result']);
        setcookie('user_id', $data['result']['user_id'], null, '/');
        setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
        $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
        setcookie('uname', $nickname, null, '/');
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($data['result']['user_id']);
        $cartLogic->doUserLoginHandle(); // 用户登录后 需要对购物车 一些操作
        return json($data);
    }

    /**
     *  退出登录.
     */
    public function logout()
    {
        setcookie('uname', '', time() - 3600, '/');
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('PHPSESSID', '', time() - 3600, '/');
        session_unset();
        session_destroy();
        //$this->success("退出成功",U('Home/Index/index'));
        return json(['status' => 1, 'msg' => '退出登录成功', 'result' => null]);
    }

    /**
     * 密码找回  By J.
     *
     * @param Request $request
     *
     * @return \think\response\Json
     */
    public function findPassword(Request $request)
    {
        $mobile = I('mobile', '', 'trim');
        $code = I('code', '');
        $scene = I('scene', 6);
        $step = I('step', 1);
        $session_id = I('unique_id', session_id());

        $return['step'] = $step;
        //检查是否第三方登录用户
        $logic = new UsersLogic();

        $user = M('users')->where([
            'mobile' => $mobile,
            'is_lock' => 0,
        ])->find();

        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                return json(['status' => 0, 'msg' => '验证码还未验证通过', 'result' => $return]);
            }
        } else {
            $res = $logic->check_validate_code($code, $user['mobile'], 'phone', $session_id, $scene);
            if (!$res && 1 != $res['status']) {
                return json(['status' => 0, 'msg' => $res['msg'], 'result' => $return]);
            }
        }
        if ($request->isPost() && 2 == $step) {
            $userLogic = new UsersLogic();
            $data = $userLogic->resetPassword($user['user_id'], I('post.new_password'), I('post.confirm_password')); // 获取用户信息
            if (-1 == $data['status']) {
                return json(['status' => 0, 'msg' => $data['msg'], 'result' => $return]);
            }

            return json(['status' => 1, 'msg' => $data['msg'], 'result' => $return]);
        }

        return json(['status' => 1, 'msg' => '验证码验证通过', 'result' => $return]);
    }
}
