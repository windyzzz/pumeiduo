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

    /**
     * 活动弹窗列表
     * @return \think\response\Json
     */
    public function popup()
    {
        $where = [
            'is_open' => 1,
            'start_time' => ['<', time()],
            'end_time' => ['>', time()]
        ];
        // 活动弹窗列表
        $popupList = M('popup')->where($where)->field('id, type, show_path')->order('sort desc')->limit(0, 3)->select();
        foreach ($popupList as $key => $popup) {
            $popupList[$key]['show_path'] = SITE_URL . $popup['show_path'];
            if (in_array($popup['type'], [6, 7, 8])) {
                $popupList[$key]['need_login'] = 1;
            } else {
                $popupList[$key]['need_login'] = 0;
            }
            $popupList[$key]['type_ids'] = [];
        }
        $returnData = [
            'popup_list' => !empty($popupList) ? $popupList : []
        ];
        return json(['status' => 1, 'result' => $returnData]);
    }

    /**
     * 用户弹窗记录
     * @return \think\response\Json
     */
    public function userPopupLog()
    {
        $popupIds = I('popup_ids', '');
        if (empty($popupIds)) return json(['status' => 1]);
        $popupIds = explode(',', $popupIds);
        foreach ($popupIds as $popupId) {
            if (M('user_popup_log')->where(['user_id' => $this->user_id, 'popup_id' => $popupId])->find()) {
                M('user_popup_log')->where(['user_id' => $this->user_id, 'popup_id' => $popupId])->update(['log_time' => time()]);
            } else {
                M('user_popup_log')->add([
                    'user_id' => $this->user_id,
                    'popup_id' => $popupId,
                    'log_time' => time()
                ]);
            }
        }
        return json(['status' => 1]);
    }
}
