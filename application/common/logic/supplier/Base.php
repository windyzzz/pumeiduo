<?php

namespace app\common\logic\supplier;


class Base
{
    protected $appId;
    protected $appSecret;
    protected $url;

    public function __construct()
    {
        $this->appId = \think\Env::get('SUPPLIER.APPID');
        $this->appSecret = \think\Env::get('SUPPLIER.APPSECRET');
        $this->url = \think\Env::get('SUPPLIER.URL');
    }

    /**
     * 生产签名
     * @param $data
     * @return string
     */
    protected function makeSign($data)
    {
        $data['app_secret'] = $this->appSecret;
        return hmac_md5_sign($this->appId, $data);
    }

    /**
     * 获取数据
     * @param $api
     * @param $data
     * @return array|mixed
     */
    protected function getData($api, $data)
    {
        $data['sign'] = $this->makeSign($data);
        $data['appid'] = $this->appId;
        $res = json_decode(httpRequest($this->url . $api, 'POST', $data), true);
        var_dump($res);
        exit();
        if (in_array($res['code'], ['200', '00001'])) {
            if (is_array($res['data'])) {
                return $res['data'];
            } else {
                return json_decode($res['data'], true);
            }
        } else {
            return [];
        }
    }
}