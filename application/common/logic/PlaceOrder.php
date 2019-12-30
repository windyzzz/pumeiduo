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

use app\common\model\CouponList;
use app\common\model\Order;
use app\common\model\TeamActivity;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Cache;
use think\Db;
use think\Hook;

/**
 * 提交下单类
 * Class CatsLogic.
 */
class PlaceOrder
{
    private $invoiceTitle;
    private $userNote;
    private $taxpayer;
    private $pay;
    private $order;
    private $userAddress;
    private $payPsw;
    private $promType;
    private $promId;

    /**
     * PlaceOrder constructor.
     *
     * @param Pay $pay
     */
    public function __construct(Pay $pay)
    {
        $this->pay = $pay;
        $this->order = new Order();
    }

    /**
     * 设置密码后加密.
     *
     * @param $payPsw
     */
    public function setPayPsw($payPsw)
    {
        $this->payPsw = $payPsw;
    }

    public function setInvoiceTitle($invoiceTitle)
    {
        $this->invoiceTitle = $invoiceTitle;
    }

    public function setUserNote($userNote)
    {
        $this->userNote = $userNote;
    }

    public function setTaxpayer($taxpayer)
    {
        $this->taxpayer = $taxpayer;
    }

    public function setUserAddress($userAddress)
    {
        $this->userAddress = $userAddress;
    }

    private function setPromType($prom_type)
    {
        $this->promType = $prom_type;
    }

    private function setPromId($prom_id)
    {
        $this->promId = $prom_id;
    }

    public function addNormalOrder()
    {
        $this->check();
        $this->queueInc();
        $this->addOrder();

        $this->addOrderGoods();
        Hook::listen('user_add_order', $this->order); //下单行为
        $reduce = tpCache('shopping.reduce');

        if (1 == $reduce || empty($reduce)) {
            minus_stock($this->order); //下单减库存
        }

        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
        if (0 == $this->order['order_amount']) {
            update_pay_status($this->order['order_sn']);
        }
        $this->deductionCoupon(); //扣除优惠券
        $this->deductionCouponRe(); //扣除优惠券
        $this->changUserPointMoney($this->order); //扣除用户积分余额
        $this->queueDec();
    }

    public function addGroupBuyOrder($prom_id)
    {
        $this->setPromType(2);
        $this->setPromId($prom_id);
        $this->check();
        $this->queueInc();
        $this->addOrder();
        $this->addOrderGoods();
        Hook::listen('user_add_order', $this->order); //下单行为
        $reduce = tpCache('shopping.reduce');
        if (1 == $reduce || empty($reduce)) {
            minus_stock($this->order); //下单减库存
        }
        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
        if (0 == $this->order['order_amount']) {
            update_pay_status($this->order['order_sn']);
        }
        $this->deductionCoupon(); //扣除优惠券
        $this->changUserPointMoney($this->order); //扣除用户积分余额
        $this->queueDec();
    }

    public function addTeamOrder(TeamActivity $teamActivity)
    {
        $this->setPromType(6);
        $this->setPromId($teamActivity['team_id']);
        $this->check();
        $this->queueInc();
        $this->addOrder();
        $this->addOrderGoods();
        Hook::listen('user_add_order', $this->order); //下单行为
        if (2 != $teamActivity['team_type']) {
            if (1 == tpCache('shopping.reduce')) {
                minus_stock($this->order); //下单减库存
            }
        }
        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
        if (0 == $this->order['order_amount']) {
            update_pay_status($this->order['order_sn']);
        }
        $this->queueDec();
    }

    /**
     * 获取订单表数据.
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * 提交订单前检查.
     *
     * @throws TpshopException
     */
    public function check()
    {
//        $pay_points = $this->pay->getPayPoints();
//        $user_electronic = $this->pay->getUserElectronic();
//        if ($pay_points || $user_electronic) {
            $user = $this->pay->getUser();
            if (1 == $user['is_lock']) {
                throw new TpshopException('提交订单', 0, ['status' => -5, 'msg' => '账号异常已被锁定，不能使用积分或电子币支付！', 'result' => '']);
            }
            if (empty($user['paypwd'])) {
                throw new TpshopException('提交订单', 0, ['status' => -6, 'msg' => '请先设置支付密码', 'result' => '']);
            }
            if (empty($this->payPsw)) {
                throw new TpshopException('提交订单', 0, ['status' => -7, 'msg' => '请输入支付密码', 'result' => '']);
            }
            if (systemEncrypt($this->payPsw) !== $user['paypwd']) {
                throw new TpshopException('提交订单', 0, ['status' => -8, 'msg' => '支付密码错误', 'result' => '']);
            }
//        }
    }

    private function queueInc()
    {
        $queue = Cache::get('queue');
        if ($queue >= 100) {
            throw new TpshopException('提交订单', 0, ['status' => -99, 'msg' => '当前人数过多请耐心排队!' . $queue, 'result' => '']);
        }
        Cache::inc('queue');
    }

    /**
     * 订单提交结束
     */
    private function queueDec()
    {
        Cache::dec('queue');
    }

    /**
     * 插入订单表.
     *
     * @throws TpshopException
     */
    private function addOrder()
    {
        $OrderLogic = new OrderLogic();
        $user = $this->pay->getUser();

        $orderData = [
            'order_sn' => $OrderLogic->get_order_sn(), // 订单编号
            'user_id' => $user['user_id'], // 用户id
            'email' => $user['email'], //'邮箱'
            'invoice_title' => $this->invoiceTitle, //'发票抬头',
            'goods_price' => $this->pay->getGoodsPrice(), //'商品价格',
            'shipping_price' => $this->pay->getShippingPrice(), //'物流价格',
            'user_electronic' => $this->pay->getUserElectronic(), //'使用余额',
            'coupon_price' => $this->pay->getCouponPrice(), //'使用优惠券',
            'integral' => $this->pay->getPayPoints(), //'使用积分',
            'integral_money' => $this->pay->getPayPoints(), //'使用积分抵多少钱',
            'total_amount' => $this->pay->getTotalAmount(), // 订单总额
            'order_amount' => $this->pay->getOrderAmount(), //'应付款金额',
            'add_time' => time(), // 下单时间
        ];
        if (!empty($this->userAddress)) {
            $orderData['consignee'] = $this->userAddress['consignee']; // 收货人
            $orderData['province'] = $this->userAddress['province']; //'省份id',
            $orderData['city'] = $this->userAddress['city']; //'城市id',
            $orderData['district'] = $this->userAddress['district']; //'县',
            $orderData['twon'] = $this->userAddress['twon']; // '街道',
            $orderData['address'] = $this->userAddress['address']; //'详细地址'
            $orderData['mobile'] = $this->userAddress['mobile']; //'手机',
            $orderData['zipcode'] = $this->userAddress['zipcode']; //'邮编',
        }
        if (!empty($this->userNote)) {
            $orderData['user_note'] = $this->userNote; // 用户下单备注
        }
        if (!empty($this->taxpayer)) {
            $orderData['taxpayer'] = $this->taxpayer; //'发票纳税人识别号',
        }
        $orderPromId = $this->pay->getOrderPromId();
        $orderPromAmount = $this->pay->getOrderPromAmount();
        if ($orderPromId > 0) {
            $orderData['order_prom_id'] = $orderPromId; //'订单优惠活动id',
            $orderData['order_prom_amount'] = $orderPromAmount; //'订单优惠活动金额,
        }
        if ($this->promType) {
            $orderData['prom_type'] = $this->promType; //订单类型
        }
        if ($this->promId > 0) {
            $orderData['prom_id'] = $this->promId; //活动id
        }
        if ($orderData['integral'] > 0 || $orderData['user_electronic'] > 0) {
            $orderData['pay_name'] = $orderData['user_electronic'] ? '电子币支付' : '积分兑换'; //支付方式，可能是余额支付或积分兑换，后面其他支付方式会替换
        }

        $this->order->data($orderData, true);
        $orderSaveResult = $this->order->save();
        if (false === $orderSaveResult) {
            throw new TpshopException('订单入库', 0, ['status' => -8, 'msg' => '添加订单失败', 'result' => '']);
        }
    }

    /**
     * 插入订单商品表.
     */
    private function addOrderGoods()
    {
        if ($this->pay->getOrderPromAmount() > 0) {
            $orderDiscounts = $this->pay->getOrderPromAmount() + $this->pay->getCouponPrice();  //整个订单优惠价钱
        } else {
            $orderDiscounts = $this->pay->getCouponPrice();  //整个订单优惠价钱
        }
        $payList = $this->pay->getPayList();
        $payList = collection($payList)->toArray();

        $goods_ids = get_arr_column($payList, 'goods_id');
        $goodsArr = Db::name('goods')->where('goods_id', 'IN', $goods_ids)->getField('goods_id,cost_price,give_integral,commission,exchange_integral,trade_type,sale_type');
        $orderGoodsAllData = [];

        $orderPromId = [];  // 订单优惠促销ID
        $orderDiscount = 0.00;  // 订单优惠金额

        foreach ($payList as $payKey => $payItem) {
            unset($payItem['goods']);
            $totalPriceToRatio = $payItem['member_goods_price'] / $this->pay->getGoodsPrice();  //商品价格占总价的比例
            $finalPrice = round($payItem['member_goods_price'] - ($totalPriceToRatio * $orderDiscounts), 3);
            $orderGoodsData = array();
            $orderGoodsData['order_id'] = $this->order['order_id']; // 订单id
            $orderGoodsData['goods_id'] = $payItem['goods_id']; // 商品id
            $orderGoodsData['goods_name'] = $payItem['goods_name']; // 商品名称
            $orderGoodsData['goods_sn'] = $payItem['goods_sn']; // 商品货号
            $orderGoodsData['goods_num'] = $payItem['goods_num']; // 购买数量
            $orderGoodsData['final_price'] = $finalPrice; // 每件商品实际支付价格
            $orderGoodsData['goods_price'] = $payItem['goods_price']; // 商品价
            $orderGoodsData['re_id'] = isset($payItem['re_id']) ? intval($payItem['re_id']) : 0;
            if (!empty($payItem['spec_key'])) {
                $orderGoodsData['spec_key'] = $payItem['spec_key']; // 商品规格
                $orderGoodsData['spec_key_name'] = $payItem['spec_key_name']; // 商品规格名称
            } else {
                $orderGoodsData['spec_key'] = ''; // 商品规格
                $orderGoodsData['spec_key_name'] = ''; // 商品规格名称
            }
            $orderGoodsData['sku'] = $payItem['sku'] ? $payItem['sku'] : ''; // sku
            $orderGoodsData['member_goods_price'] = $payItem['member_goods_price']; // 会员折扣价
            $orderGoodsData['cost_price'] = $goodsArr[$payItem['goods_id']]['cost_price']; // 成本价
            $orderGoodsData['give_integral'] = $goodsArr[$payItem['goods_id']]['give_integral']; // 购买商品赠送积分
            $orderGoodsData['commission'] = $goodsArr[$payItem['goods_id']]['commission'];
            $orderGoodsData['trade_type'] = $goodsArr[$payItem['goods_id']]['trade_type'];
            $orderGoodsData['sale_type'] = $goodsArr[$payItem['goods_id']]['sale_type'];
            $orderGoodsData['use_integral'] = $payItem['use_integral'] ?: 0;
            if ($orderGoodsData['final_price'] == $orderGoodsData['goods_price']) {
                $orderGoodsData['use_integral'] = 0;
            }
            if ($payItem['prom_type']) {
                $orderGoodsData['prom_type'] = $payItem['prom_type']; // 0普通订单 1限时抢购 2团购 3促销优惠 7订单合购优惠
                $orderGoodsData['prom_id'] = $payItem['prom_id']; // 活动id
            } else {
                $orderGoodsData['prom_type'] = 0; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                $orderGoodsData['prom_id'] = 0; // 活动id
            }
            $orderGoodsData['is_gift'] = 0;
            array_push($orderGoodsAllData, $orderGoodsData);

            if ($payItem['gift2_goods']) {
                foreach ($payItem['gift2_goods'] as $k => $v) {
                    $finalPrice = 0;
                    $orderGoodsData = array();
                    $orderGoodsData['order_id'] = $this->order['order_id']; // 订单id
                    $orderGoodsData['goods_id'] = $v['goods_id']; // 商品id
                    $orderGoodsData['goods_name'] = $v['goods_name']; // 商品名称
                    $orderGoodsData['goods_sn'] = $v['goods_sn']; // 商品货号
                    $orderGoodsData['goods_num'] = $v['goods_num']; // 购买数量
                    $orderGoodsData['final_price'] = $finalPrice; // 每件商品实际支付价格
                    $orderGoodsData['goods_price'] = 0; // 商品价
                    $orderGoodsData['re_id'] = 0;
                    if (!empty($v['spec_key'])) {
                        $orderGoodsData['spec_key'] = $v['spec_key']; // 商品规格
                        $orderGoodsData['spec_key_name'] = $v['spec_key_name']; // 商品规格名称
                    } else {
                        $orderGoodsData['spec_key'] = ''; // 商品规格
                        $orderGoodsData['spec_key_name'] = ''; // 商品规格名称
                    }
                    $orderGoodsData['sku'] = $v['sku'] ? $v['sku'] : ''; // sku
                    $orderGoodsData['member_goods_price'] = 0; // 会员折扣价
                    $orderGoodsData['cost_price'] = 0; // 成本价
                    $orderGoodsData['give_integral'] = 0; // 购买商品赠送积分
                    $orderGoodsData['commission'] = 0;
                    $orderGoodsData['trade_type'] = $v['trade_type'];
                    $orderGoodsData['sale_type'] = 1;
                    $orderGoodsData['use_integral'] = 0;
                    $orderGoodsData['prom_type'] = $v['prom_type'];
                    $orderGoodsData['prom_id'] = $v['prom_id'];
                    $orderGoodsData['is_gift'] = 1;
                    array_push($orderGoodsAllData, $orderGoodsData);
                }
            }
            if ($payItem['gift_goods']) {
                foreach ($payItem['gift_goods'] as $k => $v) {
                    $finalPrice = 0;
                    $orderGoodsData = array();
                    $orderGoodsData['order_id'] = $this->order['order_id']; // 订单id
                    $orderGoodsData['goods_id'] = $v['goods_id']; // 商品id
                    $orderGoodsData['goods_name'] = $v['goods_name']; // 商品名称
                    $orderGoodsData['goods_sn'] = $v['goods_sn']; // 商品货号
                    $orderGoodsData['goods_num'] = $v['goods_num']; // 购买数量
                    $orderGoodsData['final_price'] = $finalPrice; // 每件商品实际支付价格
                    $orderGoodsData['goods_price'] = 0; // 商品价
                    $orderGoodsData['re_id'] = 0;
                    if (!empty($v['spec_key'])) {
                        $orderGoodsData['spec_key'] = $v['spec_key']; // 商品规格
                        $orderGoodsData['spec_key_name'] = $v['spec_key_name']; // 商品规格名称
                    } else {
                        $orderGoodsData['spec_key'] = ''; // 商品规格
                        $orderGoodsData['spec_key_name'] = ''; // 商品规格名称
                    }
                    $orderGoodsData['sku'] = $v['sku'] ? $v['sku'] : ''; // sku
                    $orderGoodsData['member_goods_price'] = 0; // 会员折扣价
                    $orderGoodsData['cost_price'] = 0; // 成本价
                    $orderGoodsData['give_integral'] = 0; // 购买商品赠送积分
                    $orderGoodsData['commission'] = 0;
                    $orderGoodsData['trade_type'] = $v['trade_type'];
                    $orderGoodsData['sale_type'] = 1;
                    $orderGoodsData['use_integral'] = 0;
                    $orderGoodsData['prom_type'] = $v['prom_type'];
                    $orderGoodsData['prom_id'] = $v['prom_id'];
                    $orderGoodsData['is_gift'] = 1;
                    array_push($orderGoodsAllData, $orderGoodsData);
                }
            }
            if ($this->order['goods_price'] > 0) {
                // 订单优惠促销（查看是否有优惠价格）
                $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
                    ->where(['opg.type' => 1, 'goods_id' => $payItem['goods_id'], 'item_id' => $payItem['item_id']])
                    ->where(['op.type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
                    ->field('order_prom_id, order_price, discount_price')->find();
                if (!empty($orderProm) && !in_array($orderProm['order_prom_id'], $orderPromId)) {
                    if ($this->order['goods_price'] >= $orderProm['order_price']) {
                        // 订单价格满足要求
                        $orderDiscount += $orderProm['discount_price'];
                    }
                    $orderPromId[] = $orderProm['order_prom_id'];
                }
            }
        }

        Db::name('order_goods')->insertAll($orderGoodsAllData);
        if ($orderDiscount != 0.00) {
            // 增加优惠金额
            Db::name('order')->where(['order_id' => $this->order['order_id']])->setInc('order_prom_amount', $orderDiscount);
        }
    }

    /**
     * 扣除优惠券.
     */
    public function deductionCoupon()
    {
        $couponId = $this->pay->getCouponId();
        if ($couponId > 0) {
            $user = $this->pay->getUser();
            $couponList = new CouponList();
            $userCoupon = $couponList->where(['status' => 0, 'id' => $couponId])->find();
            if ($userCoupon) {
                $userCoupon->uid = $user['user_id'];
                $userCoupon->order_id = $this->order['order_id'];
                $userCoupon->use_time = time();
                $userCoupon->status = 1;
                $userCoupon->save();
                Db::name('coupon')->where('id', $userCoupon['cid'])->setInc('use_num'); // 优惠券的使用数量加一
            }
        }
    }

    /**
     * 扣除兑换券.
     */
    public function deductionCouponRe()
    {
        $couponId = $this->pay->getCouponIdRe();
        if ($couponId) {
            $user = $this->pay->getUser();
            $couponList = new CouponList();
            $couponIdArr = explode(',', $couponId);
            foreach ($couponIdArr as $k => $couponId) {
                $userCoupon = $couponList->where(['status' => 0, 'id' => $couponId])->find();
                if ($userCoupon) {
                    $userCoupon->uid = $user['user_id'];
                    $userCoupon->order_id = $this->order['order_id'];
                    $userCoupon->use_time = time();
                    $userCoupon->status = 1;
                    $userCoupon->save();
                    Db::name('coupon')->where('id', $userCoupon['cid'])->setInc('use_num'); // 优惠券的使用数量加一
                }
            }

        }
    }

    /**
     * 扣除用户积分余额.
     *
     * @param Order $order
     */
    public function changUserPointMoney(Order $order)
    {
        if ($this->pay->getPayPoints() > 0 || $this->pay->getUserElectronic() > 0) {
            $user = $this->pay->getUser();
            $user = Users::get($user['user_id']);
            if ($this->pay->getPayPoints() > 0) {
                $user->pay_points = $user->pay_points - $this->pay->getPayPoints(); // 消费积分
            }
            if ($this->pay->getUserElectronic() > 0) {
                $user->user_electronic = $user->user_electronic - $this->pay->getUserElectronic(); // 抵扣余额
            }
            $user->save();
            $accountLogData = [
                'user_id' => $order['user_id'],
                'user_electronic' => -$this->pay->getUserElectronic(),
                'pay_points' => -$this->pay->getPayPoints(),
                'change_time' => time(),
                'desc' => '下单消费',
                'order_sn' => $order['order_sn'],
                'order_id' => $order['order_id'],
                'type' => 3,
            ];
            Db::name('account_log')->insert($accountLogData);
        }
    }

    /**
     * 这方法特殊，只限拼团使用。
     *
     * @param $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }
}
