<?php

namespace app\common\logic\push;

class VivoPush extends BasePush
{
    private $cacheFilename;
    private $appid;
    private $appKey;
    private $appSecret;
    private $authUrl = 'https://api-push.vivo.com.cn/message/auth';//鉴权
    private $pushUrl = 'https://api-push.vivo.com.cn/message/send';//单个推送
    private $savePayloadhUrl = 'https://api-push.vivo.com.cn/message/saveListPayload';//生成消息体
    private $pushBatchUrl = 'https://api-push.vivo.com.cn/message/pushToList';//批量推送
    private $maxPushCount = 100;//单次最大推送设备数量

    function __construct($appid, $appKey, $appSecret)
    {
        $this->cacheFilename = __DIR__ . "/vivo_access_token.php";
        $this->cacheFilename = str_replace("/", DIRECTORY_SEPARATOR, $this->cacheFilename);
        $this->appid = $appid;
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        parent::__construct();
    }

    /**
     * 发送推送
     * @param $regid regid 单个或数组
     * @param $title 标题
     * @param $desc 简介
     * @param array $extData 自定义字段
     * @return bool
     */
    public function send($regid, $title, $desc, $extData = [], $pushType = 0)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return FALSE;
        }
        $postData = [
            'notifyType' => 4,
            'title' => $title,
            'content' => $desc,
            'timeToLive' => 86400 * 7,
            'skipType' => 4,
            'skipContent' => $desc,
            'requestId' => uniqid()
        ];
        if (!empty($extData)) {
            $postData['clientCustomMap'] = $extData;//自定义消息
        }
        if (is_array($regid) && count($regid) > 1) {
            $res = $this->sendBatch($regid, $postData);
        } else {
            if (is_string($regid)) {
                $temp = $regid;
                $regid = [];
                $regid[] = $temp;
            }
            $postData['regId'] = implode('', $regid);
            $resInfo = $this->request(
                $this->pushUrl,
                json_encode($postData),
                $this->_getHeader()
            );

            $res = FALSE;
            if ($resInfo['result'] == 0) {
                $res = TRUE;
            }
        }

        return $res;
    }

    /**
     * 批量推送
     * @param $regid
     * @param $postData
     * @return bool
     */
    public function sendBatch($regid, $postData)
    {
        //先生成消息体
        $taskRes = $this->request(
            $this->savePayloadhUrl,
            json_encode($postData),
            $this->_getHeader()
        );
        if (!$taskRes) {
            return FALSE;
        }
        if ($taskRes && $taskRes['result'] != 0) {
            return FALSE;
        }
        $taskId = $taskRes['taskId'];

        //拿到任务Id去发送
        $taskPostData = [
//            'regIds' => $regid,
            'taskId' => $taskId,
            'requestId' => uniqid()
        ];

        //判断regid 个数大于等于 2，小于等于 1000
        $regidParams = array_chunk($regid, $this->maxPushCount);
        foreach ($regidParams as $k => $v) {
            $taskPostData['regIds'] = $v;
            $res = $this->_doSendBatch($taskPostData);
        }
        return $res;
    }

    /**
     * 批量推送方法
     * @param $taskPostData
     * @return bool
     */
    private function _doSendBatch($taskPostData)
    {
        $res = $this->request(
            $this->pushBatchUrl,
            json_encode($taskPostData),
            $this->_getHeader()
        );
        if ($res && $res['result'] == 0) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 获取header
     * @return array
     */
    private function _getHeader()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) return [];

        return ['Content-Type:application/json; charset=utf-8', 'authToken:' . $accessToken];
    }

    /**
     * 获取accesstoken
     * @return bool
     */
    public function getAccessToken()
    {
        list($msec, $sec) = explode(' ', microtime());
        $timestamp = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $cacheInfo = $this->get_php_file($this->cacheFilename);

        if ($cacheInfo['expire_time'] < time()) {//过期
            $postData = [
                'appId' => $this->appid,
                'appKey' => $this->appKey,
                'timestamp' => $timestamp,
            ];
            $sign = $this->getSign($postData);
            $postData['sign'] = $sign;
            $resArray = $this->request(
                $this->authUrl,
                json_encode($postData),
                ['Content-Type:application/json; charset=utf-8']
            );

            $accessToken = FALSE;
            if ($resArray['result'] == 0) {
                $accessToken = $resArray['authToken'];
            }

            if ($accessToken) {
                $cacheInfo['expire_time'] = time() + 86000;
                $cacheInfo['access_token'] = $accessToken;
                $this->set_php_file($this->cacheFilename, json_encode($cacheInfo));
            }
        } else {
            $accessToken = $cacheInfo['access_token'];
        }
        return $accessToken;
    }

    /**
     * 获取sign值
     * @param $data
     * @return string
     */
    public function getSign($data)
    {
        $params = '';
        foreach ($data as $v) {
            $params .= $v;
        }
        $params .= $this->appSecret;
        return strtolower(md5($params));
    }
}