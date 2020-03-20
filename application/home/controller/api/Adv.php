<?php

namespace app\home\controller\api;

class Adv extends Base
{

    public function index()
    {
        $position_id = I('position_id');
        $position_id_arr = explode(',', $position_id);
        $now_time = time();
        $arr = array();
        foreach ($position_id_arr as $k => $position_id) {
            $arr[$position_id] = M('ad')->field('ad_code, ad_link, ad_name, target_type, target_type_id')
                ->where('pid', $position_id)
                ->where('enabled', 1)
                ->where('start_time', 'elt', $now_time)
                ->where('end_time', 'egt', $now_time)
                ->order('orderby')
                ->select();
        }
        if (count($arr) == 1) {
            $adList = $arr[$position_id];
            foreach ($adList as $k => $item) {
                // APP跳转页面接口参数
                $adList[$k]['target_type_ids'] = [
                    'goods_id' => $item['target_type'] == 1 ? $item['target_type_id'] : 0,
                    'prom_id' => $item['target_type'] == 2 ? $item['target_type_id'] : 0
                ];
                unset($adList[$k]['target_type_id']);
                // 是否需要登录
                if (in_array($item['target_type'], [3, 4])) {
                    $adList[$k]['need_login'] = 1;
                } else {
                    $adList[$k]['need_login'] = 0;
                }
            }
            return json(['status' => 1, 'msg' => 'success', 'result' => $adList]);
        } else {
            return json(['status' => 1, 'msg' => 'success', 'result' => $arr]);
        }
    }


    public function popup()
    {
        $where = [
            'is_open' => 1,
            'start_time' => ['<', time()],
            'end_time' => ['>', time()]
        ];
        // 活动弹窗列表
        $popupList = M('popup')->where($where)->field('id, type, show_limit, show_path')->order('sort desc')->select();
        if (!$this->passAuth && $this->user_id) {
            // 用户弹窗记录
            $userPopupLog = M('user_popup_log')->where(['user_id' => $this->user_id])->getField('popup_id, log_time', true);
        }
        foreach ($popupList as $key => $popup) {
            switch ($popup['show_limit']) {
                case 1:
                    // 每天一次
                    if (!isset($userPopupLog[$popup['id']]) && $this->user_id) {
                        // 增加用户记录
                        M('user_popup_log')->add([
                            'user_id' => $this->user_id,
                            'popup_id' => $popup['id'],
                            'log_time' => time()
                        ]);
                    } elseif (isset($userPopupLog[$popup['id']])) {
                        if ($userPopupLog[$popup['id']]['log_time'] < strtotime(date('Y-m-d 00:00:00', time()))) {
                            // 当天用户未弹出，更新用户记录
                            M('user_popup_log')->where(['user_id' => $this->user_id, 'popup_id' => $popup['id']])->update(['log_time' => time()]);
                        } else {
                            // 当天用户已弹出
                            unset($popupList[$key]);
                        }
                    }
                    break;
                case 2:
                    // 活动期间一次
                    if (!isset($userPopupLog[$popup['id']]) && $this->user_id) {
                        // 增加用户记录
                        M('user_popup_log')->add([
                            'user_id' => $this->user_id,
                            'popup_id' => $popup['id'],
                            'log_time' => time()
                        ]);
                    } elseif (isset($userPopupLog[$popup['id']])) {
                        // 用户已弹出
                        unset($popupList[$key]);
                    }
                    break;
            }
        }
    }
}
