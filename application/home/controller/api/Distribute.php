<?php

namespace app\home\controller\api;


use think\Page;

class Distribute extends Base
{
    /**
     * 获取下级用户统计
     * @return \think\response\Json
     */
    public function member()
    {
        $level = I('level', 1);
        $where = [];
        switch ($level) {
            case 1:
                $where['first_leader'] = $this->user_id;
                break;
            case 2:
                $where['second_leader'] = $this->user_id;
                break;
            case 3:
                $where['third_leader'] = $this->user_id;
                break;
            default:
                return json(['status' => 0, 'msg' => '等级不存在']);
        }
        $memberCount = M('users')->where($where)->count('user_id');
        return json(['status' => 1, 'result' => ['member_count' => $memberCount]]);
    }

    /**
     * 获取提成记录（分享订单列表）
     * @return \think\response\Json
     */
    public function rebateLog()
    {
        $startAt = I('start_at', date('Y-m-01', time()));
        $endAt = I('end_at', date('Y-m-t', time()));
        $status = I('status', 0);

        $where = [
            'rl.user_id' => $this->user_id,
            'rl.create_time' => ['BETWEEN', [strtotime($startAt), strtotime($endAt)]],
            'rl.status' => $status
        ];
        // 订单ID
        $orderIds = M('rebate_log rl')->where($where)->getField('rl.order_id', true);
        // 订单商品列表
        $orderGoods = M('order_goods og')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->where(['order_id' => ['in', array_unique($orderIds)]])
            ->field('og.order_id, og.goods_id, og.goods_name, og.spec_key_name, g.original_img, g.commission, og.goods_num, og.final_price, og.use_integral')->select();
        // 提成记录
        $page = new Page(count($orderIds), 10);
        $rebateLog = M('rebate_log rl')
            ->join('users u', 'u.user_id = rl.buy_user_id')
            ->where($where)->field('rl.*, u.nickname buy_user_nickname, u.user_name buy_user_username, u.head_pic buy_user_head')
            ->limit($page->firstRow . ',' . $page->listRows)->order('create_time DESC')->select();
        $return = [];
        foreach ($rebateLog as $k => $log) {
            $return[$k] = [
                'log_id' => $log['id'],
                'buy_user_id' => $log['buy_user_id'],
                'buy_user_head' => $log['buy_user_head'],
                'order_id' => $log['order_id'],
                'order_sn' => $log['order_sn'],
                'commission' => $log['money'],
                'status' => $log['status'],
                'create_time' => $log['create_time'],
                'goods_list' => []
            ];
            foreach ($orderGoods as $goods) {
                if ($log['order_id'] == $goods['order_id']) {
                    $return[$k]['goods_list'][] = [
                        'goods_id' => $goods['goods_id'],
                        'goods_name' => $goods['goods_name'],
                        'spec_key_name' => $goods['spec_key_name'],
                        'original_img' => $goods['original_img'],
                        'goods_num' => $goods['goods_num'],
                        'exchange_price' => $goods['final_price'],
                        'exchange_integral' => $goods['use_integral'],
                        'commission' => $goods['commission']
                    ];
                }
            }
        }
        return json(['status' => 1, 'result' => $return]);
    }
}