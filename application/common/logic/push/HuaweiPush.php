<?php

namespace app\common\logic\push;

class HuaweiPush extends BasePush
{
    private $cacheFilename;
    private $appid;
    private $secret;
    private $packageName;
    private $pushUrl = 'https://api.push.hicloud.com/pushsend.do';//推送url
    private $authUrl = 'https://login.vmall.com/oauth2/token';//鉴权
    private $maxPushCount = 100;

    function __construct($appid, $secret, $package)
    {
        $this->cacheFilename = __DIR__ . "/huawei_access_token.php";
        $this->cacheFilename = str_replace("/", DIRECTORY_SEPARATOR, $this->cacheFilename);
        $this->appid = $appid;
        $this->secret = $secret;
        $this->packageName = $package;
        parent::__construct();
    }

    /**
     * 推送
     * @param $regid regid 单个或批量
     * @param $title 标题
     * @param $desc 简介
     * @param array $extData 额外参数
     * @param int $pushType 0通知 1透传 3通知栏消息，异步透传消息请根据接口文档设置
     * @return bool
     */
    public function send($regid, $title, $desc, $extData = [], $pushType = 0)
    {
        $body = array();
        $body['title'] = $title;
        $body['content'] = $desc;

        $param = array();
        $param['appPkgName'] = $this->packageName;

        $action = array();
        $action['param'] = $param;//消息点击动作参数
        $action['type'] = 3;//类型3为打开APP，其他行为请参考接口文档设置

        $msg = array();
        $msg['action'] = $action;//消息点击动作
        $msg['type'] = $pushType;
        if ($msg['type'] == 1 && !empty($extData)) {
            $body = array_merge($body, $extData);
            unset($msg['action']);
        }
        $msg['body'] = $body;//通知栏消息body内容

        $ext = array();

        $hps = array();
        $hps['msg'] = $msg;
        $hps['ext'] = $extData;

        $payload = array();
        $payload['hps'] = $hps;

        if (is_string($regid)) {
            $temp = $regid;
            $regid = [];
            $regid[] = $temp;
        }
        $regidParams = array_chunk($regid, $this->maxPushCount);
        foreach ($regidParams as $k => $v) {
            $res = $this->_doSend($v, $payload);
        }
        return $res;
    }

    /**
     * 发送推送
     * @param $regidList regid列表
     * @param $payload 消息体
     * @return bool
     */
    private function _doSend($regidList, $payload)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return FALSE;
        }
        $res = $this->request(
            $this->pushUrl . '?nsp_ctx=' . urlencode("{\"ver\":\"1\",\"appId\": \"{$this->appid}\"}"),
            [
                'access_token' => $accessToken,
                'nsp_svc' => 'openpush.message.api.send',
                'nsp_ts' => (int)time(),
                'device_token_list' => json_encode($regidList),
                'payload' => json_encode($payload),
            ],
            $this->_getHeader(),
            TRUE
        );
        if (!$res && $res['code'] == 80000000) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 返回header头
     * @return array
     */
    private function _getHeader()
    {
        return ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'];
    }

    /**
     * 获取accesstoken
     * @return bool
     */
    public function getAccessToken()
    {
        $cacheInfo = $this->get_php_file($this->cacheFilename);
        if ($cacheInfo['expire_time'] < time()) {//过期
            $resArray = $this->request(
                $this->authUrl,
                [
                    'grant_type' => 'client_credentials',
                    'client_secret' => $this->secret,
                    'client_id' => $this->appid
                ],
                $this->_getHeader(),
                true
            );

            $accessToken = FALSE;
            if (isset($resArray['access_token']) && isset($resArray['expires_in'])) {
                $accessToken = $resArray['access_token'];
            }

            if ($accessToken) {
                $cacheInfo['expire_time'] = time() + 3500;
                $cacheInfo['access_token'] = $accessToken;
                $this->set_php_file($this->cacheFilename, json_encode($cacheInfo));
            }
        } else {
            $accessToken = $cacheInfo['access_token'];
        }
        return $accessToken;
    }
}
