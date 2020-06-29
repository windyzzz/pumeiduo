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
     * @param $method
     * @return array|mixed
     */
    protected function getData($api, $data, $method = 'POST')
    {
        $data['sign'] = $this->makeSign($data);
        $data['appid'] = $this->appId;
        $res = json_decode(httpRequest($this->url . $api, $method, $data), true);
        if (in_array($res['code'], ['200', '00001', '1'])) {
            if (is_array($res['data'])) {
                $return = ['status' => 1, 'data' => $res['data']];
            } else {
                $return = ['status' => 1, 'data' => json_decode($res['data'], true) ?? []];
            }
        } else {
            $return = ['status' => 0, 'msg' => $res['msg']];
        }
        return $return;
    }
}