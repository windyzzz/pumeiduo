<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

use think\Model;

/**
 * 分类逻辑定义
 * Class CatsLogic.
 */
class RefundLogic extends Model
{
    //订单商品售后退款
    public function updateRefundGoods($rec_id, $refund_type = 0)
    {
        $order_goods = M('order_goods')->where(['rec_id' => $rec_id])->find();
        $return_goods = M('return_goods')->where(['rec_id' => $rec_id])->find();
        $updata = ['refund_type' => $refund_type, 'refund_time' => time(), 'status' => 5];
        //使用积分或者电子币抵扣部分原路退还
        if (($return_goods['refund_integral'] > 0 || $return_goods['refund_electronic'] > 0)) {
            accountLog($return_goods['user_id'], 0, $return_goods['refund_integral'], '用户申请商品退款', 0, $return_goods['order_id'], $return_goods['order_sn'], $return_goods['refund_electronic'], 19);
        }
        //在线支付金额退到余额去
        if (1 == $refund_type && $return_goods['refund_money'] > 0) {
            accountLog($return_goods['user_id'], $return_goods['refund_money'], 0, '用户申请商品退款', 0, $return_goods['order_id'], $return_goods['order_sn'], 0, 19);
        }
        M('return_goods')->where(['rec_id' => $rec_id])->save($updata); //更新退款申请状态
        M('order_goods')->where(['rec_id' => $rec_id])->save(['is_send' => 3]); //修改订单商品状态
//        if (1 == $return_goods['is_receive']) {
        //赠送积分追回
        if ($order_goods['give_integral'] > 0) {
            $user = get_user_info($return_goods['user_id']);
            $give_integral = $order_goods['give_integral'] * $order_goods['goods_num'];
            if ($give_integral < $user['pay_points']) {
                accountLog($return_goods['user_id'], 0, -$give_integral, '退货积分追回', 0, $return_goods['order_id'], $return_goods['order_sn'], 0, 19);
            }
        }
        //追回订单商品赠送的优惠券
        $coupon_info = M('coupon_list')->where(['uid' => $return_goods['user_id'], 'get_order_id' => $return_goods['order_id']])->find();
        if (!empty($coupon_info)) {
            if (1 == $coupon_info['status']) { //如果优惠券被使用,那么从退款里扣
                $coupon = M('coupon')->where(['id' => $coupon_info['cid']])->find();
                if ($return_goods['refund_money'] > $coupon['money']) {
                    //退款金额大于优惠券金额直接扣
                    $return_goods['refund_money'] = $return_goods['refund_money'] - $coupon['money']; //更新实际退款金额
                    M('return_goods')->where(['id' => $return_goods['id']])->save(['refund_money' => $return_goods['refund_money']]);
                } else {
                    //否则从退还余额里扣
                    $return_goods['refund_deposit'] = $return_goods['refund_deposit'] - $coupon['money']; //更新实际退还余额
                    M('return_goods')->where(['id' => $return_goods['id']])->save(['refund_deposit' => $return_goods['refund_deposit']]);
                }
            } else {
                M('coupon_list')->where(['id' => $coupon_info['id']])->delete();
                M('coupon')->where(['id' => $coupon_info['cid']])->setDec('send_num'); //优惠券追回
            }
        }
//        }
        //退还使用的优惠券
        $order_goods_count = M('order_goods')->where(['order_id' => $return_goods['order_id']])->sum('goods_num');
        $return_goods_count = M('return_goods')->where(['order_id' => $return_goods['order_id']])->sum('goods_num');
        if ($order_goods_count == $return_goods_count) {
            $coupon_list = M('coupon_list')->where(['uid' => $return_goods['user_id'], 'order_id' => $return_goods['order_id']])->find();
            if (!empty($coupon_list)) {
                $update_coupon_data = ['status' => 0, 'use_time' => 0, 'order_id' => 0];
                M('coupon_list')->where(['id' => $coupon_list['id'], 'status' => 1])->save($update_coupon_data); //符合条件的，优惠券就退给他
            }
        }
        $expense_data = ['money' => $return_goods['refund_money'] + $return_goods['refund_deposit'], 'log_type_id' => $rec_id, 'type' => 3, 'user_id' => $return_goods['user_id']];
        expenseLog($expense_data); //退款记录日志
    }

    /**
     * 取消订单退还余额，优惠券等.
     *
     * @param $order
     *
     * @return bool
     */
    public function updateRefundOrder($order, $type = 0)
    {
        //使用积分或者余额抵扣部分一一退还
        if ($order['user_money'] > 0 || $order['integral'] > 0) {
            $update_money_res = accountLog($order['user_id'], $order['user_money'], $order['integral'], '用户申请订单退款', 0, $order['order_id'], $order['order_sn'], 0, 19);
            if (!$update_money_res) {
                return false;
            }
        }
        //在线支付金额退到余额
        if ($order['order_amount'] > 0 && 1 == $type) {
            //改方法已经是退回余额, 不需要判断原路退回还是退还到余额
            accountLog($order['user_id'], $order['order_amount'], 0, '用户取消订单退款', 0, $order['order_id'], $order['order_sn'], 0, 10);
        }
        //符合条件的，该笔订单使用的优惠券就退还
        $coupon_list = M('coupon_list')->where(['uid' => $order['user_id'], 'order_id' => $order['order_id']])->find();
        if (!empty($coupon_list)) {
            $update_coupon_data = ['status' => 0, 'use_time' => 0, 'order_id' => 0];
            M('coupon_list')->where(['id' => $coupon_list['id'], 'status' => 1])->save($update_coupon_data);
        }
        M('order')->where(['order_id' => $order['order_id']])->save(['pay_status' => 3]); //更改订单状态
        $orderLogic = new \app\common\logic\OrderLogic();
        $orderLogic->alterReturnGoodsInventory($order); //取消订单后改变库存
        $expense_data = [
            'money' => $order['user_money'],
            'log_type_id' => $order['order_id'],
            'type' => 2,
            'user_id' => $order['user_id'],
        ];
        expenseLog($expense_data); //平台支出记录
        return true;
    }
}
