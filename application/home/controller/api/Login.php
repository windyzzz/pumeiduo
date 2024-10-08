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
use app\common\logic\OrderLogic;
use app\common\logic\Token as TokenLogic;
use app\common\logic\UsersLogic;
use app\common\logic\wechat\WechatUtil;
use app\home\validate\UserAppLogin;
use think\Exception;
use think\Loader;
use think\Hook;
use think\Request;
use think\Url;

class Login extends Base
{
    public $user_id = 0;
    public $user = [];

    public function __construct()
    {
        parent::__construct();
        // header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Method:POST,GET');
//        $user = session('user');
//        if ($user) {
//            $this->user = $user;
//            $this->user_id = $user ? $user['user_id'] : 0;
//        }
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
        $oauth = I('oauth', '');
        $oauthCode = I('oauth_code', '');
        $openId = '';
        if ($oauth && $oauthCode) {
            // 获取授权处理信息
            $data = M('Plugin')->where('code', $oauth)->where('type', 'login')->find();
            $config = unserialize($data['config_value']);
            $config['code'] = $oauthCode;
            include_once "plugins/login/wechatApplet/wechatApplet.class.php";
            $class = '\\wechatApplet';
            $classObj = new $class($config);
            try {
                $res = $classObj->getCodeInfo();
                $openId = $res['openid'];
            } catch (Exception $e) {
                return json(['status' => 0, 'msg' => 'openid获取失败']);
            }
        }
        //验证码验证
        // if (isset($_POST['verify_code'])) {
        //     $verify_code = I('post.verify_code');
        //     $verify = new Verify();
        //     if (!$verify->check($verify_code, 'user_login')) {
        //         $res = array('status' => 0, 'msg' => '验证码错误');
        //         exit(json_encode($res));
        //     }
        // }
        $source = $this->isApp == 1 ? 3 : 1;    // 1微信 3APP
        $source = $this->isApplet == 1 ? 4 : $source;   // 4小程序
        $logic = new UsersLogic();
        $res = $logic->login($username, $password, $openId, $source);
        if (1 == $res['status']) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            $this->redis->set('user_' . $res['result']['token'], $res['result'], config('REDIS_TIME'));
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            // 用户登录后 需要对购物车 一些操作
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->setUserToken($res['result']['token']);
            $cartLogic->doUserLoginHandle();
            // 登录后将超时未支付订单给取消掉
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);
            $orderLogic->abolishOrder();
            $user = $res['result'];
            $res['result'] = [
                'user_id' => $user['user_id'],
                'sex' => $user['sex'],
                'nickname' => $user['nickname'] == '' ? $user['user_name'] : $user['nickname'],
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
                'jpush_tags' => [$user['push_tag']],
                'new_profit' => 0
            ];
            // 是否展示新用户奖励弹窗
            if ($user['is_new'] == 1 && $logic->checkNewProfit($user['user_id'])['status'] !== 0) {
                $res['result']['new_profit'] = 1;
            }
        }

        return json($res);
    }

    /**
     *  检查登录.
     */
    public function checkLogin()
    {
        $params = I('get.');
        $params['user_token'] = $this->userToken;
        // 检查登陆
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);
        Url::root('/');
        $return['baseUrl'] = url('/', '', '', true);
        session('invite', I('invite', 0));
        S('invite_' . $this->userToken, I('invite', 0), 180);

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
        file_put_contents($file, '[' . date('Y-m-d H:i:s') . ']  获取所有参数GET：' . json_encode($params) . "\n", FILE_APPEND | LOCK_EX);
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);
    }

    /**
     *  注册.
     */
    public function reg(Request $request)
    {
        if ($this->user_id > 0) {
            return json(['status' => 0, 'msg' => '你已经登录过了', 'result' => null]);
        }
        if (!$request->isPost()) {
            return json(['status' => 0, 'msg' => '请求方式出错', 'result' => null]);
        }

        $username = I('post.username', '');
        $password = I('post.password', '');
        $password2 = I('post.password2', '');
        $code = I('post.code', '');
        $scene = I('post.scene', 1);

        $logic = new UsersLogic();
        if ($code != '1238') {
            $session_id = S('mobile_token_' . $username);
            if (!$session_id) {
                return json(['status' => 0, 'msg' => '验证码已过期']);
            }
            // 手机/邮箱验证码检查，如果没以上两种功能默认是图片验证码检查
            if (check_mobile($username)) {
                $reg_sms_enable = tpCache('sms.regis_sms_enable');
//                $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
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
        }

        $oauth = I('oauth', '');
        $oauthCode = I('oauth_code', '');
        $openId = '';
        if ($oauth && $oauthCode) {
            // 获取授权处理信息
            $data = M('Plugin')->where('code', $oauth)->where('type', 'login')->find();
            $config = unserialize($data['config_value']);
            $config['code'] = $oauthCode;
            include_once "plugins/login/wechatApplet/wechatApplet.class.php";
            $class = '\\wechatApplet';
            $classObj = new $class($config);
            try {
                $res = $classObj->getCodeInfo();
                $openId = $res['openid'];
            } catch (Exception $e) {
                return json(['status' => 0, 'msg' => 'openid获取失败']);
            }
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

        $invite = I('invite', '');
        $inviteOpenid = I('invite_openid', '');
//        if (!empty($invite)) {
//            $invite = get_user_info($invite); //根据user_id查找邀请人
//        }
        $source = $this->isApp == 1 ? 3 : 1;    // 1微信 3APP
        $source = $this->isApplet == 1 ? 4 : $source;   // 4小程序
        $data = $logic->reg($username, $password, $password2, 0, $invite, $inviteOpenid, '', '', $openId, $this->userToken, $source);
        if (1 != $data['status']) {
            return json($data);
        }
        if (I('pre_reg', '')) {
            // 预注册不需要登录
            return json($data);
        }
        session('user', $data['result']);
        $this->redis->set('user_' . $this->userToken, $data['result'], config('REDIS_TIME'));
        setcookie('user_id', $data['result']['user_id'], null, '/');
        setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
        $nickname = empty($data['result']['nickname']) ? $username : $data['result']['nickname'];
        setcookie('uname', $nickname, null, '/');
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($data['result']['user_id']);
        $cartLogic->setUserToken($this->userToken);
        $cartLogic->doUserLoginHandle(); // 用户登录后 需要对购物车 一些操作
        return json($data);
    }

    /**
     *  退出登录.
     */
    public function logout()
    {
        if (!$this->isApp && !$this->isApplet) {
            setcookie('uname', '', time() - 3600, '/');
            setcookie('cn', '', time() - 3600, '/');
            setcookie('user_id', '', time() - 3600, '/');
            setcookie('PHPSESSID', '', time() - 3600, '/');
            session_unset();
            session_destroy();
        }
        //$this->success("退出成功",U('Home/Index/index'));
        if ($this->userToken) {
            (new UsersLogic())->logout($this->userToken);
        }
        return json(['status' => 1, 'msg' => '退出登录成功', 'result' => null]);
    }

    /*
     * 微信授权登录
     * */
    public function app_login(UserAppLogin $validate)
    {
        $params['user_token'] = isset($this->userToken) ? $this->userToken : null;
        Hook::exec('app\\home\\behavior\\CheckGuest', 'run', $params);

        $data = input('post.');

        if (!$validate->check($data)) {
            return json(['status' => -1, 'msg' => $validate->getError()]);
        }

        $logic = new UsersLogic();

        $result = $logic->handleAppLogin($data, $params['user_token']);

        return json($result);
    }

    /*
     * app 退出登录
     * */
    public function app_logout()
    {
        $logic = new UsersLogic();

        $result = $logic->app_logout($this->user['token']);

        return json($result);
    }

    /**
     * 获取邀请码二维码
     *
     * @return \think\response\Json
     */
    public function getInviteInfo()
    {
        $invite_id = I('invite_id/d', $this->user_id);
        if (!$invite_id) {
            return json(['status' => -1, 'msg' => '接口使用错误，缺少必要参数', 'result' => null]);
        }
        Url::root('/');
        $baseUrl = url('/', '', '', true);

        $filename = 'public/images/qrcode/user/user_' . $invite_id . '_big.jpg';

        if (!file_exists($filename)) {
            $filename = $this->scerweima($invite_id);

            //生成二维码图片

            $img = imagecreatefromjpeg("public/images/qr_bg.jpg");// 加载已有图像

            //设置字体颜色
            $black = imagecolorallocate($img, 253, 234, 96);

            //字体类型，本例为黑体
            $font = "public/css/msyh.ttf";

            //将ttf文字写到图片中
            imagettftext($img, 28, 0, 370, 520, $black, $font, $invite_id);

            $src = imagecreatefromstring(file_get_contents($filename));
            //获取水印图片的宽高
            list($src_w, $src_h) = getimagesize($filename);

            //将水印图片复制到目标图片上，最后个参数80是设置透明度，这里实现半透明效果
            imagecopymerge($img, $src, 242, 665, 0, 0, $src_w, $src_h, 100);

            $image_url = PUBLIC_PATH . 'images/qrcode/user/user_' . $invite_id . '_big.jpg';

            //fdea60

            //发送头信息
            ImageJPEG($img, $image_url);

            imagedestroy($img);

        }

        $return['user_id'] = $invite_id;
        $return['basic_url'] = $baseUrl;
        $return['qr_img'] = $filename;
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);

    }

    private function scerweima($user_id)
    {
        Loader::import('phpqrcode', EXTEND_PATH);

        Url::root('/');
        $baseUrl = url('/', '', '', true);

        $url = $baseUrl . '/#/register?invite=' . $user_id;

        $value = $url;                  //二维码内容

        $errorCorrectionLevel = 'L';    //容错级别
        $matrixPointSize = 8;           //生成图片大小

        //生成二维码图片
        $filename = 'public/images/qrcode/user/user_' . $user_id . '_min.png';

        \QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
        return $filename;

        // $QR = $filename;                //已经生成的原始二维码图片文件

        // $QR = imagecreatefromstring(file_get_contents($QR));

        // //输出图片
        // imagepng($QR, 'qrcode.png');
        // imagedestroy($QR);
        // return '<img src="qrcode.png" alt="使用微信扫描支付">';
    }

    /**
     * 获取邀请人信息（H5专用）
     * @return \think\response\Json
     */
    public function getInviteUser()
    {
        $inviteId = I('invite', '');
        if (!$inviteId) {
            return json(['status' => 0, 'msg' => '请传入邀请人ID']);
        }
        $userInfo = M('users')->where(['user_id' => $inviteId])->field('user_id, head_pic')->find();
        if (empty($userInfo)) {
            return json(['status' => 0, 'msg' => '邀请人信息不存在']);
        }
        if (empty($userInfo['head_pic'])) {
            Url::root('/');
            $baseUrl = url('/', '', '', true);
            $userInfo['head_pic'] = $baseUrl . '/public/images/default_head.png';
        }
        return json(['status' => 1, 'result' => $userInfo]);
    }

    /**
     * 账号注销
     * @return \think\response\Json
     */
    public function do_cancel()
    {
        $res = M('users')->where('user_id', $this->user_id)->update(['is_cancel' => 1]);
        if ($res) {
            return json(['status' => 1, 'msg' => '您的账户已注销']);
        }
        return json(['status' => 0, 'msg' => '注销失败']);
    }
}
