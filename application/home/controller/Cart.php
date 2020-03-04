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

use app\common\logic\CartLogic;
use app\common\logic\CouponLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\Integral;
use app\common\logic\OrderLogic;
use app\common\logic\Pay;
use app\common\logic\PickupLogic;
use app\common\logic\PlaceOrder;
use app\common\logic\UserAddressLogic;
use app\common\model\CouponList;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use think\Db;
use think\Hook;
use think\Request;

class Cart
{
    public $cartLogic; // 购物车逻辑操作类
    public $user_id = 0;
    public $user = [];

    /**
     * 初始化函数.
     */
    public function __construct()
    {
        $this->cartLogic = new CartLogic();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where('user_id', $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];

            // 给用户计算会员价 登录前后不一样
            if ($user) {
                $user['discount'] = (empty($user['discount'])) ? 1 : $user['discount'];
                if (1 != $user['discount']) {
                    $c = Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->where('member_goods_price = goods_price')->count();
                    $c && Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->update(['member_goods_price' => ['exp', 'goods_price*'.$user['discount']]]);
                }
            }
        }
    }

    public function index()
    {
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(); //用户购物车
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum(); //获取用户购物车商品总数
        $return = [];
        $return['userCartGoodsTypeNum'] = $userCartGoodsTypeNum;
        $return['cartList'] = $cartList; //购物车表
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 更新购物车，并返回计算结果.
     */
    public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCart($cart);

        return json($result);
    }

    /**
     * 更新购物车，并返回计算结果.
     */
    private function AsyncUpdateCarts($cart)
    {
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCarts($cart);

        return $result;
    }

    /**
     *  购物车加减.
     */
    public function changeNum()
    {
        // $cart = input('cart/a',[]);
        $cart_id = input('cart_id', 0);

        $goods_change_type = input('goods_change_type', 'add');

        $goods_num = M('Cart')->where('id', $cart_id)->getField('goods_num');

        if ('add' == $goods_change_type) {
            ++$goods_num;
        } else {
            --$goods_num;
        }

        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart_id, $goods_num);

        return json($result);
    }

    /**
     *  购物车改变状态
     */
    public function changeType()
    {
        $cart = input('cart/a', []);
        if (empty($cart)) {
            return json(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeType($cart['id'], $cart['type']);

        return json($result);
    }

    /**
     * 删除购物车商品
     */
    public function delete()
    {
        $cart_ids = input('cart_ids/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if ($result > 0) {
            return json(['status' => 1, 'msg' => '删除成功', 'result' => $result]);
        }

        return json(['status' => 0, 'msg' => '删除失败', 'result' => $result]);
    }

    /**
     * 购物车数量汇总 By J.
     *
     * @return \think\response\Json
     */
    public function getCartNum()
    {
        $num = M('Cart')->where('user_id', $this->user_id)->sum('goods_num');

        return json(['status' => 1, 'msg' => 'ok', 'result' => $num]);
    }

    /**
     * 购物车优惠券领取列表.
     */
    public function getStoreCoupon()
    {
        $goods_ids = input('goods_ids/a', []);
        $goods_category_ids = input('goods_category_ids/a', []);
        if (empty($goods_ids) && empty($goods_category_ids)) {
            return json(['status' => 0, 'msg' => '获取失败', 'result' => '']);
        }
        $CouponLogic = new CouponLogic();
        $newStoreCoupon = $CouponLogic->getStoreGoodsCoupon($goods_ids, $goods_category_ids);
        if ($newStoreCoupon) {
            $user_coupon = Db::name('coupon_list')->where('uid', $this->user_id)->getField('cid', true);
            foreach ($newStoreCoupon as $key => $val) {
                if (in_array($newStoreCoupon[$key]['id'], $user_coupon)) {
                    $newStoreCoupon[$key]['is_get'] = 1; //已领取
                } else {
                    $newStoreCoupon[$key]['is_get'] = 0; //未领取
                }
            }
        }

        return json(['status' => 1, 'msg' => '获取成功', 'result' => $newStoreCoupon]);
    }

    /**
     * ajax 将商品加入购物车.
     */
    public function ajaxAddCart()
    {
        $goods_id = I('goods_id/d'); // 商品id
        $goods_num = I('goods_num/d'); // 商品数量
        $item_id = I('item_id/d'); // 商品规格id
        $type = I('type/d', 1); // 加入购物车类型
        $cartType = I('cart_type/d', 0); // 加入购物车类型
        $cart_id = I('cart_id/d', 0); // 购物车中的ID
        if (empty($goods_id)) {
            return json(['status' => 0, 'msg' => '请选择要购买的商品', 'result' => '']);
        }
        if (empty($goods_num)) {
            return json(['status' => 0, 'msg' => '购买商品数量不能为0', 'result' => '']);
        }
        if ($goods_num > 200) {
            return json(['status' => 0, 'msg' => '购买商品数量大于200', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setCartId($cart_id);
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setGoodsModel($goods_id);
        $cartLogic->setType($type);
        $cartLogic->setCartType($cartType);
        if ($item_id) {
            $cartLogic->setSpecGoodsPriceModel($item_id);
        }
        $cartLogic->setGoodsBuyNum($goods_num);
        $result = $cartLogic->addGoodsToCart();

        return json($result);
    }

    /**
     * 购物车第二步确定页面.
     */
    public function cart2()
    {
        $goods_id = input('goods_id/d'); // 商品id
        $goods_num = input('goods_num/d'); // 商品数量
        $item_id = input('item_id/d'); // 商品规格id
        $action = input('action'); // 行为
        $type = input('type', 1); // 结算类型
        $cart_type = input('cart_type', 0); // 购物车类型
        $cart_id = input('cart_id', ''); // 购物车id字符串

        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);

        $cartLogic = new CartLogic();
        $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if ('buy_now' == $action) {
            $cartLogic->setGoodsModel($goods_id);
            $cartLogic->setSpecGoodsPriceModel($item_id);
            $cartLogic->setGoodsBuyNum($goods_num);
            $cartLogic->setType($type);
            $cartLogic->setCartType($cart_type);
            try {
                $buyGoods = $cartLogic->buyNow();
            } catch (TpshopException $t) {
                $error = $t->getErrorArr();

                return json(['status' => 0, 'msg' => $error['msg'], 'result' => null]);
            }
            $cartList['cartList'][0] = $buyGoods;

            $cartGoodsTotalNum = $goods_num;
        } else {
            $cart_id = explode(',', $cart_id);

            if ($cart_id) {
                foreach ($cart_id as $k => $v) {
                    $data = [];
                    $data['id'] = $v;
                    $data['selected'] = 1;
                    $cart_id[$k] = $data;
                }
                $result = $this->AsyncUpdateCarts($cart_id);

                if (1 != $result['status']) {
                    return json(['status' => 0, 'msg' => $result['msg'], 'result' => null]);
                }
            }

            if (0 == $cartLogic->getUserCartOrderCount()) {
                return json(['status' => 0, 'msg' => '你的购物车没有选中商品', 'result' => null]);
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
            $cartGoodsTotalNum = count($cartList['cartList']);
        }
        $point = 0;
        $give_integral = 0;
        $weight = 0;
        $total_fee = 0;
        $order_prom_fee = 0;
        foreach ($cartList['cartList'] as $v) {
            $point += $v['goods_num'] * $v['use_integral'];
            $total_fee += ($v['use_integral'] + $v['member_goods_price']) * $v['goods_num'];
            $goodsInfo = M('Goods')->field('give_integral,weight')->where('goods_id', $v['goods_id'])->find();
            $give_integral += $goodsInfo['give_integral'];
            $weight += $goodsInfo['weight'];
            if (isset($v['is_order_prom']) && $v['is_order_prom'] == 1) {
                // 订单优惠促销商品总价
                $order_prom_fee += ($v['use_integral'] + $v['member_goods_price']) * $v['goods_num'];
            }
        }

        $cartGoodsList = get_arr_column($cartList['cartList'], 'goods');
        $cartGoodsId = get_arr_column($cartGoodsList, 'goods_id');
        $cartGoodsCatId = get_arr_column($cartGoodsList, 'cat_id');
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量

        $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId); //用户可用的优惠券列表
        $userCouponListRe = $couponLogic->getUserAbleCouponListRe($this->user_id, $cartGoodsId, $cartGoodsCatId); //用户可用的优惠券列表

        $cartList = array_merge($cartList, $cartPriceInfo);
        $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        $return['userCartCouponList'] = $userCartCouponList;  //优惠券，用able判断是否用

        $userCartCouponListRe = $cartLogic->getCouponCartList($cartList, $userCouponListRe);
        $return['userCartCouponListRe'] = $userCartCouponListRe;

        $Pay = new \app\common\logic\Pay();
        $cartList['cartList'] = $Pay->activity2_goods($cartList['cartList'], $order_prom_fee);

        $return['cartGoodsTotalNum'] = $cartGoodsTotalNum;
        $return['cartList'] = $cartList['cartList']; // 购物车的商品
        $return['cartPriceInfo'] = $cartPriceInfo; //商品优惠价
        $user_wealth['pay_points'] = $this->user['pay_points'];
        $user_wealth['user_electronic'] = $this->user['user_electronic'];
        $return['user_wealth'] = $user_wealth;
        $return['use_point'] = $point;
        $return['total_fee'] = $total_fee;
        $return['give_integral'] = $give_integral;
        $return['weight'] = round($weight / 1000);
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress()
    {
        $address_list = Db::name('UserAddress')->where(['user_id' => $this->user_id, 'is_pickup' => 0])->order('is_default desc')->select();
        if ($address_list) {
            $area_id = [];
            foreach ($address_list as $val) {
                $area_id[] = $val['province'];
                $area_id[] = $val['city'];
                $area_id[] = $val['district'];
                $area_id[] = $val['twon'];
            }
            $area_id = array_filter($area_id);
            $area_id = implode(',', $area_id);
            $regionList = Db::name('region2')->where('id', 'in', $area_id)->getField('id,name');
            $return['regionList'] = $regionList;
        }
        $address_where['is_default'] = 1;
        $c = Db::name('UserAddress')->where(['user_id' => $this->user_id, 'is_default' => 1, 'is_pickup' => 0])->count(); // 看看有没默认收货地址
        if ((count($address_list) > 0) && (0 == $c)) { // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;
        }
        $return['address_list'] = $address_list;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 获取自提点信息
     */
    public function ajaxPickup()
    {
        $province_id = I('province_id/d');
        $city_id = I('city_id/d');
        $district_id = I('district_id/d');
        if (empty($province_id) || empty($city_id) || empty($district_id)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        $user_address = new UserAddressLogic();
        $address_list = $user_address->getUserPickup($this->user_id);
        $pickup = new PickupLogic();
        $pickup_list = $pickup->getPickupItemByPCD($province_id, $city_id, $district_id);
        $return['pickup_list'] = $pickup_list;
        $return['address_list'] = $address_list;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 更换自提点
     */
    public function replace_pickup(Request $request)
    {
        $province_id = I('get.province_id/d');
        $city_id = I('get.city_id/d');
        $district_id = I('get.district_id/d');
        $call_back = I('get.call_back');
        if ($request->isPost()) {
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功
        }
        $address = ['province' => $province_id, 'city' => $city_id, 'district' => $district_id];
        $p = Db::name('region2')->where(['parent_id' => 0, 'level' => 1])->select();
        $c = Db::name('region2')->where(['parent_id' => $province_id, 'level' => 2])->select();
        $d = Db::name('region2')->where(['parent_id' => $city_id, 'level' => 3])->select();
        $return['province'] = $p;
        $return['city'] = $c;
        $return['district'] = $d;
        $return['address'] = $address;
        $return['call_back'] = $call_back;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 更换自提点
     */
    public function ajax_PickupPoint()
    {
        $province_id = I('province_id/d');
        $city_id = I('city_id/d');
        $district_id = I('district_id/d');
        $pick_up_model = new PickupLogic();
        $pick_up_list = $pick_up_model->getPickupListByPCD($province_id, $city_id, $district_id);

        return json(['status' => 1, 'msg' => 'success', 'result' => $pick_up_list]);
    }

    /**
     * ajax 获取订单商品价格 或者提交 订单.
     */
    public function cart3()
    {
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);
        Hook::exec('app\\home\\behavior\\CheckValid', 'run', $params);
        $address_id = input('address_id/d'); //  收货地址id
        $invoice_title = input('invoice_title');  // 发票
        $taxpayer = input('taxpayer');       // 纳税人识别号
        $coupon_id = input('coupon_id/d'); //  优惠券id
        // $pay_points         = input("pay_points/d",0); //  使用积分
        $user_electronic = input('user_electronic/f', 0); //  使用电子币
        $user_note = input('user_note', ''); // 用户留言
        $payPwd = input('payPwd', ''); // 支付密码
        $goods_id = input('goods_id/d'); // 商品id
        $goods_num = input('goods_num/d'); // 商品数量
        $item_id = input('item_id/d'); // 商品规格id
        $action = input('action'); // 立即购买
        $type = input('type', 1); // 商品购买类型
        $cart_type = input('cart_type', 0); // 商品购买类型
        $act = input('act', ''); // 商品购买类型

        if (strlen($user_note) > 50) {
            return json(['status' => -1, 'msg' => '备注超出限制可输入字符长度！', 'result' => null]);
        }
        if (!$address_id) {
            return json(['status' => -3, 'msg' => '请先填写收货人信息', 'result' => '']); // 返回结果状态
        }
        $address = Db::name('UserAddress')->where('address_id', $address_id)->find();
        $cartLogic = new CartLogic();
        $pay = new Pay();

        try {
            $cartLogic->setUserId($this->user_id);
            $pay->setUserId($this->user_id);
            if ('buy_now' == $action) {
                $cartLogic->setGoodsModel($goods_id);
                $cartLogic->setSpecGoodsPriceModel($item_id);
                $cartLogic->setGoodsBuyNum($goods_num);
                $cartLogic->setType($type);
                $cartLogic->setCartType($cart_type);
                $buyGoods = $cartLogic->buyNow();
                $cartList[0] = $buyGoods;
                $pay->payGoodsList($cartList);
            } else {
                $userCartList = $cartLogic->getCartList(1);
                $cartLogic->checkStockCartList($userCartList);
                $pay->payCart($userCartList);
            }

            list($prom_type, $prom_id) = $pay->getPromInfo();

            $pay->check(); // 加价购活动
            $pay->activityPayBefore(); // 参与活动促销 加价购活动

//            $pay->orderPromotion();
            $pay->goodsPromotion();
            $pay->delivery($address['district']);

            $pay->useCouponById($coupon_id,$pay->getPayList());

            //$pay->useCouponByIdRe($re_id);
            $pay_points = $pay->getUsePoint();
            $pay->usePayPoints($pay_points);
            $pay->useUserElectronic($user_electronic); // 电子币

            $pay->activity2();   // 指定商品赠品 / 订单优惠赠品
            $pay->activity3();         // 订单优惠促销
            $pay->activity();    // 参与活动奖励 例如:赠品活动

            $coupon = null;
            if ($coupon_id) {
                $couponList = new CouponList();
                $userCoupon = $couponList->where(['uid' => $this->user['user_id'], 'id' => $coupon_id])->find();
                if ($userCoupon) {
                    $coupon = Db::name('coupon')->where(['id' => $userCoupon['cid'], 'status' => 1])->find();
                }
            }

            // 提交订单
            if ('submit_order' == $act) {
                $placeOrder = new PlaceOrder($pay);
                $placeOrder->setUserAddress($address);
                $placeOrder->setInvoiceTitle($invoice_title);
                $placeOrder->setUserNote($user_note);
                $placeOrder->setTaxpayer($taxpayer);
                $placeOrder->setPayPsw($payPwd);
                if (2 == $prom_type) {
                    $placeOrder->addGroupBuyOrder($prom_id, 2);
                } else {
                    $placeOrder->addNormalOrder(2);
                }

                $cartLogic->clear();
                $order = $placeOrder->getOrder();
                $pay->activityRecord($order);
                // update_user_distribut($order['user_id'],$order['order_id']);
                return json(['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_sn'], 'order_id' => $order['order_id']]);
            }
            $return = $pay->toArray();
            $return['coupon_info'] = $coupon;

            return json(['status' => 1, 'msg' => '计算成功', 'result' => $return]);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();

            return json($error);
        }
    }

    /**
     * ajax 获取订单商品价格 或者提交 订单
     * 已经用心方法 这个方法 cart9  准备作废
     */

    /*
     * 订单支付页面
     */
    public function cart4()
    {
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);

        $order_id = I('order_id/d');
        $order_sn = I('order_sn/s', '');

        $scene = I('scene', 0); // 0 PC+手机 1 手机 2 PC 3 App

        $order_where['user_id'] = $this->user_id;
        if ($order_sn) {
            $order_where['order_sn'] = $order_sn;
        } else {
            $order_where['order_id'] = $order_id;
        }
        $order = M('Order')->where($order_where)->find();
        if (empty($order)) {
            return json(['status' => 0, 'msg' => '订单不存在！', 'result' => null]);
        }
        if (3 == $order['order_status']) {
            return json(['status' => 0, 'msg' => '该订单已取消', 'result' => null]);
        }

        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if (1 == $order['pay_status']) {
            return json(['status' => 0, 'msg' => '订单已经支付过！', 'result' => null]);
        }
        //如果是预售订单，支付尾款
        if (2 == $order['pay_status'] && 4 == $order['prom_type']) {
            $pre_sell_info = M('goods_activity')->where(['act_id' => $order['order_prom_id']])->find();
            $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
            if ($pre_sell_info['retainage_start'] > time()) {
                return json(['status' => 0, 'msg' => '还未到支付尾款时间'.date('Y-m-d H:i:s', $pre_sell_info['retainage_start']), 'result' => null]);
            }
            if ($pre_sell_info['retainage_end'] < time()) {
                return json(['status' => 0, 'msg' => '对不起，该预售商品已过尾款支付时间'.date('Y-m-d H:i:s', $pre_sell_info['retainage_start']), 'result' => null]);
            }
        }

        $payment_where = [
            'type' => 'payment',
            'status' => 1,
            'scene' => $scene,
        ];

        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id' => $order['order_id']])->getField('prom_type', true);
        $no_cod_order_prom_type = ['4,5']; //预售订单，虚拟订单不支持货到付款
        if (in_array($order['prom_type'], $no_cod_order_prom_type) || in_array(1, $orderGoodsPromType)) {
            $payment_where['code'] = ['neq', 'cod'];
        }
        $paymentList = M('Plugin')->field('code,name,version,author,desc,icon')->where($payment_where)->select();
        // $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if (2 == $val['config_value']['is_bank']) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            $paymentList[$key]['icon'] = '/plugins/payment/'.$val['code'].'/logo.jpg';
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $return['paymentList'] = $paymentList;
        $return['bank_img'] = $bank_img;
        $return['order'] = $order;
        $return['bankCodeList'] = $bankCodeList;
        $return['pay_date'] = date('Y-m-d', strtotime('+1 day'));

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    //ajax 请求购物车列表
    public function header_cart_list()
    {
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList();
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList);
        $return['cartList'] = $cartList; // 购物车的品
        $return['cartPriceInfo'] = $cartPriceInfo; // 计
        $template = I('template', 'header_cart_list');
        $return['template'] = $template;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 预售商品下单流程.
     */
    public function pre_sell_cart()
    {
        $act_id = I('act_id/d');
        $goods_num = I('goods_num/d');
        if (empty($act_id)) {
            return json(['status' => 0, 'msg' => '没有选择需要购买商品', 'result' => null]);
        }
        if (empty($goods_num)) {
            return json(['status' => 0, 'msg' => '购买商品数量不能为0', 'result' => null]);
        }
        if (0 == $this->user_id) {
            return json(['status' => 0, 'msg' => '请先登录', 'result' => null]);
        }
        $pre_sell_info = M('goods_activity')->where(['act_id' => $act_id, 'act_type' => 1])->find();
        if (empty($pre_sell_info)) {
            return json(['status' => 0, 'msg' => '商品不存在或已下架', 'result' => null]);
        }
        $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
        if ($pre_sell_info['act_count'] + $goods_num > $pre_sell_info['restrict_amount']) {
            $buy_num = $pre_sell_info['restrict_amount'] - $pre_sell_info['act_count'];

            return json(['status' => 0, 'msg' => '预售商品库存不足，还剩下'.$buy_num.'件',  'result' => null]);
        }
        $goodsActivityLogic = new GoodsActivityLogic();
        $pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']); //预售商品的订购数量和订单数量
        $pre_sell_price['cut_price'] = $goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']); //预售商品价格
        $pre_sell_price['goods_num'] = $goods_num;
        $pre_sell_price['deposit_price'] = floatval($pre_sell_info['deposit']);
        // 提交订单
        if ('submit_order' == $_REQUEST['act']) {
            $invoice_title = I('invoice_title'); // 发票
            $taxpayer = I('taxpayer'); // 纳税人识别号
            $address_id = I('address_id/d'); //  收货地址id
            if (empty($address_id)) {
                exit(json_encode(['status' => -3, 'msg' => '请先填写收货人信息', 'result' => null])); // 返回结果状态
            }
            $orderLogic = new OrderLogic();
            $result = $orderLogic->addPreSellOrder($this->user_id, $address_id, $invoice_title, $act_id, $pre_sell_price, $taxpayer); // 添加订单
            exit(json_encode($result));
        }
        $return['pre_sell_info'] = $pre_sell_info; // 购物车的预售品
        $return['pre_sell_price'] = $pre_sell_price;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 兑换积分商品
     */
    public function buyIntegralGoods()
    {
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num');
        $Integral = new Integral();
        $Integral->setUserById($this->user_id);
        $Integral->setGoodsById($goods_id);
        $Integral->setSpecGoodsPriceById($item_id);
        $Integral->setBuyNum($goods_num);
        try {
            $Integral->checkBuy();
            $url = U('Cart/integral', ['goods_id' => $goods_id, 'item_id' => $item_id, 'goods_num' => $goods_num]);
            $result = ['status' => 1, 'msg' => '购买成功', 'result' => ['url' => $url]];

            return json($result);
        } catch (TpshopException $t) {
            $result = $t->getErrorArr();

            return json($result);
        }
    }

    /**
     *  积分商品结算页.
     *
     * @return mixed
     */
    public function integral()
    {
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        if (empty($this->user)) {
            return json(['status' => 0, 'msg' => '请登录', 'result' => null]);
        }
        if (empty($goods_id)) {
            return json(['status' => 0, 'msg' => '非法操作', 'result' => null]);
        }
        if (empty($goods_num)) {
            return json(['status' => 0, 'msg' => '购买数不能为零', 'result' => null]);
        }
        $Goods = new Goods();
        $goods = $Goods->where(['goods_id' => $goods_id])->find();
        if (empty($goods)) {
            return json(['status' => 0, 'msg' => '该商品不存在', 'result' => null]);
        }
        if (empty($item_id)) {
            $goods_spec_list = SpecGoodsPrice::all(['goods_id' => $goods_id]);
            if (count($goods_spec_list) > 0) {
                return json(['status' => 0, 'msg' => '请传递规格参数', 'result' => null]);
            }
            $goods_price = $goods['shop_price'];
        //没有规格
        } else {
            //有规格
            $specGoodsPrice = SpecGoodsPrice::get(['item_id' => $item_id, 'goods_id' => $goods_id]);
            if ($goods_num > $specGoodsPrice['store_count']) {
                return json(['status' => 0, 'msg' => '该商品规格库存不足，剩余'.$specGoodsPrice['store_count'].'份', 'result' => null]);
            }
            $goods_price = $specGoodsPrice['price'];
            $return['specGoodsPrice'] = $specGoodsPrice;
        }
        $point_rate = tpCache('shopping.point_rate');
        $return['point_rate'] = $point_rate;
        $return['goods'] = $goods;
        $return['goods_price'] = $goods_price;
        $return['goods_num'] = $goods_num;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     *  积分商品价格提交.
     *
     * @return mixed
     */
    public function integral2()
    {
        if (0 == $this->user_id) {
            return json(['status' => -100, 'msg' => '登录超时请重新登录!', 'result' => null]);
        }
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input('address_id/d'); //  收货地址id
        $user_note = input('user_note'); // 给卖家留言
        $invoice_title = input('invoice_title'); // 发票
        $taxpayer = input('taxpayer'); // 发票纳税人识别号
        $user_money = input('user_money/f', 0); //  使用余额
        $payPwd = input('payPwd');
        $integral = new Integral();
        $integral->setUserById($this->user_id);
        $integral->setGoodsById($goods_id);
        $integral->setBuyNum($goods_num);
        $integral->setSpecGoodsPriceById($item_id);
        $integral->setUserAddressById($address_id);
        $integral->useUserMoney($user_money);
        try {
            $integral->checkBuy();
            $pay = $integral->pay();
            // 提交订单
            if ('submit_order' == $_REQUEST['act']) {
                $placeOrder = new PlaceOrder($pay);
                $placeOrder->setUserAddress($integral->getUserAddress());
                $placeOrder->setInvoiceTitle($invoice_title);
                $placeOrder->setUserNote($user_note);
                $placeOrder->setTaxpayer($taxpayer);
                $placeOrder->setPayPsw($payPwd);
                $placeOrder->addNormalOrder(2);
                $order = $placeOrder->getOrder();

                return json(['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_id']]);
            }

            return json(['status' => 1, 'msg' => '计算成功', 'result' => $pay->toArray()]);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();

            return json($error);
        }
    }

    /**
     *  获取发票信息.
     *
     * @date2017/10/19 14:45
     */
    public function invoice()
    {
        $map['user_id'] = $this->user_id;
        $field = [
            'invoice_title',
            'taxpayer',
            'invoice_desc',
        ];

        $info = M('user_extend')->field($field)->where($map)->find();
        if (empty($info)) {
            $result = ['status' => -1, 'msg' => 'N', 'result' => ''];
        } else {
            $result = ['status' => 1, 'msg' => 'Y', 'result' => $info];
        }

        return json($result);
    }

    /**
     *  保存发票信息.
     *
     * @date2017/10/19 14:45
     */
    public function save_invoice(Request $request)
    {
        if ($request->isAjax()) {
            //A.1获取发票信息
            $invoice_title = trim(I('invoice_title'));
            $taxpayer = trim(I('taxpayer'));
            $invoice_desc = trim(I('invoice_desc'));
            //B.1校验用户是否有历史发票记录
            $map['user_id'] = $this->user_id;
            $info = M('user_extend')->where($map)->find();
            //B.2发票信息
            $data = [];
            $data['invoice_title'] = $invoice_title;
            $data['taxpayer'] = $taxpayer;
            $data['invoice_desc'] = $invoice_desc;
            //B.3发票抬头
            if ('个人' == $invoice_title) {
                $data['invoice_title'] = '个人';
                $data['taxpayer'] = '';
            }
            //是否存贮过发票信息
            if (empty($info)) {
                $data['user_id'] = $this->user_id;
                (M('user_extend')->add($data)) ?
                $status = 1 : $status = -1;
            } else {
                (M('user_extend')->where($map)->save($data)) ?
                $status = 1 : $status = -1;
            }
            $result = ['status' => $status, 'msg' => '', 'result' => ''];

            return json($result);
        }
    }

    /**
     * 优惠券兑换.
     */
    public function cartCouponExchange()
    {
        $coupon_code = input('coupon_code');
        $couponLogic = new CouponLogic();
        $return = $couponLogic->exchangeCoupon($this->user_id, $coupon_code);

        return json($return);
    }
}
