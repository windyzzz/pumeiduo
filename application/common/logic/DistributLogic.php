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
            ->field('goods_id, goods_num, final_price, pay_type')
            ->where('order_id', $order['order_id'])
            ->select();

        // 查看用户等级
        $userLevel = M('users')->where(['user_id' => $order['user_id']])->value('distribut_level');

        $distribut_total_money = 0;
        $is_vip = false;
        foreach ($order_goods as $ov) {
            $goods_info = M('goods')->field('zone, distribut_id, commission, integral_pv, retail_pv')->where(['goods_id' => $ov['goods_id']])->find();
            if (3 == $goods_info['zone'] && $goods_info['distribut_id'] > 0) {
                $is_vip = true;
                continue;
            }
            $hasGoodsPv = false;
            if ($userLevel >= 3) {
                switch ($ov['pay_type']) {
                    case 1:
                        // 现金+积分
                        if ($goods_info['integral_pv'] > 0) {
                            $hasGoodsPv = true;
                        }
                        break;
                    case 2:
                        // 现金
                        if ($goods_info['retail_pv'] > 0) {
                            $hasGoodsPv = true;
                        }
                        break;
                }
            }
            if (!$hasGoodsPv) {
                $distribut_total_money = bcadd($distribut_total_money, bcdiv(bcmul(bcmul($ov['final_price'], $ov['goods_num'], 2), $goods_info['commission'], 2), 100, 2), 2);
            }
        }

        $invite_uid = M('Users')->where('user_id', $order['user_id'])->getField('invite_uid');
        if ($invite_uid == 0) $invite_uid = M('Users')->where('user_id', $order['user_id'])->getField('first_leader');
        $OrderCommonLogic = new \app\common\logic\OrderLogic();
        if ($is_vip) {
            // vip商品
            $invite_uid_info = get_user_info($invite_uid, 0);
            $referee_money = 0;     // 奖励金额
            $referee_point = 0;     // 奖励积分
            $referee_vip_svip_money = 0;    // VIP直属SVIP奖励金额
            $referee_vip_svip_point = 0;    // VIP直属SVIP奖励积分
            switch ($invite_uid_info['distribut_level']) {
                case 1:
                    break;
                case 2:
                    // VIP推荐VIP奖励
                    $referee_money = tpCache('distribut.referee_vip_money');
                    $referee_point = tpCache('distribut.referee_vip_point');
                    // VIP的直接上级SVIP
                    $vipSvipInfo = M('users')->where(['user_id' => $invite_uid_info['first_leader']])->field('distribut_level')->find();
                    if (!empty($vipSvipInfo) && $vipSvipInfo['distribut_level'] >= 3) {
                        $referee_vip_svip_money = tpCache('distribut.referee_vip_svip_money');
                        $referee_vip_svip_point = tpCache('distribut.referee_vip_svip_point');
                    }
                    break;
                case 3:
                    // SVIP推荐VIP奖励
                    $referee_money = tpCache('distribut.referee_svip_money');
                    $referee_point = tpCache('distribut.referee_svip_point');
                    break;
            }
            if (($referee_money > 0 || $referee_point > 0) && $invite_uid_info['distribut_level'] >= 2) {
                $rebate_info['order_money'] = $referee_money;
                $data = [];
                $data['user_id'] = $invite_uid;
                $data['money'] = $referee_money;
                $data['point'] = $referee_point;
                $data['level'] = 1;
                $data = array_merge($rebate_info, $data);
                M('rebate_log')->add($data);
            }
            if (($referee_vip_svip_money > 0 || $referee_vip_svip_point > 0) && !empty($vipSvipInfo) && $vipSvipInfo['distribut_level'] >= 3) {
                $rebate_info['order_money'] = $referee_vip_svip_money;
                $data = [];
                $data['user_id'] = $invite_uid_info['first_leader'];
                $data['money'] = $referee_vip_svip_money;
                $data['point'] = $referee_vip_svip_point;
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
            $second_leader = $this->_getShopUid($invite_uid, 2, [$first_leader]);
            $third_leader = $this->_getShopUid($invite_uid, 2, [$first_leader, $second_leader]);
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
        if ($user_info['invite_uid'] != $uid) {
            $shop_id = $this->_getShopUid($user_info['invite_uid'], $level, $where);
        }

        return $shop_id;
    }

    // 订单收货确认后自动分成
    public function auto_confirm()
    {
        // 确认分成时间
        $confirm_time = time();
        $where = [
            'is_distribut' => 0, // 未分成
            'shipping_status' => 1,  //已发货
            'order_status' => ['IN', [2, 6]],   // 已收货 售后
            'pay_status' => 1, //已支付
            'end_sale_time' => ['ELT', $confirm_time],
        ];

        // 分成订单列表
        $orderList = M('Order')->field('order_id, order_sn')->where($where)->select();
        if ($orderList) {
            foreach ($orderList as $v) {
                // 查看订单商品是否正在申请售后（未处理完成）
                if (M('return_goods')->where(['order_id' => $v['order_id'], 'status' => ['IN', [0, 1]]])->value('id')) {
                    continue;
                }
                $this->confirmOrder($v['order_sn'], $v['order_id']);
            }
        }
    }

    // 订单收货确认后自动分成_测试
    public function auto_confirm_test()
    {
        //确认分成时间
//        $confirm_time = time() + 10 * 86400;
        // 确认分成时间
        $confirm_time = time();
        $where = [
            'is_distribut' => 0, // 未分成
            'shipping_status' => 1,  //已发货
            'order_status' => ['IN', [2, 6]],   // 已收货 售后
            'pay_status' => 1, //已支付
            'end_sale_time' => ['ELT', $confirm_time],
            'add_time' => ['GT', '1587052800']    // 测试服
        ];

        // 分成订单列表
        $orderList = M('Order')->field('order_id, order_sn')->where($where)->select();
        if ($orderList) {
            foreach ($orderList as $v) {
                // 查看订单商品是否正在申请售后（未处理完成）
                if (M('return_goods')->where(['order_id' => $v['order_id'], 'status' => ['IN', [0, 1]]])->value('id')) {
                    continue;
                }
                $this->confirmOrder($v['order_sn'], $v['order_id']);
            }
        }
    }

    public function confirmOrder($order_sn, $order_id)
    {
        $rebateList = M('RebateLog')->field('user_id,money,point')->where(array('order_sn' => $order_sn, 'status' => array('in', array(0, 1, 2))))->select();
        if ($rebateList) {
            foreach ($rebateList as $rk => $rv) {
                if ($rv['sale_service'] == 1) {
                    // 查看订单商品是否正在申请售后（未处理完成）
                    if (M('return_goods')->where(['order_id' => $order_id, 'status' => ['IN', [0, 1]]])->value('id')) {
                        continue;
                    }
                }
                accountLog($rv['user_id'], $rv['money'], $rv['point'], "订单：{$order_sn} 佣金分成", $rv['money'], $order_id, $order_sn, 0, 1);
            }
            M('RebateLog')->where(array('order_sn' => $order_sn, 'status' => array('in', array(0, 1, 2))))->update(['status' => 3, 'confirm_time' => time()]);
        }

        M('Order')->where('order_sn', $order_sn)->update(['is_distribut' => 1]);
    }
}
