<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller;

use app\common\logic\MessageLogic;
use app\common\logic\OrderLogic;
use app\common\logic\VirtualLogic;
use think\Db;

class Virtual extends Base
{
    public $user_id = 0;
    public $user = [];

    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where('user_id', $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            $this->assign('user_id', $this->user_id);
            //获取用户信息的数量
            $messageLogic = new MessageLogic();
            $user_message_count = $messageLogic->getUserMessageCount();
            $this->assign('user_message_count', $user_message_count);
        } else {
            $nologin = [
                'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
                'verifyHandle', 'reg', 'send_sms_reg_code', 'identity', 'check_validate_code',
                'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code',
            ];
            if (!in_array(ACTION_NAME, $nologin)) {
                header('location:'.U('Home/User/login'));
                exit;
            }
            if (ACTION_NAME == 'password') {
                $_SERVER['HTTP_REFERER'] = U('Home/User/index');
            }
        }

        //用户中心面包屑导航
        $navigate_user = navigate_user();
        $this->assign('navigate_user', $navigate_user);
    }

    public function buy_virtual()
    {
        $goods = $this->check_virtual_goods();
        $this->assign('goods', $goods);

        return $this->fetch();
    }

    public function buy_step()
    {
        C('TOKEN_ON', false);
        $goods = $this->check_virtual_goods();
        $this->assign('goods', $goods);

        return $this->fetch();
    }

    public function buy_step2()
    {
        $order_id = I('order_id/d', 0);
        $order = M('Order')->where("order_id = $order_id")->find();
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if (1 == $order['pay_status']) {
            $order_detail_url = U('Home/Order/order_detail', ['id' => $order_id]);
            header("Location: $order_detail_url");
        }
        $payment_where = [
            'type' => 'payment',
            'status' => 1,
            'scene' => ['in', [0, 2]],
            'code' => ['neq', 'cod'],
        ];
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if (2 == $val['config_value']['is_bank']) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('order', $order);
        $this->assign('bankCodeList', $bankCodeList);
        $this->assign('pay_date', date('Y-m-d', strtotime('+1 day')));

        return $this->fetch();
    }

    public function add_order()
    {
        C('TOKEN_ON', false);
        $data = I('post.');
        $goods = $this->check_virtual_goods();
        $CartLogic = new OrderLogic();
        $goods_price = $goods['shop_price'] * $goods['goods_num'];
        $isbuyWhere = [
            'og.goods_id' => $data['goods_id'],
            'o.user_id' => $this->user_id,
            'o.deleted' => 0,
            'o.order_status' => ['neq', 3],
        ];
        $isbuy = M('order_goods')->alias('og')
            ->join(C('DB_PREFIX').'order o', 'og.order_id = o.order_id', 'LEFT')
            ->where($isbuyWhere)
            ->sum('og.goods_num');
        if (($goods['goods_num'] + $isbuy) > $goods['virtual_limit']) {
            $this->ajaxReturn(['status' => '-1', 'msg' => '您已超过该商品的限制购买数']);
        }

        $data['consignee'] = empty($this->user['nickname']) ? $this->user['realname'].$data['mobile'] : $this->user['nickname'];
        $orderArr = ['user_id' => $this->user_id, 'mobile' => $data['mobile'], 'user_note' => $data['user_note'],
                'order_sn' => $CartLogic->get_order_sn(), 'goods_price' => $goods_price, 'consignee' => $data['consignee'],
                'prom_type' => 5, 'add_time' => time(),
                'order_amount' => $goods_price, 'total_amount' => $goods_price, 'shipping_time' => $goods['virtual_indate'], //有效期限
        ];
        $order_id = M('order')->add($orderArr);

        $data2['order_id'] = $order_id; // 订单id
        $data2['goods_id'] = $goods['goods_id']; // 商品id
        $data2['goods_name'] = $goods['goods_name']; // 商品名称
        $data2['goods_sn'] = $goods['goods_sn']; // 商品货号
        $data2['goods_num'] = $goods['goods_num']; // 购买数量
        $data2['market_price'] = $goods['market_price']; // 市场价
        $data2['goods_price'] = $goods['shop_price']; // 商品价
        $data2['spec_key'] = $goods['goods_spec_key']; // 商品规格
        $data2['spec_key_name'] = $goods['spec_key_name']; // 商品规格名称
        $data2['sku'] = $goods['sku']; // 商品条码
        $data2['member_goods_price'] = $goods['shop_price']; // 会员折扣价
        $data2['cost_price'] = $goods['cost_price']; // 成本价
        $data2['give_integral'] = $goods['give_integral']; // 购买商品赠送积分
        $data2['prom_type'] = $goods['prom_type']; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
        $order_goods_id = M('OrderGoods')->add($data2);

        if ($order_goods_id) {
            $reduce = tpCache('shopping.reduce');
            if (1 == $reduce || empty($reduce)) {
                $order = Db::name('order')->where(['order_id' => $order_id])->find();
                minus_stock($order); //下单减库存
            }
//     		if(file_exists(APP_PATH.'Common/Logic/DistributLogic.class.php'))
//     		{
//     			//分销开关全局
//     			$order = M("Order")->where(array('order_id' => $order_id))->find();
//     			$distributLogic = new \Common\Logic\DistributLogic();
//     			$distributLogic->rebate_log($order); // 生成分成记录
//     		}
//    		$this->redirect(U('Virtual/buy_step2',array('order_id'=>$order_id)));
            $this->ajaxReturn(['status' => '1', 'msg' => '虚拟商品成功', 'result' => $order_id]);
        } else {
            $this->ajaxReturn(['status' => '-1', 'msg' => '虚拟商品下单失败']);
        }
    }

    public function check_virtual_goods()
    {
        $goods_id = I('goods_id/d');
        if (empty($goods_id)) {
            $this->error('请求参数错误');
        }
        $goods = M('goods')->where(['goods_id' => $goods_id])->find();
        if (!$goods) {
            $this->error('该商品不允许购买，原因有：商品下架、不存在、过期等');
        }
        if (1 == $goods['is_virtual'] && $goods['virtual_indate'] > time() && $goods['store_count'] > 0) {
            $goods_num = $goods['goods_num'] = I('goods_num/d');
            if ($goods_num < 1) {
                $this->error('最少购买1件');
            }
            if ($goods['virtual_limit'] > $goods['store_count'] || 0 == $goods['virtual_limit']) {
                $goods['virtual_limit'] = $goods['store_count'];
            }
            $goods_spec = I('goods_spec/a');
            if (!empty($goods_spec) && 'undefined' != $goods_spec) {
                $specGoodsPriceList = M('SpecGoodsPrice')->where(['goods_id' => $goods_id])->cache(true, TPSHOP_CACHE_TIME)->getField('key,key_name,price,store_count,sku'); // 获取商品对应的规格价钱 库存 条码
                foreach ($goods_spec as $key => $val) {
                    if ('undefined' != $val) {
                        $spec_item[] = $val; // 所选择的规格项
//                        $goods['spec_key_name'] .= $specGoodsPriceList[$val]['key_name'];
                    }
                }
                if (!empty($spec_item) && 'undefined' != $spec_item) { // 有选择商品规格
                    sort($spec_item);
                    $spec_key = implode('_', $spec_item);
                    if ($specGoodsPriceList[$spec_key]['store_count'] < $goods_num) {
                        $this->error('该商品规格库存不足');
                    }
                    $goods['goods_spec_key'] = $spec_key;
                    $goods['spec_key_name'] = $specGoodsPriceList[$spec_key]['key_name'];
                    $spec_price = $specGoodsPriceList[$spec_key]['price']; // 获取规格指定的价格
                    $goods['shop_price'] = empty($spec_price) ? $goods['shop_price'] : $spec_price;
                }
            }

            $goods_spec_key = I('goods_spec_key');
            if (!empty($goods_spec_key)) {
                $specGoods = M('SpecGoodsPrice')->where(['goods_id' => $goods_id, 'key' => $goods_spec_key])->find();
                if ($specGoods['store_count'] < $goods_num) {
                    $this->error('该商品规格库存不足');
                }
                $goods['shop_price'] = empty($specGoods['price']) ? $goods['shop_price'] : $specGoods['price'];
                $goods['goods_spec_key'] = $goods_spec_key;
                $goods['spec_key_name'] = $specGoods['key_name'];
            }
            $goods['goods_fee'] = $goods['shop_price'] * $goods['goods_num'];

            return $goods;
        }
        $this->error('该商品不允许购买，原因可能：商品下架、不存在、过期等');
    }

    /*
     * 虚拟订单列表
     */
    public function virtual_list()
    {
        $type = I('get.type');
        $search_key = I('search_key');
        $virtualLogic = new \app\common\logic\VirtualLogic();
        $result = $virtualLogic->orderList($this->user_id, $type, $search_key);

        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('page', $result['page']->show());
        $this->assign('lists', $result['order_list']);
        $this->assign('active', 'order_list');
        $this->assign('active_status', I('get.type'));
        $this->assign('now', time());

        return $this->fetch();
    }

    /**
     * 虚拟订单详情.
     */
    public function virtual_order()
    {
        $Order = new \app\common\model\Order();
        $VirtualLogic = new VirtualLogic();
        $order_id = I('get.order_id/d');
        $map['order_id'] = $order_id;
        $map['user_id'] = $this->user_id;
        $orderobj = $Order->where($map)->find();
        if (!$orderobj) {
            $this->error('没有获取到订单信息');
        }
        // 添加属性  包括按钮显示属性 和 订单状态显示属性
        $order_info = $orderobj->append(['order_status_detail', 'virtual_order_button', 'order_goods'])->toArray();
        if (5 != $order_info['prom_type']) {   //普通订单
            $this->redirect(U('Order/order_detail', ['id' => $order_id]));
        }
        //获取订单操作记录
        $order_action = Db::name('order_action')->where(['order_id' => $order_id])->select();

        $data = $VirtualLogic->check_virtual_code($order_info);
        $vrorders = $data['vrorders'];
        $order_info = $data['order_info'];
        $this->assign('vrorders', $vrorders);
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);

        return $this->fetch();
    }

    /**
     * 虚拟订单确定收货.
     */
    public function virtual_confirm()
    {
        $order_id = I('post.order_id/d', 0);
        $data = confirm_order($order_id, $this->user_id);
        if (1 == $data['status']) {
            Db::name('order_goods')->where(['order_id' => $order_id])->save(['is_send' => 1]);  //订单商品状态需要更改一下，不然评价列表找不到
        }
        $this->ajaxReturn($data);
    }
}
