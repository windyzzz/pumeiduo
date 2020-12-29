<?php


class wechatApplet
{
    private $appId;

    private $appSecret;

    private $appCode;

    private $returnUrl;

    public function __construct($config)
    {
        $this->appId = $config['appid'];
        $this->appSecret = $config['secret'];
        $this->appCode = $config['code'] ?? '';
    }

    public function get_contents($url, $method = 'GET', $fields = [])
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
            return json_encode(['errcode' => '-1', 'errmsg' => '请求失败']);
        }
        return $response;
    }

    public function getAccessToken()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appId . '&secret=' . $this->appSecret;
        $response = $this->get_contents($url);
        $res = json_decode($response, true);
        if (isset($res['errcode']) && $res['errcode'] != 0) {
            throw new \think\Exception($res['errmsg']);
        }
        return $res['access_token'];
    }

    /**
     * 根据code获取信息
     * @return string
     * @throws \think\Exception
     */
    public function getCodeInfo()
    {
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $this->appId .
            '&secret=' . $this->appSecret . '&js_code=' . $this->appCode . '&grant_type=authorization_code';
        $response = $this->get_contents($url);
        $res = json_decode($response, true);
        if (isset($res['errcode']) && $res['errcode'] != 0) {
            throw new \think\Exception($res['errmsg']);
        }
        return $res;
    }

    /**
     * 数据解密
     * @param $sessionKey
     * @param $iv
     * @param $encryptedData
     * @return mixed
     * @throws \think\Exception
     */
    public function decryptData($sessionKey, $iv, $encryptedData)
    {
        include_once "wxBizDataCrypt.php";
        $wxc = new WXBizDataCrypt($this->appId, $sessionKey);
        $errCode = $wxc->decryptData($encryptedData, $iv, $data);
        if ($errCode != 0) {
            throw new \think\Exception(ErrorCode::getErrorMsg($errCode));
        }
        return json_decode($data, true);
    }

    /**
     * 获取二维码
     * @param $param
     * @return bool|string
     * @throws \think\Exception
     */
    public function getQrCode($param)
    {
        $accessToken = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $accessToken;
        $fields = [];
        if (isset($param['goods_id']) && isset($param['user_id'])) {
            // 商品分享码
            $fields = [
                'page' => 'pages/goods_details/index',
                'scene' => 'id=' . $param['goods_id'] . '&pid=' . $param['user_id'],
            ];
        } elseif (isset($param['user_id'])) {
            // 个人分享码

        }
        $response = $this->get_contents($url, 'POST', $fields);
        if (is_null(json_decode($response))) {
            // 图片buff
            return $response;
        }
        $res = json_decode($response, true);
        if (isset($res['errcode']) && $res['errcode'] != 0) {
            throw new \think\Exception($res['errmsg']);
        }
        return $response;
    }
}
