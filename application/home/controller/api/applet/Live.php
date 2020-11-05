<?php

namespace app\home\controller\api\applet;


class Live extends Base
{
    /**
     * 获取直播列表
     * @return \think\response\Json
     */
    public function liveList()
    {
        $url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token=' . $this->accessToken;
        $fields = [
            'start' => 9,   // 前10个测试用
            'limit' => 10
        ];
        $response = $this->get_contents($url, 'POST', $fields);
        $res = json_decode($response, true);
        if (isset($res['errcode']) && $res['errcode'] != 0) {
            return json(['status' => 0, 'msg' => $res['errmsg']]);
        }
        $return = [
            'list' => $res['room_info']
        ];
        return json(['status' => 1, 'result' => $return]);
    }
}