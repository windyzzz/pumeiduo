<?php

namespace app\home\controller\api;


use app\common\logic\OrderLogic;
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
        $where = [
            'is_lock' => 0,
            'is_cancel' => 0
        ];
        switch ($level) {
            case 1:
                $where['first_leader'] = $this->user_id;
                $level = '一级会员';
                break;
            case 2:
                $where['second_leader'] = $this->user_id;
                $level = '二级会员';
                break;
            case 3:
                $where['third_leader'] = $this->user_id;
                $level = '三级会员';
                break;
            default:
                return json(['status' => 0, 'msg' => '等级不存在']);
        }
        $memberCount = M('users')->where($where)->count('user_id');
        return json(['status' => 1, 'result' => ['level' => $level, 'member_count' => $memberCount]]);
    }

    /**
     * 获取下级用户列表
     * @return \think\response\Json
     */
    public function memberList()
    {
        $level = I('level', 1);
        $where = [
            'is_lock' => 0,
            'is_cancel' => 0
        ];
        switch ($level) {
            case 1:
                $where['first_leader'] = $this->user_id;
                $level = '一级粉丝';
                break;
            case 2:
                $where['second_leader'] = $this->user_id;
                $level = '二级粉丝';
                break;
            case 3:
                $where['third_leader'] = $this->user_id;
                $level = '三级粉丝';
                break;
            default:
                return json(['status' => 0, 'msg' => '等级不存在']);
        }
        $memberList = M('users')->where($where)->field('user_id, nickname, head_pic, user_name, distribut_level')->select();
        foreach ($memberList as $key => $member) {
            if ($member['nickname'] == '') {
                $memberList[$key]['nickname'] = $member['user_name'];
            }
            unset($memberList[$key]['user_name']);
        }
        return json(['status' => 1, 'result' => ['level' => $level, 'member_list' => $memberList]]);
    }

    /**
     * 获取提成记录（分享订单列表）
     * @return \think\response\Json
     */
    public function rebateLog()
    {
        $startAt = I('start_at', strtotime(date('Y-m-01 00:00:00', time())) . '');
        $endAt = I('end_at', strtotime(date('Y-m-t 23:59:59', time())) . '');
        $status = I('status', '');

        $where = [
            'rl.user_id' => $this->user_id,
            'rl.create_time' => ['BETWEEN', [strtotime(date('Y-m-d 00:00:00', $startAt)), strtotime(date('Y-m-d 23:59:59', $endAt))]],
            'rl.money' => ['GT', 0]
        ];
        if ($status != '') {
            switch ($status) {
                case 6:
                    $where['rl.sale_service'] = 1;
                    break;
                default:
                    $where['rl.status'] = $status;
                    $where['rl.sale_service'] = 0;
            }
        }
        // 订单ID
        $orderIds = M('rebate_log rl')->where($where)->getField('rl.order_id', true);
        // 订单商品列表
        $orderGoods = M('order_goods og')
            ->join('order o', 'o.order_id = og.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->where(['og.order_id' => ['in', array_unique($orderIds)]])
            ->field('og.rec_id, og.order_id, og.goods_id, og.goods_name, og.spec_key_name, g.original_img, og.goods_num, og.final_price, og.member_goods_price, og.use_integral, og.commission, og.goods_pv, o.add_time')->select();
        // 提成记录
        $page = new Page(count($orderIds), 10);
        $rebateLog = M('rebate_log rl')
            ->join('users u', 'u.user_id = rl.buy_user_id')
            ->join('order o', 'o.order_id = rl.order_id')
            ->where($where)->field('rl.*, u.nickname buy_user_nickname, u.user_name buy_user_username, u.head_pic buy_user_head,o.order_status, o.end_sale_time')
            ->limit($page->firstRow . ',' . $page->listRows)->order('create_time DESC')->select();
        $list = [];
        $OrderLogic = new OrderLogic();
        foreach ($rebateLog as $k => $log) {
            $list[$k] = [
                'log_id' => $log['id'],
                'buy_user_id' => $log['buy_user_id'],
                'buy_user_head' => $log['buy_user_head'],
                'order_id' => $log['order_id'],
                'order_sn' => $log['order_sn'],
                'commission' => $log['money'],
                'status' => $log['sale_service'] == 1 ? '6' : $log['status'],
                'status_desc' => $log['sale_service'] == 1 ? '已售后' : rebate_status($log['status']),
                'create_time' => $log['create_time'],
                'goods_list' => [],
                'end_sale_tips' => ''
            ];
            if (in_array($log['status'], [0, 1, 2]) && $log['order_status'] == 2 && !empty($log['end_sale_time'])) {
                $list[$k]['end_sale_tips'] = '已确认收货，预计' . date('Y年m月d日', $log['end_sale_time']) . '到账';
            }
            foreach ($orderGoods as $goods) {
                if ($log['order_id'] == $goods['order_id']) {
                    $hasCommission = true;
                    if ($log['sale_service'] == 1) {
                        // 提成记录状态为 已售后
                        if (M('return_goods')->where(['rec_id' => $goods['rec_id'], 'status' => ['NOT IN', [-2, -1, 4, 6]]])->value('id')) {
                            $hasCommission = false;
                        }
                    }
                    $list[$k]['goods_list'][] = [
                        'goods_id' => $goods['goods_id'],
                        'goods_name' => $goods['goods_name'],
                        'spec_key_name' => $goods['spec_key_name'],
                        'original_img' => $goods['original_img'],
                        'original_img_new' => getFullPath($goods['original_img']),
                        'goods_num' => $goods['goods_num'],
                        'exchange_price' => $goods['member_goods_price'],
                        'exchange_integral' => $goods['use_integral'],
                        'commission' => $hasCommission ? $goods['goods_pv'] == 0 ? bcadd($OrderLogic->getRongMoney(bcdiv(bcmul(bcmul($goods['final_price'], $goods['goods_num'], 2), $goods['commission'], 2), 100, 2), $log['level'], $goods['add_time'], $goods['goods_id']), 0, 2) : '0.00' : '0.00',
                        'goods_pv' => $this->user['distribut_level'] >= 3 ? $hasCommission ? $goods['goods_pv'] > 0 ? $goods['goods_pv'] : '0.00' : '0.00' : '',
                        'is_freeze' => $hasCommission ? 0 : 1
                    ];
                }
            }
        }
        $return = [
            'date' => [
                'start_at' => $startAt,
                'end_at' => $endAt,
            ],
            'list' => $list
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 根据购买用户获取提成记录（上部分）
     * @return \think\response\Json
     */
    public function rebateLogSum()
    {
        $buyUserId = I('buy_user_id', '');
        if (!$buyUserId) return json(['status' => 0, 'msg' => '请传入购买者ID']);

        $where = [
            'rl.user_id' => $this->user_id,
            'rl.buy_user_id' => $buyUserId,
            'rl.status' => ['in', [3, 5]],
            'rl.money' => ['GT', 0]
        ];
        $rebateLogSum = M('rebate_log rl')->where($where)->sum('rl.money');
        // 购买者用户信息
        $buyUser = M('users')->where(['user_id' => $buyUserId])->field('user_id, nickname, user_name, head_pic')->find();
        $return = [
            'buy_user' => [
                'user_id' => $buyUser['user_id'],
                'nickname' => !empty($buyUser['nickname']) ? $buyUser['nickname'] : $buyUser['user_name'],
                'head_pic' => $buyUser['head_pic']
            ],
            'income' => $rebateLogSum
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 根据购买用户获取提成记录（下部分）
     * @return \think\response\Json
     */
    public function rebateLogList()
    {
        $buyUserId = I('buy_user_id', '');
        if (!$buyUserId) return json(['status' => 0, 'msg' => '请传入购买者ID']);
        $startAt = I('start_at', strtotime(date('Y-m-01 00:00:00', time())));
        $endAt = I('end_at', strtotime(date('Y-m-t 23:59:59', time())));
        $status = I('status', '');

        $where = [
            'rl.user_id' => $this->user_id,
            'rl.buy_user_id' => $buyUserId,
            'rl.create_time' => ['BETWEEN', [strtotime(date('Y-m-d 00:00:00', $startAt)), strtotime(date('Y-m-d 23:59:59', $endAt))]],
            'rl.money' => ['GT', 0]
        ];
        if ($status != '') {
            switch ($status) {
                case 6:
                    $where['rl.sale_service'] = 1;
                    break;
                default:
                    $where['rl.status'] = $status;
                    $where['rl.sale_service'] = 0;
            }
        }
        // 订单ID
        $orderIds = M('rebate_log rl')->where($where)->getField('rl.order_id', true);
        // 订单商品列表
        $orderGoods = M('order_goods og')
            ->join('order o', 'o.order_id = og.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->where(['og.order_id' => ['in', array_unique($orderIds)]])
            ->field('og.order_id, og.goods_id, og.goods_name, og.spec_key_name, g.original_img, og.goods_num, og.final_price, og.member_goods_price, og.use_integral, og.commission, og.goods_pv, o.add_time')->select();
        // 提成记录
        $page = new Page(count($orderIds), 10);
        $rebateLog = M('rebate_log rl')->join('order o', 'o.order_id = rl.order_id')
            ->where($where)->field('rl.*, o.order_status, o.end_sale_time')
            ->limit($page->firstRow . ',' . $page->listRows)->order('create_time DESC')->select();
        $list = [];
        $OrderLogic = new OrderLogic();
        foreach ($rebateLog as $k => $log) {
            $list[$k] = [
                'log_id' => $log['id'],
                'order_id' => $log['order_id'],
                'order_sn' => $log['order_sn'],
                'commission' => $log['money'],
                'status' => $log['sale_service'] == 1 ? '6' : $log['status'],
                'status_desc' => $log['sale_service'] == 1 ? '已售后' : rebate_status($log['status']),
                'create_time' => $log['create_time'],
                'goods_list' => [],
                'end_sale_tips' => ''
            ];
            if (in_array($log['status'], [0, 1, 2]) && $log['order_status'] == 2 && !empty($log['end_sale_time'])) {
                $list[$k]['end_sale_tips'] = '已确认收货，预计' . date('Y年m月d日', $log['end_sale_time']) . '到账';
            }
            foreach ($orderGoods as $goods) {
                if ($log['order_id'] == $goods['order_id']) {
                    $hasCommission = true;
                    if ($log['sale_service'] == 1) {
                        // 提成记录状态为 已售后
                        if (M('return_goods')->where(['rec_id' => $goods['rec_id'], 'status' => ['NOT IN', [-2, -1, 4, 6]]])->value('id')) {
                            $hasCommission = false;
                        }
                    }
                    $list[$k]['goods_list'][] = [
                        'goods_id' => $goods['goods_id'],
                        'goods_name' => $goods['goods_name'],
                        'spec_key_name' => $goods['spec_key_name'],
                        'original_img' => $goods['original_img'],
                        'original_img_new' => getFullPath($goods['original_img']),
                        'goods_num' => $goods['goods_num'],
                        'exchange_price' => $goods['member_goods_price'],
                        'exchange_integral' => $goods['use_integral'],
                        'commission' => $hasCommission ? $goods['goods_pv'] ? bcadd($OrderLogic->getRongMoney(bcdiv(bcmul(bcmul($goods['final_price'], $goods['goods_num'], 2), $goods['commission'], 2), 100, 2), $log['level'], $goods['add_time'], $goods['goods_id']), 0, 2) : '0.00' : '0.00',
                        'goods_pv' => $this->user['distribut_level'] >= 3 ? $hasCommission ? $goods['goods_pv'] > 0 ? $goods['goods_pv'] : '0.00' : '0.00' : '',
                        'is_freeze' => $hasCommission ? 0 : 1
                    ];
                }
            }
        }
        $return = [
            'date' => [
                'start_at' => $startAt,
                'end_at' => $endAt,
            ],
            'list' => $list
        ];
        return json(['status' => 1, 'result' => $return]);
    }
}