<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\admin\logic\RefundLogic;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\model\OrderGoods;
use app\common\util\TpshopException;
use think\AjaxPage;
use think\Db;
use think\Page;
use think\Url;

class Order extends Base
{
    public $order_status;
    public $pay_status;
    public $shipping_status;

    /*
     * 初始化操作
     */
    public function _initialize()
    {
        parent::_initialize();
        C('TOKEN_ON', false); // 关闭表单令牌验证
        $this->order_status = C('ORDER_STATUS');
        $this->pay_status = C('PAY_STATUS');
        $this->shipping_status = C('SHIPPING_STATUS');
        // 订单 支付 发货状态
        $this->assign('order_status', $this->order_status);
        $this->assign('pay_status', $this->pay_status);
        $this->assign('shipping_status', $this->shipping_status);
    }

    /*
     *订单首页
     */
    public function index()
    {
        return $this->fetch();
    }

    /*
     *Ajax首页
     */
    public function ajaxindex()
    {
        $orderLogic = new OrderLogic();
        $begin = $this->begin;
        $end = $this->end;
        if (I('pay_start_time')) {
            $pay_begin = I('pay_start_time');
            $pay_end = I('pay_end_time');
        }

        $pay_begin = strtotime($pay_begin);
        $pay_end = strtotime($pay_end);

        // 搜索条件
        $condition = [];
        $keyType = I('keytype');
        $keywords = I('keywords', '', 'trim');
        $user_id = I('user_id', '', 'trim');

        $consignee = ($keyType && 'consignee' == $keyType) ? $keywords : I('consignee', '', 'trim');
        $consignee ? $condition['consignee'] = trim($consignee) : false;

        if ($begin && $end) {
            $condition['add_time'] = ['between', "$begin,$end"];
        }

        if ($pay_begin && $pay_end) {
            $condition['pay_time'] = ['between', "$pay_begin,$pay_end"];
        }

        // $condition['prom_type'] = array('lt',5);
        if ('' != $user_id) {
            $condition['user_id'] = ['eq', $user_id];
        }

        $order_sn = ($keyType && 'order_sn' == $keyType) ? $keywords : I('order_sn');
        $order_sn ? $condition['order_sn'] = ['like', '%' . trim($order_sn) . '%'] : false;

        '' != I('order_status') ? $condition['order_status'] = I('order_status') : false;
        '' != I('pay_status') ? $condition['pay_status'] = I('pay_status') : false;
        '' != I('pay_code') ? $condition['pay_code'] = I('pay_code') : false;
        '' != I('shipping_status') ? $condition['shipping_status'] = I('shipping_status') : false;
        '' != I('prom_type') ? $condition['prom_type'] = I('prom_type') : false;
        I('user_id') ? $condition['user_id'] = trim(I('user_id')) : false;
        $sort_order = I('order_by', 'DESC') . ' ' . I('sort');

        $count = M('order')->where($condition)->count();
        $Page = new AjaxPage($count, 20);
        $show = $Page->show();
        //获取订单列表
        $orderList = $orderLogic->getOrderList($condition, $sort_order, $Page->firstRow, $Page->listRows);

        foreach ($orderList as $k => $v) {
            if (2 == $v['prom_type']) {
                $group_details = M('group_detail')->where('group_id', $v['prom_id'])->select();
                foreach ($group_details as $gk => $gv) {
                    $order_sn = explode(',', $gv['order_sn_list']);
                    if (in_array($v['order_sn'], $order_sn)) {
                        $orderList[$k]['group_buy'] = $gv;
                        $orderList[$k]['group_buy']['status'] = C('GROUP_STATUS')[$gv['status']];
                    }
                }
            }
            switch ($v['source']) {
                case 1:
                    $orderList[$k]['source'] = '微信';
                    break;
                case 2:
                    $orderList[$k]['source'] = 'PC';
                    break;
                case 3:
                    $orderList[$k]['source'] = 'APP';
                    break;
                case 4:
                    $orderList[$k]['source'] = '管理后台';
                    break;
            }
        }

        $field = 'sum(order_amount) as order_amount,sum(goods_price) as goods_price,sum(coupon_price) as coupon_price,
        sum(integral_money) as integral_money,sum(user_electronic) as user_electronic, sum(order_prom_amount) as order_prom_amount, 
        sum(shipping_price) as shipping_price, sum(order_pv) as order_pv';
        $all = M('order')->where($condition)->field($field)->find();
        $this->assign('all', $all);
        $this->assign('orderList', $orderList);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    //虚拟订单
    public function virtual_list()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    // 虚拟订单
    public function virtual_info()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    public function virtual_cancel()
    {
        $order_id = I('order_id/d');
        if (IS_POST) {
            $admin_note = I('admin_note');
            $order = M('order')->where(['order_id' => $order_id])->find();
            if ($order) {
                $r = M('order')->where(['order_id' => $order_id])->save(['order_status' => 3, 'admin_note' => $admin_note]);
                if ($r) {
                    $orderLogic = new OrderLogic();
                    $orderLogic->orderActionLog($order_id, $admin_note, '取消订单');
                    $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
                } else {
                    $this->ajaxReturn(['status' => -1, 'msg' => '操作失败']);
                }
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => '订单不存在']);
            }
        }
        $order = M('order')->where(['order_id' => $order_id])->find();
        $this->assign('order', $order);

        return $this->fetch();
    }

    public function verify_code()
    {
        if (IS_POST) {
            $vr_code = trim(I('vr_code'));
            if (!preg_match('/^[a-zA-Z0-9]{15,18}$/', $vr_code)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '兑换码格式错误，请重新输入']);
            }
            $vr_code_info = M('vr_order_code')->where(['vr_code' => $vr_code])->find();
            $order = M('order')->where(['order_id' => $vr_code_info['order_id']])->field('order_status,order_sn,user_note')->find();
            if ($order['order_status'] > 1) {
                $this->ajaxReturn(['status' => 0, 'msg' => '兑换码对应订单状态不符合要求']);
            }
            if (empty($vr_code_info)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '该兑换码不存在']);
            }
            if ('1' == $vr_code_info['vr_state']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '该兑换码已被使用']);
            }
            if ($vr_code_info['vr_indate'] < time()) {
                $this->ajaxReturn(['status' => 0, 'msg' => '该兑换码已过期，使用截止日期为： ' . date('Y-m-d H:i:s', $vr_code_info['vr_indate'])]);
            }
            if ($vr_code_info['refund_lock'] > 0) {//退款锁定状态:0为正常,1为锁定(待审核),2为同意
                $this->ajaxReturn(['status' => 0, 'msg' => '该兑换码已申请退款，不能使用']);
            }
            $update['vr_state'] = 1;
            $update['vr_usetime'] = time();
            M('vr_order_code')->where(['vr_code' => $vr_code])->save($update);
            //检查订单是否完成
            $condition = [];
            $condition['vr_state'] = 0;
            $condition['refund_lock'] = ['in', [0, 1]];
            $condition['order_id'] = $vr_code_info['order_id'];
            $condition['vr_indate'] = ['gt', time()];
            $vr_order = M('vr_order_code')->where($condition)->select();
            if (empty($vr_order)) {
                $data['order_status'] = 2;  //此处不能直接为4，不然前台不能评论
                $data['confirm_time'] = time();
                M('order')->where(['order_id' => $vr_code_info['order_id']])->save($data);
                M('order_goods')->where(['order_id' => $vr_code_info['order_id']])->save(['is_send' => 1]);  //把订单状态改为已收货
            }
            $order_goods = M('order_goods')->where(['order_id' => $vr_code_info['order_id']])->find();
            if ($order_goods) {
                $result = [
                    'vr_code' => $vr_code,
                    'order_goods' => $order_goods,
                    'order' => $order,
                    'goods_image' => goods_thum_images($order_goods['goods_id'], 240, 240),
                ];
                $this->ajaxReturn(['status' => 1, 'msg' => '兑换成功', 'result' => $result]);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '虚拟订单商品不存在']);
            }
        }

        return $this->fetch();
    }

    //虚拟订单临时支付方法，以后要删除
    public function generateVirtualCode()
    {
        $order_id = I('order_id/d');
        // 获取操作表
        $order = M('order')->where(['order_id' => $order_id])->find();
        update_pay_status($order['order_sn'], ['admin_id' => session('admin_id'), 'note' => '订单付款成功']);
        $vr_order_code = Db::name('vr_order_code')->where('order_id', $order_id)->find();
        if (!empty($vr_order_code)) {
            $this->success('成功生成兑换码', U('Order/virtual_info', ['order_id' => $order_id]), 1);
        } else {
            $this->error('生成兑换码失败', U('Order/virtual_info', ['order_id' => $order_id]), 1);
        }
    }

    /*
     * ajax 发货订单列表
    */
    public function ajaxdelivery()
    {
        $condition = [];
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        '' != I('order_sn') ? $condition['order_sn'] = trim(I('order_sn')) : false;
        $shipping_status = I('shipping_status');
        $condition['shipping_status'] = empty($shipping_status) ? ['neq', 1] : $shipping_status;
        $condition['order_status'] = ['in', '1,2,4'];
        $condition['prom_type'] = ['neq', 5];
        $count = M('order')->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        //搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            if (!is_array($val)) {
                $Page->parameter[$key] = urlencode($val);
            }
        }
        $show = $Page->show();
        $orderList = M('order')->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->order('add_time DESC')->select();
        $this->assign('orderList', $orderList);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    // 退款订单列表 （未发货，已支付，订单状态：已取消 的订单）
    public function refund_order_list()
    {
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        '' != I('order_sn') ? $condition['order_sn'] = trim(I('order_sn')) : false;
        '' != I('mobile') ? $condition['mobile'] = trim(I('mobile')) : false;
        $prom_type = input('prom_type');
        if ($prom_type) {
            $condition['prom_type'] = $prom_type;
        }
        $condition['shipping_status'] = 0;
        $condition['order_status'] = 3;
        $condition['pay_status'] = ['gt', 0];
        $count = M('order')->where($condition)->count();
        $Page = new Page($count, 10);
        //搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            if (!is_array($val)) {
                $Page->parameter[$key] = urlencode($val);
            }
        }
        $show = $Page->show();
        $orderList = M('order')->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->order('add_time DESC')->select();
        $this->assign('orderList', $orderList);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    public function refund_order_info($order_id)
    {
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->append(['full_address', 'orderGoods'])->toArray();
        $this->assign('order', $order);

        return $this->fetch();
    }

    //取消订单退款原路退回
    public function refund_order()
    {
        $data = I('post.');
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $data['order_id']]);
        $order = $orderObj->append(['full_address'])->toArray();
        if (!$order) {
            $this->error('订单不存在或参数错误');
        }
        if (3 == $data['pay_status']) {
            $refundLogic = new RefundLogic();
            if (1 == $data['refund_type']) {
                //取消订单退款退余额
                if ($refundLogic->updateRefundOrder($order, 1)) {
                    $this->success('成功退款到账户余额');
                } else {
                    $this->error('退款失败');
                }
            }
            if (0 == $data['refund_type']) {
                //取消订单支付原路退回
                if ('weixin' == $order['pay_code'] || 'alipay' == $order['pay_code'] || 'alipayMobile' == $order['pay_code']) {
                    $this->error('该订单支付方式不支持在线退回2.0');
                } else {
                    $this->error('该订单支付方式不支持在线退回');
                }
            }
        } else {
            M('order')->where(['order_id' => $order['order_id']])->save($data);
            $this->success('拒绝退款操作成功');
        }
    }

    /**
     * 订单详情.
     *
     * @param $order_id
     *
     * @return mixed
     */
    public function detail($order_id)
    {
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->append(['full_address', 'orderGoods', 'adminOrderButton'])->toArray();

        $orderGoods = $order['orderGoods'];
        $express = Db::name('delivery_doc')->where('order_id', $order_id)->select();  //发货信息（可能多个）
        $user = Db::name('users')->where(['user_id' => $order['user_id']])->find();
        $this->assign('order', $order);
        $this->assign('user', $user);
        $split = count($orderGoods) > 1 ? 1 : 0;
        $buyGoodsNum = 1;   // 要支付购买的商品种类数
        foreach ($orderGoods as $val) {
            if ($val['is_gift'] == 0 && !in_array($val['prom_type'], [8, 9])) {
                $buyGoodsNum += 1;
            }
        }
        foreach ($orderGoods as $key => $val) {
            if ($val['goods_num'] > 1) {
                $split = 1;
            }
            if ($val['is_gift'] == 1 && $val['prom_type'] == 0) {
                $orderGoods[$key]['final_goods_price'] = 0.00;
                $orderGoods[$key]['prom_value'] = '无';
            } else {
                // 优惠促销
                switch ($val['prom_type']) {
                    case 1:
                        // 秒杀
                        $orderGoods[$key]['final_goods_price'] = $val['member_goods_price'];
                        $orderGoods[$key]['prom_value'] = '秒杀';
                        break;
                    case 2:
                        // 团购
                        $orderGoods[$key]['final_goods_price'] = $val['member_goods_price'];
                        $orderGoods[$key]['prom_value'] = '秒杀';
                        break;
                    case 3:
                        // 优惠促销
                        $prom = M('prom_goods')->where(['id' => $val['prom_id']])->field('type, expression')->find();
                        switch ($prom['type']) {
                            case 0:
                            case 4:
                                // 打折
                                $orderGoods[$key]['final_goods_price'] = bcdiv(bcmul($val['member_goods_price'], $prom['expression'], 2), 100, 2);
                                $orderGoods[$key]['prom_value'] = '折扣优惠';
                                break;
                            case 1:
                            case 5:
                                $expression = bcdiv($prom['expression'], $buyGoodsNum, 2);
                                // 减价
                                $orderGoods[$key]['final_goods_price'] = bcsub($val['member_goods_price'], bcdiv($expression, $val['goods_num'], 2), 2);
                                $orderGoods[$key]['prom_value'] = '减价优惠';
                                break;
                            case 2:
                                // 固定金额
                                $orderGoods[$key]['final_goods_price'] = $val['member_goods_price'];
                                $orderGoods[$key]['prom_value'] = '固定金额';
                                break;
                        }
                        break;
                    case 7:
                        // 订单优惠促销
                        $discountPrice = M('order_prom')->where(['id' => $val['prom_id']])->value('discount_price');
                        $expression = bcdiv($discountPrice, $buyGoodsNum, 2);
                        $orderGoods[$key]['final_goods_price'] = bcsub($val['member_goods_price'], bcdiv($expression, $val['goods_num'], 2), 2);
                        $orderGoods[$key]['prom_value'] = '订单优惠促销';
                        break;
                    default:
                        $orderGoods[$key]['final_goods_price'] = $val['member_goods_price'];
                        $orderGoods[$key]['prom_value'] = '无';
                }
            }
        }
        $this->assign('split', $split);
        $this->assign('express', $express);

        return $this->fetch();
    }

    /**
     * 获取订单操作记录.
     */
    public function getOrderAction()
    {
        $order_id = input('order_id/d', 0);
        $order_id <= 0 && $this->ajaxReturn(['status' => 1, 'msg' => '参数错误！！']);
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->toArray();
        // 获取操作记录
        $action_log = Db::name('order_action')->where(['order_id' => $order_id])->order('log_time desc')->select();
        $admins = M('admin')->getField('admin_id , user_name', true);
        $user = M('users')->field('user_id,nickname')->where(['user_id' => $order['user_id']])->find();
        //查找用户昵称
        foreach ($action_log as $k => $v) {
            if (0 == $v['action_user']) {
                $action_log["$k"]['action_user_name'] = '用户:' . $user['nickname'];
            } else {
                $action_log["$k"]['action_user_name'] = '管理员:' . $admins[$v['action_user']];
            }
            $action_log["$k"]['log_time'] = date('Y-m-d H:i:s', $v['log_time']);
            $action_log["$k"]['order_status'] = $this->order_status[$v['order_status']];
            $action_log["$k"]['pay_status'] = $this->pay_status[$v['pay_status']];
            $action_log["$k"]['shipping_status'] = $this->shipping_status[$v['shipping_status']];
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '参数错误！！', 'data' => $action_log]);
    }

    /*
     * 拆分订单
     */
    public function split_order()
    {
        $order_id = I('order_id');
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->append(['full_address', 'orderGoods'])->toArray();
        if (0 != $order['shipping_status']) {
            $this->error('已发货订单不允许编辑');
            exit;
        }
        $orderGoods = $order['orderGoods'];
        if (IS_POST) {
            //################################先处理原单剩余商品和原订单信息
            $old_goods = I('old_goods/a');
            $oldArr = [];

            foreach ($orderGoods as $val) {
                if (empty($old_goods[$val['rec_id']])) {
                    M('order_goods')->where('rec_id=' . $val['rec_id'])->delete(); //删除商品
                } else {
                    //修改商品数量
                    if ($old_goods[$val['rec_id']] != $val['goods_num']) {
                        $val['goods_num'] = $old_goods[$val['rec_id']];
                        M('order_goods')->where('rec_id=' . $val['rec_id'])->save(['goods_num' => $val['goods_num']]);
                    }
                    $oldArr[] = $val; //剩余商品
                }
                $all_goods[$val['rec_id']] = $val; //所有商品信息
            }
            $oldPay = new Pay();
            try {
                $oldPay->setUserId($order['user_id']);
                $oldPay->payOrder($oldArr);
                $oldPay->delivery($order['district']);
//                $oldPay->orderPromotion();
            } catch (TpshopException $t) {
                $error = $t->getErrorArr();
                $this->error($error['msg']);
            }
            //修改订单费用
            $res['goods_price'] = $oldPay->getGoodsPrice(); // 商品总价
            $res['order_amount'] = $oldPay->getOrderAmount(); // 应付金额
            $res['total_amount'] = $oldPay->getTotalAmount(); // 订单总价
//    		M('order')->where("order_id=".$order_id)->save($res);
            //################################原单处理结束

            //################################新单处理
            for ($i = 1; $i < 20; ++$i) {
                $temp = $this->request->param($i . '_old_goods/a');
                if (!empty($temp)) {
                    $split_goods[] = $temp;
                }
            }

            foreach ($split_goods as $key => $vrr) {
                foreach ($vrr as $k => $v) {
                    $all_goods[$k]['goods_num'] = $v;
                    $brr[$key][] = $all_goods[$k];
                }
            }

            foreach ($brr as $goods) {
                $newPay = new Pay();
                try {
                    $newPay->setUserId($order['user_id']);
                    $newPay->payGoodsList($goods);
                    $newPay->delivery($order['district']);
//                    $newPay->orderPromotion();
                } catch (TpshopException $t) {
                    $error = $t->getErrorArr();
                    $this->error($error['msg']);
                }
                $new_order = $order;
                $new_order['order_sn'] = date('YmdHis') . mt_rand(1000, 9999);
                $new_order['parent_sn'] = $order['order_sn'];
                //修改订单费用
                $new_order['goods_price'] = $newPay->getGoodsPrice(); // 商品总价
                $new_order['order_amount'] = $newPay->getOrderAmount(); // 应付金额
                $new_order['total_amount'] = $newPay->getTotalAmount(); // 订单总价
                $new_order['add_time'] = time();
                unset($new_order['order_id']);
                $new_order_id = DB::name('order')->insertGetId($new_order); //插入订单表
                foreach ($goods as $vv) {
                    $vv['order_id'] = $new_order_id;
                    unset($vv['rec_id']);
                    $nid = M('order_goods')->add($vv); //插入订单商品表
                }
            }
            //################################新单处理结束
            $this->success('操作成功', U('Admin/Order/detail', ['order_id' => $order_id]));
            exit;
        }

        foreach ($orderGoods as $val) {
            $brr[$val['rec_id']] = ['goods_num' => $val['goods_num'], 'goods_name' => getSubstr($val['goods_name'], 0, 35) . $val['spec_key_name']];
        }
        $this->assign('order', $order);
        $this->assign('goods_num_arr', json_encode($brr));
        $this->assign('orderGoods', $orderGoods);

        return $this->fetch();
    }

    /*
     * 价钱修改
     */
    public function editprice($order_id)
    {
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->toArray();
        $this->editable($order);
        if (IS_POST) {
            $admin_id = session('admin_id');
            if (empty($admin_id)) {
                $this->error('非法操作');
                exit;
            }
            $update['discount'] = I('post.discount');
            $update['shipping_price'] = I('post.shipping_price');
            $update['order_amount'] = $order['order_amount'] + $update['shipping_price'] - $update['discount'] - $order['user_money'] - $order['integral_money'] - $order['coupon_price'] - $order['shipping_price'];
            $row = M('order')->where(['order_id' => $order_id])->save($update);
            if (!$row) {
                $this->success('没有更新数据', U('Admin/Order/editprice', ['order_id' => $order_id]));
            } else {
                $this->success('操作成功', U('Admin/Order/detail', ['order_id' => $order_id]));
            }
            exit;
        }
        $this->assign('order', $order);

        return $this->fetch();
    }

    /**
     * 订单删除.
     *
     * @param int $id 订单id
     */
    public function delete_order()
    {
        $order_id = I('post.order_id/d', 0);
        $orderLogic = new OrderLogic();
        $del = $orderLogic->delOrder($order_id);
        $this->ajaxReturn($del);
    }

    /**
     * 订单取消付款.
     *
     * @param $order_id
     *
     * @return mixed
     */
    public function pay_cancel($order_id)
    {
        if (I('remark')) {
            $data = I('post.');
            $note = ['退款到用户余额', '已通过其他方式退款', '不处理，误操作项'];
            if (0 == $data['refundType'] && $data['amount'] > 0) {
                accountLog($data['user_id'], $data['amount'], 0, '退款到用户余额', 0, 0, '', 0, 19);
            }
            $orderLogic = new OrderLogic();
            $orderLogic->orderProcessHandle($data['order_id'], 'pay_cancel');
            $d = $orderLogic->orderActionLog($data['order_id'], '支付取消', $data['remark'] . ':' . $note[$data['refundType']]);
            if ($d) {
                exit('<script>window.parent.pay_callback(1);</script>');
            }
            exit('<script>window.parent.pay_callback(0);</script>');
        }
        $order = M('order')->where("order_id=$order_id")->find();
        $this->assign('order', $order);

        return $this->fetch();
    }

    /**
     * 订单打印.
     *
     * @param string $id
     *
     * @return mixed
     */
    public function order_print($id = '')
    {
        if ($id) {
            $order_id = $id;
        } else {
            $order_id = I('order_id');
        }
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->append(['full_address', 'orderGoods', 'orderUser'])->toArray();
        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        $order['full_address'] = $order['province'] . ' ' . $order['city'] . ' ' . $order['district'] . ' ' . $order['address'];
        switch ($order['source']) {
            case 1:
                $order['source_desc'] = '微信';
                break;
            case 2:
                $order['source_desc'] = 'PC';
                break;
            case 3:
                $order['source_desc'] = 'APP';
                break;
            case 4:
                $order['source_desc'] = '管理后台';
                break;
        }
        if ($id) {
            return $order;
        }
        $shop = tpCache('shop_info');
        $this->assign('order', $order);
        $this->assign('shop', $shop);
        $template = I('template', 'picking');

        return $this->fetch($template);
    }

    /*
     * 批量打印发货单
     */
    public function delivery_print()
    {
        $ids = input('print_ids');
        $order_ids = trim($ids, ',');
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel->whereIn('order_id', $order_ids)->select();
        if ($orderObj) {
            $order = collection($orderObj)->append(['orderGoods', 'full_address'])->toArray();
        } else {
            $order = [];
        }
        $total_amount = 0;

        foreach ($order as $k => $v) {
            switch ($v['source']) {
                case 1:
                    $order[$k]['source_desc'] = '微信';
                    break;
                case 2:
                    $order[$k]['source_desc'] = 'PC';
                    break;
                case 3:
                    $order[$k]['source_desc'] = 'APP';
                    break;
                case 4:
                    $order[$k]['source_desc'] = '管理后台';
                    break;
            }
//            $order_length = count($v['orderGoods']);
            foreach ($v['orderGoods'] as $gk => $gv) {
                $goods_info = M('Goods')
                    ->alias('g')->field('s.suppliers_name,g.trade_type')
                    ->join('__SUPPLIERS__ s', 'g.suppliers_id = s.suppliers_id', 'LEFT')
                    ->where('g.goods_id', $gv['goods_id'])
                    ->find();
                $order[$k]['orderGoods'][$gk]['suppliers'] = $goods_info['suppliers_name'];
                $order[$k]['orderGoods'][$gk]['trade_type'] = trade_type($goods_info['trade_type']);

                // 超值套组 拆分
                if (2 == $gv['sale_type']) {
                    // $order_goods = array();
                    $g_list = M('GoodsSeries')
                        ->field('gs.*,g.goods_name,g.shop_price,g.original_img,g.trade_type,g.goods_sn,sg.key_name as spec_key_name')
                        ->alias('gs')
                        ->join('__GOODS__ g', 'g.goods_id = gs.g_id')
                        ->join('__SPEC_GOODS_PRICE__ sg', 'sg.item_id = gs.item_id', 'left')
                        ->where('gs.goods_id', $gv['goods_id'])
                        ->select();
                    if ($g_list) {
                        foreach ($g_list as $key => $value) {
                            // $j = $gk + $order_length + 1;
                            $data = [
                                'goods_name' => $value['goods_name'],
                                'shop_price' => $value['shop_price'],
                                'final_price' => $value['shop_price'],
                                'original_img' => $value['original_img'],
                                'goods_num' => $value['g_number'] * $gv['goods_num'],
                                'goods_sn' => $value['goods_sn'],
                                'spec_key_name' => $value['spec_key_name'],
                                'trade_type' => $order[$k]['orderGoods'][$gk]['trade_type'],
                                'suppliers' => $order[$k]['orderGoods'][$gk]['suppliers'],
                                'is_send' => $order[$k]['orderGoods'][$gk]['is_send'],
                            ];
                            $order[$k]['orderGoods'][] = $data;
                            $total_amount += $data['goods_num'] * $data['final_price'];
                        }
                        unset($order[$k]['orderGoods'][$gk]);
                    }
                    $total_amount += $order[$k]['orderGoods'][$gk]['goods_num'] * $order[$k]['orderGoods'][$gk]['final_price'];
                }
            }
        }

        $shop = tpCache('shop_info');
        $this->assign('order', $order);
        $this->assign('total_amount', $total_amount);
        $this->assign('shop', $shop);
        $template = I('template', 'print');

        return $this->fetch($template);
    }

    /**
     * 快递单打印.
     */
    public function shipping_print($id = '')
    {
        if ($id) {
            $order_id = $id;
        } else {
            $order_id = I('get.order_id');
        }
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->append(['full_address'])->toArray();
        //查询是否存在订单及物流
        $shipping = Db::name('shipping')->where('shipping_code', $order['shipping_code'])->find();
        if (!$shipping) {
            $this->error('快递公司不存在');
        }
        if (empty($shipping['template_html'])) {
            $this->error('请设置' . $shipping['shipping_name'] . '打印模板');
        }
        $shop = tpCache('shop_info'); //获取网站信息
        $shop['province'] = empty($shop['province']) ? '' : getRegionName($shop['province']);
        $shop['city'] = empty($shop['city']) ? '' : getRegionName($shop['city']);
        $shop['district'] = empty($shop['district']) ? '' : getRegionName($shop['district']);

        $order['province'] = getRegionName($order['province']);
        $order['city'] = getRegionName($order['city']);
        $order['district'] = getRegionName($order['district']);
        $template_var = ['发货点-名称', '发货点-联系人', '发货点-电话', '发货点-省份', '发货点-城市',
            '发货点-区县', '发货点-手机', '发货点-详细地址', '收件人-姓名', '收件人-手机', '收件人-电话',
            '收件人-省份', '收件人-城市', '收件人-区县', '收件人-邮编', '收件人-详细地址', '时间-年', '时间-月',
            '时间-日', '时间-当前日期', '订单-订单号', '订单-备注', '订单-配送费用',];
        $content_var = [$shop['store_name'], $shop['contact'], $shop['phone'], $shop['province'], $shop['city'],
            $shop['district'], $shop['phone'], $shop['address'], $order['consignee'], $order['mobile'], $order['phone'],
            $order['province'], $order['city'], $order['district'], $order['zipcode'], $order['address'], date('Y'), date('M'),
            date('d'), date('Y-m-d'), $order['order_sn'], $order['admin_note'], $order['shipping_price'],
        ];
        $shipping['template_html_replace'] = str_replace($template_var, $content_var, $shipping['template_html']);
        if ($id) {
            return $shipping;
        }
        $shippings[0] = $shipping;
        $this->assign('shipping', $shippings);

        return $this->fetch('print_express');
    }

    /*
     * 批量打印快递单
     */
    public function shipping_print_batch()
    {
        $ids = I('post.ids3');
        $ids = substr($ids, 0, -1);
        $ids = explode(',', $ids);
        if (!is_numeric($ids[0])) {
            unset($ids[0]);
        }

        $shippings = [];
        foreach ($ids as $k => $v) {
            $shippings[$k] = $this->shipping_print($v);
        }
        $this->assign('shipping', $shippings);

        return $this->fetch('print_express');
    }

    /**
     * 生成发货单.
     */
    public function deliveryHandle()
    {
        $data = I('post.');
//        $order_id = $data['order_id'];
//        $order = M('order')->field('add_time')->where(array('order_id' => $order_id))->find();
//        if ($order['add_time'] > order_time()) {
//            $this->error('新订单不可以发货', U('Admin/Order/delivery_info', ['order_id' => $data['order_id']]));
//        }

        $orderLogic = new OrderLogic();
        $res = $orderLogic->deliveryHandle($data);
        if (1 == $res['status']) {
            if (2 == $data['send_type'] && !empty($res['printhtml'])) {
                $this->assign('printhtml', $res['printhtml']);
                return $this->fetch('print_online');
            }
            $this->success('操作成功', U('Admin/Order/delivery_info', ['order_id' => $data['order_id']]));
        } else {
            $this->error($res['msg'], U('Admin/Order/delivery_info', ['order_id' => $data['order_id']]));
        }
    }

    public function delivery_info($id = '')
    {
        if ($id) {
            $order_id = $id;
        } else {
            $order_id = I('order_id');
        }
        $orderGoodsModel = new OrderGoods();
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel->where(['order_id' => $order_id])->find();
        $order = $orderObj->append(['full_address'])->toArray();
        $orderGoods = $orderGoodsModel::all(['order_id' => $order_id, 'is_send' => ['lt', 2]]);
        if ($id) {
            if (!$orderGoods) {
                $this->error('所选订单有商品已完成退货或换货'); // 已经完成售后的不能再发货
            }
        } else {
            if (!$orderGoods) {
                $this->error('此订单商品已完成退货或换货'); // 已经完成售后的不能再发货
            }
        }
        if ($id) {
            $order['orderGoods'] = $orderGoods;
            $order['goods_num'] = count($orderGoods);
            return $order;
        }
        // 物流信息
        $delivery_record = M('delivery_doc d')->join('__ADMIN__ a', 'a.admin_id = d.admin_id')->where('d.order_id=' . $order_id)->select();
        if ($delivery_record) {
            $order['invoice_no'] = $delivery_record[count($delivery_record) - 1]['invoice_no'];
        }
        // 处理订单商品独自的物流信息
        foreach ($orderGoods as $key => $goods) {
            $orderGoods[$key]['invoice_no'] = '';
            $orderGoods[$key]['note'] = '';
            foreach ($delivery_record as $record) {
                if ($goods['rec_id'] == $record['rec_id']) {
                    $orderGoods[$key]['invoice_no'] = $record['invoice_no'];
                    $orderGoods[$key]['note'] = $record['note'];
                }
            }
        }
        // 是否显示发货按钮
        $is_show_de = 1;
        if ($order['add_time'] > order_time()) {
//            $is_show_de = 0;
        }
        $this->assign('is_show_de', $is_show_de);
        $this->assign('order', $order);
        $this->assign('orderGoods', $orderGoods);
        $this->assign('delivery_record', $delivery_record); // 发货记录
        $shipping_list = Db::name('shipping')->field('shipping_name,shipping_code')->where('')->select();
        $this->assign('shipping_list', $shipping_list);
        $express_switch = tpCache('express.express_switch');
        $this->assign('express_switch', $express_switch);

        return $this->fetch();
    }

    /**
     * 发货单列表.
     */
    public function delivery_list()
    {
        return $this->fetch();
    }

    /*
    *批量发货
    */
    public function delivery_batch()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /*
    *批量发货处理
    */
    public function delivery_batch_handle()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 删除某个退换货申请.
     */
    public function return_del()
    {
        $id = I('get.id');
        M('return_goods')->where("id = $id")->delete();
        $this->success('成功删除!');
    }

    /**
     * 退换货操作.
     */
    public function return_info()
    {
        $return_id = I('id');
        $return_goods = M('return_goods')->where("id= $return_id")->find();
        !$return_goods && $this->error('非法操作!');
        $user = M('users')->where(['user_id' => $return_goods['user_id']])->find();
        $order = M('order')->where(['order_id' => $return_goods['order_id']])->find();
        $order['goods'] = M('order_goods')->where(['rec_id' => $return_goods['rec_id']])->find();
        $return_goods['delivery'] = unserialize($return_goods['delivery']);  //订单的物流信息，服务类型为换货会显示
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if ($return_goods['imgs']) {
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        }

        Url::root('/');
        if ($return_goods['imgs']) {
            foreach ($return_goods['imgs'] as $k => $v) {
                $return_goods['imgs'][$k] = url('/', '', '', true) . $v;
            }
        }

        $this->assign('id', $return_id); // 退换货记录ID
        $this->assign('user', $user); // 用户
        $this->assign('return_goods', $return_goods); // 退换货
        $this->assign('order', $order); //退货订单信息
        $this->assign('return_type', C('RETURN_TYPE')); //退货订单信息
        $this->assign('refund_status', C('REFUND_STATUS'));

        return $this->fetch();
    }

    /**
     *修改退货状态
     */
    public function checkReturniInfo()
    {
        $orderLogic = new OrderLogic();
        $post_data = I('post.');
        if (!$post_data['id']) {
            $this->ajaxReturn(['status' => -1, 'msg' => '非法操作!']);
        }
        $return_goods = Db::name('return_goods')->where(['id' => $post_data['id']])->find();
        !$return_goods && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作!']);
        $order_goods = M('order_goods')->where(['rec_id' => $return_goods['rec_id']])->find();
        $type_msg = C('RETURN_TYPE');
        $status_msg = C('REFUND_STATUS');
        switch ($post_data['status']) {
            case 1:
                $post_data['checktime'] = time();
                break;
            case 3:
                $post_data['receivetime'] = time();
                break;  //卖家收货时间
            default:
        }

        if ($return_goods['type'] > 0 && 4 == $post_data['status']) {
            // 更新分成记录状态
            $other_return = M('return_goods')->where(['order_id' => $return_goods['order_id'], 'rec_id' => ['neq', $return_goods['rec_id']], 'status' => 0])->find();
            if (!$other_return) {
                M('rebate_log')->where('order_sn', $return_goods['order_sn'])->update(['status' => 2]);
            }
            if ($order_goods['goods_pv'] == 0) {
                // 换货成功直接解冻分成
                $rebate_list = M('rebate_log')->where('order_sn', $return_goods['order_sn'])->select();
                if ($rebate_list) {
                    $OrderLogic = new \app\common\logic\OrderLogic();
                    foreach ($rebate_list as $rk => $rv) {
                        $money = $OrderLogic->getDecMoney($rv['order_id'], $rv['level']);
                        $dec_money = $money[$return_goods['rec_id']]['money'];
                        $dec_point = $money[$return_goods['rec_id']]['point'];
                        M('rebate_log')->where('id', $rv['id'])->update([
                            'money' => ['exp', "money + {$dec_money}"],
                            'point' => ['exp', "point + {$dec_point}"],
                            'freeze_money' => ['exp', "freeze_money - {$dec_money}"],
                        ]);
                    }
                }
            }
            $post_data['seller_delivery']['express_time'] = date('Y-m-d H:i:s');
            $post_data['change_time'] = time();
            $post_data['seller_delivery'] = serialize($post_data['seller_delivery']); //换货的物流信息
            Db::name('order_goods')->where(['rec_id' => $return_goods['rec_id']])->save(['is_send' => 2]);

            $rebate_info = M('rebate_log')->where('order_sn', $return_goods['order_sn'])->find();
            if (3 == $rebate_info['status'] || 5 == $rebate_info['status']) {
                accountLog($return_goods['user_id'], $dec_money, 0, '换货成功', 0, $return_goods['order_id'], $return_goods['order_sn'], 0);
            }
        }
        $note = "退换货:{$type_msg[$return_goods['type']]}, 状态:{$status_msg[$post_data['status']]},处理备注：{$post_data['remark']}";
        // 更新退换货记录
        M('return_goods')->where(['id' => $post_data['id']])->save($post_data);

        if (1 == $post_data['status']) {
            //审核通过才更改订单商品状态，进行退货，退款时要改对应商品修改库存
            $order = \app\common\model\Order::get($return_goods['order_id']);
//            $commonOrderLogic = new \app\common\logic\OrderLogic();
//            $commonOrderLogic->alterReturnGoodsInventory($order, $return_goods['rec_id']); //审核通过，恢复原来库存
            if ($return_goods['type'] < 2) {
                $orderLogic->disposereRurnOrderCoupon($return_goods); // 是退货可能要处理优惠券
//                $order_info = $order->toArray();
            }
        } elseif (-1 == $post_data['status']) {
            // 更新分成记录状态
            $other_return = M('return_goods')->where(['order_id' => $return_goods['order_id'], 'rec_id' => ['neq', $return_goods['rec_id']], 'status' => 0])->find();
            if (!$other_return) {
                M('rebate_log')->where('order_sn', $return_goods['order_sn'])->update(['status' => 2]);
            }
            if ($order_goods['goods_pv'] == 0) {
                // 解冻分成
                $rebate_list = M('rebate_log')->where('order_sn', $return_goods['order_sn'])->select();
                if ($rebate_list) {
                    $OrderLogic = new \app\common\logic\OrderLogic();
                    foreach ($rebate_list as $rk => $rv) {
                        $money = $OrderLogic->getDecMoney($rv['order_id'], $rv['level']);
                        $dec_money = $money[$return_goods['rec_id']]['money'];
                        $dec_point = $money[$return_goods['rec_id']]['point'];
                        M('rebate_log')->where('id', $rv['id'])->update([
                            'money' => ['exp', "money + {$dec_money}"],
                            'point' => ['exp', "point + {$dec_point}"],
                            'freeze_money' => ['exp', "freeze_money - {$dec_money}"],
                        ]);
                    }
                }
            }
        }
        $orderLogic->orderActionLog($return_goods['order_id'], '退换货', $note);

        //仅退款-审核通过 和 退货退款确认收货
        if (($post_data['status'] == 1 && $return_goods['type'] == 0) || ($post_data['status'] == 3 && $return_goods['type'] == 1)) {
            //通知 退货补回库存
            include_once "plugins/Tb.php";
            $TbLogic = new \Tb();
            $TbLogic->add_tb(3, 9, $return_goods['id'], 0);
        }

        // 更新订单pv
        if (in_array($return_goods['type'], [0, 1]) && !in_array($post_data['status'], [-2, -1, 0])) {
            if (!isset($order)) {
                $order = \app\common\model\Order::get($return_goods['order_id']);
            }
            if ($order['order_pv'] > 0) {
                $goods = M('goods g')->join('order_goods og', 'og.goods_id = g.goods_id')->where(['og.rec_id' => $return_goods['rec_id']])->field('integral_pv, retail_pv')->find();
                $orderAmountRate = bcdiv(bcadd($order['order_amount'], $order['user_electronic'], 2), $order['total_amount'], 2); // 订单实际支付金额比率
                $goods_pv = 0;
                switch ($order_goods['pay_type']) {
                    case 1:
                        $goods_pv = bcmul(bcmul($goods['integral_pv'], $order_goods['goods_num'], 2), $orderAmountRate, 2);
                        break;
                    case 2:
                        $goods_pv = bcmul(bcmul($goods['retail_pv'], $order_goods['goods_num'], 2), $orderAmountRate, 2);
                        break;
                }
                if ($goods_pv > 0) {
                    $order_pv = bcsub($order['order_pv'], $goods_pv, 2);
                    M('order')->where(['order_id' => $order['order_id']])->update([
                        'order_pv' => $order_pv,
                        'pv_tb' => 0,
                        'pv_send' => 0
                    ]);
                }
            }
        }

        $this->ajaxReturn(['status' => 1, 'msg' => '修改成功', 'url' => '']);
    }

    //售后退款原路退回
    public function refund_back()
    {
        $return_id = I('id');
        $return_goods = M('return_goods')->where("id= $return_id")->find();
        if ($return_goods['type'] == 1 && $return_goods['status'] != 3) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请先确认收货', 'url' => '']);
            exit;
        }
        if ($_SERVER['SERVER_ADDR'] != '61.238.101.138') {
            $this->ajaxReturn(['status' => 0, 'msg' => '只能在正式服退款', 'url' => '']);
            exit;
        }

        $data = I('post.');
        if ($data) {
            M('return_goods')->where('id', $return_id)->update(['refund_money' => $data['refund_money'], 'refund_electronic' => $data['refund_electronic'], 'refund_integral' => $data['refund_integral']]);
        }
        $return_goods = M('return_goods')->where("id= $return_id")->find();
        $rec_goods = M('order_goods')->where(['order_id' => $return_goods['order_id'], 'goods_id' => $return_goods['goods_id']])->find();
        $order = M('order')->where(['order_id' => $rec_goods['order_id']])->find();
        $orderLogic = new OrderLogic();
        $return_goods['refund_money'] = $orderLogic->getRefundGoodsMoney($return_goods);

        if ('weixinJsApi' == $order['pay_code'] || 'weixin' == $order['pay_code'] || 'alipay' == $order['pay_code'] || 'alipayMobile' == $order['pay_code']) {
            if ('weixinJsApi' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/weixinJsApi/weixinJsApi.class.php';
                $payment_obj = new \weixinJsApi();
                $result = $payment_obj->refund1($order, $data['refund_money']);
                if ('SUCCESS' == $result['return_code'] && 'SUCCESS' == $result['result_code']) {
                    $refundLogic = new RefundLogic();
                    $refundLogic->updateRefundGoods($return_goods['rec_id']); //订单商品售后退款
                    // $this->success('退款成功');
                    $this->ajaxReturn(['status' => 1, 'msg' => '退款成功', 'url' => '']);
                } else {
                    $this->ajaxReturn(['status' => 0, 'msg' => $result['return_msg'], 'url' => '']);
                }
            } elseif ('alipay' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/alipay/alipay.class.php';
                $payment_obj = new \alipay();
                $result = $payment_obj->refund1($order, $data['refund_money'], '售后订单退款');
                if ('10000' == $result->code) {
                    $refundLogic = new RefundLogic();
                    $refundLogic->updateRefundGoods($return_goods['rec_id']); //订单商品售后退款
                    // $this->success('退款成功');
                    $this->ajaxReturn(['status' => 1, 'msg' => '退款成功', 'url' => '']);
                } else {
                    $this->ajaxReturn(['status' => 0, 'msg' => $result['return_msg'], 'url' => '']);
                }
            } elseif ('weixin' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/weixin/weixin.class.php';
                $payment_obj = new \weixin();
                // $data = array('transaction_id'=>$order['transaction_id'],'total_fee'=>$order['order_amount'],'refund_fee'=>$return_goods['refund_money']);
                //          $result = $payment_obj->payment_refund($data);
                $result = $payment_obj->refund1($order, $data['refund_money']);

                if ('SUCCESS' == $result['return_code'] && 'SUCCESS' == $result['result_code']) {
                    $refundLogic = new RefundLogic();
                    $refundLogic->updateRefundGoods($return_goods['rec_id']); //订单商品售后退款
                    // $this->success('退款成功');
                    $this->ajaxReturn(['status' => 1, 'msg' => '退款成功', 'url' => '']);
                } else {
                    $this->ajaxReturn(['status' => 0, 'msg' => $result['return_msg'], 'url' => '']);
                }
            } elseif ('alipayMobile' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/alipayMobile/alipayMobile.class.php';
                $payment_obj = new \alipayMobile();
                $result = $payment_obj->refund1($order, $data['refund_money'], '售后订单退款');
                $msg = $result->msg;
                if ('10000' == $result->code && 'Success' == $msg) {
                    $refundLogic = new RefundLogic();
                    $refundLogic->updateRefundGoods($return_goods['rec_id']); //订单商品售后退款
                    $this->ajaxReturn(['status' => 1, 'msg' => '退款成功', 'url' => '']);
                } else {
                    $this->ajaxReturn(['status' => 0, 'msg' => $msg, 'url' => '']);
                }
            } elseif ('alipayApp' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/alipayApp/alipayApp.class.php';
                $payment_obj = new \alipayApp();
                $result = $payment_obj->refund($order, $data['refund_money'], '售后订单退款');
                $msg = $result->sub_msg;
                if ('10000' == $result->code) {
                    $refundLogic = new RefundLogic();
                    $refundLogic->updateRefundGoods($return_goods['rec_id']); //订单商品售后退款
                    $this->ajaxReturn(['status' => 1, 'msg' => '退款成功', 'url' => '']);
                } else {
                    $this->ajaxReturn(['status' => 0, 'msg' => $msg, 'url' => '']);
                }
            } elseif ('weixinApp' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/weixinApp/weixinApp.class.php';
                $payment_obj = new \weixinApp();
                $result = $payment_obj->refund($order, $data['refund_money']);
                if ($result['return_code'] == 'FAIL') {
                    $this->ajaxReturn(['status' => 0, 'msg' => $result['return_msg'], 'url' => '']);
                } elseif ($result['result_code'] == 'FAIL') {
                    $this->ajaxReturn(['status' => 0, 'msg' => $result['err_code_des'], 'url' => '']);
                } else {
                    $refundLogic = new RefundLogic();
                    $refundLogic->updateRefundGoods($return_goods['rec_id']); //订单商品售后退款
                    $this->ajaxReturn(['status' => 1, 'msg' => '退款成功', 'url' => '']);
                }
            }
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '该订单支付方式不支持在线退回', 'url' => '']);
            // $this->error('该订单支付方式不支持在线退回');
        }
    }

    /**
     * 退货，余额+积分支付
     * 有用三方金额支付的不走这个方法.
     */
    public function refund_balance()
    {
        $data = I('POST.');
        // $data = I('post.');
        if ($data) {
            M('return_goods')->where('rec_id', $data['rec_id'])->update(['refund_money' => $data['refund_money'], 'refund_electronic' => $data['refund_electronic'], 'refund_integral' => $data['refund_integral']]);
        }
        $return_goods = M('return_goods')->where(['rec_id' => $data['rec_id']])->find();
        if (5 == $return_goods['status']) {
            $this->ajaxReturn(['status' => -1, 'msg' => '操作失败，你已经退款了', 'url' => U('Admin/Order/return_list')]);
        }
        if (empty($return_goods)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数有误']);
        }
        $orderLogic = new RefundLogic();

        // M('return_goods')->where('rec_id', $data['rec_id'])->save($data);
        $orderLogic->updateRefundGoods($data['rec_id'], 1); //售后商品退款
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/Order/return_list')]);
    }

    /**
     * 管理员生成申请退货单.
     */
    public function add_return_goods()
    {
        $order_id = I('order_id');
        $goods_id = I('goods_id');

        $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id")->find();
        if (!empty($return_goods)) {
            $this->error('已经提交过退货申请!', U('Admin/Order/return_list'));
            exit;
        }
        $order = M('order')->where("order_id = $order_id")->find();

        $data['order_id'] = $order_id;
        $data['order_sn'] = $order['order_sn'];
        $data['goods_id'] = $goods_id;
        $data['addtime'] = time();
        $data['user_id'] = $order[user_id];
        $data['remark'] = '管理员申请退换货'; // 问题描述
        M('return_goods')->add($data);
        $this->success('申请成功,现在去处理退货', U('Admin/Order/return_list'));
        exit;
    }

    /**
     * 订单操作.
     *
     * @param $id
     */
    public function order_action()
    {
        $orderLogic = new OrderLogic();
        $action = I('get.type');
        $order_id = I('get.order_id');
        if ($action && $order_id) {
            if ('pay' !== $action) {
                $convert_action = C('CONVERT_ACTION')["$action"];
                $res = $orderLogic->orderActionLog($order_id, $convert_action, I('note'));
            }
            $a = $orderLogic->orderProcessHandle($order_id, $action, ['note' => I('note'), 'admin_id' => 0]);
            if (false !== $res && false !== $a) {
                if ('remove' == $action) {
                    $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Order/index')]);
                }
                $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Order/detail', ['order_id' => $order_id])]);
            } else {
                if ('remove' == $action) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'url' => U('Order/index')]);
                }
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'url' => U('Order/index')]);
            }
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'url' => U('Order/index')]);
        }
    }

    public function order_log()
    {
        $order_sn = I('order_sn');
        $user_id = I('user_id');
        $condition = [];
        $begin = $this->begin;
        $end = $this->end;
        $condition['log_time'] = ['between', "$begin,$end"];
        if ($order_sn) {   //搜索订单号
            $order_id_arr = Db::name('order')->where(['order_sn' => $order_sn])->getField('order_id', true);
            $order_ids = implode(',', $order_id_arr);
            $condition['order_id'] = ['in', $order_ids];
            $this->assign('order_sn', $order_sn);
        }
        if ($user_id) {
            $condition['o.user_id'] = $user_id;
            $this->assign('user_id', $user_id);
        }
        $admin_id = I('admin_id');
        if ($admin_id > 0) {
            $condition['action_user'] = $admin_id;
        }
        $count = M('order_action')
            ->alias('oa')
            ->join('__ORDER__ o', 'o.order_id = oa.order_id')
            ->join('__USERS__ u', 'o.user_id = u.user_id')
            ->where($condition)->count();
        $Page = new Page($count, 20);

        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($begin . '_' . $end);
        }
        $show = $Page->show();
        $list = M('order_action')
            ->alias('oa')
            ->join('__ORDER__ o', 'o.order_id = oa.order_id')
            ->join('__USERS__ u', 'o.user_id = u.user_id')
            ->where($condition)
            ->order('action_id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $orderIds = [];
        foreach ($list as $k => $log) {
            $list[$k]['user_id'] = M('users')->alias('u')->join('__ORDER__ o', 'o.user_id = u.user_id')->where('o.order_id', $log['order_id'])->getField('u.user_id');
            if (!$log['action_user']) {
                $orderIds[] = $log['order_id'];
            }
        }
        if ($orderIds) {
            $users = M('users')->alias('u')->join('__ORDER__ o', 'o.user_id = u.user_id')->getField('o.order_id,u.nickname');
        }
        $this->assign('users', $users);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        $this->assign('page', $show);
        $admin = M('admin')->getField('admin_id,user_name');
        $this->assign('admin', $admin);

        return $this->fetch();
    }

    /**
     * 检测订单是否可以编辑.
     *
     * @param $order
     */
    private function editable($order)
    {
        if (0 != $order['shipping_status']) {
            $this->error('已发货订单不允许编辑');
            exit;
        }
    }

    public function export_delivery_order()
    {
        $condition = [];
        I('consignee') ? $condition['consignee'] = trim(I('consignee')) : false;
        '' != I('order_sn') ? $condition['order_sn'] = trim(I('order_sn')) : false;
        $shipping_status = I('shipping_status');
        $condition['shipping_status'] = empty($shipping_status) ? ['neq', 1] : $shipping_status;
        $condition['order_status'] = ['in', '1,2,4'];
        $condition['prom_type'] = ['neq', 5];
        $order_ids = I('order_ids');
        if ($order_ids) {
            $condition['order_id'] = ['in', $order_ids];
        }

        $count = M('order')->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        //搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            if (!is_array($val)) {
                $Page->parameter[$key] = urlencode($val);
            }
        }

        $orderList = M('order')->where($condition)->order('add_time DESC')->select();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:180px;">用户订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">收件方收件公司</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">联系人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">联系电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="300px">收件详细地址</td>';

        $strTable .= '</tr>';
        if (is_array($orderList)) {
            $region = get_region_list();
            foreach ($orderList as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">&nbsp</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['consignee'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">&nbsp</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . "{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}" . ' </td>';

                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($orderList);
        downloadExcel($strTable, 'order');
        exit();
    }

    /**
     * 订单数据导出xls
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function export_order()
    {
        $condition = [];
        // 下单时间
        $begin = $this->begin;
        $end = $this->end;
        if ($begin && $end) {
            $condition['o.add_time'] = ['between', "$begin,$end"];
        }
        // 支付时间
        if (I('pay_start_time')) {
            $pay_begin = I('pay_start_time');
            $pay_end = I('pay_end_time');
        }
        $pay_begin = strtotime($pay_begin);
        $pay_end = strtotime($pay_end) + 86399;
        if ($pay_begin && $pay_end) {
            $condition['o.pay_time'] = ['between', "$pay_begin,$pay_end"];
        }
        // 关键字
        $keyType = I('keytype');
        $keywords = I('keywords', '', 'trim');
        $user_id = I('user_id', '', 'trim');
        $consignee = ($keyType && 'consignee' == $keyType) ? $keywords : I('consignee', '', 'trim');
        $consignee ? $condition['o.consignee'] = trim($consignee) : false;
        // $condition['prom_type'] = array('lt',5);
        if ('' != $user_id) {
            $condition['o.user_id'] = ['eq', $user_id];
        }
        $order_sn = ($keyType && 'order_sn' == $keyType) ? $keywords : I('order_sn');
        $order_sn ? $condition['o.order_sn'] = trim($order_sn) : false;

        '' != I('order_status') ? $condition['o.order_status'] = I('order_status') : false;
        '' != I('pay_status') ? $condition['o.pay_status'] = I('pay_status') : false;
        '' != I('pay_code') ? $condition['o.pay_code'] = I('pay_code') : false;
        '' != I('shipping_status') ? $condition['o.shipping_status'] = I('shipping_status') : false;
        '' != I('prom_type') ? $condition['o.prom_type'] = I('prom_type') : false;

        // 排序
        $sort_order = I('order_by', 'DESC') . ' ' . I('sort');

        // 订单数据
        $orderList = Db::name('order o')->join('users u', 'u.user_id = o.user_id', 'LEFT')
            ->field("o.*, FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as add_time, u.distribut_level")
            ->where($condition)->order($sort_order)->select();
        // 等级列表
        $level_list = M('distribut_level')->getField('level_id, level_name');
        // 用户父级ID
        $parent_list = Db::name('users')->getField('user_id, first_leader', true);
        // 订单来源
        $orderSource = ['1' => '微信', '2' => 'PC', '3' => 'APP', '4' => '管理后台'];

        // 表头
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="120">下单日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="120">支付日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">父级ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货人</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">收货地址</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">电话</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">应付金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">优惠券折扣</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分折扣</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">现金折扣</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">运费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">总pv值</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付方式</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">发货状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单来源</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品总数</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品规格</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">交易条件</td>';
        $strTable .= '</tr>';
        // 表体数据
        if (is_array($orderList)) {
            $region = get_region_list();
            foreach ($orderList as $k => $val) {
                $orderGoods = D('order_goods')->where('order_id=' . $val['order_id'])->select();
                $orderGoodsNum = count($orderGoods);
                $val['pay_time_show'] = $val['pay_time'] ? date('Y-m-d H:i:s', $val['pay_time']) : '';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;" rowspan="' . $orderGoodsNum . '">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['create_time'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['pay_time_show'] . ' </td>';
                $parentId = $parent_list[$val['user_id']];
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $parentId . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['user_id'] . '</td>';
                $level = $val['distribut_level'] ? $level_list[$val['distribut_level']] : '普通会员';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $level . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['consignee'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . "{$region[$val['province']]},{$region[$val['city']]},{$region[$val['district']]},{$region[$val['twon']]}{$val['address']}" . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['mobile'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['order_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['goods_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['coupon_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['integral_money'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['user_electronic'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['shipping_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['order_pv'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . C('ORDER_STATUS')[$val['order_status']] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['pay_name'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $this->pay_status[$val['pay_status']] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $this->shipping_status[$val['shipping_status']] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $orderSource[$val['source']] . '</td>';
                // $strGoods = '';
                $goods_num = 0;
                foreach ($orderGoods as $goods) {
                    $goods_num = $goods_num + $goods['goods_num'];
                    // $strGoods .= "商品编号：".$goods['goods_sn']. " 商品购买数量：".$goods['goods_num']." 商品名称：".$goods['goods_name'];
                    // if ($goods['spec_key_name'] != '') $strGoods .= " 规格：".$goods['spec_key_name'];
                    // $strGoods .= "<br />";
                    // $strTradeType = $goods['trade_type'] == 1 ? '仓库自发'  : '一件代发';
                }
                $strTable .= '<td style="text-align:left;font-size:12px;"    rowspan="' . $orderGoodsNum . '">' . $goods_num . ' </td>';

                $strTable .= '<td style="text-align:left;font-size:12px;">' . $orderGoods[0]['goods_sn'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $orderGoods[0]['goods_num'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $orderGoods[0]['goods_name'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $orderGoods[0]['spec_key_name'] . ' </td>';
                $strTradeType = 1 == $orderGoods[0]['trade_type'] ? '仓库自发' : '一件代发';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $strTradeType . ' </td>';
                $strTable .= '</tr>';
                unset($orderGoods[0]);

                if ($orderGoods) {
                    foreach ($orderGoods as $goods) {
                        $strTable .= '<tr>';
                        $strTable .= '<td style="text-align:left;font-size:12px;">' . $goods['goods_sn'] . ' </td>';
                        $strTable .= '<td style="text-align:left;font-size:12px;">' . $goods['goods_num'] . ' </td>';
                        $strTable .= '<td style="text-align:left;font-size:12px;">' . $goods['goods_name'] . ' </td>';
                        $strTable .= '<td style="text-align:left;font-size:12px;">' . $goods['spec_key_name'] . ' </td>';
                        $strTradeType = 1 == $goods['trade_type'] ? '仓库自发' : '一件代发';
                        $strTable .= '<td style="text-align:left;font-size:12px;">' . $strTradeType . ' </td>';
                        $strTable .= '</tr>';
                    }
                }
//                $strTable .= '<td style="text-align:left;font-size:12px;">' . $goods_num . ' </td>';
//                foreach ($orderGoods as $goods) {
//                    $goods_num = $goods_num + $goods['goods_num'];
//                }
//                unset($orderGoods);
//
//                $strTable .= '<td style="text-align:left;font-size:12px;">' . $strGoods . ' </td>';
//                $strTable .= '<td style="text-align:left;font-size:12px;">' . $strTradeType . ' </td>';
//                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($orderList);
        downloadExcel($strTable, 'order');
        exit();
    }

    /**
     * 订单数据导出csv
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function export_order_v2()
    {
        $condition = [];
        // 下单时间
        $begin = $this->begin;
        $end = $this->end;
        if ($begin && $end) {
            $condition['o.add_time'] = ['between', "$begin,$end"];
        }
        // 支付时间
        if (I('pay_start_time')) {
            $pay_begin = I('pay_start_time');
            $pay_end = I('pay_end_time');
        }
        $pay_begin = strtotime($pay_begin);
        $pay_end = strtotime($pay_end) + 86399;
        if ($pay_begin && $pay_end) {
            $condition['o.pay_time'] = ['between', "$pay_begin,$pay_end"];
        }
        // 关键字
        $keyType = I('keytype');
        $keywords = I('keywords', '', 'trim');
        $user_id = I('user_id', '', 'trim');
        $consignee = ($keyType && 'consignee' == $keyType) ? $keywords : I('consignee', '', 'trim');
        $consignee ? $condition['o.consignee'] = trim($consignee) : false;
        // $condition['prom_type'] = array('lt',5);
        if ('' != $user_id) {
            $condition['o.user_id'] = ['eq', $user_id];
        }
        $order_sn = ($keyType && 'order_sn' == $keyType) ? $keywords : I('order_sn');
        $order_sn ? $condition['o.order_sn'] = trim($order_sn) : false;

        '' != I('order_status') ? $condition['o.order_status'] = I('order_status') : false;
        '' != I('pay_status') ? $condition['o.pay_status'] = I('pay_status') : false;
        '' != I('pay_code') ? $condition['o.pay_code'] = I('pay_code') : false;
        '' != I('shipping_status') ? $condition['o.shipping_status'] = I('shipping_status') : false;
        '' != I('prom_type') ? $condition['o.prom_type'] = I('prom_type') : false;

        // 排序
        $sort_order = I('order_by', 'DESC') . ' ' . I('sort');

        // 订单数据
        $orderList = Db::name('order o')->join('users u', 'u.user_id = o.user_id', 'LEFT')
            ->field("o.*, FROM_UNIXTIME(o.add_time,'%Y-%m-%d %H:%i:%s') as add_time, u.distribut_level")
            ->where($condition)->order($sort_order)->select();
        // 等级列表
        $level_list = M('distribut_level')->getField('level_id, level_name');
        // 用户父级ID
        $parent_list = Db::name('users')->getField('user_id, first_leader', true);
        // 订单来源
        $orderSource = ['1' => '微信', '2' => 'PC', '3' => 'APP', '4' => '管理后台'];
        // 地区数据
        $region = get_region_list();

        // 表头
        $headList = [
            '订单编号', '下单日期', '支付日期', '父级ID', '会员ID', '会员等级', '收货人', '收货地址', '电话',
            '应付金额', '商品金额', '优惠券折扣', '积分折扣', '现金折扣', '运费', '总pv值',
            '订单状态', '支付状态', '发货状态', '订单来源', '支付方式',
            '商品总数', '商品编号', '商品数量', '商品名称', '商品规格', '交易条件'
        ];
        // 表数据
        $dataList = [];
        foreach ($orderList as $key => $order) {
            $payTime = $order['pay_time'] ? date('Y-m-d H:i:s', $order['pay_time']) : '';
            $parentId = $parent_list[$order['user_id']];
            $dataList[$key] = [
                "\t" . $order['order_sn'],
                $order['add_time'],
                $payTime,
                $parentId,
                $order['user_id'],
                $order['distribut_level'] ? $level_list[$order['distribut_level']] : '普通会员',
                $order['consignee'],
                "{$region[$order['province']]},{$region[$order['city']]},{$region[$order['district']]},{$region[$order['twon']]}{$order['address']}",
                "\t" . $order['mobile'],
                $order['order_amount'],
                $order['goods_price'],
                $order['coupon_price'],
                $order['integral_money'],
                $order['user_electronic'],
                $order['shipping_price'],
                $order['order_pv'],
                C('ORDER_STATUS')[$order['order_status']],
                $this->pay_status[$order['pay_status']],
                $this->shipping_status[$order['shipping_status']],
                $orderSource[$order['source']],
                $order['pay_name'],
            ];
            // 订单商品
            $orderGoods = D('order_goods')->where('order_id=' . $order['order_id'])->select();
            $goods_amount = 0;
            $goods_sn = [];
            $goods_num = [];
            $goods_name = [];
            $goods_attr = [];
            $goods_trade = [];
            foreach ($orderGoods as $goods) {
                $goods_amount += $goods['goods_num'];
                $goods_sn[] = $goods['goods_sn'];
                $goods_num[] = $goods['goods_num'];
                $goods_name[] = $goods['goods_name'];
                $goods_attr[] = $goods['spec_key_name'];
                $goods_trade[] = $goods['trade_type'] == 1 ? '仓库自发' : '一件代发';
            }
            $dataList[$key][] = $goods_amount;
            $dataList[$key][] = $goods_sn;
            $dataList[$key][] = $goods_num;
            $dataList[$key][] = $goods_name;
            $dataList[$key][] = $goods_attr;
            $dataList[$key][] = $goods_trade;
        }
        toCsvExcel($dataList, $headList, 'order');
    }

    /**
     * 退货单列表.
     */
    public function return_list()
    {
        return $this->fetch();
    }

    /*
     * ajax 退货订单列表
     */
    public function ajax_return_list()
    {
        // 搜索条件
        $order_sn = trim(I('order_sn'));
        $user_id = trim(I('user_id'));
        $order_by = I('order_by') ? I('order_by') : 'addtime';
        $sort_order = I('sort_order') ? I('sort_order') : 'desc';
        $status = I('status');
        $where = [];
        if ($order_sn) {
            $where['order_sn'] = ['like', '%' . $order_sn . '%'];
        }
        if ($user_id) {
            $where['user_id'] = $user_id;
        }
        if ('' != $status) {
            $where['status'] = $status;
        }
        $count = M('return_goods')->where($where)->count();
        $Page = new AjaxPage($count, 13);
        $show = $Page->show();
        $list = M('return_goods')->where($where)->order("$order_by $sort_order")->limit("{$Page->firstRow},{$Page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goods_list = M('goods')->where('goods_id in (' . implode(',', $goods_id_arr) . ')')->getField('goods_id,goods_name');
        }
        $state = C('REFUND_STATUS');
        $return_type = C('RETURN_TYPE');
        $this->assign('state', $state);
        $this->assign('return_type', $return_type);
        $this->assign('goods_list', $goods_list);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        $this->assign('page', $show); // 赋值分页输出
        return $this->fetch();
    }

    /**
     * 添加订单.
     */
    public function add_order()
    {
        //  获取省份
        $province = M('region2')->where(['parent_id' => 0, 'level' => 1])->select();
        $this->assign('province', $province);

        return $this->fetch();
    }

    /**
     * 提交添加订单.
     */
    public function addOrder()
    {
        $user_id = I('user_id'); // 用户id 可以为空
        $admin_note = I('admin_note'); // 管理员备注
        //收货信息
        $user = Db::name('users')->where(['user_id' => $user_id])->find();
        $address['consignee'] = I('consignee'); // 收货人
        $address['province'] = I('province'); // 省份
        $address['city'] = I('city'); // 城市
        $address['district'] = I('district'); // 县
        $address['address'] = I('address'); // 收货地址
        $address['mobile'] = I('mobile'); // 手机
        $address['zipcode'] = I('zipcode'); // 邮编
        $address['email'] = $user['email']; // 邮编
        $invoice_title = I('invoice_title'); // 发票抬头
        $taxpayer = I('taxpayer'); // 纳税人识别号

        $goods_id_arr = I('goods_id/a');
        $orderLogic = new OrderLogic();
        $order_goods = $orderLogic->get_spec_goods($goods_id_arr);
        $pay = new Pay();
        try {
            $pay->setUserId($user_id);
            $pay->payGoodsList($order_goods);
            $pay->delivery($address['district']);
//            $pay->orderPromotion();
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->error($error['msg']);
        }
        $placeOrder = new PlaceOrder($pay);
        $placeOrder->setUserAddress($address);
        $placeOrder->setInvoiceTitle($invoice_title);
        $placeOrder->setTaxpayer($taxpayer);
        $placeOrder->addNormalOrder(4);
        $order = $placeOrder->getOrder();
        if ($order) {
            M('order_action')->add([
                'order_id' => $order['order_id'],
                'action_user' => session('admin_id'),
                'order_status' => 0,  //待支付
                'shipping_status' => 0, //待确认
                'action_note' => $admin_note,
                'status_desc' => '提交订单',
                'log_time' => time(),
            ]);
            $this->success('添加商品成功', U('Admin/Order/detail', ['order_id' => $order['order_id']]));
        } else {
            $this->error('添加失败');
        }
    }

    /**
     * 订单编辑.
     *
     * @return mixed
     */
    public function edit_order()
    {
        $order_id = I('order_id');
        $orderLogic = new OrderLogic();
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel->where(['order_id' => $order_id])->find();
        $order = $orderObj->append(['full_address', 'orderGoods'])->toArray();
        if (0 != $order['shipping_status']) {
            $this->error('已发货订单不允许编辑');
            exit;
        }
        $orderGoods = $order['orderGoods'];
        if (IS_POST) {
            $order['consignee'] = I('consignee'); // 收货人
            $order['province'] = I('province'); // 省份
            $order['city'] = I('city'); // 城市
            $order['district'] = I('district'); // 县
            $order['address'] = I('address'); // 收货地址
            $order['mobile'] = I('mobile'); // 手机
            $order['invoice_title'] = I('invoice_title'); // 发票
            $order['taxpayer'] = I('taxpayer'); // 纳税人识别号
            $order['admin_note'] = I('admin_note'); // 管理员备注
            $order['admin_note'] = I('admin_note');
            $order['shipping_code'] = I('shipping'); // 物流方式
            $order['shipping_name'] = Db::name('shipping')->where('shipping_code', I('shipping'))->getField('shipping_name');
            $order['pay_code'] = I('payment'); // 支付方式
            $order['pay_name'] = M('plugin')->where(['status' => 1, 'type' => 'payment', 'code' => I('payment')])->getField('name');
            $goods_id_arr = I('goods_id/a');
            $new_goods = $old_goods_arr = [];
            //################################订单添加商品
            if ($goods_id_arr) {
                $new_goods = $orderLogic->get_spec_goods($goods_id_arr);
                foreach ($new_goods as $key => $val) {
                    $val['order_id'] = $order_id;
                    $rec_id = M('order_goods')->add($val); //订单添加商品
                    if (!$rec_id) {
                        $this->error('添加失败');
                    }
                }
            }

            //################################订单修改删除商品
            $old_goods = I('old_goods/a');
            foreach ($orderGoods as $val) {
                if (empty($old_goods[$val['rec_id']])) {
                    M('order_goods')->where('rec_id=' . $val['rec_id'])->delete(); //删除商品
                } else {
                    //修改商品数量
                    if ($old_goods[$val['rec_id']] != $val['goods_num']) {
                        $val['goods_num'] = $old_goods[$val['rec_id']];
                        M('order_goods')->where('rec_id=' . $val['rec_id'])->save(['goods_num' => $val['goods_num']]);
                    }
                    $old_goods_arr[] = $val;
                }
            }

            $goodsArr = array_merge($old_goods_arr, $new_goods);
            $pay = new Pay();
            try {
                $pay->setUserId($order['user_id']);
                $pay->payOrder($goodsArr);
                $pay->delivery($order['district']);
//                $pay->orderPromotion();
            } catch (TpshopException $t) {
                $error = $t->getErrorArr();
                $this->error($error['msg']);
            }
            //################################修改订单费用
            $order['goods_price'] = $pay->getGoodsPrice(); // 商品总价
            $order['shipping_price'] = $pay->getShippingPrice(); //物流费
            $order['order_amount'] = $pay->getOrderAmount(); // 应付金额
            $order['total_amount'] = $pay->getTotalAmount(); // 订单总价
            $o = M('order')->where('order_id=' . $order_id)->save($order);

            $l = $orderLogic->orderActionLog($order_id, '修改订单', '修改订单'); //操作日志
            if ($o && $l) {
                $this->success('修改成功', U('Admin/Order/editprice', ['order_id' => $order_id]));
            } else {
                $this->success('修改失败', U('Admin/Order/detail', ['order_id' => $order_id]));
            }
            exit;
        }
        // 获取省份
        $province = M('region2')->where(['parent_id' => 0, 'level' => 1])->select();
        //获取订单城市
        $city = M('region2')->where(['parent_id' => $order['province'], 'level' => 2])->select();
        //获取订单地区
        $area = M('region2')->where(['parent_id' => $order['city'], 'level' => 3])->select();
        //获取支付方式
        $payment_list = M('plugin')->where(['status' => 1, 'type' => 'payment'])->select();
        //获取配送方式
        $shipping_list = Db::name('shipping')->field('shipping_name,shipping_code')->where('')->select();

        $this->assign('order', $order);
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('orderGoods', $orderGoods);
        $this->assign('shipping_list', $shipping_list);
        $this->assign('payment_list', $payment_list);

        return $this->fetch();
    }

    /**
     * 选择搜索商品
     */
    public function search_goods()
    {
        $brandList = M('brand')->select();
        $categoryList = M('goods_category')->select();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);
        $where = 'exchange_integral = 0 and is_on_sale = 1 and is_virtual =' . I('is_virtual/d', 0); //搜索条件
        I('intro') && $where = "$where and " . I('intro') . ' = 1';
        if (I('cat_id')) {
            $this->assign('cat_id', I('cat_id'));
            $grandson_ids = getCatGrandson(I('cat_id'));
            $where = " $where  and cat_id in(" . implode(',', $grandson_ids) . ') '; // 初始化搜索条件
        }
        if (I('brand_id')) {
            $this->assign('brand_id', I('brand_id'));
            $where = "$where and brand_id = " . I('brand_id');
        }
        if (!empty($_REQUEST['keywords'])) {
            $this->assign('keywords', I('keywords'));
            $where = "$where and (goods_name like '%" . I('keywords') . "%' or keywords like '%" . I('keywords') . "%')";
        }
        $goods_count = M('goods')->where($where)->count();
        $Page = new Page($goods_count, C('PAGESIZE'));
        $goodsList = M('goods')->where($where)->order('goods_id DESC')->limit($Page->firstRow, $Page->listRows)->select();

        foreach ($goodsList as $key => $val) {
            $spec_goods = M('spec_goods_price')->where("goods_id = {$val['goods_id']}")->select();
            $goodsList[$key]['spec_goods'] = $spec_goods;
        }
        if ($goodsList) {
            $count = 0;
            //计算商品数量
            foreach ($goodsList as $value) {
                if ($value['spec_goods']) {
                    $count += count($value['spec_goods']);
                } else {
                    ++$count;
                }
            }
            $this->assign('totalSize', $count);
        }

        $this->assign('page', $Page->show());
        $this->assign('goodsList', $goodsList);

        return $this->fetch();
    }

    public function ajaxOrderNotice()
    {
        $order_amount = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();
        echo $order_amount;
    }

    /**
     * 删除订单日志.
     */
    public function delOrderLogo()
    {
        $ids = I('ids');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！', 'url' => '']);
        $order_ids = rtrim($ids, ',');
        $res = Db::name('order_action')->whereIn('order_id', $order_ids)->delete();
        if (false !== $res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功！', 'url' => '']);
        } else {
            $this->ajaxReturn(['status' => -1, 'msg' => '删除失败', 'url' => '']);
        }
    }

    /**
     * 修改订单地址
     * @return mixed
     */
    public function editAddress()
    {
        $orderId = I('order_id');
        $order = M('order')->where(['order_id' => $orderId])->field('order_status, shipping_status')->find();
        if (request()->isPost()) {
            $data = I('post.');
            // 数据验证
            $validate = \think\Loader::validate('Order');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = [
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                ];
                $this->ajaxReturn($return_arr);
            }
            if (!in_array($order['order_status'], [0, 1])) {
                $this->ajaxReturn(['status' => -1, 'msg' => '订单已确认不能修改地址']);
            }
            if ($order['shipping_status'] != 0) {
                $this->ajaxReturn(['status' => -1, 'msg' => '订单已发货不能修改地址']);
            }
            // 更新数据
            M('order')->where(['order_id' => $orderId])->update($data);
            // 订单操作记录
            $orderLogic = new OrderLogic();
            $convert_action = C('CONVERT_ACTION')['edit_address'];
            $orderLogic->orderActionLog($orderId, $convert_action, I('note'));
            $this->ajaxReturn(['status' => 1, 'msg' => '修改完成']);
        }
        // 订单地址
        $orderAddress = M('order o')
            ->join('region2 r1', 'r1.id = o.province')
            ->join('region2 r2', 'r2.id = o.city')
            ->join('region2 r3', 'r3.id = o.district')
            ->where(['order_id' => $orderId])
            ->field('o.order_id, o.province, o.city, o.district, r1.name province_name, r2.name city_name, r3.name district_name, 
            o.address, o.zipcode, o.mobile, o.consignee')
            ->find();
        // 省数据
        $province = M('region2')->where(['level' => 0])->select();
        // 市数据
        $city = M('region2')->where(['parent_id' => $orderAddress['province']])->select();
        // 区数据
        $district = M('region2')->where(['parent_id' => $orderAddress['city']])->select();

        $this->assign('order', $orderAddress);
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('district', $district);
        return $this->fetch('edit_address');
    }

    /**
     * 批量确认订单
     */
    public function batchConfirm()
    {
        $orderIds = trim(I('order_ids'), ',');
        $orderData = M('order')->where(['order_id' => ['IN', $orderIds]])->field('order_id, order_sn, order_status, pay_status')->select();
        foreach ($orderData as $order) {
            if ($order['pay_status'] != 1) {
                $this->ajaxReturn(['status' => 0, 'msg' => '订单：' . $order['order_sn'] . ' 未支付']);
            }
            if ($order['order_status'] != 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '订单：' . $order['order_sn'] . ' 已被确认']);
            }
        }
        $orderLogic = new OrderLogic();
        Db::startTrans();
        foreach ($orderData as $order) {
            $convert_action = C('CONVERT_ACTION')['confirm'];
            $res1 = $orderLogic->orderActionLog($order['order_id'], $convert_action, I('note'));
            $res2 = $orderLogic->orderProcessHandle($order['order_id'], 'confirm', ['note' => '批量确认订单', 'admin_id' => 0]);
            if (!$res1 || !$res2) {
                Db::rollback();
                $this->ajaxReturn(['status' => 0, 'msg' => '订单：' . $order['order_sn'] . ' 确认失败']);
            }
        }
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '批量确认订单成功']);
    }
}