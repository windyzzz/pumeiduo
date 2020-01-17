<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

/**
 * 分销类逻辑 BY J
 * Class DistributLogic.
 */
class DistributLogic
{
    public function rebateLog($order)
    {
        if ($order['order_id'] <= 0) {
            return false;
        }

        $rebate_info = [
            'buy_user_id' => $order['user_id'],
            'nickname' => $order['user_id'],
            'order_sn' => $order['order_sn'],
            'order_id' => $order['order_id'],
            'goods_price' => $order['goods_price'],
            'create_time' => time(),
            'status' => 0,
        ];

        // 计算用于分成的总金额
        $order_goods = M('OrderGoods')
            ->field('goods_id,goods_num,final_price')
            ->where('order_id', $order['order_id'])
            ->select();

        $distribut_total_money = 0;
        $is_vip = false;
        foreach ($order_goods as $ov) {
            $goods_info = M('goods')->field('zone,distribut_id,commission')->where(['goods_id' => $ov['goods_id']])->find();
            if (3 == $goods_info['zone'] && $goods_info['distribut_id'] > 0) {
                $is_vip = true;
            } else {
                $distribut_total_money = bcadd($distribut_total_money, bcdiv(bcmul(bcmul($ov['final_price'], $ov['goods_num'], 2), $goods_info['commission'], 2), 100, 2), 2);
            }
        }

        $invite_uid = M('Users')->where('user_id', $order['user_id'])->getField('invite_uid');
        $OrderCommonLogic = new \app\common\logic\OrderLogic();
        if ($is_vip) {
            //vip商品
            //$referee_vip_money = tpCache('distribut.referee_vip_money');

            $referee_vip_money = $OrderCommonLogic->getRongMoney(0, 1, $order['add_time'], 0, true);

            $referee_vip_point = tpCache('distribut.referee_vip_point');

            $invite_uid_info = get_user_info($invite_uid, 0);
            if (($referee_vip_money > 0 || $referee_vip_point > 0) && $invite_uid_info['distribut_level'] >= 2) { //直推人是VIP级别以上 奖励100元+xx积分
                $rebate_info['order_money'] = $referee_vip_money;
                $data = [];
                $data['user_id'] = $invite_uid;
                $data['money'] = $referee_vip_money;
                $data['point'] = $referee_vip_point;
                $data['level'] = 1;
                $data = array_merge($rebate_info, $data);
                M('rebate_log')->add($data);
            }

        } else {

            // $user_distributs = M('users')
            //     ->field('first_leader,second_leader,third_leader')
            //     ->where(array("user_id"=>$order['user_id']))
            //     ->find();

            $first_leader = $this->_getShopUid($invite_uid, 2);
            $second_leader = 0;//$second_leader = $this->_getShopUid($invite_uid, 2, [$first_leader]);//取消2代
            $third_leader = 0; //$third_leader = $this->_getShopUid($invite_uid, 2, [$first_leader, $second_leader]);//取消3代
            $shop_uid = 0;//$this->_getShopUid($invite_uid, 3) //取消店铺奖励


            if ($distribut_total_money > 0) {
                $rebate_info['order_money'] = $distribut_total_money;

                //普通分销提成
                if ($first_leader > 0) {
                    $data = [];
                    $data['user_id'] = $first_leader;
                    $data['money'] = $OrderCommonLogic->getRongMoney($distribut_total_money, 1, $order['add_time'], 0, false);

                    $data['level'] = 1;
                    $data = array_merge($rebate_info, $data);
                    M('rebate_log')->add($data);
                }

                if ($second_leader > 0) {
                    $data = [];
                    $data['user_id'] = $second_leader;
                    //$data['money'] = $distribut_total_money * tpCache('distribut.second_rate') / 100;
                    $data['money'] = $OrderCommonLogic->getRongMoney($distribut_total_money, 2, $order['add_time'], 0, false);

                    $data['level'] = 2;
                    $data = array_merge($rebate_info, $data);
                    M('rebate_log')->add($data);
                }

                if ($third_leader > 0) {
                    $data = [];
                    $data['user_id'] = $third_leader;
                    //$data['money'] = $distribut_total_money * tpCache('distribut.third_rate') / 100;
                    $data['money'] = $OrderCommonLogic->getRongMoney($distribut_total_money, 3, $order['add_time'], 0, false);

                    $data['level'] = 3;
                    $data = array_merge($rebate_info, $data);
                    M('rebate_log')->add($data);
                }

                //商铺分销提成

                // $shop_uid = $this->_getShopUid($invite_uid);
                if ($shop_uid > 0) {
                    $data = [];
                    $data['user_id'] = $shop_uid;
                    //$data['money'] = $distribut_total_money * tpCache('distribut.shop_rate') / 100;
                    $data['money'] = $OrderCommonLogic->getRongMoney($distribut_total_money, 0, $order['add_time'], 0, false);

                    $data['level'] = 0;
                    $data['type'] = 1;
                    $data = array_merge($rebate_info, $data);
                    M('rebate_log')->add($data);
                }
            }

        }

    }

    //获取最近的商店分销商ID
    private function _getShopUid($uid, $level, $where = '')
    {
        if ($uid < 1) {
            return 0;
        }

        $shop_id = 0;
        //等级id大于2为商铺代理
        $user_info = M('users')->field('distribut_level,user_id,invite_uid')
            ->where('user_id', $uid)
            ->find();
        $res = true;
        if ($where) {
            $res = !in_array($user_info['user_id'], $where);
        }

        if ($user_info['distribut_level'] >= $level && $res) {
            return $user_info['user_id'];
        }
        $shop_id = $this->_getShopUid($user_info['invite_uid'], $level, $where);

        return $shop_id;
    }

    //订单收货确认后自动分成
    public function auto_confirm()
    {
        //确认分成时间
        $confirm_time = time();

        $where0 = [
            'is_distribut' => 0, // 未分成
            'shipping_status' => 1,  //已发货
            'order_status' => 2,  //已收货
            'pay_status' => 1, //已支付
            // 'rg.type' => ['in',[0,1]], // 0仅退款 1退货退款
            'end_sale_time' => ['ELT', $confirm_time],
        ];

        // 分成订单列表
        $orderList = M('Order')
            ->field('o.order_sn, IFNULL(rg.id,0) as is_has_return, rg.type, o.order_id')
            // ->fetchSql(1)
            ->alias('o')
            ->join('__RETURN_GOODS__ rg', 'o.order_sn = rg.order_sn', 'LEFT')
            ->where($where0)
            ->having('is_has_return = 0')
            ->select();

        if ($orderList) {
            foreach ($orderList as $v) {
                $this->confirmOrder($v['order_sn'], $v['order_id']);
            }
        }
    }

    //订单收货确认后自动分成
    public function auto_confirm_ceshi()
    {
        //确认分成时间
        $confirm_time = time() + 10 * 86400;

        $where0 = [
            'is_distribut' => 0, // 未分成
            'shipping_status' => 1,  //已发货
            'order_status' => 2,  //已收货
            'pay_status' => 1, //已支付
            // 'rg.type' => ['in',[0,1]], // 0仅退款 1退货退款
            'end_sale_time' => ['ELT', $confirm_time],
        ];

        // 分成订单列表
        $orderList = M('Order')
            ->field('o.order_sn, IFNULL(rg.id,0) as is_has_return, rg.type, o.order_id')
            // ->fetchSql(1)
            ->alias('o')
            ->join('__RETURN_GOODS__ rg', 'o.order_sn = rg.order_sn', 'LEFT')
            ->where($where0)
            ->having('is_has_return = 0')
            ->select();

        if ($orderList) {
            foreach ($orderList as $v) {
                $this->confirmOrder($v['order_sn'], $v['order_id']);
            }
        }
    }

    public function confirmOrder($order_sn, $order_id)
    {
        $rebateList = M('RebateLog')->field('user_id,money,point')->where(array('order_sn' => $order_sn, 'status' => array('in', array(0, 1, 2))))->select();
        if ($rebateList) {
            foreach ($rebateList as $rk => $rv) {
                accountLog($rv['user_id'], $rv['money'], $rv['point'], "订单：{$order_sn} 佣金分成", $rv['money'], $order_id, $order_sn, 0, 1);
            }
            M('RebateLog')->where(array('order_sn' => $order_sn, 'status' => array('in', array(0, 1, 2))))->update(['status' => 3, 'confirm_time' => time()]);
        }

        M('Order')->where('order_sn', $order_sn)->update(['is_distribut' => 1]);
    }
}
