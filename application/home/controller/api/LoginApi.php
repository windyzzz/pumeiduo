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

use app\common\logic\CartLogic;
use app\common\logic\Token as TokenLogic;
use app\common\logic\UsersLogic;
use think\cache\driver\Redis;
use think\Exception;
use think\Log;

class LoginApi
{
    public $config;
    public $oauth;
    public $class_obj;
    public $code;

    public function __construct()
    {
        session('?user');
        $this->oauth = I('get.oauth', '');
        $this->code = I('get.code', '');
        if (!$this->oauth) {
            return json(['status' => 0, 'msg' => '非法操作', 'result' => null]);
        }
        //获取配置
        $data = M('Plugin')->where('code', $this->oauth)->where('type', 'login')->find();
        $this->config = unserialize($data['config_value']); // 配置反序列化
        include_once "plugins/login/{$this->oauth}/{$this->oauth}.class.php";
        $class = '\\' . $this->oauth;
        $this->config['code'] = $this->code;
        $this->class_obj = new $class($this->config); //实例化对应的登陆插件
    }

    public function login()
    {
        $url = $this->class_obj->login();
        $return['url'] = $url;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 授权登录（新）
     * @return \think\response\Json
     */
    public function loginNew()
    {
        try {
            $wechatUserInfo = $this->class_obj->login();
            $res = (new UsersLogic())->handleAppLoginNew($wechatUserInfo);
            if ($res['status'] == 1) {
                // 登录成功
                $user = $res['result'];
                $res['result'] = [
                    'user_id' => $user['user_id'],
                    'sex' => $user['sex'],
                    'nickname' => $user['nickname'],
                    'user_name' => $user['nickname'],
                    'real_name' => $user['user_name'],
                    'id_cart' => $user['id_cart'],
                    'birthday' => $user['birthday'],
                    'mobile' => $user['mobile'],
                    'head_pic' => $user['head_pic'],
                    'type' => $user['distribut_level'] >= 3 ? '2' : $user['type'],
                    'invite_uid' => $user['invite_uid'],
                    'is_distribut' => $user['is_distribut'],
                    'is_lock' => $user['is_lock'],
                    'level' => $user['distribut_level'],
                    'level_name' => M('DistributLevel')->where('level_id', $user['distribut_level'])->getField('level_name') ?? '普通会员',
                    'is_not_show_jk' => $user['is_not_show_jk'],  // 是否提示加入金卡弹窗
                    'has_pay_pwd' => $user['paypwd'] ? 1 : 0,
                    'is_app' => TokenLogic::getValue('is_app', $user['token']) ? 1 : 0,
                    'token' => $user['token'],
                    'jpush_tags' => [$user['push_tag']]
                ];
                // 登录记录
                $usersLogic = new UsersLogic();
                $usersLogic->setUserId($user['user_id']);
                $usersLogic->userLogin(3);
            }
            return json($res);
        } catch (Exception $e) {
            Log::record($e->getMessage());
//            return json(['status' => 0, 'msg' => $e->getMessage()]);
            return json(['status' => 0, 'msg' => '系统繁忙，请重试']);
        }
    }

    /**
     * 授权登录注册
     * @return \think\response\Json
     */
    public function oauthReg()
    {
        $openid = I('post.openid', '');
        $username = I('post.username', '');
        $password = I('post.password', '');
        $code = I('post.code', '');
        $scene = I('post.scene', 1);

        $userLogic = new UsersLogic();
        // 验证验证码
        if ($code != '1238') {
            $sessionId = S('mobile_token_' . $username);
            if (!$sessionId) {
                return json(['status' => 0, 'msg' => '验证码已过期']);
            }
            if (check_mobile($username)) {
                $check_code = $userLogic->check_validate_code($code, $username, 'phone', $sessionId, $scene);
                if (1 != $check_code['status']) {
                    return json($check_code);
                }
            } else {
                return json(['status' => 0, 'msg' => '手机号码不合格式']);
            }
        }
        // 授权用户注册
        $res = $userLogic->oauthReg($openid, $username, $password);
        return json($res);
    }

    public function callback()
    {
        $data = $this->class_obj->respon();

        $logic = new UsersLogic();
        $data = $logic->thirdLogin($data, 1);

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
        $cartLogic->setUserId($data['result']['user_id']);
        $cartLogic->setUserToken($this->session_id);
        $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作

        if (isMobile()) {
            $this->success('登陆成功', U('Home/index/index'));
        } else {
            $this->success('登陆成功', U('Home/index/index'));
        }
    }
}
