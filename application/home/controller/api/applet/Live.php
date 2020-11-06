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
        $page = I('p', 1);
        $fields = [
            'limit' => 10
        ];
        if ($page == 1) {
            $fields['start'] = 9;   // 前10个测试用
        } else {
            $fields['start'] = ($page - 1) * 10 + 9;
        }
        $url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token=' . $this->accessToken;
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