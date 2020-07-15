<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller\api;

use app\common\logic\CartLogic;
use app\common\logic\CommentLogic;
use app\common\logic\CouponLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\MessageLogic;
use app\common\logic\OrderLogic;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\logic\UsersLogic;
use app\common\util\TpshopException;
use app\home\controller\Api as ApiController;
use think\Db;
use think\Hook;
use think\Page;
use think\Request;
use think\Url;

class Order extends Base
{
    public $user_id = '0';
    public $user = [];

    public function __construct()
    {
        parent::__construct();
        // 1. 检查登陆
        $params['user_token'] = $this->userToken;
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);

        $user = session('user');
        if ($user) {
            $this->user = $user;
            $this->user_id = $user['user_id'];
        }
    }

    /*
     * 订单列表
     */
    public function order_list()
    {
        $return = [];

        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();
        $return['user_message_count'] = $user_message_count;

        //用户中心面包屑导航
        $navigate_user = navigate_user();
        $return['navigate_user'] = $navigate_user;

        $where = ' user_id=:user_id';
        $bind['user_id'] = $this->user_id;
        //条件搜索
        if (I('get.type')) {
            $where .= C(strtoupper(I('get.type')));
        }
        // 搜索订单 根据商品名称 或者 订单编号
        $search_key = trim(I('search_key'));
        if ($search_key) {
            $where .= ' and (order_sn like :search_key1 or order_id in (select order_id from `' . C('database.prefix') . 'order_goods` where goods_name like :search_key2) ) ';
            $bind['search_key1'] = "%$search_key%";
            $bind['search_key2'] = "%$search_key%";
        }
        $where .= ' and prom_type < 5 '; //虚拟拼团订单不列出来
        $where .= ' and deleted != 1 '; //虚拟拼团订单不列出来

        $count = M('order')->where($where)->bind($bind)->count();
        $Page = new Page($count, 10);

        $show = $Page->show();
        $order_str = 'order_id DESC';
        $order_list = M('order')->order($order_str)->where($where)->bind($bind)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性

            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $number_amount = '0';
            $total_give_integral = '0';
            $order_list[$k]['goods_list'] = $data['result'];
            foreach ($order_list[$k]['goods_list'] as $glk => $glv) {
                $number_amount = $number_amount + $glv['goods_num'];
                $total_give_integral = bcadd($total_give_integral, $glv['give_integral'], 2);

                if ($glv['zone'] == 3) {
                    $order_list[$k]['cancel_btn'] = '0';
                }
            }
            $order_list[$k]['number_amount'] = $number_amount;
            $order_list[$k]['total_give_integral'] = $total_give_integral;
            $order_list[$k]['cancel_time'] = $order_list[$k]['add_time'] + 1 * 60 * 60;
            $order_list[$k]['add_time'] = date('Y-m-d H:i:s', $order_list[$k]['add_time']);

            if (4 == $order_list[$k]['prom_type']) {
                $pre_sell_item = M('goods_activity')->where(['act_id' => $order_list[$k]['prom_id']])->find();
                $pre_sell_item = array_merge($pre_sell_item, unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            } else {
                $order_list[$k]['pre_sell_is_finished'] = -1; //没有参与预售的订单
            }

            if (2 == $order_list[$k]['prom_type']) {
                $group_detail = M('GroupDetail')->where('group_id', $order_list[$k]['prom_id'])->select();
                foreach ($group_detail as $gdk => $gdv) {
                    $order_sn = explode(',', $gdv['order_sn_list']);
                    if (in_array($order_list[$k]['order_sn'], $order_sn)) {
                        $order_list[$k]['group_status'] = C('GROUP_STATUS')[$gdv['status']];
                        break;
                    }
                }
            }

            //发货单号
            $order_list[$k]['invoice_no'] = M('delivery_doc')->where('order_sn', $v['order_sn'])->getField('invoice_no');
        }
        $number_amount = $count;
        $return['order_status'] = C('ORDER_STATUS');
        $return['group_status'] = C('GROUP_STATUS');
        $return['shipping_status'] = C('SHIPPING_STATUS');
        $return['pay_status'] = C('PAY_STATUS');
        $return['page'] = $show;
        $return['lists'] = $order_list;
        $return['active'] = 'order_list';
        $return['number_amount'] = $number_amount;
        $return['active_status'] = I('get.type');

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 订单列表（新）
     * @return array
     */
    public function order_list_new()
    {
        $where = "user_id = $this->user_id AND deleted != 1";
        if (I('type')) {
            $where .= C(strtoupper(I('get.type')));
        }
        $where .= ' AND prom_type < 5 '; // 虚拟拼团订单不列出来
        // 搜索订单 根据商品名称 或者 订单编号
        $search_key = trim(I('search_key'));
        $bind = [];
        if ($search_key) {
            $where .= ' AND (order_sn like :search_key1 or order_id in (select order_id from `' . C('database.prefix') . 'order_goods` where goods_name like :search_key2) ) ';
            $bind['search_key1'] = "%$search_key%";
            $bind['search_key2'] = "%$search_key%";
        }
        // 订单数量
        $orderNum = Db::name('order')->where($where)->bind($bind)->count();
        $page = new Page($orderNum, 10);
        // 订单列表
        $orderList = Db::name('order')->where($where)->bind($bind)->order('order_id DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
        $orderIds = [];
        foreach ($orderList as $order) {
            $orderIds[] = $order['order_id'];
        }
        // 订单商品数据
        $userLogic = new OrderLogic();
        $orderGoods = $userLogic->getOrderGoods($orderIds);
        // 组合数据
        $orderData = [];
        foreach ($orderList as $k => $list) {
            $payEndTime = bcadd($list['add_time'], 3600);   // 支付到期时间
            $payDeadTime = bcsub($payEndTime, time());      // 支付结束时间
            if ($payDeadTime < 0) {
                $payDeadTime = "0";
            }
            $orderList[$k] = set_btn_order_status($list);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $orderData[$k] = [
                'type' => 1,
                'type_value' => '乐活优选',
                'order_info' => [
                    'order_id' => $list['order_id'],
                    'order_sn' => $list['order_sn'],
                    'order_status' => $list['order_status'],
                    'pay_status' => $list['order_status'],
                    'shipping_status' => $list['order_status'],
                    'order_status_code' => $orderList[$k]['order_status_code'] == 'AFTER-SALES' || $orderList[$k]['order_status_code'] == 'WAITCCOMMENT' ? 'FINISH' : $orderList[$k]['order_status_code'],
                    'order_status_desc' => $orderList[$k]['order_status_desc'],
                    'order_amount' => $list['order_amount'],
                    'shipping_price' => $list['shipping_price'],
                    'add_time' => $list['add_time'],
                    'pay_end_time' => $payEndTime,
                    'pay_dead_time' => $payDeadTime,
                    'now_time' => time() . '',
                    'delivery_type' => $list['delivery_type'],
                ],
                'order_goods' => []     // 订单商品
            ];
            $goodsNum = '0';          // 商品数量
            $giveIntegral = '0';   // 返还积分
            foreach ($orderGoods as $goods) {
                if ($list['order_id'] == $goods['order_id']) {
                    $orderData[$k]['order_goods'][] = [
                        'goods_id' => $goods['goods_id'],
                        'goods_sn' => $goods['goods_sn'],
                        'goods_name' => $goods['goods_name'],
                        'spec_key_name' => $goods['spec_key_name'] ?? '',
                        'item_id' => $goods['item_id'] ?? '',
                        'goods_num' => $goods['goods_num'],
                        'shop_price' => $goods['goods_price'],
                        'exchange_integral' => $goods['use_integral'],
                        'exchange_price' => $goods['member_goods_price'],
                        'original_img' => $goods['original_img'],
                        'is_return' => $goods['is_return']
                    ];
                    $goodsNum += $goods['goods_num'];
                    $giveIntegral = bcadd($giveIntegral, $goods['give_integral'], 2);
                }
            }
            $orderData[$k]['order_info']['total_num'] = $goodsNum;
            $orderData[$k]['order_info']['give_integral'] = $giveIntegral;
        }
        return json(['status' => 1, 'result' => $orderData]);
    }

    /*
     * 订单详情
     */
    public function order_detail()
    {
        $id = I('get.id/d');
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        if (!$order_info) {
            return json(['status' => 0, 'msg' => '没有获取到订单信息', 'result' => null]);
        }
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (5 == $order_info['prom_type']) {   //虚拟订单
            $this->redirect(U('virtual/virtual_order', ['order_id' => $id]));
        }

        $order_info['order_pv'] = $this->user['distribut_level'] >= 3 ? $order_info['order_pv'] : '';

        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];

        $order_info['add_time'] = date('Y-m-d H:i:s', $order_info['add_time']);
        $order_info['pay_time'] = $order_info['pay_time'] ? date('Y-m-d H:i:s', $order_info['pay_time']) : 0;
        $order_info['shipping_time'] = $order_info['shipping_time'] ? date('Y-m-d H:i:s', $order_info['shipping_time']) : 0;
        $order_info['confirm_time'] = $order_info['confirm_time'] ? date('Y-m-d H:i:s', $order_info['confirm_time']) : 0;

        $can_return = $order_info['end_sale_time'] > time() ? true : false;

        $total_give_integral = '0';
        foreach ($order_info['goods_list'] as $gk => $gv) {
            $total_give_integral = bcadd($total_give_integral, bcmul($gv['give_integral'], $gv['goods_num'], 2), 2);
            $order_info['goods_list'][$gk]['can_return'] = $can_return;
        }
        $order_info['total_give_integral'] = $total_give_integral;
        if (4 == $order_info['prom_type']) {
            $pre_sell_item = M('goods_activity')->where(['act_id' => $order_info['prom_id']])->find();
            $pre_sell_item = array_merge($pre_sell_item, unserialize($pre_sell_item['ext_info']));
            $order_info['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
            $order_info['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
            $order_info['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            $order_info['pre_sell_deliver_goods'] = $pre_sell_item['deliver_goods'];
        } else {
            $order_info['pre_sell_is_finished'] = -1; //没有参与预售的订单
        }

        if (2 == $order_info['prom_type']) {
            $group_detail = M('group_detail')->where('group_id', $order_info['prom_id'])->select();
            foreach ($group_detail as $gkey => $gvalue) {
                $order_sn = explode(',', $gvalue['order_sn_list']);
                if (in_array($order_info['order_sn'], $order_sn)) {
                    $group_activity = M('group_buy')->find($order_info['prom_id']);
                    $order_info['group_buy_detail'] = $gvalue;
                    $order_info['group_buy_detail']['status'] = C('GROUP_STATUS')[$gvalue['status']];
                    $order_info['group_buy_detail']['time'] = date('Y-m-d H:i:s', $gvalue['time']);
                    $order_info['group_buy_detail']['time_desc'] = 2 == $gvalue['status'] ? '成团时间' : '开团时间';
                    $order_info['group_buy_detail']['batch_num'] = $group_activity['group_goods_num'];
                    break;
                }
            }
        }

        //获取订单进度条
        $sql = "SELECT action_id,log_time,status_desc,order_status FROM ((SELECT * FROM __PREFIX__order_action WHERE order_id = :id AND status_desc <>'' ORDER BY action_id) AS a) GROUP BY status_desc ORDER BY action_id";
        $bind['id'] = $id;
        $items = DB::query($sql, $bind);
        $items_count = count($items);

        $ids = $order_info['province'] . ',' . $order_info['city'] . ',' . $order_info['district'];
        $region_list = M('region2')->where('id in (' . $ids . ')')->getField('id,name');
        $invoice_no = M('DeliveryDoc')->where('order_id', $id)->getField('invoice_no', true);
        $order_info['invoice_no'] = implode(' , ', $invoice_no);
        $order_return_num = M('return_goods')->where(['order_id' => $id, 'user_id' => $this->user_id])->count();
        $order_info['is_return'] = $order_return_num > 0 ? 1 : 0;

        //发货单号
        $order_info['invoice_no'] = M('delivery_doc')->where('order_sn', $order_info['order_sn'])->getField('invoice_no');

        //获取订单操作记录
        $order_action = M('order_action')->field('*,FROM_UNIXTIME(log_time,"%Y-%m-%d %H:%i:%s") as add_time')->where(['order_id' => $id])->select();

        //双十一任务奖励
        $task_log = M('task_log')->where('order_sn', $order_info['order_sn'])->where('task_id', 1)->where('type', 1)->find();
        if (!$task_log) {
            $task_log['id'] = '0';
        }
        $task_log['created_at'] = date('Y-m-d H:i:s', $task_log['created_at']);
        $order_info['task_reward'] = $task_log;

        $return['order_status'] = C('ORDER_STATUS');
        $return['group_status'] = C('GROUP_STATUS');
        $return['shipping_status'] = C('SHIPPING_STATUS');
        $return['pay_status'] = C('PAY_STATUS');
        $return['region_list'] = $region_list;
        $return['order_info'] = $order_info;
        $return['order_action'] = $order_action;
        $return['active'] = 'order_list';

        //获取购物券
        $coupon_info = M('coupon_list')->alias('cl')
            ->where(array('cl.get_order_id' => $order_info['order_id']))
            ->join('coupon c', 'cl.cid = c.id')->field('c.name,c.condition,c.money,c.image_url as coupon_image_url,c.id')->find();

        if ($coupon_info) {
            $return['is_has_coupon'] = 1;
            $return['coupon_id'] = $coupon_info['id'];
            $return['coupon_name'] = $coupon_info['name'];
            $coupon_money = $coupon_info['money'];
            $coupon_money = bcadd($coupon_money, 0, 1);
            $coupon_money = str_replace('.0', '', $coupon_money);
            $return['coupon_dis'] = $coupon_money . '折';
            $return['coupon_image_url'] = $coupon_info['coupon_image_url'];
        } else {
            $return['coupon_id'] = '0';
            $return['is_has_coupon'] = '0';
            $return['coupon_name'] = '';
            $return['coupon_dis'] = '';
            $return['coupon_image_url'] = '';
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 订单详情（新）
     * @return array|\think\response\Json
     */
    public function order_detail_new()
    {
        $orderId = I('order_id', '');
        $map['order_id'] = $orderId;
        $map['user_id'] = $this->user_id;
        $orderInfo = M('order')->where($map)->find();
        if (!$orderInfo) {
            return json(['status' => 0, 'msg' => '没有获取到订单信息', 'result' => null]);
        }
        $orderTypeTips = '';
        if ($orderInfo['order_type'] == 2) {
            $orderTypeTips = '韩国购商品收货后如有质量或破损问题申请退换货时，请联系总部客服进行处理';
        }
        $orderInfo = set_btn_order_status($orderInfo);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        // 获取订单商品
        $userLogic = new UsersLogic();
        $orderGoods = $userLogic->get_order_goods($orderInfo['order_id'])['result'];
        // 自动确认时间
        if ($orderInfo['shipping_status'] == 1 && $orderInfo['order_status'] == 1) {
            $autoConfirmTime = $orderInfo['shipping_time'] + tpCache('shopping.auto_confirm_date') * 24 * 60 * 60;
        } else {
            $autoConfirmTime = '0';
        }
        $payEndTime = bcadd($orderInfo['add_time'], 3600);   // 支付到期时间
        $payDeadTime = bcsub($payEndTime, time());           // 支付结束时间
        if ($payDeadTime < 0) {
            $payDeadTime = "0";
        }
        // 组合数据
        $orderData = [
            'order_id' => $orderInfo['order_id'],
            'order_type' => $orderInfo['order_type'],
            'order_type_tips' => $orderTypeTips,
            'order_sn' => $orderInfo['order_sn'],
            'transaction_id' => $orderInfo['transaction_id'] ?? '',
            'order_status' => $orderInfo['order_status'],
            'pay_status' => $orderInfo['order_status'],
            'shipping_status' => $orderInfo['order_status'],
            'order_status_code' => $orderInfo['order_status_code'] == 'AFTER-SALES' || $orderInfo['order_status_code'] == 'WAITCCOMMENT' ? 'FINISH' : $orderInfo['order_status_code'],
            'order_status_desc' => $orderInfo['order_status_desc'],
            'pay_code' => $orderInfo['pay_code'],
            'pay_name' => $orderInfo['pay_name'],
            'goods_price' => $orderInfo['goods_price'],
            'weight' => 0,
            'shipping_price' => $orderInfo['shipping_price'],
            'coupon_price' => $orderInfo['coupon_price'],
            'prom_price' => $orderInfo['order_prom_amount'],
            'electronic_price' => $orderInfo['user_electronic'],
            'pay_points' => $orderInfo['integral'],
            'total_amount' => bcadd($orderInfo['goods_price'], $orderInfo['shipping_price'], 2),
            'order_amount' => $orderInfo['order_amount'],
            'give_integral' => 0,
            'add_time' => $orderInfo['add_time'],
            'pay_end_time' => $payEndTime,
            'pay_dead_time' => $payDeadTime,
            'now_time' => time() . '',
            'pay_time' => $orderInfo['pay_time'],
            'shipping_time' => $orderInfo['shipping_time'],
            'confirm_time' => $orderInfo['confirm_time'],
            'cancel_time' => $orderInfo['cancel_time'],
            'delivery_type' => $orderInfo['delivery_type'],   // 1统一发货 2分开发货
            'order_pv' => $this->user['distribut_level'] >= 3 ? $orderInfo['order_pv'] : '',
            'delivery' => [
                'consignee' => $orderInfo['consignee'],
                'mobile' => $orderInfo['mobile'],
                'province' => $orderInfo['province'],
                'province_name' => Db::name('region2')->where(['id' => $orderInfo['province']])->value('name'),
                'city' => $orderInfo['city'],
                'city_name' => Db::name('region2')->where(['id' => $orderInfo['city']])->value('name'),
                'district' => $orderInfo['district'],
                'district_name' => Db::name('region2')->where(['id' => $orderInfo['district']])->value('name'),
                'address' => $orderInfo['address'],
                'auto_confirm_time' => $autoConfirmTime,
                'shipping_name' => $orderInfo['shipping_name']
            ],
            'goods' => []
        ];
        if ($orderData['delivery']['city_name'] == '直辖区') {
            $orderData['delivery']['city_name'] = '';
        }
        if ($orderInfo['order_status_code'] == 'AFTER-SALES' || $orderInfo['order_status_code'] == 'WAITCCOMMENT' || $orderInfo['order_status_code'] == 'FINFISH') {
            $canReturn = $orderInfo['end_sale_time'] > time() ? true : false;   // 能否退货
        } else {
            $canReturn = false;
        }
        $weight = '0';
        $giveIntegral = '0';
        $giftGoodsIds = [];     // 订单赠品上级商品ID
        $giftGoodsList = [];    // 满单赠品
        $promGiftList = [];     // 订单促销优惠赠品
        foreach ($orderGoods as $key => $goods) {
            if ($goods['is_gift'] == 0) {
                if ($goods['prom_type'] == 6) {
                    // 加价购
                    $idKey = $goods['goods_id'] . '_' . $goods['spec_key'] . '_extra';
                } else {
                    $idKey = $goods['goods_id'] . '_' . $goods['spec_key'];
                }
                $giftGoodsIds[] = $idKey;
                $orderData['goods'][$idKey] = [
                    'rec_id' => $goods['rec_id'],
                    'goods_id' => $goods['goods_id'],
                    'goods_sn' => $goods['goods_sn'],
                    'goods_name' => $goods['goods_name'],
                    'spec_key_name' => $goods['spec_key_name'] ?? '',
                    'item_id' => $goods['item_id'] ?? '',
                    'goods_num' => $goods['goods_num'],
                    'shop_price' => $goods['goods_price'],
                    'exchange_integral' => $goods['use_integral'],
                    'exchange_price' => $goods['member_goods_price'],
                    'original_img' => $goods['original_img'],
//                    'can_return' => $canReturn == true ? $goods['sale_type'] == 1 ? $goods['is_return'] == 1 ? 0 : 1 : 0 : 0,   // sale_type = 1 普通商品
                    'can_return' => $canReturn == true ? ($goods['is_return'] == 1 || $goods['re_id'] > 0) ? 0 : 1 : 0,
                    'return_status' => $goods['status'] ?? '',
                    'gift_goods' => []
                ];
                $giveIntegral = bcadd($giveIntegral, bcmul($goods['give_integral'], $goods['goods_num']), 2);
            } else {
                // 赠品
                switch ($goods['prom_type']) {
                    case 7:
                        // 订单优惠促销赠品
                        if (!isset($promGiftList[$goods['prom_id']])) {
                            $promTitle = M('order_prom')->where(['id' => $goods['prom_id']])->value('title');
                            $promGiftList[$goods['prom_id']] = [
                                'prom_id' => $goods['prom_id'],
                                'title' => $promTitle . '，获赠以下赠品：',
                                'goods_list' => []
                            ];
                        }
                        $promGiftList[$goods['prom_id']]['goods_list'][] = [
                            'rec_id' => $goods['rec_id'],
                            'goods_id' => $goods['goods_id'],
                            'goods_sn' => $goods['goods_sn'],
                            'goods_name' => $goods['goods_name'],
                            'spec_key_name' => $goods['spec_key_name'] ?? '',
                            'item_id' => $goods['item_id'] ?? '',
                            'goods_num' => $goods['goods_num'],
                            'shop_price' => $goods['goods_price'],
                            'exchange_integral' => $goods['use_integral'],
                            'exchange_price' => $goods['member_goods_price'],
                            'original_img' => $goods['original_img'],
                            'can_return' => 0,
                            'return_status' => '',
                        ];
                        break;
                    case 8:
                        // 满单赠品
                        if (!isset($giftGoodsList[$goods['prom_id']])) {
                            $promTitle = M('gift_reward')->where(['reward_id' => $goods['prom_id']])->value('description');
                            $giftGoodsList[$goods['prom_id']] = [
                                'prom_id' => $goods['prom_id'],
                                'title' => $promTitle . '，获赠以下赠品：',
                                'goods_list' => []
                            ];
                        }
                        $giftGoodsList[$goods['prom_id']]['goods_list'][] = [
                            'rec_id' => $goods['rec_id'],
                            'goods_id' => $goods['goods_id'],
                            'goods_sn' => $goods['goods_sn'],
                            'goods_name' => $goods['goods_name'],
                            'spec_key_name' => $goods['spec_key_name'] ?? '',
                            'item_id' => $goods['item_id'] ?? '',
                            'goods_num' => $goods['goods_num'],
                            'shop_price' => $goods['goods_price'],
                            'exchange_integral' => $goods['use_integral'],
                            'exchange_price' => $goods['member_goods_price'],
                            'original_img' => $goods['original_img'],
                            'can_return' => 0,
                            'return_status' => '',
                        ];
                        break;
                    case 9:
                        // 指定赠品
                        $idKey = $goods['gift_goods_id'] . '_' . $goods['gift_goods_spec_key'];
                        if (in_array($idKey, $giftGoodsIds)) {
                            $orderData['goods'][$idKey]['gift_goods'][] = [
                                'rec_id' => $goods['rec_id'],
                                'goods_id' => $goods['goods_id'],
                                'goods_sn' => $goods['goods_sn'],
                                'goods_name' => $goods['goods_name'],
                                'spec_key_name' => $goods['spec_key_name'] ?? '',
                                'item_id' => $goods['item_id'] ?? '',
                                'goods_num' => $goods['goods_num'],
                                'shop_price' => $goods['goods_price'],
                                'exchange_integral' => $goods['use_integral'],
                                'exchange_price' => $goods['member_goods_price'],
                                'original_img' => $goods['original_img'],
                                'can_return' => 0,
                                'return_status' => '',
                            ];
                        }
                        break;
                }
            }
            $weight = bcadd($weight, $goods['weight'], 2);
        }
        $orderData['goods'] = array_values($orderData['goods']);
        $orderData['gift_list'] = array_merge(array_values($promGiftList), array_values($giftGoodsList));
        $orderData['weight'] = $weight . 'g';
        $orderData['give_integral'] = $giveIntegral;
        return json(['status' => 1, 'result' => $orderData]);
    }

    public function del_order()
    {
        $order_id = I('order_id/d', 0);

        $orderLogic = new OrderLogic();
        $orderLogic->setUserId($this->user_id);
        $return = $orderLogic->delOrder($order_id);

        return json($return);
        //return json(['status'=>1,'msg'=>'删除成功',U('Home/Order/order_list', array('type'=>$order_type)), 'result'=>null]);
    }

    public function del_refund_goods()
    {
        $order_id = I('id/d', 0);

        M('return_goods')->where('id', $order_id)->update(['status' => 6]);

        return json(['status' => 1, 'msg' => '删除成功', 'result' => null]);
    }

    /*
     * 取消订单
     */
    public function cancel_order()
    {
        $id = I('id/d');
        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data = $logic->cancel_order($this->user_id, $id);

        return json($data);
    }

    public function cancel_order_info()
    {
        $order_id = I('order_id/d', 0);
        $order = M('order')->where(['order_id' => $order_id, 'order_status' => 3, 'pay_status' => 1])->find();
        $return['order'] = $order;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    //取消订单弹窗
    public function refund_order()
    {
        $order_id = I('get.order_id/d');

        $order = M('order')
            ->field('order_id,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount,total_amount')
            ->where(['order_id' => $order_id, 'user_id' => $this->user_id])
            ->find();

        if (!$order) {
            return json(['status' => 0, 'msg' => '订单不存在', 'result' => null]);
        }

        $return['user'] = $this->user;
        $return['order'] = $order;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    //申请取消订单
    public function record_refund_order()
    {
        $order_id = input('post.order_id', 0);
        $user_note = input('post.user_note', '');
        $consignee = input('post.consignee', '');
        $mobile = input('post.mobile', '');

        $logic = new OrderLogic();
        $return = $logic->recordRefundOrder($this->user_id, $order_id, $user_note, $consignee, $mobile);

        return json($return);
    }

    public function virtual_order()
    {
        $Order = new \app\common\model\Order();
        $order_id = I('get.order_id/d');
        $map['order_id'] = $order_id;
        $map['user_id'] = $this->user_id;
        $orderobj = $Order->where($map)->find();
        if (!$orderobj) {
            return json(['status' => 0, 'msg' => '没有获取到订单信息', 'result' => null]);
        }
        // 添加属性  包括按钮显示属性 和 订单状态显示属性
        $order_info = $orderobj->append(['order_status_detail', 'order_button', 'order_goods'])->toArray();
        //获取订单操作记录
        $order_action = M('order_action')->where(['order_id' => $order_id])->select();
        $return['order_status'] = C('ORDER_STATUS');
        $return['pay_status'] = C('PAY_STATUS');
        $return['order_info'] = $order_info;
        $return['order_action'] = $order_action;

        if (1 == $order_info['pay_status'] && 3 != $order_info['order_status']) {
            $vrorder = M('vr_order_code')->where(['order_id' => $order_id])->select();
            $return['vrorder'] = $vrorder;
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 评论晒单
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status', -1);
        $logic = new CommentLogic();
        $data = $logic->getComment($user_id, $status); //获取评论列表
        $return['page'] = $data['show']; // 赋值分页出
        $return['comment_page'] = $data['page'];
        $return['comment_list'] = $data['result'];
        $return['active'] = 'comment';

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 删除评价.
     */
    public function delComment()
    {
        $comment_id = I('comment_id');
        if (empty($comment_id)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        $comment = Db::name('comment')->where('comment_id', $comment_id)->find();
        if ($this->user_id != $comment['user_id']) {
            return json(['status' => 0, 'msg' => '不能删除别人的评论', 'result' => null]);
        }
        Db::name('reply')->where('comment_id', $comment_id)->delete();
        Db::name('comment')->where('comment_id', $comment_id)->delete();

        return json(['status' => 1, 'msg' => '删除评论成功', 'result' => null]);
    }

    /**
     *  点赞.
     *
     * @author lxl
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(['comment_id' => $comment_id])->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $result['success'] = '0';
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(['comment_id' => $comment_id])->save($comment_data);
            $result['success'] = 1;
        }

        return json($result);
    }

    /**
     * 添加回复.
     *
     * @author dyr
     */
    public function reply_add()
    {
        $comment_id = I('post.comment_id/d');
        $reply_id = I('post.reply_id/d', 0);
        $content = I('post.content');
        $to_name = I('post.to_name', '');
        $goods_id = I('post.goods_id/d');
        $reply_data = [
            'comment_id' => $comment_id,
            'parent_id' => $reply_id,
            'content' => $content,
            'user_name' => $this->user['nickname'],
            'to_name' => $to_name,
            'reply_time' => time() . '',
        ];
        $where = ['o.user_id' => $this->user_id, 'og.goods_id' => $goods_id, 'o.pay_status' => 1];
        $user_goods_count = Db::name('order')
            ->alias('o')
            ->join('__ORDER_GOODS__ og', 'o.order_id = og.order_id', 'LEFT')
            ->where($where)
            ->count();
        if ($user_goods_count > 0) {
            M('reply')->add($reply_data);
            M('comment')->where(['comment_id' => $comment_id])->setInc('reply_num');
            $json['status'] = 1;
            $json['msg'] = '回复成功';
        } else {
            $json['status'] = -1;
            $json['msg'] = '只有购买过该商品才能进行评价';
        }
        $json['result'] = null;

        return json($json);
    }

    // 确认收货
    public function order_confirm()
    {
        $id = I('post.order_id/d', 0);
        $data = confirm_order($id, $this->user_id);

        return json($data);
    }

    /**
     * 可申请退换货.
     */
    public function return_goods_index()
    {
        $sale_t = I('sale_t/i', 0);
        $keywords = I('keywords');
        $model = new OrderLogic();
        $data = $model->getReturnGoodsIndex($sale_t, $keywords, $this->user_id);
        $return['store_list'] = $data['store_list'];
        $return['order_list'] = $data['order_list'];
        $return['page'] = $data['show'];

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 申请退货.
     */
    public function return_goods(Request $request)
    {
        $rec_id = I('rec_id', 0);
        $return_goods = M('return_goods')->where(['rec_id' => $rec_id])->find();
        if (!empty($return_goods)) {
            return json(['status' => 0, 'msg' => '已经提交过退货申请!', 'result' => null]);
        }
        $order_goods = M('order_goods')->where(['rec_id' => $rec_id])->find();
        $order_goods['goods_img'] = M('goods')->where(['goods_id' => $order_goods['goods_id']])->getField('original_img');
//        $order = M('order')->where(['order_id' => $order_goods['order_id'], 'user_id' => $this->user_id])->find();
        $order = M('order')->where(['order_id' => $order_goods['order_id']])->find();
        if (empty($order)) {
            return json(['status' => 0, 'msg' => '非法操作', 'result' => null]);
        }
        if ($order['order_type'] == 2) {
            return json(['status' => 0, 'msg' => '韩国购商品收货后如有质量或破损问题申请退换货时，请联系总部客服进行处理']);
        }
        $confirm_time_config = tpCache('shopping.auto_service_date'); //后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            return json(['status' => 0, 'msg' => '已经超过' . $confirm_time_config . '天内退货时间', 'result' => null]);
        }
        if ($request->isPost()) {
            $model = new OrderLogic();
            $res = $model->addReturnGoods($rec_id, $order);  //申请售后
            if (1 == $res['status']) {
                return json(['status' => 1, 'msg' => $res['msg'], 'result' => null]);
            }

            return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
        }
        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = '0';
        $return_address = M('region2')->where('id in (' . implode(',', $region_id) . ')')->getField('id,name');
//        $order_info = array_merge($order, $order_goods);  //合并数组
        $return['return_address'] = $return_address;
        $return['return_type'] = C('RETURN_TYPE');
        $return['goods'] = $order_goods;
        $return['order'] = $order;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 申请售后（新）
     * @return array|\think\response\Json
     */
    public function return_goods_new()
    {
        $type = I('type', -1);
        $recId = I('rec_id', '');
        if ($a = Db::name('return_goods')->where(['rec_id' => $recId, 'user_id' => $this->user_id])->find()) {
            return json(['status' => 0, 'msg' => '该商品已申请了售后']);
        }
        // 订单商品信息
        $orderGoods = (new OrderLogic())->getOrderGoodsById($recId);
        // 订单信息
        $order = Db::name('order')->where(['order_id' => $orderGoods['order_id']])->find();
        if (empty($order)) {
            return json(['status' => 0, 'msg' => '非法操作']);
        }
        if ($order['order_type'] == 2) {
            return json(['status' => 0, 'msg' => '韩国购商品收货后如有质量或破损问题申请退换货时，请联系总部客服进行处理']);
        }
        $confirmTimeConfig = tpCache('shopping.auto_service_date');   // 后台设置多少天内可申请售后
        $confirmTime = $confirmTimeConfig * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirmTime && !empty($order['confirm_time'])) {
            return json(['status' => 0, 'msg' => '已经超过' . $confirmTimeConfig . '天内退货时间']);
        }
        if ($this->request->isPost()) {
            // 申请售后
            $orderLogic = new OrderLogic();
            $res = $orderLogic->addReturnGoodsNew($recId, $type, $order, I('post.'));
            return json($res);
        } else {
            $return['order_goods'] = [
                'rec_id' => $orderGoods['rec_id'],
                'goods_id' => $orderGoods['goods_id'],
                'goods_sn' => $orderGoods['goods_sn'],
                'goods_name' => $orderGoods['goods_name'],
                'spec_key_name' => $orderGoods['spec_key_name'] ?? '',
                'item_id' => $orderGoods['item_id'] ?? '',
                'original_img' => $orderGoods['original_img']
            ];

            if ($type != -1) {
                $useApplyReturnMoney = $orderGoods['final_price'] * $orderGoods['goods_num'];    // 要退的总价 商品购买单价*申请数量
                $userExpenditureMoney = $order['goods_price'] - $order['order_prom_amount'] - $order['coupon_price'];    // 用户实际使用金额
                $user_electronic = round($order['user_electronic'] - $order['user_electronic'] * $order['shipping_price'] / $order['total_amount'], 2);
                // 该退积分支付
                $refundIntegral = round($orderGoods['use_integral'] * $orderGoods['goods_num'], 2);
                // 该退电子币
                $refundElectronic = round($useApplyReturnMoney / $userExpenditureMoney * $user_electronic, 2);
                if ($order['order_amount'] > 0) {
                    $order_amount = $order['order_amount'] + $order['paid_money'];   // 三方支付总额，预售要退定金
                    if ($order_amount > $order['shipping_price']) {
                        // 退款金额
                        $refundMoney = round($useApplyReturnMoney / $userExpenditureMoney * ($order_amount - $order['shipping_price']), 2);
                    }
                }

                // 公司地址
                $provinceName = Db::name('region2')->where(['id' => tpCache('shop_info.province')])->value('name');
                $cityName = Db::name('region2')->where(['id' => tpCache('shop_info.city')])->value('name');
                $districtName = Db::name('region2')->where(['id' => tpCache('shop_info.district')])->value('name');
                $address = tpCache('shop_info.address');
                $address = $provinceName . $cityName . $districtName . $address;
            }
            switch ($type) {
                case -1:
                    break;
                case 0:
                case 1:
                case 2:
                    if (isset($refundMoney)) {
                        if (isset($refundElectronic)) {
                            $return['return_price'] = bcadd($refundMoney, $refundElectronic, 2);
                        } else {
                            $return['return_price'] = $refundMoney . '';
                        }
                    } elseif (isset($refundElectronic)) {
                        $return['return_price'] = $refundElectronic . '';
                    } else {
                        $return['return_price'] = '0.00';
                    }
                    $return['return_reason'] = C('RETURN_REASON')[$type];
                    $return['return_contact'] = tpCache('shop_info.contact');
                    $return['return_mobile'] = tpCache('shop_info.mobile');
                    $return['return_address'] = isset($address) ? $address : '';
                    $return['return_electronic'] = isset($refundElectronic) ? $refundElectronic . '' : 0;
                    $return['return_integral'] = isset($refundIntegral) ? $refundIntegral . '' : 0;
                    break;
                default:
                    return json(['status' => 0, 'msg' => '参数错误']);
            }
            return json(['status' => 1, 'result' => $return]);
        }
    }

    /**
     * 退换货列表.
     */
    public function return_goods_list()
    {
        $where = " rg.user_id=$this->user_id ";
        $where .= ' and rg.status!=6 ';
        // 搜索订单 根据商品名称 或者 订单编号
        $search_key = trim(I('search_key'));
        if ($search_key) {
            $where .= " and rg.order_sn=$search_key";
        }

        $count = M('return_goods')->alias('rg')->where($where)->count();
        $page = new Page($count, 10);

        $list = M('return_goods')
            ->alias('rg')
            ->join('__ORDER_GOODS__ og', 'rg.goods_id = og.goods_id and rg.order_id = og.order_id', 'LEFT')
            ->where($where)
            ->order('id desc')
            ->limit("{$page->firstRow},{$page->listRows}")
            ->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where('goods_id', 'in', implode(',', $goods_id_arr))->getField('goods_id,goods_name,original_img,shop_price,exchange_integral');
        }
        $state = C('REFUND_STATUS');
        $return['state'] = $state;
        $return['goodsList'] = $goodsList;
        $return['list'] = $list;
        $return['page'] = $page->show(); // 赋值分页出
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 退换货列表（新）
     * @return \think\response\Json
     */
    public function return_goods_list_new()
    {
        $where = 'user_id = ' . $this->user_id;
        $where .= ' and status != 6';
        // 根据商品名称 或者 订单编号
        $search_key = trim(I('search_key'));
        $bind = [];
        if ($search_key) {
            $where .= ' and (order_sn like :search_key1 or rec_id in (select rec_id from `' . C('database.prefix') . 'order_goods` where goods_name like :search_key2) ) ';
            $bind['search_key1'] = "%$search_key%";
            $bind['search_key2'] = "%$search_key%";
        }
        // 记录ID
        $returnIds = Db::name('return_goods')->where($where)->bind($bind)->getField('id', true);
        $page = new Page(count($returnIds), 10);
        // 记录列表
        $returnList = (new OrderLogic())->getReturnGoods($returnIds, $page);
        // 组合数据
        $return = [];
        foreach ($returnList as $k => $returnGoods) {
            $return[$k] = [
                'order_id' => $returnGoods['order_id'],
                'order_sn' => $returnGoods['order_sn'],
                'goods_id' => $returnGoods['goods_id'],
                'goods_sn' => $returnGoods['goods_sn'],
                'goods_name' => $returnGoods['goods_name'],
                'spec_key_name' => $returnGoods['spec_key_name'] ?? '',
                'item_id' => $returnGoods['item_id'] ?? '',
                'goods_num' => $returnGoods['goods_num'],
                'original_img' => $returnGoods['original_img'],
                'return_id' => $returnGoods['id'],
                'return_type' => $returnGoods['type'],
                'return_status' => $returnGoods['status']
            ];
            $return[$k]['return_status'] = !empty($returnGoods['delivery']) ? 7 : $returnGoods['status'];
        }
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     *  退货详情.
     */
    public function return_goods_info(Request $request)
    {
        $id = I('id/d', 0);
        $ReturnGoodsModel = new \app\common\model\ReturnGoods();
        $return_goods = $ReturnGoodsModel::get(['id' => $id, 'user_id' => $this->user_id]);
        if (empty($return_goods)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        if ($request->isPost()) {
            $data = I('post.');
            $data['delivery'] = serialize($data['delivery']);
            $data['status'] = 2;
            M('return_goods')->where(['id' => $data['id'], 'user_id' => $this->user_id])->save($data);

            return json(['status' => 1, 'msg' => '发货提交成功', 'result' => null]);
        }
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        $return_goods['delivery'] = unserialize($return_goods['delivery']);  //订单的物流信息，服务类型为换货会显示
        $return_goods['third_payment'] = '';
        $orderInfo = M('order')->field('pay_code,order_amount')->where('order_id', $return_goods['order_id'])->find();
        if (0 == $return_goods['refund_type']) {
            if ('alipayMobile' == $orderInfo['pay_code']) {
                $return_goods['third_payment'] = '支付宝';
            } else {
                $return_goods['third_payment'] = '微信';
            }
        }
        $return_goods['order_amount'] = $orderInfo['order_amount'];
        $return_goods['addtime'] = date('Y-m-d H:i:s', $return_goods['addtime']);
        if ($return_goods['imgs']) {
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        }
        $goods = M('goods')->where('goods_id', $return_goods['goods_id'])->find();
        $return['state'] = C('REFUND_STATUS');
        $return['return_type'] = C('RETURN_TYPE');
        $return['goods'] = $goods;
        $return['return_goods'] = $return_goods;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 退货详情（新）
     * @return \think\response\Json
     */
    public function return_goods_info_new()
    {
        $returnId = I('return_id', '');
        $returnGoods = Db::name('return_goods')->where(['id' => $returnId])->find();
        if (empty($returnGoods)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        if ($this->request->isPost()) {
            if ($returnGoods['status'] == 2) {
                return json(['status' => 0, 'msg' => '该退货单已提交了发货信息']);
            }
            $expressName = I('express_name', '');
            $expressSn = I('express_sn', '');
            if (!$expressName || !$expressSn) {
                return json(['status' => 0, 'msg' => '快递信息不能为空']);
            }
            $data['delivery'] = [
                'express_name' => $expressName,
                'express_sn' => $expressSn
            ];
            $data['delivery'] = serialize($data['delivery']);
            $data['status'] = 2;
            M('return_goods')->where(['id' => $returnId, 'user_id' => $this->user_id])->save($data);
            return json(['status' => 1, 'msg' => '发货提交成功']);
        }
        $orderLogic = new OrderLogic();
        // 订单商品
        $orderGoods = $orderLogic->getOrderGoodsById($returnGoods['rec_id']);
        // 订单数据
        $order = M('order')->where(['order_id' => $orderGoods['order_id']])->find();
        $return = [
            'order_goods' => [
                'rec_id' => $orderGoods['rec_id'],
                'goods_id' => $orderGoods['goods_id'],
                'goods_sn' => $orderGoods['goods_sn'],
                'goods_name' => $orderGoods['goods_name'],
                'spec_key_name' => $orderGoods['spec_key_name'] ?? '',
                'item_id' => $orderGoods['item_id'] ?? '',
                'goods_num' => $orderGoods['goods_num'],
                'original_img' => $orderGoods['original_img']
            ],
            'return_id' => $returnGoods['id'],
            'return_title' => '',
            'return_desc' => '',
            'type' => $returnGoods['type'],
            'status' => $returnGoods['status'],
            'verify_time' => $returnGoods['addtime'] + tpCache('shopping.return_verify_date') * 24 * 60 * 60,   // 审核完毕时间
            'verify_remark' => $returnGoods['remark'],
            'refund_time' => $returnGoods['refund_time'],
            'return_contact' => tpCache('shop_info.contact'),
            'return_mobile' => tpCache('shop_info.mobile'),
        ];
        $provinceName = Db::name('region2')->where(['id' => tpCache('shop_info.province')])->value('name');
        $cityName = Db::name('region2')->where(['id' => tpCache('shop_info.city')])->value('name');
        $districtName = Db::name('region2')->where(['id' => tpCache('shop_info.district')])->value('name');
        $address = tpCache('shop_info.address');
        $address = $provinceName . $cityName . $districtName . $address;
        $return['return_address'] = $address;
        $return['return_reason'] = $returnGoods['reason'];
        $return['describe'] = $returnGoods['describe'];
        $return['return_price'] = $returnGoods['refund_money'] != 0 ? bcadd($returnGoods['refund_money'], $returnGoods['refund_electronic'], 2) : $returnGoods['refund_electronic'];
        $return['return_electronic'] = $returnGoods['refund_electronic'];
        $return['return_integral'] = $returnGoods['refund_integral'];
        $return['voucher'] = $returnGoods['imgs'] ? explode(',', $returnGoods['imgs']) : [];
        $return['can_delivery'] = !empty($returnGoods['delivery']) ? 0 : 1;
        if (in_array($returnGoods['type'], [1, 2])) {
            if ($returnGoods['status'] == 1) {
                if (empty($returnGoods['delivery'])) {
                    $return['status'] = 7;  // 等待买家退货
                }
            }
        }
        $return['goods_num'] = $returnGoods['goods_num'];
        $return['order_sn'] = Db::name('order')->where(['order_id' => $orderGoods['order_id']])->value('order_sn');
        $return['addtime'] = $returnGoods['addtime'];
        $return['pay_code'] = $order['pay_code'];
        $return['pay_name'] = $order['pay_name'];
        // 根据类型与状态返回提示信息
        switch ($return['type']) {
            case 0:
                switch ($return['status']) {
                    case -2:
                        $returnTitle = '买家已取消退货申请';
                        $returnDesc = '';
                        break;
                    case -1:
                        $returnTitle = '审核未通过，申请取消';
                        $returnDesc = '有任何疑问请致电客服热线：' . tpCache('shop_info.mobile');
                        break;
                    case 0:
                        $returnTitle = '商家正在审核中……';
                        $returnDesc = '审核时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 1:
                        $returnTitle = '审核通过，商家正在退款';
                        $returnDesc = '审核时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 5:
                        $returnTitle = '退款成功';
                        $returnDesc = date('Y' . '年' . 'm' . '月' . 'd' . '日 H:i', $return['refund_time']);
                        break;
                    default:
                        $returnTitle = '';
                        $returnDesc = '';
                }
                break;
            case 1:
                switch ($return['status']) {
                    case -2:
                        $returnTitle = '买家已取消退货申请';
                        $returnDesc = '';
                        break;
                    case -1:
                        $returnTitle = '审核未通过，申请取消';
                        $returnDesc = '有任何疑问请致电客服热线：' . tpCache('shop_info.mobile');
                        break;
                    case 0:
                        $returnTitle = '商家正在审核中……';
                        $returnDesc = '审核时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 2:
                        $returnTitle = '买家已退货，等待商家退款';
                        $returnDesc = '退款时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 3:
                        $returnTitle = '商家已收货，等待商家退款';
                        $returnDesc = '退款时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 5:
                        $returnTitle = '退款成功';
                        $returnDesc = date('Y' . '年' . 'm' . '月' . 'd' . '日 H:i', $return['refund_time']);
                        break;
                    case 7:
                        $returnTitle = '审核通过，商家正在退款';
                        $returnDesc = '审核时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    default:
                        $returnTitle = '';
                        $returnDesc = '';
                }
                break;
            case 2:
                switch ($return['status']) {
                    case -2:
                        $returnTitle = '买家已取消退货申请';
                        $returnDesc = '';
                        break;
                    case -1:
                        $returnTitle = '审核未通过，申请取消';
                        $returnDesc = '有任何疑问请致电客服热线：' . tpCache('shop_info.mobile');
                        break;
                    case 0:
                        $returnTitle = '商家正在审核中……';
                        $returnDesc = '审核时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 2:
                        $returnTitle = '买家已退货，等待商家换货';
                        $returnDesc = '换货时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 3:
                        $returnTitle = '商家已收货，等待商家退款';
                        $returnDesc = '换货时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    case 4:
                        $returnTitle = '商家已换货';
                        $returnDesc = '货物已以百米冲刺的速度送往您身边，请注意';
                        break;
                    case 5:
                        $returnTitle = '退款成功';
                        $returnDesc = date('Y' . '年' . 'm' . '月' . 'd' . '日 H:i', $return['refund_time']);
                        break;
                    case 7:
                        $returnTitle = '审核通过，等待买家退货';
                        $returnDesc = '审核时间还剩下 ' . differTimeStr($return['verify_time'], time());
                        break;
                    default:
                        $returnTitle = '';
                        $returnDesc = '';
                }
                break;
            default:
                $returnTitle = '';
                $returnDesc = '';
        }
        $return['return_title'] = $returnTitle;
        $return['return_desc'] = $returnDesc;
        return json(['status' => 1, 'result' => $return]);
    }

    public function return_goods_refund()
    {
        $order_sn = I('order_sn');
        $where = ['user_id' => $this->user_id];
        if ($order_sn) {
            $where['order_sn'] = $order_sn;
        }
        $where['status'] = 5;
        $count = M('return_goods')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('return_goods')->where($where)->order('id desc')->limit($page->firstRow, $page->listRows)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where('goods_id in (' . implode(',', $goods_id_arr) . ')')->getField('goods_id,goods_name');
        }
        $return['goodsList'] = $goodsList;
        $state = C('REFUND_STATUS');
        $return['list'] = $list;
        $return['state'] = $state;
        $return['page'] = $page->show(); // 赋值分页出
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 取消服务单.
     */
    public function return_goods_cancel()
    {
        $id = I('id/d', 0);
        if (empty($id)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        $res = M('return_goods')->where(['id' => $id, 'user_id' => $this->user_id])->save(['status' => -2, 'canceltime' => time()]);

        // 如果订单没有售后的商品，订单状态变回已确认
        $order_id = M('return_goods')->where(['id' => $id, 'user_id' => $this->user_id])->getField('order_id');
        if (!M('return_goods')->where('order_id', $order_id)->where('status', 'neq', -2)->find()) {
            M('order')->where('order_id', $order_id)->update(['order_status' => 2]);
        }
        $return_info = M('return_goods')->where(['id' => $id, 'user_id' => $this->user_id])->find();
        $order_goods = M('order_goods')->where(['rec_id' => $return_info['rec_id']])->find();

        if (5 != $return_info['status']) {
            // $goods_commission = M('Goods')->where('goods_id', $return_info['goods_id'])->getField('commission');
            // $dec_money = $return_info['refund_money'] * $goods_commission / 100;
            // M('rebate_log')->where("order_sn",$return_info['order_sn'])->update([
            //     'money' => ['exp',"money + {$dec_money}"],
            //     'freeze_money' => ['exp',"freeze_money - {$dec_money}"]
            // ]);
            // 更新分成记录状态
            $other_return = M('return_goods')->where(['order_id' => $return_info['order_id'], 'rec_id' => ['neq', $return_info['rec_id']], 'status' => 0])->find();
            if (!$other_return) {
                M('rebate_log')->where('order_sn', $return_info['order_sn'])->update(['status' => 2]);
            }
            if ($order_goods['goods_pv'] == 0) {
                // 解冻分成
                $rebate_list = M('rebate_log')->where('order_sn', $return_info['order_sn'])->select();
                if ($rebate_list) {
                    $OrderLogic = new OrderLogic();
                    foreach ($rebate_list as $rk => $rv) {
                        $money = $OrderLogic->getDecMoney($rv['order_id'], $rv['level']);
                        $dec_money = $money[$return_info['rec_id']]['money'];
                        $dec_point = $money[$return_info['rec_id']]['point'];
                        M('rebate_log')->where('id', $rv['id'])->update([
                            'money' => ['exp', "money + {$dec_money}"],
                            'point' => ['exp', "point + {$dec_point}"],
                            'freeze_money' => ['exp', "freeze_money - {$dec_money}"],
                        ]);
                    }
                }
            }
        }
        if ($res) {
            return json(['status' => 1, 'msg' => '成功取消服务单']);
        }

        return json(['status' => 0, 'msg' => '服务单不存在']);
    }

    /**
     * 换货商品确认收货.
     *
     * @author lxl
     * @time  17-4-25
     * */
    public function receiveConfirm()
    {
        $return_id = I('return_id/d');
        $return_info = M('return_goods')->field('order_id,order_sn,goods_id,spec_key')->where('id', $return_id)->find(); //查找退换货商品信息
        $update = M('return_goods')->where('id', $return_id)->save(['status' => 3]);  //要更新状态为已完成
        if ($update) {
            M('order_goods')->where([
                'order_id' => $return_info['order_id'],
                'goods_id' => $return_info['goods_id'],
                'spec_key' => $return_info['spec_key'],])->save(['is_send' => 2]);  //订单商品改为已换货
            return json(['status' => 1, 'msg' => '操作成功', 'result' => null]);
        }

        return json(['status' => 0, 'msg' => '操作失败', 'result' => null]);
    }

    /**
     * 获取我的粉丝数据.
     *
     * @author J
     * @time  18-08-03
     * */
    public function lower()
    {
        $first_fans = M('users')->where('invite_uid', $this->user_id)->count();
        $first_fans_id = M('users')->field('user_id')->where('invite_uid', $this->user_id)->select();

        $first_fans_id_arr = [];

        if ($first_fans_id) {
            foreach ($first_fans_id as $fk => $fv) {
                $first_fans_id_arr[] = $fv['user_id'];
            }
        }
        $second_fans = '0';
        if ($first_fans_id_arr) {
            $second_fans = M('users')->where('invite_uid', 'IN', $first_fans_id_arr)->count();
        }

        $return['first_fans'] = $first_fans; // 总数
        $return['second_fans'] = $second_fans;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 获取粉丝列表接口
     * */
    public function lower_list()
    {
        $level = I('get.level', 1);

        if (1 == $level) {
            $count = M('users')->where('first_leader', $this->user_id)->count();

            $page = new Page($count, 35);

            $list = M('users')
                ->field('user_id,head_pic,nickname,distribut_level,CASE(distribut_level) WHEN "1" THEN "粉丝" WHEN "2" THEN "VIP会员" WHEN "3" THEN "乐活优选店主" ELSE "未知" END AS distribut_level_name')
                ->where('first_leader', $this->user_id)
                ->limit("{$page->firstRow},{$page->listRows}")
                ->order('user_id desc')
                ->select();
        } else {
            $count = M('users')->where('second_leader', $this->user_id)->count();

            $page = new Page($count, 35);

            $list = M('users')
                ->field('user_id,head_pic,first_leader,nickname,distribut_level,CASE(distribut_level) WHEN "1" THEN "粉丝" WHEN "2" THEN "VIP会员" WHEN "3" THEN "乐活优选店主" ELSE "未知" END AS distribut_level_name')
                ->where('second_leader', $this->user_id)
                ->limit("{$page->firstRow},{$page->listRows}")
                ->order('user_id desc')
                ->select();
        }

        $return['count'] = $count; // 总数
        $return['level'] = $level;
        $return['member'] = $list; // 线
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 总的分销订单
     * */
    public function income()
    {
        $result = Db::query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where user_id = {$this->user_id}");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;
        $status = I('get.status', -2);

        if ('0' == $status || $status > 0) {
            switch ($status) {
                case 6:
                    $condition['sale_service'] = 1;
                    break;
                default:
                    $condition['status'] = $status;
            }
        }

        $condition['user_id'] = $this->user_id;
        $condition['money'] = ['GT', 0];
        $count = M('rebate_log')->where($condition)->count();
        $page = new Page($count, 10);
        $rebate_log = M('rebate_log')->where($condition)->limit("{$page->firstRow},{$page->listRows}")->order('id desc')->select();
        $OrderCommonLogic = new \app\common\logic\OrderLogic();
        foreach ($rebate_log as $rk => $rv) {
            $order_goods = M('OrderGoods')
                ->alias('a')
                ->field('a.*,o.add_time')
                ->join('__ORDER__ o', 'o.order_id = a.order_id', 'LEFT')
                ->where('a.order_id', $rv['order_id'])
                ->select();

            foreach ($order_goods as $ok => $ov) {
                $hasCommission = true;
                if ($rv['sale_service'] == 1) {
                    // 提成记录状态为 已售后
                    if (M('return_goods')->where(['rec_id' => $ov['rec_id'], 'status' => ['NOT IN', [-2, -1, 4, 6]]])->value('id')) {
                        $hasCommission = false;
                    }
                }
                $goodsInfo = M('Goods')->field('exchange_integral,shop_price,shop_price - exchange_integral as integral_price, original_img as imgSrc')->where('goods_id', $ov['goods_id'])->find();
                $order_goods[$ok]['add_time'] = date('Y-m-d H:i:s', $ov['add_time']);
                $order_goods[$ok]['imgSrc'] = $goodsInfo['imgSrc'];
                if ($ov['use_integral'] > 0) {
                    $order_goods[$ok]['price'] = $goodsInfo['integral_price'];
                    $order_goods[$ok]['integral'] = $ov['use_integral'];
                } else {
                    $order_goods[$ok]['price'] = $goodsInfo['shop_price'];
                    $order_goods[$ok]['integral'] = '0';
                }

                $order_goods[$ok]['get_price'] = $hasCommission ? $ov['goods_pv'] == 0 ? $OrderCommonLogic->getRongMoney(bcdiv(bcmul(bcmul($ov['final_price'], $ov['goods_num'], 2), $ov['commission'], 2), 100, 2), $rv['level'], $ov['add_time'], $ov['goods_id']) : '0.00' : '0.00';
                $order_goods[$ok]['get_pv'] = $this->user['distribut_level'] >= 3 ? $hasCommission ? $ov['goods_pv'] > 0 ? $ov['goods_pv'] : '0.00' : '0.00' : '';

                //$order_goods[$ok]['get_price'] = round(($ov['final_price'] * $ov['goods_num']) * $ov['commission'] / 100 * $distribut_rate, 2);
                $order_goods[$ok]['is_freeze'] = M('return_goods')->where(['rec_id' => $ov['rec_id'], 'status' => ['NOT IN', [-2, -1, 4, 6]]])->find() ? 1 : 0;
            }

            $rebate_log[$rk]['status_desc'] = $rv['sale_service'] == 1 ? '已售后' : rebate_status($rv['status']);
            $rebate_log[$rk]['order_goods'] = $order_goods;
            $rebate_log[$rk]['imgTx'] = M('Users')->where('user_id', $rv['buy_user_id'])->getField('head_pic');
            $rebate_log[$rk]['showContent'] = false;
        }

        $return['page'] = $page->show(); // 赋值分页出
        $return['rebate_log'] = $rebate_log;
        $return['status'] = $status;
        $return['result'] = $result;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    // 获取他人的分销订单列表
    public function userIncome()
    {
        $user_id = I('id/d', 0);
        if (!$user_id) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        $user = M('users')
            ->field('user_id,is_distribut,distribut_level,nickname,mobile,head_pic,first_leader,second_leader,third_leader')
            ->where('user_id', $user_id)
            ->find();
        if (!$user) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }

        $result = Db::query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where buy_user_id = {$user_id} and user_id = {$this->user_id} and status = 5");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;
        $status = I('get.status', -2);

        if ('0' == $status || $status > 0) {
            $condition['status'] = $status;
        }

        $condition['buy_user_id'] = $user_id;
        $condition['user_id'] = $this->user_id;
        $count = M('rebate_log')->where($condition)->count();
        $page = new Page($count, 10);
        $rebate_log = M('rebate_log')->where($condition)->limit("{$page->firstRow},{$page->listRows}")->order('id desc')->select();
        $OrderCommonLogc = new \app\common\logic\OrderLogic();
        foreach ($rebate_log as $rk => $rv) {
            $order_goods = M('OrderGoods')
                ->alias('a')
                ->field('a.*,o.add_time')
                ->join('__ORDER__ o', 'o.order_id = a.order_id', 'LEFT')
                ->where('a.order_id', $rv['order_id'])
                ->select();

            foreach ($order_goods as $ok => $ov) {
                $goodsInfo = M('Goods')->field('exchange_integral,shop_price,shop_price - exchange_integral as integral_price, original_img as imgSrc')->where('goods_id', $ov['goods_id'])->find();
                $order_goods[$ok]['add_time'] = date('Y-m-d H:i:s', $ov['add_time']);
                $order_goods[$ok]['imgSrc'] = $goodsInfo['imgSrc'];
                if ($ov['use_integral'] > 0) {
                    $order_goods[$ok]['price'] = $goodsInfo['integral_price'];
                    $order_goods[$ok]['integral'] = $ov['use_integral'];
                } else {
                    $order_goods[$ok]['price'] = $goodsInfo['shop_price'];
                    $order_goods[$ok]['integral'] = '0';
                }
                //$order_goods[$ok]['get_price'] = round(($ov['final_price'] * $ov['goods_num']) * $ov['commission'] / 100 * $distribut_rate, 2);

                $order_goods[$ok]['get_price'] = $OrderCommonLogc->getRongMoney(bcdiv(bcmul(bcmul($ov['final_price'], $ov['goods_num'], 2), $ov['commission'], 2), 100, 2), $rv['level'], $ov['add_time'], $ov['goods_id']);

                $order_goods[$ok]['is_freeze'] = M('return_goods')->where('rec_id', $ov['rec_id'])->where('status', 'gt', -1)->where(['status' => ['neq', 4]])->find() ? 1 : 0;
            }

            $rebate_log[$rk]['status_desc'] = rebate_status($rv['status']);
            $rebate_log[$rk]['order_goods'] = $order_goods;
            $rebate_log[$rk]['imgTx'] = M('Users')->where('user_id', $rv['buy_user_id'])->getField('head_pic');
            $rebate_log[$rk]['showContent'] = false;
        }

        $return['rebate_log'] = $rebate_log;
        $return['user'] = $user;
        $return['status'] = $status;
        $return['result'] = $result;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 订单商品评价列表.
     */
    public function comment_list()
    {
        $order_id = I('order_id/d');
        $rec_id = I('rec_id/d');
        if (empty($order_id) || empty($rec_id)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        //查找订单
        $order_comment_where['order_id'] = $order_id;
        $order_info = M('order')->field('order_sn,order_id,add_time,prom_type')->where($order_comment_where)->find();
        //查找评价商品
        $order_comment_where['rec_id'] = $rec_id;
        $order_goods = M('order_goods')
            ->field('rec_id,goods_id,is_comment,goods_name,goods_num,goods_price,spec_key_name')
            ->where($order_comment_where)
            ->find();
        $order_info = array_merge($order_info, $order_goods);
        $return['order_info'] = $order_info;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
    *添加评论
    */
    public function add_comment()
    {
        $user_info = session('user');
        $comment_img = serialize(I('comment_img/a')); // 上传的图片文件
        $add['rec_id'] = I('rec_id/d');
        $add['goods_id'] = I('goods_id/d');
        $add['email'] = $user_info['email'];
        $hide_username = I('hide_username');
        if (empty($hide_username)) {
            $add['username'] = $user_info['nickname'];
        }
        $add['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
        $add['order_id'] = I('order_id/d');
        $add['service_rank'] = I('service_rank');
        $add['deliver_rank'] = I('deliver_rank');
        $add['goods_rank'] = I('goods_rank');
        $add['is_show'] = 1; //默认显示
        $add['content'] = I('content');
        $add['img'] = $comment_img;
        $add['add_time'] = time() . '';
        $add['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $add['user_id'] = $this->user_id;
        $logic = new UsersLogic();
        //添加评论
        $row = $logic->add_comment($add);

        return json($row);
    }

    /**
     * 获取提交订单前的信息
     * @return \think\response\Json
     * @throws \app\common\util\TpshopException
     */
    public function orderBeforeInfo()
    {
        // 用户默认地址
        $userAddress = get_user_address_list_new($this->user_id, true);
        if (!empty($userAddress)) {
            $userAddress[0]['out_range'] = 0;
            unset($userAddress[0]['zipcode']);
            unset($userAddress[0]['is_pickup']);
            // 地址标签
            $addressTab = (new UsersLogic())->getAddressTab($this->user_id);
            if (!empty($addressTab)) {
                if (empty($userAddress[0]['tabs'])) {
                    unset($userAddress[0]['tabs']);
                    $userAddress[0]['tabs'][] = [
                        'tab_id' => 0,
                        'name' => '默认',
                        'is_selected' => 1
                    ];
                } else {
                    $tabs = explode(',', $userAddress[0]['tabs']);
                    unset($userAddress[0]['tabs']);
                    foreach ($addressTab as $item) {
                        if (in_array($item['tab_id'], $tabs)) {
                            $userAddress[0]['tabs'][] = [
                                'tab_id' => $item['tab_id'],
                                'name' => $item['name'],
                                'is_selected' => 1
                            ];
                        }
                    }
                    $userAddress[0]['tabs'][] = [
                        'tab_id' => 0,
                        'name' => '默认',
                        'is_selected' => 1
                    ];
                }
            } else {
                unset($userAddress[0]['tabs']);
                $userAddress[0]['tabs'][] = [
                    'tab_id' => 0,
                    'name' => '默认',
                    'is_selected' => 1
                ];
            }
        }

        $goodsId = I('goods_id', '');           // 商品ID
        $itemId = I('item_id', '');             // 商品规格ID
        $goodsNum = I('goods_num', '');         // 商品数量
        $payType = input('pay_type', 1);        // 结算类型
        $cartIds = I('cart_ids', '');           // 购物车ID组合
        $couponId = I('coupon_id', '');         // 优惠券ID

        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        // 获取订单商品数据
        $goodsLogic = new GoodsLogic();
        $res = $goodsLogic->getOrderGoodsData($cartLogic, $goodsId, $itemId, $goodsNum, $payType, $cartIds, $this->isApp);
        if ($res['status'] != 1) {
            return json($res);
        } else {
            $cartList['cartList'] = $res['result'];
        }

        // 检查下单商品
        $res = $cartLogic->checkCartGoods($cartList['cartList']);
        $abroad = [
            'state' => 0,
            'id_card' => '',
            'hide_id_card' => '',
            'id_card_tips' => '',
            'purchase_tips' => '',
        ];
        switch ($res['status']) {
            case 0:
                return json($res);
            case 2:
                $abroad['state'] = 1;
                // 获取身份证信息
                $abroad['id_card'] = $this->user['id_cart'] ?? '';
                $abroad['hide_id_card'] = $this->user['id_cart'] ? hideStr($this->user['id_cart'], 4, 4, 4, '*') : '';
                $abroad['id_card_tips'] = M('abroad_config')->where(['type' => 'id_card'])->value('content');
                // 获取韩国购产品购买须知
                $abroad['purchase_tips'] = M('abroad_config')->where(['type' => 'purchase'])->value('content');
                break;
        }

        // 初始化数据 商品总额/节约金额/商品总共数量/商品使用积分
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);
        $cartList = array_merge($cartList, $cartPriceInfo);

        if ($this->user['distribut_level'] >= 3) {
            // 计算商品pv
            $cartList['cartList'] = $cartLogic->calcGoodsPv($cartList['cartList']);
        }

        $cartGoodsList = get_arr_column($cartList['cartList'], 'goods');
        $cartGoodsId = get_arr_column($cartGoodsList, 'goods_id');
        $cartGoodsCatId = array_merge(get_arr_column($cartGoodsList, 'cat_id'), get_arr_column($cartGoodsList, 'extend_cat_id'));
        $couponLogic = new CouponLogic();
        // 用户可用的优惠券列表
        $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId, $this->isApp);
        $couponList = [];
        foreach ($userCouponList as $k => $coupon) {
            $couponList[$k] = [
                'coupon_id' => $coupon['coupon']['id'],
                'name' => $coupon['coupon']['name'],
                'money' => $coupon['coupon']['money'],
                'condition' => $coupon['coupon']['condition'],
                'use_type' => $coupon['coupon']['use_type'],
                'is_usual' => $coupon['coupon']['is_usual'],
                'use_start_time' => date('Y.m.d', $coupon['coupon']['use_start_time']),
                'use_end_time' => date('Y.m.d', $coupon['coupon']['use_end_time']),
                'is_selected' => 0,
                'cat_name' => $coupon['cat_name'] ?? ''
            ];
        }
        // 用户可用的兑换券列表
        $userExchangeList = $couponLogic->getUserAbleCouponListRe($this->user_id, $cartGoodsId, $cartGoodsCatId, $this->isApp);
        $exchangeList = [];
        $exchangeId = 0;
        foreach ($userExchangeList as $key => $coupon) {
            if ($key == 0) {
                $exchangeId = $coupon['coupon']['id'];
            }
            $exchangeList[] = [
                'exchange_id' => $coupon['coupon']['id'],
                'name' => $coupon['coupon']['name'],
                'money' => $coupon['coupon']['money'],
                'condition' => $coupon['coupon']['condition'],
                'use_type' => $coupon['coupon']['use_type'],
                'use_start_time' => date('Y.m.d', $coupon['coupon']['use_start_time']),
                'use_end_time' => date('Y.m.d', $coupon['coupon']['use_end_time']),
                'is_selected' => $key == 0 ? 1 : 0
            ];
        }
        $exchangeGoods = [];
        if ($exchangeId > 0) {
            // 兑换券商品
            $exchangeGoods = M('goods_coupon gc')->join('goods g', 'g.goods_id = gc.goods_id')
                ->where(['gc.coupon_id' => $exchangeId])->field('g.goods_id, g.goods_name, g.goods_remark, g.original_img')->select();
            foreach ($exchangeGoods as $key => $goods) {
                $exchangeGoods[$key]['goods_num'] = 1;
            }
        }
        try {
            $payLogic = new Pay();
            // 设置支付用户ID
            $payLogic->setUserId($this->user_id);
            // 计算购物车价格
            $payLogic->payCart($cartList['cartList']);
            // 检测支付商品购买限制
            $payLogic->check();
            // 参与活动促销
            $payLogic->goodsPromotion();

            // 使用积分
            $pay_points = $payLogic->getUsePoint();
            if ($this->user['pay_points'] < $pay_points) {
                return json(['status' => 0, 'msg' => '用户消费积分只有' . $this->user['pay_points']]);
            }
            $payLogic->usePayPoints($pay_points);

            $weight = '0';                    // 产品重量
            $give_integral = '0';             // 赠送积分
//            $order_prom_fee = '0';            // 订单优惠促销总价
            foreach ($cartList['cartList'] as $v) {
                $goodsInfo = M('Goods')->field('give_integral, weight')->where('goods_id', $v['goods_id'])->find();
                $give_integral = bcadd($give_integral, bcmul($goodsInfo['give_integral'], $v['goods_num'], 2), 2);
                $weight = bcadd($weight, $goodsInfo['weight'], 2);
//                if (isset($v['is_order_prom']) && $v['is_order_prom'] == 1) {
//                    $order_prom_fee = bcadd($order_prom_fee, bcmul(bcadd($v['use_integral'], $v['member_goods_price'], 2), $v['goods_num'], 2), 2);
//                }
            }
            if (!empty($exchangeList)) {
                // 兑换券商品积分
                foreach ($exchangeList as $key => $coupon) {
                    $integral = M('goods_coupon gc')->join('goods g', 'g.goods_id = gc.goods_id')
                        ->where(['gc.coupon_id' => $coupon['exchange_id']])->sum('g.give_integral * gc.number');
                    $give_integral = bcadd($give_integral, $integral, 2);
                    $exchangeList[$key]['title'] = $coupon['name'];
                    $exchangeList[$key]['desc'] = '购买任意商品可用';
                }
            }

            $payLogic->activity3();         // 订单优惠促销

            if (!empty($couponList)) {
                list($prom_type, $prom_id) = $payLogic->getPromInfo();
                // 筛选优惠券
                foreach ($couponList as $key => $coupon) {
                    $canCoupon = true;
                    if ($coupon['is_usual'] == '0') {
                        // 不可以叠加优惠
                        if ($payLogic->getOrderPromAmount() > 0 || in_array($prom_type, [1, 2])) {
                            $canCoupon = false;
                        }
                    }
                    if (!$canCoupon || $coupon['condition'] > $payLogic->getGoodsPrice()) {
                        unset($couponList[$key]);
                        continue;
                    }
                    $res = $couponLogic->couponTitleDesc($coupon);
                    if (empty($res)) {
                        unset($couponList[$key]);
                        continue;
                    }
                    $couponList[$key]['name'] = $res['title'];
                    $couponList[$key]['title'] = $res['title'];
                    $couponList[$key]['desc'] = $res['desc'];
                    unset($couponList[$key]['is_usual']);
                }
            }
            if (!empty($couponList)) {
                $couponList = array_values($couponList);
                $couponList[0]['is_selected'] = 1;
                $couponId = $couponList[0]['coupon_id'];
            }
            // 使用优惠券
            if (isset($couponId) && $couponId > 0) {
                $payLogic->useCouponById($couponId, $payLogic->getPayList(), 'no');
            }

            $payLogic->activity(true);      // 满单赠品
            $payLogic->activity2New();      // 指定商品赠品 / 订单优惠赠品

            // 配送物流
            if (empty($userAddress)) {
                $payLogic->delivery('0');
            } else {
                $res = $payLogic->delivery($userAddress[0]['district']);
                if (isset($res['status']) && $res['status'] == -1) {
                    $userAddress[0]['out_range'] = 1;
                }
            }
            // 订单pv
            $payLogic->setOrderPv();

            // 支付数据
            $payReturn = $payLogic->toArray();

            // 商品列表 赠品列表 加价购列表
            $payList = collection($payLogic->getPayList())->toArray();
            $goodsList = [];    // 商品列表
            foreach ($payList as $k => $list) {
                $goods = $list['goods'];
                $goodsList[$k] = [
                    'goods_id' => $goods['goods_id'],
                    'goods_sn' => $goods['goods_sn'],
                    'goods_name' => $goods['goods_name'],
                    'goods_remark' => $goods['goods_remark'] ?? $goods['spec_key_name'] ?? '',
                    'spec_key_name' => $goods['spec_key_name'] ?? '',
                    'original_img' => $goods['original_img'],
                    'goods_num' => $list['goods_num'],
                    'shop_price' => $goods['shop_price'],
                    'exchange_integral' => $list['use_integral'] ?? 0,
                    'exchange_price' => in_array($list['prom_type'], [1, 2]) ? bcadd($list['member_goods_price'], 0, 2) : '',
                    'prom_type' => $list['prom_type'] ?? 0,
                    'gift_goods' => [],
                ];
                // 处理显示金额
                if (!in_array($list['prom_type'], [1, 2])) {
                    if ($list['use_integral'] != '0') {
                        $goodsList[$k]['exchange_price'] = bcdiv(bcsub(bcmul($list['goods']['shop_price'], 100), bcmul($list['use_integral'], 100)), 100, 2);
                    } else {
                        $goodsList[$k]['exchange_price'] = $list['goods']['shop_price'];
                    }
                }
                if (isset($list['gift_goods'])) {
                    $goodsList[$k]['gift_goods'] = $list['gift_goods'];
                }
            }
            $giftList = $payLogic->getPromGiftList();   // 订单优惠促销赠品
            $giftGoodsList = $payReturn['gift_goods_list']; // 满单赠品
            if (!empty($giftGoodsList)) {
                foreach ($giftGoodsList as $gift) {
                    $giftList[] = [
                        'prom_id' => $gift['gift_reward_id'],
                        'title' => $gift['gift_description'],
                        'goods_list' => [[
                            'goods_id' => $gift['goods_id'],
                            'item_id' => $gift['item_id'] ?? '',
                            'goods_num' => $gift['goods_num'],
                            'goods_sn' => $gift['goods_sn'],
                            'goods_name' => $gift['goods_name'],
                            'goods_remark' => $gift['goods']['goods_remark'] ?? $gift['goods']['spec_key_name'] ?? '',
                            'original_img' => $gift['goods']['original_img'],
                            'spec_key_name' => $gift['goods']['spec_key_name'] ?? ''
                        ]]
                    ];
                }
            }
            $extraGoods = []; // 加价购列表
            if (!empty($payReturn['extra_goods_list'])) {
                foreach ($payReturn['extra_goods_list'] as $key => $extra) {
                    $extraGoods[$key] = [
                        'goods_id' => $extra['goods_id'],
                        'goods_name' => $extra['goods_name'],
                        'goods_remark' => $extra['goods_remark'] ?? '',
                        'original_img' => $extra['original_img'],
                        'shop_price' => $extra['goods_price'],
                        'exchange_integral' => '0',
                        'exchange_price' => $extra['goods_price'],
                        'store_count' => $extra['goods_num'],
                        'buy_limit' => $extra['buy_limit'],
                        'pay_type' => 2
                    ];
                    if ($extra['can_integral'] == 1) {
                        // 能够使用积分
                        $extraGoods[$key]['shop_price'] = bcsub($extra['goods_price'], $extra['exchange_integral'], 2);
                        $extraGoods[$key]['exchange_price'] = bcsub($extra['goods_price'], $extra['exchange_integral'], 2);
                        $extraGoods[$key]['exchange_integral'] = $extra['exchange_integral'];
                        $extraGoods[$key]['pay_type'] = 1;
                    }
                }
            }
        } catch (TpshopException $tpE) {
            return json($tpE->getErrorArr());
        }
        // 组合数据
        $return = [
            // 用户地址
            'user_address' => $userAddress,
            // 提货
            'delivery' => [
                'way' => [
                    ['id' => 1, 'name' => '邮寄']
                ],
                'self_pick' => []
            ],
            // 商品列表 赠品列表
            'order_goods' => [
                'type' => 1,
                'type_value' => '乐活优选',
                'goods_list' => array_values($goodsList),
                'gift_list' => array_values($giftList)
            ],
            // 促销标题列表
            'prom_title_data' => !empty($payReturn['prom_title_data']) ? $payReturn['prom_title_data'][0]['type_value'] : '',
            // 加价购商品
            'extra_goods' => $extraGoods,
            // 兑换券商品
            'exchange_goods' => $exchangeGoods,
            // 优惠券 兑换券
            'coupon_list' => $couponList,
            'exchange_list' => $exchangeList,
            // 价格
            'user_electronic' => $this->user['user_electronic'],
            'weight' => $weight . 'g',
            'goods_fee' => $payReturn['goods_price'],
            'shipping_price' => $payReturn['shipping_price'],
            'coupon_price' => $payReturn['coupon_price'],
            'prom_price' => $payReturn['order_prom_amount'],
            'electronic_price' => $payReturn['user_electronic'],
            'pay_points' => $payReturn['pay_points'],
            'order_amount' => $payReturn['order_amount'],
            'electronic_limit' => $payReturn['order_amount'],
            'spare_pay_points' => bcsub($this->user['pay_points'], $payReturn['pay_points'], 2),
            'give_integral' => $give_integral,
            'free_shipping_price' => tpCache('shopping.freight_free') <= $payReturn['order_amount'] ? '0' : bcsub(tpCache('shopping.freight_free'), $payReturn['order_amount'], 2),
            'order_pv' => $payReturn['order_pv'] != '0.00' ? $payReturn['order_pv'] : '',
            // 韩国购信息
            'abroad' => $abroad,
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 获取提交订单前的信息（下部分）
     * @return \think\response\Json
     */
    public function orderBeforeInfo2()
    {
        $goodsId = I('goods_id', '');           // 商品ID
        $itemId = I('item_id', '');             // 商品规格ID
        $goodsNum = I('goods_num', '');         // 商品数量
        $payType = input('pay_type', 1);        // 结算类型
        $cartIds = I('cart_ids', '');           // 购物车ID组合
        $couponId = I('coupon_id', 0);          // 优惠券ID
        $exchangeId = I('exchange_id', 0);      // 兑换券ID
        $addressId = I('address_id', '');       // 地址ID
        $userElectronic = I('user_electronic', '');     // 使用电子币
        $extraGoods = isset(I('post.')['extra_goods']) ? I('post.')['extra_goods'] : [];     // 加价购商品

        if (!$addressId) {
            // 用户默认地址
            $userAddress = get_user_address_list_new($this->user_id, true);
            if (!empty($userAddress)) {
                $userAddress = $userAddress[0];
            }
        } else {
            $userAddress = Db::name('UserAddress')->where('address_id', $addressId)->find();
            if (empty($userAddress)) {
                return json(['status' => 0, 'msg' => '收货人信息不存在']);
            }
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        // 获取订单商品数据
        $goodsLogic = new GoodsLogic();
        $res = $goodsLogic->getOrderGoodsData($cartLogic, $goodsId, $itemId, $goodsNum, $payType, $cartIds, $this->isApp);
        if ($res['status'] != 1) {
            return json($res);
        } else {
            $cartList['cartList'] = $res['result'];
        }

        // 初始化数据 商品总额/节约金额/商品总共数量/商品使用积分
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);
        $cartList = array_merge($cartList, $cartPriceInfo);

        if ($this->user['distribut_level'] >= 3) {
            // 计算商品pv
            $cartList['cartList'] = $cartLogic->calcGoodsPv($cartList['cartList']);
        }

        try {
            $payLogic = new Pay();
            // 设置支付用户ID
            $payLogic->setUserId($this->user_id);
            // 计算购物车价格
            $payLogic->payCart($cartList['cartList']);
            // 检测支付商品购买限制
            $payLogic->check();
            // 参与活动促销
            $payLogic->goodsPromotion();
            // 加价购活动
            $payLogic->activityPayBeforeNew($extraGoods, $cartLogic);

            // 使用积分
            $pay_points = $payLogic->getUsePoint();
            if ($this->user['pay_points'] < $pay_points) {
                return json(['status' => 0, 'msg' => '用户消费积分只有' . $this->user['pay_points']]);
            }
            $payLogic->usePayPoints($pay_points);

            $give_integral = '0';             // 赠送积分
            $weight = '0';                    // 产品重量
            foreach ($cartList['cartList'] as $v) {
                $goodsInfo = M('Goods')->field('give_integral, weight')->where('goods_id', $v['goods_id'])->find();
                $give_integral = bcadd($give_integral, bcmul($goodsInfo['give_integral'], $v['goods_num'], 2), 2);
                $weight = bcadd($weight, $goodsInfo['weight'], 2);
            }
            $exchangeGoods = [];
            if (!empty($exchangeId) && $exchangeId > 0) {
                // 兑换券商品 兑换券商品积分
                $exchangeGoods = M('goods_coupon gc')->join('goods g', 'g.goods_id = gc.goods_id')
                    ->where(['gc.coupon_id' => $exchangeId])->field('g.goods_id, g.goods_name, g.goods_remark, g.original_img, g.give_integral, gc.number')->select();
                foreach ($exchangeGoods as $key => $goods) {
                    $give_integral = bcadd($give_integral, bcmul($goods['give_integral'], $goods['number'], 2), 2);
                    $exchangeGoods[$key]['goods_num'] = 1;
                    unset($exchangeGoods[$key]['give_integral']);
                }
            }

            $payLogic->activity3();         // 订单优惠促销

            // 使用优惠券
            if (!empty($couponId) && $couponId > 0) {
                $payLogic->useCouponById($couponId, $payLogic->getPayList());
            }

            $payLogic->activity(true);      // 满单赠品
            $payLogic->activity2New();      // 指定商品赠品 / 订单优惠赠品

            // 配送物流
            $payLogic->delivery($userAddress['district']);
            if (isset($res['status']) && $res['status'] == -1) {
                return json(['status' => 0, 'msg' => '订单中部分商品不支持对当前地址的配送']);
            }
            // 订单pv
            $payLogic->setOrderPv();
            // 使用电子币
            $payLogic->useUserElectronic($userElectronic);

            // 支付数据
            $payReturn = $payLogic->toArray();

            // 商品列表 赠品列表 加价购列表
            $payList = collection($payLogic->getPayList())->toArray();
            $goodsList = [];    // 商品列表
            $extraGoodsIds = $payLogic->getExtraGoodsIds();
            foreach ($payList as $k => $list) {
                if (in_array($list['goods_id'], $extraGoodsIds)) {
                    continue;
                }
                $goods = $list['goods'];
                $goodsList[$k] = [
                    'goods_id' => $goods['goods_id'],
                    'goods_sn' => $goods['goods_sn'],
                    'goods_name' => $goods['goods_name'],
                    'goods_remark' => $goods['goods_remark'] ?? $goods['spec_key_name'] ?? '',
                    'spec_key_name' => $goods['spec_key_name'] ?? '',
                    'original_img' => $goods['original_img'],
                    'goods_num' => $list['goods_num'],
                    'shop_price' => $goods['shop_price'],
                    'exchange_integral' => $list['use_integral'] ?? 0,
                    'exchange_price' => in_array($list['prom_type'], [1, 2]) ? bcadd($list['member_goods_price'], 0, 2) : '',
                    'prom_type' => $list['prom_type'] ?? 0,
                    'gift_goods' => [],
                ];
                // 处理显示金额
                if (!in_array($list['prom_type'], [1, 2])) {
                    if ($list['use_integral'] != '0') {
                        $goodsList[$k]['exchange_price'] = bcdiv(bcsub(bcmul($list['goods']['shop_price'], 100), bcmul($list['use_integral'], 100)), 100, 2);
                    } else {
                        $goodsList[$k]['exchange_price'] = $list['goods']['shop_price'];
                    }
                }
                if (isset($list['gift_goods'])) {
                    $goodsList[$k]['gift_goods'] = $list['gift_goods'];
                }
            }
            $giftList = $payLogic->getPromGiftList();   // 订单优惠促销赠品
            $giftGoodsList = $payReturn['gift_goods_list']; // 满单赠品
            if (!empty($giftGoodsList)) {
                foreach ($giftGoodsList as $gift) {
                    $giftList[] = [
                        'prom_id' => $gift['gift_reward_id'],
                        'title' => $gift['gift_description'],
                        'goods_list' => [[
                            'goods_id' => $gift['goods_id'],
                            'item_id' => $gift['item_id'] ?? '',
                            'goods_num' => $gift['goods_num'],
                            'goods_sn' => $gift['goods_sn'],
                            'goods_name' => $gift['goods_name'],
                            'goods_remark' => $gift['goods']['goods_remark'] ?? $gift['goods']['spec_key_name'] ?? '',
                            'original_img' => $gift['goods']['original_img'],
                            'spec_key_name' => $gift['goods']['spec_key_name'] ?? ''
                        ]]
                    ];
                }
            }
        } catch (TpshopException $tpE) {
            return json($tpE->getErrorArr());
        }
        // 组合数据
        $return = [
            // 商品列表 赠品列表
            'order_goods' => [
                'type' => 1,
                'type_value' => '乐活优选',
                'goods_list' => array_values($goodsList),
                'gift_list' => array_values($giftList)
            ],
            // 促销标题列表
            'prom_title_data' => !empty($payReturn['prom_title_data']) ? $payReturn['prom_title_data'][0]['type_value'] : '',   // 促销标题列表
            // 兑换券商品
            'exchange_goods' => $exchangeGoods,
            'user_electronic' => $this->user['user_electronic'],
            'weight' => $weight . 'g',
            'goods_fee' => $payReturn['goods_price'],
            'shipping_price' => $payReturn['shipping_price'],
            'coupon_price' => $payReturn['coupon_price'],
            'prom_price' => $payReturn['order_prom_amount'],
            'electronic_price' => $payReturn['user_electronic'],
            'pay_points' => $payReturn['pay_points'],
            'order_amount' => $payReturn['order_amount'],
            'electronic_limit' => bcadd($payReturn['order_amount'], $payReturn['user_electronic'], 2),
            'spare_pay_points' => bcsub($this->user['pay_points'], $payReturn['pay_points'], 2),
            'give_integral' => $give_integral,
            'free_shipping_price' => tpCache('shopping.freight_free') <= $payReturn['order_amount'] ? '0' : bcsub(tpCache('shopping.freight_free'), $payReturn['order_amount'], 2),
            'order_pv' => $payReturn['order_pv'] != '0.00' ? $payReturn['order_pv'] : ''
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 创建订单
     * @return \think\response\Json
     * @throws TpshopException
     * @throws \think\Exception
     */
    public function createOrder()
    {
        $goodsId = I('goods_id', '');                   // 商品ID
        $itemId = I('item_id', '');                     // 商品规格ID
        $goodsNum = I('goods_num', '');                 // 商品数量
        $payType = input('pay_type', 1);                // 结算类型
        $cartIds = I('cart_ids', '');                   // 购物车ID组合
        $addressId = I('address_id', '');               // 地址ID
        $couponId = I('coupon_id', '');                 // 优惠券ID
        $exchangeId = I('exchange_id', '');             // 兑换券ID
        $payPwd = I('pay_pwd', '');                     // 支付密码
        $userElectronic = I('user_electronic', '');     // 使用电子币
        $extraGoods = isset(I('post.')['extra_goods']) ? I('post.')['extra_goods'] : [];     // 加价购商品
        $userNote = I('user_note', '');                 // 用户备注
        $idCard = I('id_card', 0);

        if (!$this->user['paypwd']) {
            return json(['status' => 0, 'msg' => '请先设置支付密码']);
        }
        if (!$addressId) {
            return json(['status' => 0, 'msg' => '请先填写收货人信息']);
        }
        $userAddress = Db::name('UserAddress')->where('address_id', $addressId)->find();
        if (empty($userAddress)) {
            return json(['status' => 0, 'msg' => '收货人信息不存在']);
        }
        if (strlen($userNote) > 50) {
            return json(['status' => 0, 'msg' => '备注超出限制可输入字符长度']);
        }
        if ($idCard != 0 && !check_id_card($idCard)) {
            return json(['status' => 0, 'msg' => '请填写正确的身份证格式']);
        }

        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        // 获取订单商品数据
        $goodsLogic = new GoodsLogic();
        $res = $goodsLogic->getOrderGoodsData($cartLogic, $goodsId, $itemId, $goodsNum, $payType, $cartIds, $this->isApp);
        if ($res['status'] != 1) {
            return json($res);
        } else {
            $cartList['cartList'] = $res['result'];
        }

        // 检查下单商品
        $res = $cartLogic->checkCartGoods($cartList['cartList']);
        $orderType = 1; // 圃美多
        switch ($res['status']) {
            case 0:
                return json($res);
            case 2:
                $orderType = 2; // 韩国购
                break;
        }

        // 初始化数据 商品总额/节约金额/商品总共数量/商品使用积分
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);
        $cartList = array_merge($cartList, $cartPriceInfo);

        if ($this->user['distribut_level'] >= 3) {
            // 计算商品pv
            $cartList['cartList'] = $cartLogic->calcGoodsPv($cartList['cartList']);
        }

        try {
            $payLogic = new Pay();
            $payLogic->setUserId($this->user_id);   // 设置支付用户ID
            // 计算购物车价格
            $payLogic->payCart($cartList['cartList']);
            // 检测支付商品购买限制
            $payLogic->check();
            // 参与活动促销
            $payLogic->goodsPromotion();
            // 加价购活动
            $payLogic->activityPayBeforeNew($extraGoods, $cartLogic);

            // 使用积分
            $pay_points = $payLogic->getUsePoint();
            if ($this->user['pay_points'] < $pay_points) {
                return json(['status' => 0, 'msg' => '用户消费积分只有' . $this->user['pay_points']]);
            }
            $payLogic->usePayPoints($pay_points);

            $payLogic->activity3();         // 订单优惠促销

            // 使用优惠券
            if (isset($couponId) && $couponId > 0) {
                $payLogic->useCouponById($couponId, $payLogic->getPayList());
            }
            // 使用兑换券
            if (isset($exchangeId) && $exchangeId > 0) {
                $payLogic->useCouponByIdRe($exchangeId);
            }

            $payLogic->activity(true);      // 满单赠品
            $payLogic->activity2New();      // 指定商品赠品 / 订单优惠赠品

            // 配送物流
            $res = $payLogic->delivery($userAddress['district']);
            if (isset($res['status']) && $res['status'] == -1) {
                return json(['status' => 0, 'msg' => '订单中部分商品不支持对当前地址的配送']);
            }
            // 订单pv
            $payLogic->setOrderPv();
            // 使用电子币
            $payLogic->useUserElectronic($userElectronic);
        } catch (TpshopException $tpE) {
            return json($tpE->getErrorArr());
        }
        // 创建订单
        list($prom_type, $prom_id) = $payLogic->getPromInfo();
        try {
            $placeOrder = new PlaceOrder($payLogic);
            $placeOrder->setUser($this->user);
            $placeOrder->setPayPsw($payPwd);
            $placeOrder->setUserAddress($userAddress);
            $placeOrder->setUserNote($userNote);
            $placeOrder->setUserIdCard($idCard);
            $placeOrder->setOrderType($orderType);
            Db::startTrans();
            if (2 == $prom_type) {
                $placeOrder->addGroupBuyOrder($prom_id, 3);    // 团购订单
            } else {
                $placeOrder->addNormalOrder(3);     // 普通订单
            }
            if (empty($goodsId) && !empty($cartIds)) {
                // 清除选中的购物车
                $cartLogic->clear();
            }
            $order = $placeOrder->getOrder();
            $payLogic->activityRecord($order);  // 记录
            $return = [
                'order_id' => $order['order_id'],
                'order_sn' => $order['order_sn'],
            ];
            $orderPayStatus = M('order')->where(['order_id' => $return['order_id'], 'order_sn' => $return['order_sn']])->value('pay_status');
            if ($orderPayStatus == 1) {
                Db::commit();
                return json(['status' => 11, 'msg' => '创建订单成功', 'result' => $return]);
            }

            // 获取支付方式
            $payment_where = [
                'type' => 'payment',
                'status' => 1,
                'scene' => 3,   // APP支付
            ];
            $paymentList = M('Plugin')->field('code, name, icon')->where($payment_where)->select();
            foreach ($paymentList as $key => $val) {
                $paymentList[$key]['icon'] = '/plugins/payment/' . $val['code'] . '/logo.jpg';
            }
            $return['payment_list'] = $paymentList;
            Db::commit();
            return json(['status' => 1, 'msg' => '创建订单成功', 'result' => $return]);
        } catch (TpshopException $tpE) {
            Db::rollback();
            return json(['status' => $tpE->getErrorArr()['status'], 'msg' => $tpE->getErrorArr()['msg'] ?? '创建订单失败']);
        }
    }

    /**
     * 获取订单支付方式
     * @return \think\response\Json
     */
    public function orderPayment()
    {
        $orderSn = I('order_sn', '');
        if (empty($orderSn)) {
            return json(['status' => 0, 'msg' => '订单编号不能为空']);
        }
        $order = Db::name('order')->where(['order_sn' => $orderSn])->field('order_id, order_sn, order_status, pay_status')->find();
        if (empty($order)) {
            return json(['status' => 0, 'msg' => '订单不存在']);
        }
        if (3 == $order['order_status']) {
            return json(['status' => 0, 'msg' => '该订单已取消']);
        }
        if (1 == $order['pay_status']) {
            return json(['status' => 0, 'msg' => '订单已经完成支付']);
        }
        $return = [
            'order_id' => $order['order_id'],
            'order_sn' => $order['order_sn'],
        ];
        // 获取支付方式
        $payment_where = [
            'type' => 'payment',
            'status' => 1,
            'scene' => 3,   // APP支付
        ];
        $paymentList = M('Plugin')->field('code, name, icon')->where($payment_where)->select();
        foreach ($paymentList as $key => $val) {
            $paymentList[$key]['icon'] = '/plugins/payment/' . $val['code'] . '/logo.jpg';
        }
        $return['payment_list'] = $paymentList;
        return json(['status' => 1, 'msg' => '', 'result' => $return]);
    }

    /**
     * 获取订单支付状态信息
     * @return \think\response\Json
     */
    public function orderPayStatus()
    {
        $orderId = I('order_id', '');
        if (empty($orderId)) {
            return json(['status' => 0, 'msg' => '订单编号不能为空']);
        }
        $order = Db::name('order o')->join('region2 r1', 'r1.id = o.province', 'LEFT')
            ->join('region2 r2', 'r2.id = o.city', 'LEFT')
            ->join('region2 r3', 'r3.id = o.district', 'LEFT')
            ->where(['order_id' => $orderId])->field('order_id, order_sn, user_id, order_status, pay_status, order_amount,
            consignee, mobile, r1.name province_name, city, r2.name city_name, district, r3.name district_name, address')->find();
        if (empty($order)) {
            return json(['status' => 0, 'msg' => '订单不存在']);
        }
        if (3 == $order['order_status']) {
            return json(['status' => 0, 'msg' => '该订单已取消']);
        }
        if ($order['city_name'] == '直辖市') {
            $order['city_name'] = '';
        }
        // 订单赠送优惠券
        $order['coupon'] = [
            'have_coupon' => 0,
            'user_type_name' => '',
            'coupon_name' => ''
        ];
        // 用户支付后处理
        $order['action_after_pay'] = [
            'update_jpush_tags' => []
        ];
        if ($order['pay_status'] == 1) {
            // 查看订单商品里面是否有vip升级套餐，有就显示赠送的优惠券
            $levelUp = false;
            $orderGoods = M('order_goods og')->join('goods g', 'g.goods_id = og.goods_id')->where(['og.order_id' => $orderId])->field('zone, distribut_id')->select();
            foreach ($orderGoods as $goods) {
                if (3 == $goods['zone'] && $goods['distribut_id'] > 0) {
                    $levelUp = true;
                    break;
                }
            }
            if ($levelUp) {
                // 查看是否有赠送优惠券
                $hasCoupon = M('coupon_list')->where(['uid' => $order['user_id'], 'get_order_id' => $order['order_id']])->value('cid');
                if (!empty($hasCoupon)) {
                    $order['coupon'] = [
                        'have_coupon' => 1,
                        'user_type_name' => 'VIP套餐用户',
                        'coupon_name' => '新晋VIP会员优惠券'
                    ];
                }
                // 变更用户push_tags
                $order['action_after_pay'] = [
                    'update_jpush_tags' => explode(',', $this->user['push_tag'])
                ];
            }
        }
        unset($order['order_status']);
        unset($order['user_id']);
        return json(['status' => 1, 'result' => $order]);
    }

    /**
     * 查询物流
     * @return \think\response\Json
     */
    public function express()
    {
        $orderId = I('order_id', '');
        $docId = I('doc_id', '');
        if ($orderId) {
            $where = ['dd.order_id' => $orderId];
            $order = M('order')->where(['order_id' => $orderId])->find();
        } elseif ($docId) {
            $where = ['dd.id' => $docId];
            $order = M('delivery_doc dd')->join('order o', 'o.order_id = dd.order_id')->where($where)->field('o.*')->find();
        } else {
            return json(['status' => 0, 'msg' => '订单信息不存在']);
        }
        if (empty($order)) {
            return json(['status' => 0, 'msg' => '订单信息不存在']);
        }
        if ($orderId) {
            switch ($order['delivery_type']) {
                case 1:
                    //--- 统一发货
                    // 订单商品
                    $orderGoods = M('order_goods og')->join('goods g', 'g.goods_id = og.goods_id')->where(['og.order_id' => $orderId])->field('g.goods_id, g.original_img')->find();
                    switch ($order['shipping_status']) {
                        case 0:
                        case 3:
                            return json(['status' => 1, 'result' => [
                                'delivery_status' => -1,    // 未发货
                                'delivery_status_desc' => C('DELIVERY_STATUS')[-1],
                                'order_id' => $order['order_id'],
                                'order_sn' => $order['order_sn'],
                                'shipping_name' => '',
                                'invoice_no' => '',
                                'goods_id' => $orderGoods['goods_id'],
                                'original_img' => SITE_URL . $orderGoods['original_img'],
                                'service_phone' => tpCache('shop_info.mobile'),
                                'province' => '',
                                'city' => '',
                                'district' => '',
                                'address' => '',
                                'express' => []
                            ]]);
                    }
                    // 物流消息
                    $delivery = M('delivery_doc dd')
                        ->field('dd.*')
                        ->where($where)->order('id desc')->find();
                    switch ($order['order_type']) {
                        case 1:
                            // 圃美多
                            $apiController = new ApiController();
                            $express = $apiController->queryExpress(['shipping_code' => $delivery['shipping_code'], 'queryNo' => $delivery['invoice_no']], 'array');
                            if ($express['status'] != '0') {
                                $express['result']['deliverystatus'] = 1;   // 正在派件
                                $express['result']['expPhone'] = tpCache('shop_info.mobile');
                                $express['result']['list'][] = [
                                    'time' => date('Y-m-d H:i:s', time()),
                                    'status' => '暂无物流信息'
                                ];
                            }
                            $deliveryStatus = $express['result']['deliverystatus'];
                            $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                            break;
                        case 2:
                            // 韩国购
                            // HTNS物流配送记录
                            $htnsDeliveryLogGoodsName = M('htns_delivery_log')->where(['order_id' => $orderId])->value('goods_name');
                            $htnsDeliveryLog = M('htns_delivery_log')->where(['order_id' => $orderId, 'goods_name' => $htnsDeliveryLogGoodsName])->order('create_time desc')->select();
                            $deliveryLog = [];
                            foreach ($htnsDeliveryLog as $log) {
                                $deliveryLog[] = [
                                    'time' => date('Y-m-d H:i:s', $log['create_time']),
                                    'status' => '第三方物流公司：' . C('HTNS_STATUS')[$log['status']]
                                ];
                            }
                            if (in_array($delivery['htns_status'], ['000', '120', '999'])) {
                                $apiController = new ApiController();
                                $express = $apiController->queryExpress(['shipping_code' => $delivery['shipping_code'], 'queryNo' => $delivery['invoice_no']], 'array');
                                if ($express['status'] != '0') {
                                    $express['result']['deliverystatus'] = 1;   // 正在派件
                                    $express['result']['expPhone'] = tpCache('shop_info.mobile');
                                    $express['result']['list'][] = [
                                        'time' => date('Y-m-d H:i:s', time()),
                                        'status' => '暂无物流信息'
                                    ];
                                }
                                $deliveryStatus = $express['result']['deliverystatus'];
                                $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                            } elseif (in_array($delivery['htns_status'], ['001', '991'])) {
                                $express['result']['deliverystatus'] = 4;       // 配送失败
                                $express['result']['expPhone'] = tpCache('shop_info.mobile');
                                $express['result']['list'][] = [
                                    'time' => date('Y-m-d H:i:s', time()),
                                    'status' => '暂无物流信息'
                                ];
                                $deliveryStatus = $express['result']['deliverystatus'];
                                $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                            } else {
                                $express['result']['deliverystatus'] = 1;       // 正在派件
                                $express['result']['expPhone'] = tpCache('shop_info.mobile');
                                $deliveryStatus = $express['result']['deliverystatus'];
                                $deliveryStatusDesc = '第三方物流公司正在处理';
                            }
                            if (!empty($deliveryLog)) {
                                foreach ($deliveryLog as $log) {
                                    $express['result']['list'][] = $log;
                                }
                            }
                            break;
                    }
                    $return = [
                        'delivery_status' => $deliveryStatus,
                        'delivery_status_desc' => $deliveryStatusDesc,
                        'order_id' => $order['order_id'],
                        'order_sn' => $order['order_sn'],
                        'shipping_name' => $delivery['shipping_name'],
                        'invoice_no' => $delivery['invoice_no'],
                        'goods_id' => $orderGoods['goods_id'],
                        'original_img' => SITE_URL . $orderGoods['original_img'],
                        'service_phone' => $express['result']['expPhone'],
                        'province' => Db::name('region2')->where(['id' => $delivery['province']])->value('name'),
                        'city' => Db::name('region2')->where(['id' => $delivery['city']])->value('name'),
                        'district' => Db::name('region2')->where(['id' => $delivery['district']])->value('name'),
                        'address' => $delivery['address'],
                        'express' => $express['result']['list']
                    ];
                    break;
                case 2:
                    //--- 分开发货
                    $return = [
                        'order_id' => $order['order_id'],
                        'order_goods_num' => M('delivery_doc')->where(['order_id' => $order['order_id']])->count('rec_id'),
                        'delivery' => []
                    ];
                    switch ($order['shipping_status']) {
                        case 0:
                        case 3:
                            // 订单商品
                            $orderGoods = M('order_goods og')->join('goods g', 'g.goods_id = og.goods_id')->where(['og.order_id' => $orderId])->field('g.goods_id, g.original_img')->find();
                            $return['delivery'][] = [
                                'rec_id' => '',
                                'doc_id' => '',
                                'status' => -1, // 未发货
                                'status_desc' => C('DELIVERY_STATUS')[-1],
                                'shipping_name' => '',
                                'invoice_no' => '',
                                'express' => [],
                                'goods_id' => $orderGoods['goods_id'],
                                'original_img' => SITE_URL . $orderGoods['original_img'],
                            ];
                            return json(['status' => 1, 'result' => $return]);
                    }
                    $delivery = M('delivery_doc dd')->join('order_goods og', 'og.rec_id = dd.rec_id')
                        ->join('goods g', 'g.goods_id = og.goods_id')
                        ->field('dd.*, dd.id doc_id, g.goods_id, g.original_img')
                        ->where($where)->select();
                    switch ($order['order_type']) {
                        case 1:
                            // 圃美多
                            $apiController = new ApiController();
                            foreach ($delivery as $item) {
                                $express = $apiController->queryExpress(['shipping_code' => $item['shipping_code'], 'queryNo' => $item['invoice_no']], 'array');
                                if ($express['status'] != '0') {
                                    $express['result']['deliverystatus'] = 1;   // 正在派件
                                    $express['result']['list'][] = [
                                        'time' => date('Y-m-d H:i:s', time()),
                                        'status' => '暂无物流信息'
                                    ];
                                }
                                $return['delivery'][] = [
                                    'rec_id' => $item['rec_id'],
                                    'doc_id' => $item['doc_id'],
                                    'status' => $express['result']['deliverystatus'],
                                    'status_desc' => C('DELIVERY_STATUS')[$express['result']['deliverystatus']],
                                    'shipping_name' => $item['shipping_name'],
                                    'invoice_no' => $item['invoice_no'],
                                    'express' => $express['result']['list'][0],
                                    'goods_id' => $item['goods_id'],
                                    'original_img' => SITE_URL . $item['original_img'],
                                ];
                            }
                            break;
                        case 2:
                            // 韩国购
                            $apiController = new ApiController();
                            foreach ($delivery as $item) {
                                $express['result']['list'] = [];
                                // HTNS物流配送记录
                                $htnsDeliveryLog = M('htns_delivery_log')->where(['order_id' => $orderId, 'rec_id' => $item['rec_id'], 'goods_num' => $item['goods_num']])->order('create_time DESC')->select();
                                $deliveryLog = [];
                                foreach ($htnsDeliveryLog as $log) {
                                    $deliveryLog[] = [
                                        'time' => date('Y-m-d H:i:s', $log['create_time']),
                                        'status' => '第三方物流公司：' . C('HTNS_STATUS')[$log['status']]
                                    ];
                                }
                                if (in_array($item['htns_status'], ['000', '120', '999'])) {
                                    $express = $apiController->queryExpress(['shipping_code' => $item['shipping_code'], 'queryNo' => $item['invoice_no']], 'array');
                                    if ($express['status'] != '0') {
                                        $express['result']['deliverystatus'] = 1;   // 正在派件
                                        $express['result']['list'][] = [
                                            'time' => date('Y-m-d H:i:s', time()),
                                            'status' => '暂无物流信息'
                                        ];
                                    }
                                    $deliveryStatus = $express['result']['deliverystatus'];
                                    $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                                } elseif (in_array($item['htns_status'], ['001', '991'])) {
                                    $express['result']['deliverystatus'] = 4;   // 配送失败
                                    $express['result']['list'][] = [
                                        'time' => date('Y-m-d H:i:s', time()),
                                        'status' => '暂无物流信息'
                                    ];
                                    $deliveryStatus = $express['result']['deliverystatus'];
                                    $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                                } else {
                                    $express['result']['deliverystatus'] = 1;       // 正在派件
                                    $express['result']['expPhone'] = tpCache('shop_info.mobile');
                                    $deliveryStatus = $express['result']['deliverystatus'];
                                    $deliveryStatusDesc = '第三方物流公司正在配送';
                                }
                                if (!empty($deliveryLog)) {
                                    foreach ($deliveryLog as $log) {
                                        $express['result']['list'][] = $log;
                                    }
                                }
                                $return['delivery'][] = [
                                    'rec_id' => $item['rec_id'],
                                    'doc_id' => $item['doc_id'],
                                    'status' => $deliveryStatus,
                                    'status_desc' => $deliveryStatusDesc,
                                    'shipping_name' => $item['shipping_name'],
                                    'invoice_no' => $item['invoice_no'],
                                    'express' => $express['result']['list'][0],
                                    'goods_id' => $item['goods_id'],
                                    'original_img' => SITE_URL . $item['original_img'],
                                ];
                            }
                            break;
                    }
                    break;
                default:
                    return json(['status' => 0, 'msg' => '参数错误']);
            }
        } elseif ($docId) {
            //--- 订单商品单独物流信息
            // 订单商品
            $orderGoods = M('delivery_doc dd')->join('order_goods og', 'og.rec_id = dd.rec_id')->join('goods g', 'g.goods_id = og.goods_id')
                ->where($where)->field('g.goods_id, g.original_img')->find();
            switch ($order['shipping_status']) {
                case 0:
                case 3:
                    return json(['status' => 1, 'result' => [
                        'delivery_status' => -1,    // 未发货
                        'delivery_status_desc' => C('DELIVERY_STATUS')[-1],
                        'order_id' => $order['order_id'],
                        'order_sn' => $order['order_sn'],
                        'shipping_name' => '',
                        'invoice_no' => '',
                        'goods_id' => $orderGoods['goods_id'],
                        'original_img' => SITE_URL . $orderGoods['original_img'],
                        'service_phone' => tpCache('shop_info.mobile'),
                        'province' => '',
                        'city' => '',
                        'district' => '',
                        'address' => '',
                        'express' => []
                    ]]);
            }
            // 物流信息
            $delivery = M('delivery_doc dd')
                ->field('dd.*')
                ->where($where)->order('id desc')->find();
            switch ($order['order_type']) {
                case 1:
                    // 圃美多
                    $apiController = new ApiController();
                    $express = $apiController->queryExpress(['shipping_code' => $delivery['shipping_code'], 'queryNo' => $delivery['invoice_no']], 'array');
                    if ($express['status'] != '0') {
                        $express['result']['deliverystatus'] = 1;   // 正在派件
                        $express['result']['expPhone'] = tpCache('shop_info.mobile');
                        $express['result']['list'][] = [
                            'time' => date('Y-m-d H:i:s', time()),
                            'status' => '暂无物流信息'
                        ];
                    }
                    $deliveryStatus = $express['result']['deliverystatus'];
                    $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                    break;
                case 2:
                    // 韩国购
                    // HTNS物流配送记录
                    $htnsDeliveryLog = M('htns_delivery_log')->where(['order_id' => $delivery['order_id'], 'rec_id' => $delivery['rec_id'], 'goods_num' => $delivery['goods_num']])->order('create_time DESC')->select();
                    $deliveryLog = [];
                    foreach ($htnsDeliveryLog as $log) {
                        $deliveryLog[] = [
                            'time' => date('Y-m-d H:i:s', $log['create_time']),
                            'status' => '第三方物流公司：' . C('HTNS_STATUS')[$log['status']]
                        ];
                    }
                    if (in_array($delivery['htns_status'], ['000', '120', '999'])) {
                        $apiController = new ApiController();
                        $express = $apiController->queryExpress(['shipping_code' => $delivery['shipping_code'], 'queryNo' => $delivery['invoice_no']], 'array');
                        if ($express['status'] != '0') {
                            $express['result']['deliverystatus'] = 1;   // 正在派件
                            $express['result']['expPhone'] = tpCache('shop_info.mobile');
                            $express['result']['list'][] = [
                                'time' => date('Y-m-d H:i:s', time()),
                                'status' => '暂无物流信息'
                            ];
                        }
                        $deliveryStatus = $express['result']['deliverystatus'];
                        $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                    } elseif (in_array($delivery['htns_status'], ['001', '991'])) {
                        $express['result']['deliverystatus'] = 4;       // 配送失败
                        $express['result']['expPhone'] = tpCache('shop_info.mobile');
                        $express['result']['list'][] = [
                            'time' => date('Y-m-d H:i:s', time()),
                            'status' => '暂无物流信息'
                        ];
                        $deliveryStatus = $express['result']['deliverystatus'];
                        $deliveryStatusDesc = C('DELIVERY_STATUS')[$express['result']['deliverystatus']];
                    } else {
                        $express['result']['deliverystatus'] = 1;       // 正在派件
                        $express['result']['expPhone'] = tpCache('shop_info.mobile');
                        $deliveryStatus = $express['result']['deliverystatus'];
                        $deliveryStatusDesc = '第三方物流公司正在处理';
                    }
                    if (!empty($deliveryLog)) {
                        foreach ($deliveryLog as $log) {
                            $express['result']['list'][] = $log;
                        }
                    }
                    break;
            }
            $return = [
                'delivery_status' => $deliveryStatus,
                'delivery_status_desc' => $deliveryStatusDesc,
                'order_id' => $order['order_id'],
                'order_sn' => $order['order_sn'],
                'shipping_name' => $delivery['shipping_name'],
                'invoice_no' => $delivery['invoice_no'],
                'goods_id' => $orderGoods['goods_id'],
                'original_img' => SITE_URL . $orderGoods['original_img'],
                'service_phone' => $express['result']['expPhone'],
                'province' => Db::name('region2')->where(['id' => $delivery['province']])->value('name'),
                'city' => Db::name('region2')->where(['id' => $delivery['city']])->value('name'),
                'district' => Db::name('region2')->where(['id' => $delivery['district']])->value('name'),
                'address' => $delivery['address'],
                'express' => $express['result']['list']
            ];
        }
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 订单数量统计
     * @return \think\response\Json
     */
    public function getOrderNum()
    {
        $return = [
            'WAITPAY' => 0,
            'WAITSEND' => 0,
            'WAITRECEIVE' => 0,
            'FINISH' => 0,
            'RETURN' => 0,
        ];
        // 订单数据
        $where = 'user_id = ' . $this->user_id . ' AND deleted != 1 AND prom_type < 5'; // 虚拟拼团订单不列出来
        $orderData = M('order')->where($where)->field('order_id, pay_status, order_status, shipping_status, pay_code')->select();
        foreach ($orderData as $order) {
            if ($order['pay_status'] == 0 && $order['order_status'] == 0 && $order['pay_code'] != 'cod') {
                $return['WAITPAY'] += 1;
            } elseif (($order['pay_status'] == 1 || $order['pay_code'] == 'cod') && $order['shipping_status'] != 1 && in_array($order['order_status'], [0, 1])) {
                $return['WAITSEND'] += 1;
            } elseif ($order['shipping_status'] == 1 && $order['order_status'] == 1) {
                $return['WAITRECEIVE'] += 1;
            } elseif ($order['order_status'] == 2 || $order['order_status'] == 4 || $order['order_status'] == 6) {
                $return['FINISH'] += 1;
            }
        }
        // 退换货数据
        $where = 'user_id = ' . $this->user_id . ' AND status != 6';
        $return['RETURN'] = M('return_goods')->where($where)->count('id');
        return json(['status' => 1, 'result' => $return]);
    }
}
