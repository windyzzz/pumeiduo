<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use think\Request;

class weixin
{
    private $appId;

    private $appSecret;

    private $returnUrl;

    public function __construct($config)
    {
        $this->appId = $config['appid'];
        $this->appSecret = $config['secret'];
        $this->returnUrl = 'https://pc.mall.pumeiduo.com/Home/LoginApi/callback?oauth=weixin';
    }

    public function login()
    {
        $_SESSION['state'] = md5(uniqid(rand(), true));
        //拼接URL
        $dialog_url = 'https://open.weixin.qq.com/connect/qrconnect?appid='
            .$this->appId.'&redirect_uri='.urlencode($this->returnUrl).'&response_type=code'
            .'&scope=snsapi_login'
            .'&state='
            .$_SESSION['state']
            .'#wechat_redirect';
        echo "<script> top.location.href='".$dialog_url."'</script>";
        exit;
    }

    public function respon()
    {
//        if($_REQUEST['state'] != $_SESSION['state'])
        if ($_REQUEST['state'] == $_SESSION['state']) {
            $code = $_REQUEST['code'];
            //拼接URL
            $token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='
                .$this->appId.'&secret='.$this->appSecret
                .'&code='.$code.'&grant_type=authorization_code';

            $response = $this->get_contents($token_url);

            $msg = json_decode($response);
            if (isset($msg->errcode)) {
                echo '<h3>error:</h3>'.$msg->errcode.'.';
                echo '<h3>msg  :</h3>'.$msg->errmsg.'.';
                exit;
            }

            $data_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='
                .$msg->access_token.'&openid='.$msg->openid
                ;
            $response = $this->get_contents($data_url);
            $result = json_decode($response);
            if (isset($result->errcode)) {
                echo '<h3>error:</h3>'.$result->errcode;
                echo '<h3>msg  :</h3>'.$result->errmsg;
                exit;
            }
            $_SESSION['state'] = null; // 验证SESSION
            return [
                'openid' => $result->openid, // QQ openid
                'nickname' => $result->nickname,
                'sex' => $result->sex,
                'oauth' => 'weixinWeb',
                'head_pic' => $result->headimgurl,
                'unionid' => $result->unionid,
            ];
        }

        return false;
    }

    public function get_contents($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        curl_close($ch);

        //-------请求为空
        if (empty($response)) {
            exit('50001');
        }

        return $response;
    }
}
