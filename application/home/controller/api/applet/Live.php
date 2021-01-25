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
        if (cache('applet_live_list_page' . $page)) {
            $roomList = cache('applet_live_list_page' . $page);
        } else {
            $fields = [
                'limit' => 10
            ];
            if ($page == 1) {
                $fields['start'] = 0;
            } else {
                $fields['start'] = ($page - 1) * 10 + 0;
            }
            $url = 'https://api.weixin.qq.com/wxa/business/getliveinfo?access_token=' . $this->accessToken;
            $response = $this->get_contents($url, 'POST', $fields);
            $res = json_decode($response, true);
            if (isset($res['errcode']) && $res['errcode'] != 0) {
                if ($res['errcode'] == '9410000') {
                    // 直播列表为空
                    return json(['status' => 1, 'result' => ['list' => []]]);
                }
                return json(['status' => $res['errcode'], 'msg' => $res['errmsg']]);
            }
            $roomList = $res['room_info'];
            // 缓存数据
            cache('applet_live_list_page' . $page, $roomList, 60);
        }
        $return = [
            'list' => $roomList
        ];
        return json(['status' => 1, 'result' => $return]);
    }
}
