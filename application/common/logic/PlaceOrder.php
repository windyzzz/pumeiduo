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

use app\common\logic\Token as TokenLogic;
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
    private $user;
    private $userAddress;
    private $payPsw;
    private $promType;
    private $promId;
    private $userIdCard;
    private $orderType = 1;
    private $order1Goods = [];
    private $order2Goods = [];
    private $hasAgent = 0;
    private $isLiveAbroad = 0;
    private $isLiveSupplier = 0;

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

    public function setUser($user)
    {
        $this->user = $user;
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

    public function setUserIdCard($idCard)
    {
        $this->userIdCard = $idCard;
    }

    public function setOrderType($orderType)
    {
        $this->orderType = $orderType;
    }

    public function setHasAgent($hasAgent)
    {
        $this->hasAgent = $hasAgent;
    }

    public function isLiveAbroad($isLiveAbroad)
    {
        $this->isLiveAbroad = $isLiveAbroad;
    }

    public function isLiveSupplier($isLiveSupplier)
    {
        $this->isLiveSupplier = $isLiveSupplier;
    }

    public function addNormalOrder($source = 1)
    {
        $this->check($source);
        $this->queueInc();
        $this->addOrder($source);
        $this->addSplitOrder($source);
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
        if ($this->order['order_type'] != 5) {
            $this->deductionCoupon(); //扣除优惠券
            $this->deductionCouponRe(); //扣除兑换券
            $this->changUserPointMoney($this->order); //扣除用户积分余额
        }
        $this->queueDec();
    }

    public function addGroupBuyOrder($prom_id, $source = 1)
    {
        $this->setPromType(2);
        $this->setPromId($prom_id);
        $this->check($source);
        $this->queueInc();
        $this->addOrder($source);
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

    public function addTeamOrder(TeamActivity $teamActivity, $source = 1)
    {
        $this->setPromType(6);
        $this->setPromId($teamActivity['team_id']);
        $this->check($source);
        $this->queueInc();
        $this->addOrder($source);
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
    public function check($source = 1)
    {
//        $pay_points = $this->pay->getPayPoints();
//        $user_electronic = $this->pay->getUserElectronic();
//        if ($pay_points || $user_electronic) {
        $user = $this->pay->getUser();
        if (1 == $user['is_lock']) {
            throw new TpshopException('提交订单', 0, ['status' => -5, 'msg' => '账号异常已被锁定，不能使用积分或电子币支付！', 'result' => '']);
        }
        if ($source != 4 || $this->pay->getUserElectronic() != 0) {
            if (empty($user['paypwd'])) {
                throw new TpshopException('提交订单', 0, ['status' => -6, 'msg' => '请先设置支付密码', 'result' => '']);
            }
            if (empty($this->payPsw)) {
                throw new TpshopException('提交订单', 0, ['status' => -7, 'msg' => '请输入支付密码', 'result' => '']);
            }
            if (systemEncrypt($this->payPsw) !== $user['paypwd']) {
                throw new TpshopException('提交订单', 0, ['status' => -8, 'msg' => '支付密码错误', 'result' => '']);
            }
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
    private function addOrder($source = 1)
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
            'user_electronic' => $this->pay->getUserElectronic(), //'使用电子币',
            'coupon_price' => $this->pay->getCouponPrice(), //'使用优惠券',
            'integral' => $this->pay->getPayPoints(), //'使用积分',
            'integral_money' => $this->pay->getPayPoints(), //'使用积分抵多少钱',
            'total_amount' => $this->pay->getTotalAmount(), // 订单总额
            'order_amount' => $this->pay->getOrderAmount(), //'应付款金额',
            'add_time' => time(), // 下单时间
            'coupon_id' => $this->pay->getCouponId(),
            'source' => $source,
            'order_pv' => $this->pay->getOrderPv(),
            'id_card' => $this->userIdCard ?? '',
            'order_type' => $this->orderType,
            'is_agent' => $this->hasAgent,
            'school_credit' => $this->pay->getSchoolCredit(),
            'is_live_abroad' => $this->isLiveAbroad,
            'is_live_supplier' => $this->isLiveSupplier,
        ];
        if ($this->orderType == 5) {
            // 商学院兑换订单
            $orderData['pay_code'] = 'school_credit';
            $orderData['pay_name'] = '乐活豆兑换';
            $orderData['order_amount'] = 0;
        }
        if ($orderData['order_pv'] > 0) {
            $orderData['pv_user_id'] = $user['user_id'];
        }
        if (!empty($this->userAddress)) {
            $orderData['consignee'] = $this->userAddress['consignee']; // 收货人
            $orderData['province'] = $this->userAddress['province']; //'省份id',
            $orderData['city'] = $this->userAddress['city']; //'城市id',
            $orderData['district'] = $this->userAddress['district']; //'县',
            $orderData['twon'] = $this->userAddress['twon']; // '街道',
            $orderData['address'] = $this->userAddress['address']; //'详细地址'
            $orderData['mobile'] = $this->userAddress['mobile']; //'手机',
            $orderData['zipcode'] = !empty($this->userAddress['zipcode']) ? $this->userAddress['zipcode'] : M('region2')->where(['id' => $this->userAddress['district']])->value('zipcode'); //'邮编',
            if (empty($orderData['zipcode'])) {
                $orderData['zipcode'] = M('region2')->where(['id' => $this->userAddress['city']])->value('zipcode');
            }
        }
        if (!empty($this->userNote)) {
            $orderData['user_note'] = $this->userNote; // 用户下单备注
        }
        if (!empty($this->taxpayer)) {
            $orderData['taxpayer'] = $this->taxpayer; //'发票纳税人识别号',
        }
        $orderPromIds = $this->pay->getOrderPromIds();
        if (!empty($orderPromIds)) {
            $orderPromId = '';
            if (isset($orderPromIds['goods_prom'])) {
                $orderPromId .= 'goods_prom: ' . implode(',', $orderPromIds['goods_prom']) . '; ';
            }
            if (isset($orderPromIds['order_prom'])) {
                $orderPromId .= 'order_prom: ' . implode(',', $orderPromIds['order_prom']) . '; ';
            }
            $orderData['order_prom_id'] = $orderPromId; //'订单优惠活动id',
        }
        $orderPromAmount = $this->pay->getOrderPromAmount();
        $orderData['order_prom_amount'] = $orderPromAmount; //'订单优惠活动金额,
        if ($this->promId > 0) {
            $orderData['prom_id'] = $this->promId; //活动id
        }
        if ($this->promType) {
            $orderData['prom_type'] = $this->promType; //活动订单类型
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
            $orderDiscounts = bcsub(bcadd($this->pay->getOrderPromAmount(), $this->pay->getCouponPrice(), 2), $this->pay->getGoodsPromAmount(), 2);  //整个订单优惠价钱
        } else {
            $orderDiscounts = $this->pay->getCouponPrice();  //整个订单优惠价钱
        }
        $payList = $this->pay->getPayList();
        $payList = collection($payList)->toArray();

        $goods_ids = get_arr_column($payList, 'goods_id');
        $goodsArr = Db::name('goods')->where('goods_id', 'IN', $goods_ids)->getField('goods_id,cost_price,give_integral,commission,exchange_integral,trade_type,sale_type');
        $orderGoodsAllData = [];

        $promRate = $this->pay->getTotalAmount() != '0' ? bcsub(1, ($orderDiscounts / $this->pay->getTotalAmount()), 2) : 1;
        $orderPromId = [];  // 订单优惠促销ID
        $orderDiscount = 0.00;  // 订单优惠金额
        foreach ($payList as $payKey => $payItem) {
            $finalPrice = $this->pay->getGoodsPrice() == 0 ? $payItem['member_goods_price'] : bcsub($payItem['member_goods_price'], bcmul($payItem['member_goods_price'] / $this->pay->getGoodsPrice(), $orderDiscounts, 2), 2);
            $orderGoodsData = [
                'order_id' => $this->order['order_id'],         // 订单id
                'goods_id' => $payItem['goods_id'],             // 商品id
                'goods_name' => $payItem['goods_name'],         // 商品名称
                'goods_sn' => $payItem['goods_sn'],             // 商品货号
                'goods_num' => $payItem['goods_num'],           // 购买数量
                'final_price' => $finalPrice,                   // 每件商品实际支付价格
                'goods_price' => $payItem['goods_price'],       // 商品价
                'cost_price' => $goodsArr[$payItem['goods_id']]['cost_price'],          // 成本价,
                'member_goods_price' => $payItem['member_goods_price'],                 // 会员折扣价
                'give_integral' => $this->order['order_type'] != 5 ? $goodsArr[$payItem['goods_id']]['give_integral'] : 0,    // 购买商品赠送积分
                'spec_key' => '',
                'spec_key_name' => '',
                'is_gift' => 0,
                'gift_goods_id' => 0,
                'gift_goods_spec_key' => '',
                'prom_type' => 0,
                'prom_id' => 0,
                'sku' => $payItem['sku'] ? $payItem['sku'] : '',                        // sku
                'use_integral' => $payItem['use_integral'] ?: 0,
                'commission' => $goodsArr[$payItem['goods_id']]['commission'],
                'trade_type' => $goodsArr[$payItem['goods_id']]['trade_type'],
                'sale_type' => $goodsArr[$payItem['goods_id']]['sale_type'],
                're_id' => isset($payItem['re_id']) ? intval($payItem['re_id']) : 0,
                'goods_pv' => isset($payItem['goods_pv']) ? bcmul(bcmul($payItem['goods_pv'], $payItem['goods_num'], 2), $promRate, 2) : 0,
                'pay_type' => $payItem['type'],      // 购买方式：1现金+积分 2现金
                'order_id2' => 0,
                'supplier_goods_id' => 0,
                'is_agent' => $payItem['goods']['is_agent'],
                'school_credit' => $payItem['school_credit'] ?? 0,
            ];
            if (!empty($payItem['spec_key'])) {
                $orderGoodsData['spec_key'] = $payItem['spec_key'];
                $orderGoodsData['spec_key_name'] = $payItem['spec_key_name'];
            }
            if ($orderGoodsData['final_price'] == $orderGoodsData['goods_price']) {
                $orderGoodsData['use_integral'] = 0;
            }
            if ($payItem['prom_type']) {
                $orderGoodsData['prom_type'] = $payItem['prom_type'];   // 0普通订单 1限时抢购 2团购 3促销优惠 7订单合购优惠
                $orderGoodsData['prom_id'] = $payItem['prom_id'];       // 活动id
            }
            array_push($orderGoodsAllData, $orderGoodsData);
            if (!empty($payItem['gift2_goods'])) {
                foreach ($payItem['gift2_goods'] as $k => $v) {
                    $orderGoodsData = [
                        'order_id' => $this->order['order_id'],         // 订单id
                        'goods_id' => $v['goods_id'],                   // 商品id
                        'goods_name' => $v['goods_name'],               // 商品名称
                        'goods_sn' => $v['goods_sn'],                   // 商品货号
                        'goods_num' => $v['goods_num'],                 // 购买数量
                        'final_price' => 0,                             // 每件商品实际支付价格
                        'goods_price' => 0,                             // 商品价
                        'cost_price' => 0,
                        'member_goods_price' => 0,
                        'give_integral' => 0,
                        'spec_key' => '',
                        'spec_key_name' => '',
                        'is_gift' => 1,
                        'gift_goods_id' => $payItem['goods_id'],
                        'gift_goods_spec_key' => $payItem['spec_key'],
                        'prom_type' => $v['prom_type'],
                        'prom_id' => $v['prom_id'],
                        'sku' => $v['sku'] ? $v['sku'] : '',            // sku
                        'use_integral' => 0,
                        'commission' => 0,
                        'trade_type' => $v['trade_type'],
                        'sale_type' => 1,
                        're_id' => 0,
                        'goods_pv' => 0,
                        'pay_type' => 0,
                        'order_id2' => 0,
                        'supplier_goods_id' => 0,
                        'is_agent' => 0,
                        'school_credit' => $payItem['school_credit'] ?? 0,
                    ];
                    if (!empty($v['spec_key'])) {
                        $orderGoodsData['spec_key'] = $v['spec_key'];           // 商品规格
                        $orderGoodsData['spec_key_name'] = $v['spec_key_name']; // 商品规格名称
                    }
                    array_push($orderGoodsAllData, $orderGoodsData);
                }
            }
            if (!empty($payItem['gift_goods'])) {
                foreach ($payItem['gift_goods'] as $k => $v) {
                    $orderGoodsData = [
                        'order_id' => $this->order['order_id'],         // 订单id
                        'goods_id' => $v['goods_id'],                   // 商品id
                        'goods_name' => $v['goods_name'],               // 商品名称
                        'goods_sn' => $v['goods_sn'],                   // 商品货号
                        'goods_num' => $v['goods_num'],                 // 购买数量
                        'final_price' => 0,                             // 每件商品实际支付价格
                        'goods_price' => 0,                             // 商品价
                        'cost_price' => 0,
                        'member_goods_price' => 0,
                        'give_integral' => 0,
                        'spec_key' => '',
                        'spec_key_name' => '',
                        'is_gift' => 1,
                        'gift_goods_id' => $payItem['goods_id'],
                        'gift_goods_spec_key' => $payItem['spec_key'],
                        'prom_type' => $v['prom_type'],
                        'prom_id' => $v['prom_id'],
                        'sku' => $v['sku'] ? $v['sku'] : '', // sku
                        'use_integral' => 0,
                        'commission' => 0,
                        'trade_type' => $v['trade_type'],
                        'sale_type' => 1,
                        're_id' => 0,
                        'goods_pv' => 0,
                        'pay_type' => 0,
                        'order_id2' => 0,
                        'supplier_goods_id' => 0,
                        'is_agent' => 0,
                        'school_credit' => $payItem['school_credit'] ?? 0,
                    ];
                    if (!empty($v['spec_key'])) {
                        $orderGoodsData['spec_key'] = $v['spec_key']; // 商品规格
                        $orderGoodsData['spec_key_name'] = $v['spec_key_name']; // 商品规格名称
                    }
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
                        $orderDiscount = bcadd($orderDiscount, $orderProm['discount_price'], 2);
                    }
                    $orderPromId[] = $orderProm['order_prom_id'];
                }
            }
        }
        // 订单优惠赠品
        $promGiftList = $this->pay->getPromGiftList();
        if (!empty($promGiftList)) {
            foreach ($promGiftList as $prom) {
                foreach ($prom['goods_list'] as $goods) {
                    $goodsInfo = M('goods')->where(['goods_id' => $goods['goods_id']])->field('goods_sn, trade_type')->find();
                    $goodsSpec = M('spec_goods_price')->where(['item_id' => $goods['item_id']])->field('key, key_name')->find();
                    $orderGoodsData = [
                        'order_id' => $this->order['order_id'],         // 订单id
                        'goods_id' => $goods['goods_id'],               // 商品id
                        'goods_name' => $goods['goods_name'],           // 商品名称
                        'goods_sn' => $goodsInfo['goods_sn'],           // 商品货号
                        'goods_num' => $goods['goods_num'],             // 购买数量
                        'final_price' => 0,                             // 每件商品实际支付价格
                        'goods_price' => 0,                             // 商品价
                        'cost_price' => 0,
                        'member_goods_price' => 0,
                        'give_integral' => 0,
                        'spec_key' => $goodsSpec['key'],
                        'spec_key_name' => $goodsSpec['key_name'],
                        'is_gift' => 1,
                        'gift_goods_id' => 0,
                        'gift_goods_spec_key' => '',
                        'prom_type' => 7,
                        'prom_id' => $prom['prom_id'],
                        'sku' => '',
                        'use_integral' => 0,
                        'commission' => 0,
                        'trade_type' => $goodsInfo['trade_type'],
                        'sale_type' => 1,
                        're_id' => 0,
                        'goods_pv' => 0,
                        'pay_type' => 0,
                        'order_id2' => 0,
                        'supplier_goods_id' => 0,
                        'is_agent' => 0,
                        'school_credit' => $payItem['school_credit'] ?? 0,
                    ];
                    array_push($orderGoodsAllData, $orderGoodsData);
                }
            }
        }
        // 满单赠品
        $giftGoodsList = $this->pay->getGiftGoodsList();
        if (!empty($giftGoodsList)) {
            foreach ($giftGoodsList as $giftGoods) {
                $orderGoodsData = [
                    'order_id' => $this->order['order_id'],         // 订单id
                    'goods_id' => $giftGoods['goods_id'],               // 商品id
                    'goods_name' => $giftGoods['goods_name'],           // 商品名称
                    'goods_sn' => $giftGoods['goods_sn'],           // 商品货号
                    'goods_num' => $giftGoods['goods_num'],             // 购买数量
                    'final_price' => 0,                             // 每件商品实际支付价格
                    'goods_price' => 0,                             // 商品价
                    'cost_price' => 0,
                    'member_goods_price' => 0,
                    'give_integral' => 0,
                    'spec_key' => $giftGoods['goods']['spec_key'],
                    'spec_key_name' => $giftGoods['goods']['spec_key_name'],
                    'is_gift' => 1,
                    'gift_goods_id' => 0,
                    'gift_goods_spec_key' => '',
                    'prom_type' => 8,
                    'prom_id' => $giftGoods['gift_reward_id'],
                    'sku' => '',
                    'use_integral' => 0,
                    'commission' => 0,
                    'trade_type' => $giftGoods['goods']['trade_type'],
                    'sale_type' => 1,
                    're_id' => 0,
                    'goods_pv' => 0,
                    'pay_type' => 0,
                    'order_id2' => 0,
                    'supplier_goods_id' => 0,
                    'is_agent' => 0,
                    'school_credit' => $payItem['school_credit'] ?? 0,
                ];
                array_push($orderGoodsAllData, $orderGoodsData);
            }
        }
        if (!empty($this->order1Goods) || !empty($this->order2Goods)) {
            foreach ($orderGoodsAllData as &$orderGoods) {
                foreach ($this->order1Goods as $order1Goods) {
                    if ($orderGoods['goods_id'] == $order1Goods['goods_id'] && $orderGoods['spec_key'] == $order1Goods['spec_key']) {
                        $orderGoods['order_id2'] = $order1Goods['order_id'];
                        $orderGoods['supplier_goods_id'] = $order1Goods['supplier_goods_id'];
                        break;
                    }
                }
                foreach ($this->order2Goods as $order2Goods) {
                    if ($orderGoods['goods_id'] == $order2Goods['goods_id'] && $orderGoods['spec_key'] == $order2Goods['spec_key']) {
                        $orderGoods['order_id2'] = $order2Goods['order_id'];
                        $orderGoods['supplier_goods_id'] = $order2Goods['supplier_goods_id'];
                        break;
                    }
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
            $userCouponId = $couponList->where(['cid' => $couponId, 'uid' => $user['user_id'], 'status' => 0])->order('send_time')->value('id');
            if ($userCouponId) {
                $updateData = [
                    'uid' => $user['user_id'],
                    'order_id' => $this->order['order_id'],
                    'use_time' => time(),
                    'status' => 1
                ];
                Db::name('coupon_list')->where('id', $userCouponId)->update($updateData);
                Db::name('coupon')->where('id', $couponId)->setInc('use_num'); // 优惠券的使用数量加一
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
                $userCouponId = $couponList->where(['cid' => $couponId, 'uid' => $user['user_id'], 'status' => 0])->order('send_time')->value('id');
                if ($userCouponId) {
                    $updateData = [
                        'uid' => $user['user_id'],
                        'order_id' => $this->order['order_id'],
                        'use_time' => time(),
                        'status' => 1
                    ];
                    Db::name('coupon_list')->where('id', $userCouponId)->update($updateData);
                    Db::name('coupon')->where('id', $couponId)->setInc('use_num'); // 优惠券的使用数量加一
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
                $user->pay_points = bcsub($user->pay_points, $this->pay->getPayPoints(), 2); // 消费积分
            }
            if ($this->pay->getUserElectronic() > 0) {
                $user->user_electronic = bcsub($user->user_electronic, $this->pay->getUserElectronic(), 2); // 抵扣余额
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
            // 使用登录奖励
            $taskLogic = new TaskLogic(4);
            $taskLogic->useLoginProfit($order);
            // 更新缓存
            $user = Db::name('users')->where('user_id', $user['user_id'])->find();
            TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
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

    /**
     * 添加子订单
     * @param int $source
     * @return bool
     * @throws TpshopException
     */
    private function addSplitOrder($source = 1)
    {
        $res = $this->pay->getOrderSplit();
        if (empty($res)) {
            return true;
        }
        $order1 = $res['order1'];
        $order2 = $res['order2'];
        $res = $this->pay->getOrderSplitGoods();
        $order1Goods = $res['order1_goods'];
        $order2Goods = $res['order2_goods'];
        $orderLogic = new OrderLogic();
        // 有供应链商品的情况下才拆分订单
        if (!empty($order1) && !empty($order2)) {
            /*
             * 子订单1
             */
            $orderData = [
                'parent_id' => $this->order['order_id'],
                'order_sn' => 'C' . $orderLogic->get_order_sn(),
                'order_type' => 1,
                'user_id' => $this->pay->getUser()['user_id'],
                'goods_price' => $order1['goods_price'],
                'shipping_price' => $this->pay->getShippingPrice(),
                'user_electronic' => $order1['user_electronic'],
                'coupon_price' => $order1['order_coupon_price'],
                'order_prom_amount' => $order1['order_prom_price'],
                'integral' => $order1['integral'],
                'integral_money' => $order1['integral'],
                'total_amount' => $order1['goods_price'],
                'order_amount' => $order1['order_amount'],
                'add_time' => $this->order['add_time'],
                'coupon_id' => $this->pay->getCouponId(),
                'source' => $source,
                'order_pv' => $order1['order_pv'],
                'consignee' => $this->order['consignee'],
                'id_card' => $this->order['id_card'],
                'province' => $this->order['province'],
                'city' => $this->order['city'],
                'district' => $this->order['district'],
                'twon' => $this->order['twon'],
                'address' => $this->order['address'],
                'mobile' => $this->order['mobile'],
                'zipcode' => $this->order['zipcode'],
                'user_note' => $this->order['user_note'] ?? '',
                'invoice_title' => $this->order['invoice_title'] ?? '',
                'taxpayer' => $this->order['taxpayer'] ?? '',
            ];
            $order1Id = M('order')->add($orderData);
            if ($order1Id === false) {
                throw new TpshopException('订单入库', 0, ['status' => -8, 'msg' => '添加子订单1失败', 'result' => '']);
            }
            foreach ($order1Goods as &$orderGoods) {
                $orderGoods['order_id'] = $order1Id;
            }
            $this->order1Goods = $order1Goods;
        }
        if (!empty($order2)) {
            /*
             * 子订单2
             */
            $orderData = [
                'parent_id' => $this->order['order_id'],
                'order_sn' => 'C' . $orderLogic->get_order_sn(),
                'order_type' => 3,
                'user_id' => $this->pay->getUser()['user_id'],
                'goods_price' => $order2['goods_price'],
                'shipping_price' => 0,
                'user_electronic' => $order2['user_electronic'],
                'coupon_price' => $order2['order_coupon_price'],
                'order_prom_amount' => $order2['order_prom_price'],
                'integral' => $order2['integral'],
                'integral_money' => $order2['integral'],
                'total_amount' => $order2['goods_price'],
                'order_amount' => $order2['order_amount'],
                'add_time' => $this->order['add_time'],
                'coupon_id' => $this->pay->getCouponId(),
                'source' => $source,
                'order_pv' => $order2['order_pv'],
                'consignee' => $this->order['consignee'],
                'id_card' => $this->order['id_card'],
                'province' => $this->order['province'],
                'city' => $this->order['city'],
                'district' => $this->order['district'],
                'twon' => $this->order['twon'],
                'address' => $this->order['address'],
                'mobile' => $this->order['mobile'],
                'zipcode' => $this->order['zipcode'],
                'user_note' => $this->order['user_note'] ?? '',
                'invoice_title' => $this->order['invoice_title'] ?? '',
                'taxpayer' => $this->order['taxpayer'] ?? '',
            ];
            $order2Id = M('order')->add($orderData);
            if ($order2Id === false) {
                throw new TpshopException('订单入库', 0, ['status' => -8, 'msg' => '添加子订单2失败', 'result' => '']);
            }
            foreach ($order2Goods as &$orderGoods) {
                $orderGoods['order_id'] = $order2Id;
            }
            $this->order2Goods = $order2Goods;
        }
    }
}
