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

class wechatApp
{
    private $appId;

    private $appSecret;

    private $appCode;

    private $returnUrl;

    public function __construct($config)
    {
        $this->appId = $config['appid'];
        $this->appSecret = $config['secret'];
        $this->appCode = $config['code'];
        $this->returnUrl = 'https://pc.mall.pumeiduo.com/Home/LoginApi/callback?oauth=weixin';
    }

    public function login()
    {
        // 获取access_token
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $this->appId .
            '&secret=' . $this->appSecret . '&code=' . $this->appCode . '&grant_type=authorization_code';
        $response = $this->get_contents($url);
        $res = json_decode($response, true);
        if (isset($res['errcode'])) {
            throw new \think\Exception($res['errmsg']);
        }
        $accessToken = $res['access_token'];
        $refreshToken = $res['refresh_token'];
        $openid = $res['openid'];
        $scope = $res['scope'];
        $unionid = $res['unionid'];

        // 验证access_token
        $url = 'https://api.weixin.qq.com/sns/auth?access_token=' . $accessToken . '&openid=' . $openid;
        $response = $this->get_contents($url);
        $res = json_decode($response, true);
        if ($res['errcode'] !== 0) {
            switch ($res['errcode']) {
                case 42001:
                    // access_token 超时
                    $url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=' . $this->appId .
                        '&grant_type=refresh_token&refresh_token=' . $refreshToken;
                    $response = $this->get_contents($url);
                    $res = json_decode($response, true);
                    if ($res['errcode'] == 0) {
                        $accessToken = $res['access_token'];
                        $refreshToken = $res['refresh_token'];
                        $openid = $res['openid'];
                        $scope = $res['scope'];
                        $unionid = $res['unionid'];
                    } else {
                        throw new \think\Exception('errcode=' . $res['errcode'] . ' | msg=' . $res['errmsg']);
                    }
                    break;
                default:
                    throw new \think\Exception('errcode=' . $res['errcode'] . ' | msg=' . $res['errmsg']);
            }
        }

        // 获取用户信息
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $accessToken . '&openid=' . $openid;
        $response = $this->get_contents($url);
        $res = json_decode($response, true);
        if (isset($res['errcode'])) {
            throw new \think\Exception('errcode=' . $res['errcode'] . ' | msg=' . $res['errmsg']);
        }
        return $res;
    }

    public function respon()
    {
//        if($_REQUEST['state'] != $_SESSION['state'])
        if ($_REQUEST['state'] == $_SESSION['state']) {
            $code = $_REQUEST['code'];
            //拼接URL
            $token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='
                . $this->appId . '&secret=' . $this->appSecret
                . '&code=' . $code . '&grant_type=authorization_code';

            $response = $this->get_contents($token_url);

            $msg = json_decode($response);
            if (isset($msg->errcode)) {
                echo '<h3>error:</h3>' . $msg->errcode . '.';
                echo '<h3>msg  :</h3>' . $msg->errmsg . '.';
                exit;
            }

            $data_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='
                . $msg->access_token . '&openid=' . $msg->openid;
            $response = $this->get_contents($data_url);
            $result = json_decode($response);
            if (isset($result->errcode)) {
                echo '<h3>error:</h3>' . $result->errcode;
                echo '<h3>msg  :</h3>' . $result->errmsg;
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
