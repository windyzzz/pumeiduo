<?php

namespace app\common\logic\push;

class MeizuPush extends BasePush
{
    private $appid;
    private $appKey;
    private $appSecret;
    private $pushUrl = 'https://server-api-push.meizu.com/garcia/api/server/push/varnished/pushByPushId';//推送URL
    private $staticsUrl = 'https://server-api-push.meizu.com/garcia/api/server/push/statistics/dailyPushStatics';//获取统计数据URL
    private $maxPushCount = 1000;

    function __construct($appid, $appKey, $appSecret)
    {
        $this->appid = $appid;
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        parent::__construct();
    }

    /**
     * 推送消息
     * @param $regid 客户端生成的pushId
     * @param $title 推送标题
     * @param $desc 推送详情
     * @return bool
     */
    public function send($regid, $title, $desc, $extData = [], $pushType = 0)
    {
        $noticeBarInfo = [
            'title' => $title,
            'content' => $desc
        ];
        $pushTimeInfo = [
            'offLine' => 1,
            'validTime' => 72
        ];
        $clickTypeInfo = [
            'parameters' => $extData
        ];
        $messageJson = [
            'noticeBarInfo' => $noticeBarInfo,
            'pushTimeInfo' => $pushTimeInfo,
            'clickTypeInfo' => $clickTypeInfo
        ];

        //一批最多不能超过1000个 多个英文逗号分割必填
        $postData = [
            'appId' => $this->appid,
            'messageJson' => json_encode($messageJson),
        ];

        if (is_string($regid)) {
            $temp = $regid;
            $regid = [];
            $regid[] = $temp;
        }
        $regidParams = array_chunk($regid, $this->maxPushCount);
        foreach ($regidParams as $k => $v) {
            $postData['pushIds'] = implode(',', $v);
            $res = $this->_doSend($postData);
        }
        return $res;
    }

    /**
     * 推送方法
     * @param $postData
     * @return bool
     */
    private function _doSend($postData)
    {
        $sign = $this->getSign($postData);
        $postData['sign'] = $sign;
        $res = $this->request(
            $this->pushUrl,
            $postData,
            ['Content-Type:application/x-www-form-urlencoded;charset=UTF-8'],
            TRUE
        );
        if ($res['code'] == 200) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 生成签名
     * @param $data
     * @return string
     */
    public function getSign($data)
    {
        $params = '';
        ksort($data);
        foreach ($data as $k => $v) {
            $params .= $k . '=' . $v;
        }
        $params .= $this->appSecret;
        return strtolower(md5($params));
    }
}
