<?php

namespace app\home\controller\api\applet;


class Base extends \app\home\controller\api\Base
{
    private $appId;
    private $appSecret;
    protected $accessToken = null;

    /**
     * 初始化小程序access_token
     */
    public function __construct()
    {
        parent::__construct();
        $data = M('Plugin')->where('code', 'wechatApplet')->where('type', 'login')->find();
        $config = unserialize($data['config_value']);
        $this->appId = $config['appid'];
        $this->appSecret = $config['secret'];
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appId . '&secret=' . $this->appSecret;
        $response = $this->get_contents($url);
        $res = json_decode($response, true);
        if (isset($res['errcode']) && $res['errcode'] != 0) {
            die(json_encode(['status' => 0, 'msg' => $res['errmsg']]));
        }
        $this->accessToken = $res['access_token'];
    }

    protected function get_contents($url, $method = 'GET', $fields = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($fields)) {
                    $tmpdatastr = is_array($fields) ? json_encode($fields) : $fields;
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $tmpdatastr);
                }
                break;
            case 'GET':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                if (!empty($fields)) {
                    $url .= stripos($url, '?') !== false ? '&' : '?' . http_build_query($fields);
                }
                break;
            default:
                return false;
        }
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