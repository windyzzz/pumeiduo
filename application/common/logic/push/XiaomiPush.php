<?php

namespace app\common\logic\push;

class XiaomiPush extends BasePush
{
    private $secret;
    private $package;
    private $maxPushCount = 1000;
    private $pushV3 = 'https://api.xmpush.xiaomi.com/v3/message/regid';

    function __construct($secret, $package)
    {
        $this->secret = $secret;
        $this->package = $package;
        parent::__construct();
    }

    /**
     * 推送单个regid或者多个regid
     * @param regid String或Array
     * @param title 推送标题
     * @param desc 推送详情
     * @param extData 自定义消息体
     * @param pushType 推送类型 0为通知 1为透传消息
     */
    public function send($regid, $title, $desc, $extData = [], $pushType = 0)
    {
        $postData = [
            'restricted_package_name' => $this->package,
            'pass_through' => $pushType,
            'title' => $title,
            'description' => $desc,
            'notify_type' => -1,
        ];
        if (!empty($extData)) {
            $postData['payload'] = urlencode(json_encode($extData));
        }

        if (is_string($regid)) {
            $temp = $regid;
            $regid = [];
            $regid[] = $temp;
        }
        $regidParams = array_chunk($regid, $this->maxPushCount);
        foreach ($regidParams as $k => $v) {
            $postData['registration_id'] = implode(',', $v);
            $res = $this->_doSend($postData);
        }
        return $res;
    }

    /**
     * 发送操作
     * @param $postData
     * @return bool
     */
    private function _doSend($postData)
    {
        $res = $this->request(
            $this->pushV3,
            $postData,
            ["Authorization: key={$this->secret}"]
        );
        if ($res['code'] == 0) {
            return TRUE;
        }
        return FALSE;
    }
}