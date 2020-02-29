<?php

namespace app\common\logic\push;

class OppoPush extends BasePush
{
    private $cacheFilename;
    private $title;
    private $desc;
    private $extData;
    private $clientKey;
    private $clientMasterSecret;
    private $authUrl = 'https://api.push.oppomobile.com/server/v1/auth';//鉴权
    private $singlePushUrl = 'https://api.push.oppomobile.com/server/v1/message/notification/unicast';//单推
    private $getMessageIdUrl = 'https://api.push.oppomobile.com/server/v1/message/notification/save_message_content';//获取消息ID
    private $broadcastUrl = 'https://api.push.oppomobile.com/server/v1/message/notification/broadcast';//批量广播推送
    private $maxPushCount = 1000;

    function __construct($clientKey, $clientMasterSecret)
    {
        $this->cacheFilename = __DIR__ . "/oppo_access_token.php";
        $this->cacheFilename = str_replace("/", DIRECTORY_SEPARATOR, $this->cacheFilename);
        $this->clientKey = $clientKey;
        $this->clientMasterSecret = $clientMasterSecret;
        parent::__construct();
    }

    /**
     * 推送
     * @param $regid regid 单个或批量
     * @param $title 标题
     * @param $desc 简介
     * @return bool
     */
    public function send($regid, $title, $desc, $extData = [], $pushType = 0)
    {
        if (empty($regid) || empty($title) || empty($desc)) return FALSE;
        $this->title = $title;
        $this->desc = $desc;
        $this->extData = $extData;
        if (is_array($regid) && count($regid) > 1) {
            $res = $this->_sendBatch($regid);
        } else {
            $res = $this->_doSend($regid);
        }
        return $res;
    }

    /**
     * 推送单条消息
     */
    private function _doSend($regid)
    {
        if (is_string($regid)) {
            $temp = $regid;
            $regid = [];
            $regid[] = $temp;
        }
        $messageData = [
            'target_type' => 2,
            'target_value' => implode(';', $regid),
            'notification' => $this->getMessageBody()
        ];
        $postData = [
            'message' => json_encode($messageData)
        ];
        $res = $this->request(
            $this->singlePushUrl,
            $postData,
            $this->_getHeader(),
            TRUE
        );

        if ($res && $res['code'] == 0 && isset($res['data']['messageId'])) {
            $messageId = $res['data']['messageId'];
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 提送批量消息
     * @param $regid
     * @return bool
     */
    private function _sendBatch($regid)
    {
        //获取消息ID
        $messageId = $this->_getMessageId();
        if (!$messageId) return FALSE;

        $postData = [
            'message_id' => $messageId,
            'target_type' => 2
        ];

        $regidParams = array_chunk($regid, $this->maxPushCount);
        foreach ($regidParams as $k => $v) {
            $postData['target_value'] = implode(';', $v);
            $res = $this->_doSendBatch($postData);
        }
        return $res;
    }

    private function _doSendBatch($postData)
    {
        $res = $this->request(
            $this->broadcastUrl,
            $postData,
            $this->_getHeader(),
            TRUE
        );

        if ($res && $res['code'] == 0 && isset($res['data']['task_id'])) {
            $taskId = $res['data']['task_id'];
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 获取消息ID
     * @return bool
     */
    private function _getMessageId()
    {
        $messageBody = $this->getMessageBody();
        $res = $this->request(
            $this->getMessageIdUrl,
            $messageBody,
            $this->_getHeader(),
            TRUE
        );
        if ($res && $res['code'] == 0 && isset($res['data']['message_id'])) {
            return $res['data']['message_id'];
        }
        return FALSE;
    }

    /**
     * 构造消息体
     * @return array
     */
    private function getMessageBody()
    {
        return [
            'app_message_id' => uniqid(),
            'title' => $this->title,
            'content' => $this->desc,
            'click_action_type' => 0,
            'action_parameters' => json_encode($this->extData),
            'off_line_ttl' => 86400 * 3,
        ];
    }

    private function _getHeader()
    {
        $authToken = $this->getAccessToken();
        if (!$authToken) return [];
        return ['Content-Type:application/x-www-form-urlencoded; charset=utf-8', 'auth_token:' . $authToken];
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
                'app_key' => $this->clientKey,
                'timestamp' => $timestamp,
            ];
            $sign = $this->getSign($postData);
            $postData['sign'] = $sign;
            $resArray = $this->request(
                $this->authUrl,
                $postData,
                ['Content-Type:application/x-www-form-urlencoded; charset=utf-8'],
                TRUE
            );
            $accessToken = FALSE;
            if ($resArray['code'] == 0 && isset($resArray['data']['auth_token'])) {
                $accessToken = $resArray['data']['auth_token'];
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
     * 生成sign
     * @param $data
     * @return string
     */
    public function getSign($data)
    {
//        sha256(appkey+timestamp+mastersecret)
        $params = '';
        foreach ($data as $v) {
            $params .= $v;
        }
        $params .= $this->clientMasterSecret;
        return hash('sha256', $params);
    }
}
