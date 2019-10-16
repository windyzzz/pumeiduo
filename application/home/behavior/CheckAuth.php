<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\behavior;

use app\common\logic\UsersLogic;
use app\common\logic\wechat\WechatUtil;
use think\cache\driver\Redis;
use think\Db;
use think\Url;

class CheckAuth
{
    private $weixin_config;
    private $site_url;
    protected $redis;

    public function __construct()
    {
        $this->redis = new Redis();
    }

    public function run(&$params)
    {
        Url::root('/');
        $invite = I('invite', 0);
        if ($invite > 0) {
            // 是否是邀请
//            session('invite', $invite);
            $this->redis->set('invite_' . $params['user_token'], $invite, 180);
            $file = 'invite.txt';
            file_put_contents($file, '['. date('Y-m-d H:i:s').']  设置新用户邀请人Session：'.$invite."\n", FILE_APPEND | LOCK_EX);
        }

        $this->site_url = url('/', '', '', true);
        $return['baseUrl'] = $this->site_url;
        $return['result'] = $this->site_url;
        // 行为逻辑
        if (session('user') || (isset($params['user_token']) && $this->redis->has('user_' . $params['user_token']))) {
            // 原系统进入 或者 APP进入
            if (session('user')) {
                $session_user = session('user');
            } else {
                $session_user = $this->redis->get('user_' . $params['user_token']);
            }
            if (!$session_user) exit(json_encode(['status' => -1, 'msg' => '你还没有登录呢', 'result' => $return]));
            $select_user = Db::name('users')->where('user_id', $session_user['user_id'])->find();
            $oauth_users = Db::name('oauth_users')->where(['user_id' => $session_user['user_id']])->find();
            empty($oauth_users) && $oauth_users = [];
            if (empty($select_user)) {
                session('user', null);
                $this->redis->rm('user_' . $params['user_token']);
//                $_SESSION['openid'] = 0;
                $this->redis->rm('user_' . $params['user_token'] . '_openid');

                if ('weixin' == I('web')) {
                    if (is_array($this->weixin_config)) {
                        $wxuser = $this->GetOpenid($params['user_token']); //授权获取openid以及微信用户信息

                        if (1 == $wxuser['type']) {
                            exit(json_encode(['status' => -1, 'msg' => '你还没有登录呢', 'result' => $wxuser]));
                        }
                    }
                }

                exit(json_encode(['status' => -1, 'msg' => '你还没有登录呢', 'result' => $return]));
            }

            $user = array_merge($select_user, $oauth_users);
            session('user', $user);
            $this->redis->set('user_' . $params['user_token'], $user, 86400 * 5);
        } else {
            // $nologin = array(
            //         'login','pop_login','do_login','logout','verify','set_pwd','finished',
            //         'verifyHandle','send_sms_reg_code','identity','check_validate_code',
            //     'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code','bind_account','bind_guide','bind_reg',
            // );
            // if(!in_array(ACTION_NAME,$nologin)){
//                define('SESSION_ID',session_id()); //将当前的session_id保存为常量，供其它方法调用
            // 判断当前用户是否手机
            // if(isMobile())
            //     cookie('is_mobile','1',3600);
            // else
            //     cookie('is_mobile','0',3600);

            //微信浏览器
            if ('weixin' == $params['web']) {
                $this->weixin_config = M('wx_user')->find(); //取微获信配置
                // $this->assign('wechat_config', $this->weixin_config);
//                $user_temp = session('user');
                $user_temp = $this->redis->get('user_' . $params['user_token']);
                if (isset($user_temp['user_id']) && $user_temp['user_id']) {
                    $user = M('users')->where('user_id', $user_temp['user_id'])->find();
                    if (!$user) {
                        $_SESSION['openid'] = 0;
                        session('user', null);
                        $this->redis->rm('user_' . $params['user_token']);
                    }
                }

                if (is_array($this->weixin_config)) {
                    $wxuser = $this->GetOpenid($params['user_token']); //授权获取openid以及微信用户信息

                    if (1 == $wxuser['type']) {
                        exit(json_encode(['status' => -1, 'msg' => '你还没有登录呢', 'result' => $wxuser]));
                    }
                    $wxuser = $wxuser['result'];

                    $logic = new UsersLogic();

                    $data = $logic->thirdLogin($wxuser);

                    if (1 == $data['status']) {
                        $logic->afterLogin($data['result']);

                        header("Location: $this->site_url"); // 跳转到微信授权页面 需要用户确认登录的页面
                        exit();
                        // exit(json_encode(['status'=>1, 'msg'=>'你已经登录', 'result'=>null]));
                    }
                    exit(json_encode(['status' => -1, 'msg' => $data['msg'], 'result' => $return]));
                }
            }

            exit(json_encode(['status' => -1, 'msg' => '你还没有登录呢', 'result' => $return]));
            // }
        }
    }

    public function GetOpenid($userToken)
    {
        // if($_SESSION['openid'])
        //     return ['type'=>2, 'result'=>$_SESSION['data']];
        //通过code获得openid
        if (!isset($_GET['code'])) {
            //触发微信返回code码
            //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            // $baseUrl = urlencode($this->get_url());
            $invite = $this->redis->get('invite_' . $userToken);
            $file = 'invite.txt';
            file_put_contents($file, '['. date('Y-m-d H:i:s').']  把邀请人信息添加到授权回调地址：'.$invite."\n", FILE_APPEND | LOCK_EX);
            $baseUrl = urlencode($this->site_url."/index.php?m=Home&c=api.Login&a=callback&invite=$invite");
            $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址
            // Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
            // exit();
            return ['type' => 1, 'result' => $url, 'baseUrl' => $this->site_url];
        }
        $invite = $this->redis->get('invite_' . $userToken);
        $file = 'invite.txt';
        file_put_contents($file, '['. date('Y-m-d H:i:s').']  授权回来，获取邀请人Session：'.$invite."\n", FILE_APPEND | LOCK_EX);
        //上面获取到code后这里跳转回来
        $code = $_GET['code'];
        $data = $this->getOpenidFromMp($code); //获取网页授权access_token和用户openid
            $data2 = $this->GetUserInfo($data['access_token'], $data['openid']); //获取微信用户信息
            $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
        $data['sex'] = $data2['sex'];
        $data['head_pic'] = $data2['headimgurl'];
        $data['subscribe'] = $data2['subscribe'];
        $data['oauth_child'] = 'mp';
        $_SESSION['openid'] = $data['openid'];
        $data['oauth'] = 'weixin';
        if (isset($data2['unionid'])) {
            $data['unionid'] = $data2['unionid'];
        }
        $_SESSION['data'] = $data;

        return ['type' => 2, 'result' => $data, 'baseUrl' => $this->site_url];
        // return $data;
    }

    /**
     * 通过access_token openid 从工作平台获取UserInfo.
     *
     * @return openid
     */
    public function GetUserInfo($access_token, $openid)
    {
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token, $openid);
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); //设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch); //运行curl，结果以jason形式返回
        $data = json_decode($res, true);
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        //if(!isset($data['unionid'])){
        $wechat = new WechatUtil($this->weixin_config);
        $fan = $wechat->getFanInfo($openid); //获取基础支持的access_token
        if (false !== $fan) {
            $data['subscribe'] = $fan['subscribe'];
        }
        //}
        return $data;
    }

    /**
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token, $openid)
    {
        $urlObj['access_token'] = $access_token;
        $urlObj['openid'] = $openid;
        $urlObj['lang'] = 'zh_CN';
        $bizString = $this->ToUrlParams($urlObj);

        return 'https://api.weixin.qq.com/sns/userinfo?'.$bizString;
    }

    /**
     * 通过code从工作平台获取openid机器access_token.
     *
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function getOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
        //1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
        //2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); //设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch); //运行curl，结果以jason形式返回
        $data = json_decode($res, true);
        curl_close($ch);

        return $data;
    }

    /**
     * 构造获取open和access_toke的url地址
     *
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj['appid'] = $this->weixin_config['appid'];
        $urlObj['secret'] = $this->weixin_config['appsecret'];
        $urlObj['code'] = $code;
        $urlObj['grant_type'] = 'authorization_code';
        $bizString = $this->ToUrlParams($urlObj);

        return 'https://api.weixin.qq.com/sns/oauth2/access_token?'.$bizString;
    }

    /**
     * 获取当前的url 地址
     *
     * @return type
     */
    private function get_url()
    {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && '443' == $_SERVER['SERVER_PORT'] ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);

        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }

    /**
     * 构造获取code的url连接.
     *
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj['appid'] = $this->weixin_config['appid'];
        $urlObj['redirect_uri'] = "$redirectUrl";
        $urlObj['response_type'] = 'code';
        //        $urlObj["scope"] = "snsapi_base";
        $urlObj['scope'] = 'snsapi_userinfo';
        $urlObj['state'] = 'STATE'.'#wechat_redirect';
        $bizString = $this->ToUrlParams($urlObj);

        return 'https://open.weixin.qq.com/connect/oauth2/authorize?'.$bizString;
    }

    /**
     * 拼接签名字符串.
     *
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = '';
        foreach ($urlObj as $k => $v) {
            if ('sign' != $k) {
                $buff .= $k.'='.$v.'&';
            }
        }

        $buff = trim($buff, '&');

        return $buff;
    }
}
