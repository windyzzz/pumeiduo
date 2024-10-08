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
            $arr[$position_id] = M('ad')->field('ad_code, ad_link, ad_name, bgcolor, target_type, target_type_id')
                ->where('pid', $position_id)
                ->where('enabled', 1)
                ->where('start_time', 'elt', $now_time)
                ->where('end_time', 'egt', $now_time)
                ->order('orderby DESC')
                ->select();
        }
        if (count($arr) == 1) {
            $adList = $arr[$position_id];
            foreach ($adList as $k => $item) {
                // 十六进制颜色转RGB
//                $adList[$k]['bgcolor'] = HexToRGBA($item['bgcolor'], 1);
                // APP跳转页面接口参数
                $adList[$k]['target_type_ids'] = [
                    'goods_id' => $item['target_type'] == 1 ? $item['target_type_id'] : "0",
                    'prom_id' => $item['target_type'] == 2 ? $item['target_type_id'] : "0",
                    'cate_id' => $item['target_type'] == 11 ? $item['target_type_id'] : "",
                    'cate_name' => $item['target_type'] == 11 ? M('goods_category')->where(['id' => $item['target_type_id']])->value('name') : "",
                ];
                unset($adList[$k]['target_type_id']);
                // 是否需要登录
                if (in_array($item['target_type'], [3, 4, 7, 10])) {
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
        $popupList = M('popup')->where($where)->field('id, type, type_id, item_id, show_path')->order('sort desc')->limit(0, 3)->select();
        foreach ($popupList as $key => $popup) {
            $popupList[$key]['show_path'] = SITE_URL . $popup['show_path'];
            if (in_array($popup['type'], [6, 7, 8, 12])) {
                $popupList[$key]['need_login'] = 1;
            } else {
                $popupList[$key]['need_login'] = 0;
            }
            $popupList[$key]['type_ids'] = [];
            $popupList[$key]['type_ids_2'] = [
                'goods_id' => $popup['type'] == 9 ? $popup['type_id'] : "0",
                'item_id' => $popup['type'] == 9 ? $popup['item_id'] : "0",
                'cate_id' => $popup['type'] == 10 ? $popup['type_id'] : "",
                'cate_name' => $popup['type'] == 10 ? M('goods_category')->where(['id' => $popup['type_id']])->value('name') : "",
            ];
        }
        $returnData = [
            'popup_list' => !empty($popupList) ? $popupList : []
        ];
        return json(['status' => 1, 'result' => $returnData]);
    }
}
