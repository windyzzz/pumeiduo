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

use app\common\logic\order\ExtraLogic;
use app\common\logic\order\GiftLogic;
use app\common\logic\order\Gift2Logic;
use app\common\logic\supplier\GoodsService;
use app\common\model\CouponList;
use app\common\util\TpshopException;
use think\Db;

/**
 * 计算价格类
 * Class CatsLogic.
 */
class Pay
{
    protected $userId;
    protected $user;
    protected $payList;

    private $totalAmount = '0';             // 订单总价
    private $orderAmount = '0';             // 应付金额
    private $shippingPrice = '0';           // 物流费
    private $goodsPrice = '0';              // 商品总价
//    private $orderPromPrice = '0';          // 订单优惠促销商品总价
    private $cutFee = '0';                  // 共节约多少钱
    private $totalNum = '0';                // 商品总共数量
    private $integralMoney = '0';           // 积分抵消金额
    private $userElectronic = '0';          // 使用电子币
    private $payPoints = '0';               // 使用积分
    private $couponPrice = '0';             // 优惠券抵消金额

    private $orderPromIds = [];             // 订单优惠IDs
    private $orderPromAmount = '0';         // 订单优惠金额
    private $goodsPromAmount = '0';         // 商品优惠金额
    private $couponId;
    private $couponIdRe;

    private $promTitleData = [];            // 优惠标题数据

    private $giftLogic;
    private $gift2Logic;
    private $gift_goods_list;               // 赠品商品列表
//    private $gift2_goods_list;              // 赠品商品列表
    private $promGiftList;                  // 订单优惠赠品

    private $extraLogic;
    private $extra_goods_ids = [];          // 加价购商品ID
    private $extra_goods_list;              // 加价购商品列表
    private $extra_reward;                  // 加价购商品记录列表
    private $extra_price = '0';             // 加价购商品价格

    private $goodsPv = '0';                       // 商品pv
    private $orderPv = '0';                       // 订单总pv

    private $order1 = [];                   // 圃美多、海外购商品子订单
    private $order1Goods = [];
    private $order2 = [];                   // 供应链商品子订单
    private $order2Goods = [];

    public function __construct()
    {
        $this->giftLogic = new GiftLogic();
        $this->gift2Logic = new Gift2Logic();
        $this->extraLogic = new ExtraLogic();
    }

    /**
     * 计算订单表的普通订单商品
     *
     * @param $order_goods
     *
     * @throws TpshopException
     */
    public function payOrder($order_goods)
    {
        $this->payList = $order_goods;
        $order = Db::name('order')->where('order_id', $this->payList[0]['order_id'])->find();
        if (empty($order)) {
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '找不到订单数据', 'result' => '']);
        }
        $reduce = tpCache('shopping.reduce');
        if (0 == $order['pay_status'] && 2 == $reduce) {
            $goodsListCount = count($this->payList);
            for ($payCursor = '0'; $payCursor < $goodsListCount; ++$payCursor) {
                $goods_stock = getGoodNum($this->payList[$payCursor]['goods_id'], $this->payList[$payCursor]['spec_key']); // 最多可购买的库存数量
                if ($goods_stock <= '0' && $this->payList[$payCursor]['goods_num'] > $goods_stock) {
                    throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => $this->payList[$payCursor]['goods_name'] . ',' . $this->payList[$payCursor]['spec_key_name'] . '库存不足,请重新下单', 'result' => '']);
                }
            }
        }
        $this->Calculation();
    }

    /**
     * 计算购买购物车的商品
     *
     * @param $cart_list
     *
     * @throws TpshopException
     */
    public function payCart($cart_list)
    {
        $this->payList = $cart_list;
        $goodsListCount = count($this->payList);
        if (0 == $goodsListCount) {
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '你的购物车没有选中商品', 'result' => '']);
        }
        $this->Calculation();
    }

    /**
     * 计算购买商品表的商品
     *
     * @param $goods_list
     *
     * @throws TpshopException
     */
    public function payGoodsList($goods_list)
    {
        $goodsListCount = count($goods_list);
        if (0 == $goodsListCount) {
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '你的购物车没有选中商品', 'result' => '']);
        }
        $discount = $this->getDiscount();
        for ($goodsCursor = '0'; $goodsCursor < $goodsListCount; ++$goodsCursor) {
            //优先使用member_goods_price，没有member_goods_price使用goods_price
            if (empty($goods_list[$goodsCursor]['member_goods_price'])) {
                //积分商品不打折。因为是全积分商品打会员折扣，结算会出现负数
                if ($goods_list[$goodsCursor]['exchange_integral'] > 0) {
                    $goods_list[$goodsCursor]['member_goods_price'] = $goods_list[$goodsCursor]['goods_price'];
                } else {
                    $goods_list[$goodsCursor]['member_goods_price'] = bcmul($discount, $goods_list[$goodsCursor]['goods_price'], 2);
                }
            }
        }
        $this->payList = $goods_list;
        $this->Calculation();
    }

    public function getUsePoint()
    {
        $point = '0';
        foreach ($this->payList as $v) {
            $point = bcadd($point, bcmul($v['goods_num'], $v['use_integral'], 2), 2);
        }
        $this->payPoints = bcadd($this->payPoints, $point, 2);
        return $this->payPoints;
    }

    public function getPromInfo()
    {
        $prom_type = '0';
        $prom_id = '0';
        foreach ($this->payList as $v) {
            if ($v['prom_type'] > 0) {
                $prom_type = $v['prom_type'];
                $prom_id = $v['prom_id'];
                break;
            }
        }

        return [$prom_type, $prom_id];
    }

    /**
     * 初始化计算.
     */
    private function Calculation()
    {
        $goodsListCount = count($this->payList);
        for ($payCursor = '0'; $payCursor < $goodsListCount; ++$payCursor) {
            $this->totalAmount = bcadd($this->totalAmount, $this->payList[$payCursor]['goods_price'], 2);
            $goods_fee = $this->payList[$payCursor]['goods_fee'] = bcmul($this->payList[$payCursor]['goods_num'], $this->payList[$payCursor]['member_goods_price'], 2);    // 小计
            $this->goodsPrice = bcadd($this->goodsPrice, $goods_fee, 2); // 商品总价
            if (array_key_exists('market_price', $this->payList[$payCursor])) {
                $this->cutFee = bcadd($this->cutFee, bcmul($this->payList[$payCursor]['goods_num'], bcsub($this->payList[$payCursor]['market_price'], $this->payList[$payCursor]['member_goods_price'], 2), 2), 2); // 共节约
            }
            $this->totalNum = bcadd($this->totalNum, $this->payList[$payCursor]['goods_num'], 2);
//            if (isset($this->payList[$payCursor]['is_order_prom']) && $this->payList[$payCursor]['is_order_prom'] == 1) {
//                $this->orderPromPrice = bcadd($this->orderPromPrice, bcmul($this->payList[$payCursor]['goods_num'], $this->payList[$payCursor]['member_goods_price'], 2), 2);
//            }
        }
        $this->orderAmount = $this->goodsPrice;
//        $this->totalAmount = $this->goodsPrice;
    }

    /**
     * 设置用户ID.
     *
     * @param $user_id
     * @throws TpshopException
     *
     */
    public function setUserId($user_id)
    {
        $this->userId = $user_id;
        $this->user = Db::name('users')->where(['user_id' => $this->userId])->find();
        if (empty($this->user)) {
            throw new TpshopException('计算订单价格', 0, ['status' => -9, 'msg' => '未找到用户', 'result' => '']);
        }
    }

    /**
     * 设置购物商品列表
     * @param $goodsList
     */
    public function setPayList($goodsList)
    {
        $this->payList = $goodsList;
    }

    /**
     * 设置订单pv
     * @param $payList
     */
    public function setOrderPv()
    {
        foreach ($this->payList as $item) {
            if (isset($item['goods_pv'])) {
                $this->goodsPv = bcadd($this->goodsPv, bcmul($item['goods_pv'], $item['goods_num'], 2), 2);
            }
        }
        // 订单优惠的价格
        $promAmount = bcsub(bcadd($this->orderPromAmount, $this->couponPrice, 2), $this->goodsPromAmount, 2);
        // 优惠比例
        $promRate = $this->totalAmount != '0' ? bcsub(1, ($promAmount / $this->totalAmount), 2) : '0';
        $this->orderPv = $promRate < 1 ? bcmul($promRate, $this->goodsPv, 2) : $this->goodsPv;
    }

    /**
     * 拆分订单商品数据
     * @param $payList
     */
    public function setOrderSplitGoods($payList)
    {
        foreach ($payList as $goods) {
            $goodsData = [
                'goods_id' => $goods['goods_id'],
                'supplier_goods_id' => $goods['goods']['supplier_goods_id'],
                'goods_num' => $goods['goods_num'],
                'goods_price' => $goods['goods_price'],
                'member_goods_price' => $goods['member_goods_price'],
                'use_integral' => $goods['integral'],
                'spec_key' => $goods['spec_key'],
                'spec_key_name' => $goods['spec_key_name'],
                'goods_pv' => isset($goods['goods_pv']) ? $goods['goods_pv'] : 0,
            ];
            if ($goods['goods']['is_supply'] == 0) {
                $this->order1Goods[$goods['goods_id'] . '_' . $goods['item_id']] = $goodsData;
            } elseif ($goods['goods']['is_supply'] == 1) {
                $this->order2Goods[$goods['goods_id'] . '_' . $goods['item_id']] = $goodsData;
            }
        }
    }

    /**
     * 检查供应链商品地区购买限制
     * @param $userAddress
     * @throws TpshopException
     */
    public function checkOrderSplitGoods($userAddress)
    {
        if (!empty($this->order2Goods)) {
            $province = M('region2')->where(['id' => $userAddress['province']])->value('ml_region_id');
            $city = M('region2')->where(['id' => $userAddress['city']])->value('ml_region_id');
            $district = M('region2')->where(['id' => $userAddress['district']])->value('ml_region_id');
            $town = M('region2')->where(['id' => $userAddress['twon']])->value('ml_region_id') ?? 0;
            $goodsData = [];
            $supplierGoodsData = [];
            foreach ($this->order2Goods as $orderGoods) {
                $goodsData[] = [
                    'goods_id' => $orderGoods['supplier_goods_id'],
                    'spec_key' => $orderGoods['spec_key'],
                    'goods_num' => $orderGoods['goods_num'],
                ];
                $supplierGoodsData[$orderGoods['supplier_goods_id']] = [
                    'goods_num' => $orderGoods['goods_num']
                ];
            }
            $res = (new GoodsService())->checkGoodsRegion($goodsData, $province, $city, $district, $town);
            if ($res['status'] == 0) {
                throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['msg' => $res['msg']]);
            }
            foreach ($res['data'] as $v) {
                if ($v['goods_count'] <= 0 || $v['goods_count'] < $supplierGoodsData[$v['goods_id']]['goods_num']) {
                    throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['status' => 0, 'msg' => $v['goods_name'] . ' 库存不足']);
                }
                if ($v['buy_num'] > $supplierGoodsData[$v['goods_id']]['goods_num']) {
                    throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['status' => 0, 'msg' => $v['goods_name'] . ' 最低购买数量为' . $v['buy_num']]);
                }
                if (isset($v['isAreaRestrict']) && $v['isAreaRestrict'] == true) {
                    throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['status' => 0, 'msg' => $v['goods_name'] . ' 当前地址不可购买']);
                }
                if (isset($v['isNoStock']) && $v['isNoStock'] == true) {
                    throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['status' => 0, 'msg' => $v['goods_name'] . ' 当前地区无库存']);
                }
                if (isset($v['isNoGoods']) && $v['isNoGoods'] == true) {
                    throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['status' => 0, 'msg' => $v['goods_name'] . ' 商品已失效']);
                }
                if (isset($v['IsOnSale']) && $v['IsOnSale'] == true) {
                    throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['status' => 0, 'msg' => $v['goods_name'] . ' 商品已下架']);
                }
            }
        }
    }

    /**
     * 拆分订单数据
     */
    public function setOrderSplit()
    {
        // 订单属性优惠价格（订单优惠 + 优惠券优惠 - 商品优惠）
        $promAmount = bcsub(bcadd($this->orderPromAmount, $this->couponPrice, 2), $this->goodsPromAmount, 2);
        // 优惠比例
        $promRate = bcsub(1, ($promAmount / $this->totalAmount), 2);
        if (empty($this->order1Goods) || empty($this->order2Goods)) {
//            $promAmount = $promAmount;
            $orderPromAmount = $this->orderPromAmount;
            $couponPrice = $this->couponPrice;
            $userElectronic = $this->userElectronic;
        } else {
            $promAmount = bcdiv($promAmount, 2, 2);
            $orderPromAmount = bcdiv($this->orderPromAmount, 2, 2);
            $couponPrice = bcdiv($this->couponPrice, 2, 2);
            $userElectronic = bcdiv($this->userElectronic, 2, 2);
        }
        if (!empty($this->order1Goods)) {
            /*
             * 子订单1
             */
            $goodsPrice = 0;
            $integral = 0;
            $goodsPv = 0;
            foreach ($this->order1Goods as $goods) {
                $goodsPrice = bcadd($goodsPrice, bcmul($goods['member_goods_price'], $goods['goods_num'], 2), 2);
                $integral = bcadd($integral, bcmul($goods['use_integral'], $goods['goods_num'], 2), 2);
                $goodsPv = bcadd($goodsPv, bcmul($goods['goods_pv'], $goods['goods_num'], 2), 2);
            }
            $this->order1['goods_price'] = $goodsPrice;
            $this->order1['integral'] = $integral;
            $this->order1['order_pv'] = $promRate < 1 ? bcmul($promRate, $goodsPv, 2) : $goodsPv;

            $order1GoodsRate = $this->order1['goods_price'] / bcadd($this->order1['goods_price'], $this->order2['goods_price'], 2);
            // 子订单的优惠分摊（订单优惠 + 优惠券优惠）
            $this->order1['goods_prom_price'] = $promAmount;
            // 子订单的订单优惠分摊
            $this->order1['order_prom_price'] = $orderPromAmount;
            // 子订单的优惠券优惠分摊
            $this->order1['order_coupon_price'] = $couponPrice;
            // 子订单的电子币抵扣分摊
            $this->order1['user_electronic'] = $userElectronic;
            // 子订单实付价
            $this->order1['order_amount'] = bcmul($order1GoodsRate, $this->orderAmount, 2);
        }
        if (!empty($this->order2Goods)) {
            /*
             * 子订单2
             */
            $goodsPrice = 0;
            $integral = 0;
            $goodsPv = 0;
            foreach ($this->order2Goods as $goods) {
                $goodsPrice = bcadd($goodsPrice, bcmul($goods['member_goods_price'], $goods['goods_num'], 2), 2);
                $integral = bcadd($integral, bcmul($goods['use_integral'], $goods['goods_num'], 2), 2);
                $goodsPv = bcadd($goodsPv, bcmul($goods['goods_pv'], $goods['goods_num'], 2), 2);
            }
            $this->order2['goods_price'] = $goodsPrice;
            $this->order2['integral'] = $integral;
            $this->order2['order_pv'] = $promRate < 1 ? bcmul($promRate, $goodsPv, 2) : $goodsPv;

            $order2GoodsRate = $this->order2['goods_price'] / bcadd($this->order1['goods_price'], $this->order2['goods_price'], 2);
            // 子订单的优惠分摊（订单优惠 + 优惠券优惠）
            $this->order2['goods_prom_price'] = $promAmount;
            // 子订单的订单优惠分摊
            $this->order2['order_prom_price'] = $orderPromAmount;
            // 子订单的优惠券优惠分摊
            $this->order2['order_coupon_price'] = $couponPrice;
            // 子订单的电子币抵扣分摊
            $this->order2['user_electronic'] = $userElectronic;
            // 子订单实付价
            $this->order2['order_amount'] = bcmul($order2GoodsRate, $this->orderAmount, 2);
        }
    }

    /**
     * 使用积分.
     *
     * @param $pay_points
     * @param $is_exchange |是否有使用积分兑换商品流程
     * @throws TpshopException
     *
     */
    public function usePayPoints($pay_points, $is_exchange = false)
    {
        //1.检查可用最大积分数 BY J
        $GoodsLogic = new GoodsLogic();
        $can_use_integral = $GoodsLogic->countIntegral($this->payList);
        if (0 != $pay_points && 0 == $can_use_integral) {
            throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => '该笔订单不能使用积分', 'result' => '']);
        }
        // if($pay_points > $can_use_integral){
        //     throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => '您使用的积分不能大于' . $can_use_integral, 'result' => '']);
        // }

        if ($pay_points > 0 && $this->orderAmount > 0) {
            $point_rate = tpCache('shopping.point_rate'); //兑换比例
            if (false == $is_exchange) {
                $use_percent_point = tpCache('shopping.point_use_percent');     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
                $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用
                if (0 == $use_percent_point) {
                    // throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => '该笔订单不能使用积分', 'result' => '']);
                }
                if ($use_percent_point > 0 && $use_percent_point < 100) {
                    //计算订单最多使用多少积分
                    $point_limit = bcmul($this->orderAmount, bcmul($point_rate, $use_percent_point, 2), 2);
                    if ($pay_points > $point_limit) {
                        // throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => "该笔订单, 您使用的积分不能大于" . $point_limit, 'result' => '']);
                    }
                }
                if ($pay_points > $this->user['pay_points']) {
                    throw new TpshopException('计算订单价格', 0, ['status' => -5, 'msg' => '你的账户可用积分为:' . $this->user['pay_points'], 'result' => '']);
                }
                if ($min_use_limit_point > 0 && $pay_points < $min_use_limit_point) {
                    // throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => "您使用的积分必须大于".$min_use_limit_point."才可以使用", 'result' => '']);
                }
                // $order_amount_pay_point = floor($this->orderAmount * $point_rate);
                // if($pay_points > $order_amount_pay_point){

                //     $this->payPoints = $order_amount_pay_point;
                // }else{
                $this->payPoints = $pay_points;
                // }

                $this->integralMoney = $this->payPoints;

                // $this->orderAmount = $this->orderAmount - $this->integralMoney;
            } else {
                //积分兑换流程
                if ($pay_points <= $this->user['pay_points']) {
                    $this->payPoints = $pay_points;
                    $this->integralMoney = bcsub($pay_points, $point_rate, 2); //总积分兑换成的金额
                } else {
                    $this->payPoints = '0'; //需要兑换的总积分
                    $this->integralMoney = '0'; //总积分兑换成的金额
                }
                // $this->orderAmount = $this->orderAmount - $this->integralMoney;
            }
        }
    }

    /**
     * 检测支付商品购买限制 BY J.
     *
     * @param $user_electronic
     * @throws TpshopException
     *
     */
    public function check()
    {
        $user_info = $this->getUser();
        $pay_list = $this->payList;

        //1.分销--会员升级区商品购买顺序
        //2.商品限购
        //3.超值套组
        //4.团购
        //5.加价购
        $district_level = [];
        foreach ($pay_list as $k => $v) {
            $pay_list[$k]['item_id'] = M('spec_goods_price')->where(['goods_id' => $v['goods_id'], 'key' => $v['spec_key']])->value('item_id') ?? 0;
            $goods_info = M('goods')
                ->field('goods_id,goods_name,distribut_id,zone,limit_buy_num,sale_type,prom_type')
                ->where(['goods_id' => $v['goods_id']])
                ->find();

            if (3 == $goods_info['zone'] && $goods_info['distribut_id'] > 0) {
                $district_level[] = $goods_info['distribut_id'];
            }

            //商品限购
            if ($goods_info['limit_buy_num'] > 0) {
                $buy_num = M('order')
                    ->alias('oi')
                    ->join('__ORDER_GOODS__ og', 'oi.order_id = og.order_id', 'LEFT')
                    ->where('user_id', $user_info['user_id'])
                    ->where('order_status', 'NOT IN', [3, 5])
                    ->where('og.goods_id', $goods_info['goods_id'])
                    ->sum('og.goods_num');
                if (bcadd($buy_num, $v['goods_num'], 2) > $goods_info['limit_buy_num']) {
                    throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => "超出商品：【{$goods_info['goods_name']}】 限购数量， 每人限购 {$goods_info['limit_buy_num']} 件", 'result' => '']);
                }
            }

            //3.超值套组
            // $goods_info = M('Goods')->where('goods_id',$v['goods_id'])->getField('sale_type');
//            if (2 == $goods_info['sale_type']) {
//                $g_list = M('GoodsSeries')->where('goods_id', $v['goods_id'])->select();
//                if ($g_list) {
//                    foreach ($g_list as $gk => $gv) {
//                        $series_goods_info = M('Goods')->field('goods_id,goods_name,store_count')->where('goods_id', $gv['g_id'])->find();
//                        if ($gv['item_id']) {
//                            // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
//                            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
//                            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $gv['g_id'], 'item_id' => $gv['item_id']]);
//                            if ($specGoodsPrice['store_count'] < $v['goods_num'] * $gv['g_number']) {
//                                throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => "购买数量超出套组商品：【{$series_goods_info['goods_name']},{$specGoodsPrice['key_name']}】 库存数量 {$specGoodsPrice['store_count']}", 'result' => '']);
//                            }
//                        } else {
//                            if ($series_goods_info['store_count'] < $v['goods_num'] * $gv['g_number']) {
//                                throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => "购买数量超出套组商品：【{$series_goods_info['goods_name']}】 库存数量 {$series_goods_info['store_count']}", 'result' => '']);
//                            }
//                        }
//                    }
//                }
//            }

            // 团购
            $now = time();
            if (2 == $goods_info['prom_type']) {
                $group_activity = M('group_buy')
                    ->where('goods_id', $v['goods_id'])
                    ->where('item_id', isset($v['item_id']) ? $v['item_id'] : 0)
                    ->where('start_time', 'elt', $now)
                    ->where('end_time', 'egt', $now)
                    ->where('is_end', 0)
                    ->find();
                if ($group_activity) {
                    if ($group_activity['buy_limit'] > 0) {
                        if ($v['goods_num'] > $group_activity['buy_limit']) {
                            throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => "购买数量超出团购商品：【{$goods_info['goods_name']}】 的限购数量 {$group_activity['buy_limit']}", 'result' => '']);
                        }

                        $buy_num = M('order')
                            ->alias('oi')
                            ->join('__ORDER_GOODS__ og', 'oi.order_id = og.order_id', 'LEFT')
                            ->where('user_id', $user_info['user_id'])
                            ->where('order_status', 'NOT IN', [3, 5])
                            ->where('og.goods_id', $goods_info['goods_id'])
                            ->where('og.prom_id', $group_activity['id'])
                            ->sum('og.goods_num');

                        if (bcadd($buy_num, $v['goods_num'], 2) > $group_activity['buy_limit']) {
                            throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => "超出团购商品：【{$goods_info['goods_name']}】 限购数量， 每人限购 {$group_activity['buy_limit']} 件", 'result' => '']);
                        }
                    }

                    // 同一个人在同一个团中只能下单一次
                    $group_detail = M('group_detail')->where('group_id', $group_activity['id'])->order('batch desc')->find();
                    if ($group_detail && 1 == $group_detail['status'] && $group_detail['order_sn_list']) {
                        $order_sn_list = explode(',', $group_detail['order_sn_list']);
                        $order_users = M('Order')->where('order_sn', 'in', $order_sn_list)->getField('user_id', true);
                        if (in_array($user_info['user_id'], $order_users)) {
                            throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => '你已经参加过该批次的团购，请勿重复下单参加', 'result' => '']);
                        }
                    }
                }
            }
        }

        if ($district_level) {
            sort($district_level);
            $buy_distribut_level = current($district_level);
            $user_distribut_level = M('users')->where(['user_id' => $user_info['user_id']])->getField('distribut_level');

            // if($buy_distribut_level - 1 > $user_distribut_level){
            //     // throw new TpshopException("计算订单价格",0,['status' => -1, 'msg' => '会员升级区商品请先从低级的等级购买才能买高级！', 'result' => '']);
            // }

            if ($buy_distribut_level <= $user_distribut_level) {
                throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => '你已经是比这个高级的会员，不必购买这个了', 'result' => '']);
            }
        }

        // 加价购活动
        $this->extraLogic->setUserId($this->userId);
        $this->extraLogic->setGoodsList($this->payList);
        $this->extra_goods_list = $this->extraLogic->getGoodsList();
    }

    public function activity($isApp = false)
    {
        // 赠品活动
        $this->giftLogic->setUserId($this->userId);
        $this->giftLogic->setMoney($this->orderAmount);
        $this->giftLogic->setGoodsList($this->payList);
        $goods_list = $this->giftLogic->getGoodsList();
        if ($goods_list) {
            $this->gift_goods_list = array_values($goods_list);
            if (!$isApp) {
                $this->payList = array_merge($this->payList, $goods_list);
            }
        }
    }

    public function activityRecord($order)
    {
        // 满单赠品
        $this->giftLogic->setOrder($order);
        $this->giftLogic->record();
        // 加价购
        $this->extraLogic->setOrder($order);
        $this->extraLogic->setRewardInfo($this->extra_reward);
        $this->extraLogic->record();
    }

    public function activity2()
    {
        if ($this->payList) {
            $goods_list = $this->activity2_goods($this->payList);
            $this->payList = $goods_list;
        }
    }

    function activity2_goods($goods_list)
    {
        foreach ($goods_list as $k => $v) {
            if (isset($v['spec_key'])) {
                $gift2_goods = M('gift2_goods')
                    ->alias('gg')
                    ->field('gg.goods_id,gg.item_id,gg.stock as goods_num,gt.id,gg.buy_stock,g.goods_remark')
                    ->join('gift2 gt', 'gt.id = gg.promo_id')
                    ->join('goods g', 'g.goods_id = gg.buy_goods_id')
                    ->join('spec_goods_price sg', "sg.item_id = gg.buy_item_id and sg.key = '" . $v['spec_key'] . "'")
                    ->where(array('gg.buy_goods_id' => $v['goods_id'], 'gg.buy_stock' => array('elt', $v['goods_num']), 'gt.start_time' => array('elt', NOW_TIME), 'gt.end_time' => array('egt', NOW_TIME)))
                    ->select();
//                $v['item_id'] = M('spec_goods_price')->where(['goods_id' => $v['goods_id'], 'key' => $v['spec_key']])->value('item_id');
            } else {
                $gift2_goods = M('gift2_goods')
                    ->alias('gg')
                    ->field('gg.goods_id,gg.item_id,gg.stock as goods_num,gt.id,gg.buy_stock,g.goods_remark')
                    ->join('goods g', 'g.goods_id = gg.buy_goods_id')
                    ->join('gift2 gt', 'gt.id = gg.promo_id')
                    ->where(array('gg.buy_goods_id' => $v['goods_id'], 'gg.buy_item_id' => 0, 'gg.buy_stock' => array('elt', $v['goods_num']), 'gt.start_time' => array('elt', NOW_TIME), 'gt.end_time' => array('egt', NOW_TIME)))
                    ->select();
            }
            if ($gift2_goods) {
                $gift2_goods_list = array();
                foreach ($gift2_goods as $key => $val) {
                    $gift2_goods_list[$key] = $val;
                    $stock = bcdiv($v['goods_num'], $val['buy_stock'], 0);

                    $goods = M('goods')->where(array('goods_id' => $val['goods_id']))->field('goods_id,goods_sn,goods_name,sku,trade_type,original_img,store_count')->find();

                    if ($goods['store_count'] < $val['goods_num']) {
                        $gift2_goods_list = array();
                        break;
                    }
                    if ($goods['store_count'] < $stock) {
                        throw new TpshopException('计算订单价格', 0, ['status' => -51, 'msg' => $goods['goods_name'] . '只剩下' . $goods['store_count'] . '件赠品，请重新下单', 'result' => '']);
                    }

                    $gift2_goods_list[$key]['goods_num'] = bcmul($stock, $gift2_goods_list[$key]['goods_num'], 2);
                    $gift2_goods_list[$key]['goods_id'] = $goods['goods_id'];
                    $gift2_goods_list[$key]['goods_sn'] = $goods['goods_sn'];
                    $gift2_goods_list[$key]['goods_name'] = $goods['goods_name'];
                    $gift2_goods_list[$key]['sku'] = $goods['sku'];
                    $gift2_goods_list[$key]['trade_type'] = $goods['trade_type'];
                    $gift2_goods_list[$key]['prom_type'] = 9;
                    $gift2_goods_list[$key]['prom_id'] = $val['id'];
                    $gift2_goods_list[$key]['original_img'] = $goods['original_img'];

                    if (!empty($val['item_id'])) {
                        $spec_goods_price = M('spec_goods_price')->where(array('goods_id' => $val['goods_id'], 'item_id' => $val['item_id']))->field('key,key_name,store_count')->find();
                        $gift2_goods_list[$key]['spec_key'] = $spec_goods_price['key'];
                        $gift2_goods_list[$key]['spec_key_name'] = $spec_goods_price['key_name'];
                        if ($spec_goods_price['store_count'] < $val['goods_num']) {
                            $gift2_goods_list = array();
                            break;
                        }
                        if ($spec_goods_price['store_count'] < $stock) {
                            throw new TpshopException('计算订单价格', 0, ['status' => -51, 'msg' => $goods['goods_name'] . '只剩下' . $spec_goods_price['store_count'] . '件赠品，请重新下单', 'result' => '']);
                        }
                    } else {
                        $gift2_goods_list[$key]['spec_key'] = '';
                        $gift2_goods_list[$key]['spec_key_name'] = '';
                    }
                }
                if (count($gift2_goods_list) > 0) {
                    $goods_list[$k]['gift2_goods'] = $gift2_goods_list;
                }
            }
        }
        $giftGoods = [];
        // 订单优惠促销（查看是否有赠送商品）
        $orderProm = M('order_prom')
            ->where(['type' => ['in', '0, 2'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
            ->field('id, title, order_price')->select();
        foreach ($orderProm as $prom) {
            if ($this->orderAmount >= $prom['order_price']) {
                // 订单价格满足要求
                $giftGoodsList = Db::name('order_prom_goods opg')->join('goods g', 'g.goods_id = opg.goods_id')
                    ->join('spec_goods_price sgp', 'sgp.item_id = opg.item_id', 'LEFT')->where(['opg.order_prom_id' => $prom['id'], 'opg.type' => 2])
                    ->field('opg.goods_id, opg.item_id, opg.goods_num, g.goods_sn, g.goods_name, g.goods_remark, g.sku, g.trade_type, g.prom_type, g.prom_id, g.original_img, sgp.key spec_key, sgp.key_name spec_key_name')
                    ->select();
                if ($giftGoodsList) {
                    if (!empty($giftGoods)) {
                        $giftGoods = array_merge($giftGoods, $giftGoodsList);
                    } else {
                        $giftGoods = $giftGoodsList;
                    }
                }
            }
        }
        if (!empty($giftGoods)) {
            if (!empty($goods_list[count($goods_list) - 1]['gift2_goods'])) {
                $goods_list[count($goods_list) - 1]['gift2_goods'][] = $giftGoods;
            } else {
                $goods_list[count($goods_list) - 1]['gift2_goods'] = $giftGoods;
            }
        }

        return $goods_list;
    }


    public function activity2New()
    {
        if ($this->payList) {
            $goods_list = $this->activity2_goods_new($this->payList);
            $this->payList = $goods_list;
        }
    }

    function activity2_goods_new($goods_list)
    {
        foreach ($goods_list as $k => $v) {
            if (isset($v['spec_key']) && ($v['spec_key'] != 0 || $v['spec_key'] != '')) {
                $gift2_goods = M('gift2_goods')
                    ->alias('gg')
                    ->field('gg.goods_id,gg.item_id,gg.stock as goods_num,gt.id,gg.buy_stock,g.goods_remark')
                    ->join('gift2 gt', 'gt.id = gg.promo_id')
                    ->join('goods g', 'g.goods_id = gg.buy_goods_id')
                    ->join('spec_goods_price sg', "sg.item_id = gg.buy_item_id and sg.key = '" . $v['spec_key'] . "'")
                    ->where(array('gg.buy_goods_id' => $v['goods_id'], 'gg.buy_stock' => array('elt', $v['goods_num']), 'gt.start_time' => array('elt', NOW_TIME), 'gt.end_time' => array('egt', NOW_TIME)))
                    ->select();
//                $v['item_id'] = M('spec_goods_price')->where(['goods_id' => $v['goods_id'], 'key' => $v['spec_key']])->value('item_id');
            } else {
                $gift2_goods = M('gift2_goods')
                    ->alias('gg')
                    ->field('gg.goods_id,gg.item_id,gg.stock as goods_num,gt.id,gg.buy_stock,g.goods_remark')
                    ->join('goods g', 'g.goods_id = gg.buy_goods_id')
                    ->join('gift2 gt', 'gt.id = gg.promo_id')
                    ->where(array('gg.buy_goods_id' => $v['goods_id'], 'gg.buy_item_id' => 0, 'gg.buy_stock' => array('elt', $v['goods_num']), 'gt.start_time' => array('elt', NOW_TIME), 'gt.end_time' => array('egt', NOW_TIME)))
                    ->select();
            }
            if ($gift2_goods) {
                $gift2_goods_list = array();
                foreach ($gift2_goods as $key => $val) {
                    $gift2_goods_list[$key] = $val;
                    $stock = bcdiv($v['goods_num'], $val['buy_stock'], 0);

                    $goods = M('goods')->where(array('goods_id' => $val['goods_id']))->field('goods_id,goods_sn,goods_name,sku,trade_type,original_img,store_count')->find();

                    if ($goods['store_count'] < $val['goods_num']) {
                        $gift2_goods_list = array();
                        break;
                    }
                    if ($goods['store_count'] < $stock) {
                        throw new TpshopException('计算订单价格', 0, ['status' => -51, 'msg' => $goods['goods_name'] . '只剩下' . $goods['store_count'] . '件赠品，请重新下单', 'result' => '']);
                    }

                    $gift2_goods_list[$key]['goods_num'] = bcmul($stock, $gift2_goods_list[$key]['goods_num'], 2);
                    $gift2_goods_list[$key]['goods_id'] = $goods['goods_id'];
                    $gift2_goods_list[$key]['goods_sn'] = $goods['goods_sn'];
                    $gift2_goods_list[$key]['goods_name'] = $goods['goods_name'];
                    $gift2_goods_list[$key]['sku'] = $goods['sku'];
                    $gift2_goods_list[$key]['trade_type'] = $goods['trade_type'];
                    $gift2_goods_list[$key]['prom_type'] = 9;
                    $gift2_goods_list[$key]['prom_id'] = $val['id'];
                    $gift2_goods_list[$key]['original_img'] = $goods['original_img'];

                    if (!empty($val['item_id'])) {
                        $spec_goods_price = M('spec_goods_price')->where(array('goods_id' => $val['goods_id'], 'item_id' => $val['item_id']))->field('key,key_name,store_count')->find();
                        $gift2_goods_list[$key]['spec_key'] = $spec_goods_price['key'];
                        $gift2_goods_list[$key]['spec_key_name'] = $spec_goods_price['key_name'];
                        if ($spec_goods_price['store_count'] < $val['goods_num']) {
                            $gift2_goods_list = array();
                            break;
                        }
                        if ($spec_goods_price['store_count'] < $stock) {
                            throw new TpshopException('计算订单价格', 0, ['status' => -51, 'msg' => $goods['goods_name'] . '只剩下' . $spec_goods_price['store_count'] . '件赠品，请重新下单', 'result' => '']);
                        }
                    } else {
                        $gift2_goods_list[$key]['spec_key'] = '';
                        $gift2_goods_list[$key]['spec_key_name'] = '';
                    }
                }
                if (count($gift2_goods_list) > 0) {
                    $goods_list[$k]['gift_goods'] = $gift2_goods_list;
                }
            } else {
                $goods_list[$k]['gift_goods'] = [];
            }
        }
        $giftGoods = [];
        // 订单优惠促销（查看是否有赠送商品）
        $orderProm = M('order_prom')
            ->where(['type' => ['in', '0, 2'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
            ->field('id, title, order_price')->select();
        foreach ($orderProm as $prom) {
            if ($this->orderAmount >= $prom['order_price']) {
                $giftGoods[$prom['id']] = [
                    'prom_id' => $prom['id'],
                    'title' => $prom['title'] . '，获赠以下赠品：'
                ];
                // 订单价格满足要求
                $giftGoodsList = Db::name('order_prom_goods opg')->join('goods g', 'g.goods_id = opg.goods_id')
                    ->join('spec_goods_price sgp', 'sgp.item_id = opg.item_id', 'LEFT')->where(['opg.order_prom_id' => $prom['id'], 'opg.type' => 2])
                    ->field('opg.goods_id, opg.item_id, opg.goods_num, g.goods_sn, g.goods_name, g.goods_remark, g.original_img, sgp.key_name spec_key_name')
                    ->select();
                $giftGoods[$prom['id']]['goods_list'] = $giftGoodsList;
            }
        }
        if (!empty($this->promGiftList)) {
            $this->promGiftList = array_merge($this->promGiftList, $giftGoods);
        } else {
            $this->promGiftList = array_values($giftGoods);
        }
        return $goods_list;
    }

    public function activity3()
    {
        foreach ($this->payList as $payList) {
            if (3 == $payList['goods']['zone'] && $payList['goods']['distribut_id'] != 0) {
                // VIP升级套餐
                return true;
            }
        }
        // 订单优惠促销（查看是否有优惠价格）
        $orderProm = M('order_prom')
            ->where(['type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
            ->field('id, title, type, order_price, discount_price')->order('discount_price DESC')->select();
        foreach ($orderProm as $prom) {
            if ($this->orderAmount >= $prom['order_price']) {
                // 订单价格满足要求
                $this->orderAmount = bcsub($this->orderAmount, $prom['discount_price'], 2);
                $this->orderPromAmount = bcadd($this->orderPromAmount, $prom['discount_price'], 2);
                $this->orderPromIds['order_prom'][] = $prom['id'];
                if (in_array($prom['type'], [0, 1])) {
                    if (!isset($this->promTitleData[8 . '_' . $prom['id']])) {
                        $this->promTitleData[8 . '_' . $prom['id']] = [
                            'prom_id' => $prom['id'],
                            'type' => 8,
                            'type_value' => $prom['title']
                        ];
                    }
                }
                break;
            }
        }
    }

    /**
     * 计算商品订单优惠促销
     * @param $goodsList
     * @param $goodsPrice
     * @return float|mixed
     */
    public function calcGoodsOrderProm($goodsPrice)
    {
        foreach ($this->payList as $payList) {
            if (isset($payList['goods'])) {
                if (3 == $payList['goods']['zone'] && $payList['goods']['distribut_id'] != 0) {
                    // VIP升级套餐
                    return '0.00';
                }
            } else {
                $goods = M('goods')->where(['goods_id' => $payList['goods_id']])->field('zone, distribut_id')->find();
                if (3 == $goods['zone'] && $goods['distribut_id'] != 0) {
                    // VIP升级套餐
                    return '0.00';
                }
            }
        }
        $goodsDiscount = '0.00';
        // 订单优惠促销（查看是否有优惠价格）
        $orderProm = M('order_prom')
            ->where(['type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
            ->field('id, order_price, discount_price')->order('discount_price DESC')->select();
        foreach ($orderProm as $prom) {
            if ($goodsPrice >= $prom['order_price']) {
                // 订单价格满足要求
                $goodsDiscount = bcadd($goodsDiscount, $prom['discount_price'], 2);
                break;
            }
        }
        return $goodsDiscount;
    }

    function gift2_goods($goods_id)
    {
        $gift2_goods = M('gift2_goods')
            ->alias('gg')
            ->field('gt.title,sum(gg.stock) as gift_stock,gg.buy_stock,gg.goods_id,gg.item_id')
            ->join('goods g', 'g.goods_id = gg.buy_goods_id')
            ->join('gift2 gt', 'gt.id = gg.promo_id')
            ->where(array('gg.buy_goods_id' => $goods_id, 'gt.start_time' => array('elt', NOW_TIME), 'gt.end_time' => array('egt', NOW_TIME)))
            ->group('gg.buy_goods_id')
            ->select();
        $gift2_goods_list = array();
        if ($gift2_goods) {

            foreach ($gift2_goods as $key => $val) {
                $gift2_goods_list[$key] = $val;
                $goods = M('goods')->where(array('goods_id' => $val['goods_id']))->field('goods_id,goods_sn,goods_name,sku,trade_type,original_img,store_count')->find();

                if ($goods['store_count'] < $val['gift_stock']) {
                    $gift2_goods_list = array();
                    break;
                }

                if ($val['item_id']) {
                    $spec_goods_price = M('spec_goods_price')->where(array('goods_id' => $val['goods_id'], 'item_id' => $val['item_id']))->field('key,key_name,store_count')->find();
                    if ($spec_goods_price['store_count'] < $val['gift_stock']) {
                        $gift2_goods_list = array();
                        break;
                    }

                }
            }
        }

        return $gift2_goods_list;
    }

    // 加价购活动
    public function activityPayBefore()
    {
        $data = file_get_contents('php://input');
        $result = json_decode($data, true);
        $want_to_buy_extra_goods = $result['extra_goods'];
        if ($this->extra_goods_list && $want_to_buy_extra_goods) {
//            $want_to_buy_extra_goods = array(
            ////                array('goods_id'=>381,'goods_num'=>200),
//                array('goods_id'=>393,'goods_num'=>1),
//            );

            $extra_goods_list = convert_arr_key($this->extra_goods_list, 'goods_id');
            foreach ($want_to_buy_extra_goods as $v) {
                if (!isset($extra_goods_list[$v['goods_id']])) {
                    throw new TpshopException('计算订单价格', 0, ['status' => -51, 'msg' => '请求参数非法', 'result' => '']);
                }
                if ($extra_goods_list[$v['goods_id']]['store_count'] < 1) {
                    throw new TpshopException('计算订单价格', 0, ['status' => -52, 'msg' => '加价购商品库存不足', 'result' => '']);
                }
                if ($extra_goods_list[$v['goods_id']]['goods_num'] < $v['goods_num']) {
                    throw new TpshopException('计算订单价格', 0, ['status' => -53, 'msg' => '超出购买加价购商品数量', 'result' => '']);
                }
            }

            $cartLogic = new CartLogic();
            $cartLogic->setUserId($this->userId);

            $extra_list = [];
            foreach ($want_to_buy_extra_goods as $ak => $av) {
                $cartLogic->setGoodsModel($av['goods_id']);
                $cartLogic->setGoodsBuyNum($av['goods_num']);
                if ($extra_goods_list[$av['goods_id']]['exchange_integral'] > 0) {
                    $cartLogic->setType(1);
//                    $this->payPoints += $av['goods_num'] * $extra_goods_list[$av['goods_id']]['exchange_integral'];
                } else {
                    $cartLogic->setType(2);
                }
                $cartLogic->setCartType(0);
                $buyGoods = $cartLogic->buyNow();
                $buyGoods['member_goods_price'] = $extra_goods_list[$av['goods_id']]['goods_price'];
                $buyGoods['prom_type'] = $extra_goods_list[$av['goods_id']]['prom_type'];
                $buyGoods['prom_id'] = $extra_goods_list[$av['goods_id']]['prom_id'];

                $this->orderAmount = bcadd($this->orderAmount, bcmul($av['goods_num'], $buyGoods['member_goods_price'], 2), 2);
                $this->goodsPrice = bcadd($this->goodsPrice, bcmul($av['goods_num'], $buyGoods['member_goods_price'], 2), 2);
                $this->totalAmount = bcadd($this->totalAmount, bcmul($av['goods_num'], $buyGoods['member_goods_price'], 2), 2);

                $extra_list[$ak] = $buyGoods;
                $data = [];
                $data['extra_id'] = $extra_goods_list[$av['goods_id']]['prom_id'];
                $data['extra_title'] = $extra_goods_list[$av['goods_id']]['extra_title'];
                $data['extra_reward_id'] = $extra_goods_list[$av['goods_id']]['extra_reward_id'];
                $data['user_id'] = $this->userId;
                $data['reward_goods_id'] = $av['goods_id'];
                $data['reward_num'] = $av['goods_num'];
                $data['type'] = 1;
                $data['status'] = '0';
                $this->extra_reward[] = $data;
            }
            $this->payList = array_merge($this->payList, $extra_list);
        }
    }

    // 加价购活动（新）
    public function activityPayBeforeNew($buyExtraGoods = [], $cartLogic)
    {
        $extra_goods_list = convert_arr_key($this->extra_goods_list, 'goods_id');
        $extraGoodsList = [];
        foreach ($buyExtraGoods as $key => $extra) {
            if (!isset($extra_goods_list[$extra['goods_id']])) {
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '请求参数非法']);
            }
            $extraGoods = $extra_goods_list[$extra['goods_id']];
            if ($extraGoods['store_count'] < 1) {
                // 库存不足
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '加价购商品库存不足']);
            }
            if ($extraGoods['goods_num'] < $extra['goods_num']) {
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '超出购买加价购商品数量']);
            }
            if ($extraGoods['buy_limit'] != 0) {
                // 购买记录
                $extraLogNum = M('extra_log el')
                    ->join('order o', 'o.order_sn = el.order_sn')
                    ->join('order_goods og', 'og.order_id = o.order_id')
                    ->where([
                        'el.extra_reward_id' => $extraGoods['extra_reward_id'],
                        'el.status' => 1,
                        'og.goods_id' => $extraGoods['goods_id']
                    ])->sum('og.goods_num');
                if ($extra['goods_num'] + $extraLogNum > $extraGoods['buy_limit']) {
                    throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '超出购买加价购每人限购数量']);
                }
            }

            $this->extra_goods_ids[] = $extra['goods_id'];
            $cartLogic->setGoodsModel($extra['goods_id']);
//            $cartLogic->setSpecGoodsPriceModel($itemId);
            $cartLogic->setGoodsBuyNum($extra['goods_num']);
            $cartLogic->setType($extra['pay_type']);
            $cartLogic->setCartType(0);
            try {
                $buyGoods = $cartLogic->buyNow();
            } catch (TpshopException $tpE) {
                $error = $tpE->getErrorArr();
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => $error['msg']]);
            }
//            $buyGoods['member_goods_price'] = $extraGoods['goods_price'];
            $buyGoods['prom_type'] = $extraGoods['prom_type'];
            $buyGoods['prom_id'] = $extraGoods['prom_id'];
            $extraGoodsList[] = $buyGoods;

            $this->extra_price = bcadd($this->extra_price, bcmul($extra['goods_num'], $buyGoods['member_goods_price'], 2), 2);

            // 加价购活动奖励记录
            $data['extra_id'] = $extraGoods['prom_id'];
            $data['extra_title'] = $extraGoods['extra_title'];
            $data['extra_reward_id'] = $extraGoods['extra_reward_id'];
            $data['user_id'] = $this->userId;
            $data['reward_goods_id'] = $extraGoods['goods_id'];
            $data['reward_num'] = $extraGoods['goods_num'];
            $data['type'] = 1;
            $data['status'] = '0';
            $this->extra_reward[] = $data;
        }
        $this->payList = array_merge($this->payList, $extraGoodsList);
        $this->orderAmount = bcadd($this->orderAmount, $this->extra_price, 2);
        $this->goodsPrice = bcadd($this->goodsPrice, $this->extra_price, 2);
        $this->totalAmount = bcadd($this->totalAmount, $this->extra_price, 2);
    }


    /**
     * 使用电子币
     *
     * @param $user_electronic
     * @throws TpshopException
     *
     */
    public function useUserElectronic($user_electronic)
    {
        if ($user_electronic > 0) {
            if ($user_electronic > $this->user['user_electronic']) {
                throw new TpshopException('计算订单价格', 0, ['status' => -6, 'msg' => '你的账户可用电子币为:' . $this->user['user_electronic'], 'result' => '']);
            }
            if ($user_electronic > $this->orderAmount) {
                $this->userElectronic = $this->orderAmount;
                $this->orderAmount = '0';
            } else {
                $this->userElectronic = $user_electronic;
                $this->orderAmount = bcsub($this->orderAmount, $this->userElectronic, 2);
            }
        }
    }

    /**
     * 减去应付金额.
     *
     * @param $cut_money
     */
    public function cutOrderAmount($cut_money)
    {
        $this->orderAmount = bcsub($this->orderAmount, $cut_money, 2);
    }

    /**
     * 使用优惠券.
     *
     * @param $coupon_id
     */
    public function useCouponById($coupon_id, $payList = array(), $output = 'throw')
    {
        if ($coupon_id > 0) {
            $couponList = new CouponList();
//            if ($coupon_id > 1000) {
//                $coupon_id = M('coupon_list')->where(['id' => $coupon_id])->value('cid');
//            }
            $where = array(
                'uid' => $this->user['user_id'],
                'cid' => $coupon_id,
                'status' => 0
            );
            $userCoupon = $couponList->where($where)->find();
            if ($userCoupon) {
                $coupon = Db::name('coupon')->where(['id' => $userCoupon['cid'], 'status' => 1])->find(); // 获取有效优惠券类型表
                if ($coupon) {
                    $canCoupon = true;
                    if ($coupon['is_usual'] == '0') {
                        list($prom_type, $prom_id) = $this->getPromInfo();
                        // 不可以叠加优惠
                        if ($this->orderPromAmount > 0 || in_array($prom_type, [1, 2])) {
                            $canCoupon = false;
                        }
                    }
                    if ($canCoupon && $coupon['condition'] <= $this->orderAmount) {
                        $this->couponId = $coupon_id;
                        switch ($coupon['use_type']) {
                            case 0:
                            case 1:
                            case 2:
                                $this->couponPrice = $coupon['money'];
                                break;
                            case 4:
                                $this->couponPrice = '0';
                                $goods_ids = Db::name('goods_coupon')->where(array('coupon_id' => $userCoupon['cid']))->getField('goods_id', true);
                                foreach ($payList as $k => $v) {
                                    if (in_array($v['goods_id'], $goods_ids)) {
                                        $dis_price = bcmul(bcdiv(bcmul($v['member_goods_price'], $coupon['money'], 2), 10, 2), $v['goods_num'], 2);
                                        $couponPrice = bcsub(bcmul($v['member_goods_price'], $v['goods_num'], 2), $dis_price, 2);
                                        $this->couponPrice = bcadd($this->couponPrice, $couponPrice, 2);
                                    }
                                }
                                break;
                        }
                        $this->orderAmount = bcsub($this->orderAmount, $this->couponPrice, 2);
                    }
                }
            } elseif ($output == 'throw') {
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '优惠券已被使用']);
            }
        }
    }

    public function useCouponByIdRe($coupon_id_str)
    {
        if ($coupon_id_str > 0) {
//            list($prom_type, $prom_id) = $this->getPromInfo();
//            if ($prom_type != 6 && $prom_id > 0) {
//                throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => '现金券不能参与活动商品使用！', 'result' => ['']]);
//            }
            $coupon_id_arr = explode(',', $coupon_id_str);
            $coupon_ids_arr = array();
            foreach ($coupon_id_arr as $kfdd => $coupon_id) {
//                if ($coupon_id > 1000) {
//                    $coupon_id = M('coupon_list')->where(['id' => $coupon_id])->value('cid');
//                }
                $where = array(
                    'uid' => $this->user['user_id'],
                    'cid' => $coupon_id,
                    'status' => 0
                );
                $userCoupon = M('coupon_list')->where($where)->find();
                if ($userCoupon) {
                    $coupon = Db::name('coupon')->where(['id' => $userCoupon['cid'], 'status' => 1])->find(); // 获取有效优惠券类型表
                    if ($coupon && $coupon['use_type'] == 5) {
                        $coupon_ids_arr[] = $coupon_id;

                        $cartLogic = new CartLogic();
                        $cartLogic->setUserId($this->userId);

                        $extra_list = [];
                        /*$coupon_goods_list = Db::name('goods_coupon')->where('coupon_id', $coupon['id'])->getField('goods_id,coupon_id,number', true);
                        $coupon_goods_ids = get_arr_column($coupon_goods_list,'good_id');
                        $enable_goods = M('goods')->where('goods_id', 'in', $coupon_goods_ids)->select();*/

                        $enable_goods = M('goods_coupon')
                            ->alias('gc')
                            ->join('goods g', 'g.goods_id = gc.goods_id')
                            ->where('gc.coupon_id', $coupon['id'])
                            ->field('gc.goods_id,gc.number,g.goods_name,g.original_img,g.exchange_integral,shop_price - g.exchange_integral as member_price')
                            ->select();

                        foreach ($enable_goods as $ak => $av) {
                            $cartLogic->setGoodsModel($av['goods_id']);
                            $cartLogic->setGoodsBuyNum($av['number']);
                            if ($av['exchange_integral'] > 0) {
                                $cartLogic->setType(1);
                            } else {
                                $cartLogic->setType(2);
                            }
                            $cartLogic->setCartType(0);
                            $buyGoods = $cartLogic->buyNow(true, true);
                            $buyGoods['member_goods_price'] = '0';
                            $buyGoods['use_integral'] = '0';
                            $buyGoods['re_id'] = $coupon_id;
                            $extra_list[$ak] = $buyGoods;
                        }
                        $this->payList = array_merge($this->payList, $extra_list);
                    }
                    $this->couponIdRe = implode(',', $coupon_ids_arr);
                } else {
                    throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '兑换券已被使用']);
                }
            }
        }
    }

    /**
     * 配送
     *
     * @param $district_id
     *
     * @throws TpshopException
     */
    public function delivery($district_id)
    {
        if ($district_id === '0') {
            return $this->shippingPrice = '0';
        }
        if (!is_int($district_id) && empty($district_id)) {
            throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => '请填写收货信息', 'result' => ['']]);
        }
        $GoodsLogic = new GoodsLogic();
        $checkGoodsShipping = $GoodsLogic->checkGoodsListShipping($this->payList, $district_id);
        foreach ($checkGoodsShipping as $shippingKey => $shippingVal) {
            if (true != $shippingVal['shipping_able']) {
//                throw new TpshopException("计算订单价格", 0, ['status' => -1, 'msg' => '订单中部分商品不支持对当前地址的配送']);
                // 订单中部分商品不支持对当前地址的配送
                return ['status' => -1];
            }
        }
        $this->shippingPrice = $GoodsLogic->getFreight($this->payList, $district_id, bcsub($this->orderPromAmount, $this->goodsPromAmount, 2));
        $this->orderAmount = bcadd($this->orderAmount, $this->shippingPrice, 2);
        $this->totalAmount = bcadd($this->totalAmount, $this->shippingPrice, 2);
    }

    /**
     * 获取折扣.
     *
     * @return int
     */
    private function getDiscount()
    {
        return 1;
//        if (empty($this->user['discount'])) {
//            return 1;
//        }
//
//        return $this->user['discount'];
    }

    /**
     * 使用订单优惠.
     */
//    public function orderPromotion()
//    {
//        $time = time();
//        $order_prom_where = ['type' => ['lt', 2], 'end_time' => ['gt', $time], 'start_time' => ['lt', $time], 'money' => ['elt', $this->goodsPrice]];
//        $orderProm = Db::name('prom_order')->where($order_prom_where)->order('money desc')->find();
//        if ($orderProm) {
//            if (0 == $orderProm['type']) {
//                $expressionAmount = round(bcdiv(bcmul($this->goodsPrice, $orderProm['expression'], 2), 100, 2), 2); //满额打折
//                $this->orderPromAmount = round(bcsub($this->goodsPrice, $expressionAmount, 2), 2);
//                $this->orderPromId = $orderProm['id'];
//            } elseif (1 == $orderProm['type']) {
//                $this->orderPromAmount = $orderProm['expression'];
//                $this->orderPromId = $orderProm['id'];
//            }
//        }
//        $this->orderAmount = bcsub($this->orderAmount, $this->orderPromAmount, 2);
//    }

    /**
     * 使用优惠促销
     */
    public function goodsPromotion($goodsList = [], $isOrder = true, $output = 'log')
    {
        $user_info = $this->getUser();
        $pay_list = !empty($goodsList) ? $goodsList : $this->payList;

        $goodsPromAmount = '0';
        $promGoodsData = [];
        foreach ($pay_list as $k => $v) {
            if (isset($v['goods'])) {
                if (3 == $v['goods']['zone'] && $v['goods']['distribut_id'] != 0) {
                    // VIP升级套餐
                    break;
                }
            } else {
                $goods = M('goods')->where(['goods_id' => $v['goods_id']])->field('zone, distribut_id')->find();
                if (3 == $goods['zone'] && $goods['distribut_id'] != 0) {
                    // VIP升级套餐
                    break;
                }
            }
            $goods_tao_grade = M('goods_tao_grade')
                ->alias('g')
                ->join('prom_goods pg', "g.promo_id = pg.id and pg.group like '%" . $user_info['distribut_level'] . "%' and pg.start_time <= " . NOW_TIME . " and pg.end_time >= " . NOW_TIME . " and pg.is_end = '0' and pg.is_open = 1 and pg.min_num <= " . $v["goods_num"])
                ->where(array('g.goods_id' => $v['goods_id']))
                ->field('pg.id, pg.title, pg.type, pg.goods_num, pg.goods_price, pg.buy_limit, pg.expression')
                ->select();
            $is_can_buy = true;
            if ($goods_tao_grade) {
                foreach ($goods_tao_grade as $key => $group_activity) {
//                    if (isset($this->orderPromIds['goods_prom']) && in_array($group_activity['id'], $this->orderPromIds['goods_prom'])) {
//                        continue;
//                    }
                    if ($isOrder && $group_activity['buy_limit'] > 0) {
                        $buy_num = M('order')
                            ->alias('oi')
                            ->join('__ORDER_GOODS__ og', 'oi.order_id = og.order_id', 'LEFT')
                            ->where('user_id', $user_info['user_id'])
                            ->where('order_status', 'NOT IN', [3, 5])
                            ->where('og.goods_id', $v['goods_id'])
                            ->where('og.prom_id', $group_activity['id'])
                            ->where('og.prom_type', 10)
                            ->sum('og.goods_num');
                        $buy_num = intval($buy_num);
                        if (bcadd($buy_num, $v['goods_num'], 2) > $group_activity['buy_limit']) {
                            $is_can_buy = false;
                            break;
                        }
                    }
//                    $this->payList[$k]['prom_id'] = $group_activity['id'];
//                    $this->payList[$k]['prom_type'] = 10;
                    $oldMGoodsPrice = $v['member_goods_price'];
                    switch ($group_activity['type']) {
                        case 0:
                            // 直接打折
                            $member_goods_price = bcdiv(bcmul($oldMGoodsPrice, $group_activity['expression'], 2), 100, 2);
                            $goodsPromAmount = bcadd($goodsPromAmount, bcmul(bcsub($oldMGoodsPrice, $member_goods_price, 2), $v['goods_num'], 2), 2);

                            $this->payList[$k]['member_goods_price'] = $member_goods_price;
                            $this->orderPromIds['goods_prom'][] = $group_activity['id'];
                            if (isset($v['goods_pv'])) {
                                $this->payList[$k]['goods_pv'] = bcdiv(bcmul($v['goods_pv'], $group_activity['expression'], 2), 100, 2);
                            }
                            break;
                        case 1:
                            // 减价优惠
                            $member_goods_price = bcsub($oldMGoodsPrice, $group_activity['expression'], 2);
                            $goodsPromAmount = bcadd($goodsPromAmount, bcmul(bcsub($oldMGoodsPrice, $member_goods_price, 2), $v['goods_num'], 2), 2);

                            $this->payList[$k]['member_goods_price'] = $member_goods_price;
                            $this->orderPromIds['goods_prom'][] = $group_activity['id'];
                            if (isset($v['goods_pv'])) {
                                $this->payList[$k]['goods_pv'] = bcmul($v['goods_pv'], ($member_goods_price / $oldMGoodsPrice), 2);
                            }
                            break;
                        case 2:
                            // 固定金额
                            $this->payList[$k]['member_goods_price'] = $group_activity['expression'];
                            $this->orderPromIds['goods_prom'][] = $group_activity['id'];
                            if (isset($v['goods_pv'])) {
                                $this->payList[$k]['goods_pv'] = bcmul($v['goods_pv'], ($group_activity['expression'] / $oldMGoodsPrice), 2);
                            }
                            break;
                        case 4:
                            // 满打折
                        case 5:
                            // 满优惠
                            if (empty($promGoodsData[$group_activity['id']])) {
                                $promGoodsData[$group_activity['id']] = [
                                    'goods_num' => $v['goods_num'],
                                    'goods_price' => bcmul($oldMGoodsPrice, $v['goods_num'], 2)
                                ];
                            } else {
                                $promGoodsData[$group_activity['id']]['goods_num'] += $v['goods_num'];
                                $promGoodsData[$group_activity['id']]['goods_price'] = bcadd($promGoodsData[$group_activity['id']]['goods_price'], bcmul($v['member_goods_price'], $v['goods_num'], 2), 2);
                            }
                            $pay_list[$k]['prom_type'] = 3;
                            $pay_list[$k]['prom_id'] = $group_activity['id'];
                            break;
                        default:
                            $goodsPromAmount = bcadd($goodsPromAmount, 0, 2);
                    }
                }
            }
            if (!$is_can_buy) {
                $goods_info = M('goods')->where(array('goods_id' => $v['goods_id']))->find();
                throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => "超出活动商品：【{$goods_info['goods_name']}】 限购数量， 每人限购 {$group_activity['buy_limit']} 件", 'result' => '']);
            }
        }
        $this->goodsPromAmount = bcadd($this->goodsPromAmount, $goodsPromAmount, 2);
        // 再计算优惠（满打折、满减价）
        if (!empty($promGoodsData)) {
            $promAmount = '0';
            foreach ($promGoodsData as $promId => $prom) {
                $promInfo = M('prom_goods')->where(['id' => $promId])->field('type, goods_num, goods_price, expression')->find();
                switch ($promInfo['type']) {
                    case 4:
                        if ($prom['goods_num'] >= $promInfo['goods_num']) {
                            $promAmount = bcdiv(bcmul($prom['goods_price'], $promInfo['expression'], 2), 100, 2);
                            $promAmount = bcsub($prom['goods_price'], $promAmount, 2);
                            // 优惠设置的商品
                            $promGoods = M('goods_tao_grade')->where(['promo_id' => $promId])->field('goods_id, item_id')->select();
                            foreach ($pay_list as $k => $v) {
                                $oldMGoodsPrice = $v['member_goods_price'];
                                foreach ($promGoods as $goods) {
                                    if ($v['goods_id'] == $goods['goods_id'] && $v['item_id'] == $goods['item_id']) {
                                        $pay_list[$k]['member_goods_price'] = bcdiv(bcmul($oldMGoodsPrice, $promInfo['expression'], 2), 100, 2);
                                        if (isset($v['goods_pv'])) {
                                            $this->payList[$k]['goods_pv'] = bcdiv(bcmul($v['goods_pv'], $promInfo['expression'], 2), 100, 2);
                                        }
                                    }
                                }
                            }
                        }
                        break;
                    case 5:
                        if ($prom['goods_price'] >= $promInfo['goods_price']) {
                            $promAmount = $promInfo['expression'];
                            // 优惠设置的商品
                            $promGoods = M('goods_tao_grade')->where(['promo_id' => $promId])->field('goods_id, item_id')->select();
                            foreach ($pay_list as $k => $v) {
                                $oldMGoodsPrice = $v['member_goods_price'];
                                foreach ($promGoods as $goods) {
                                    if ($v['goods_id'] == $goods['goods_id'] && $v['item_id'] == $goods['item_id']) {
                                        $pay_list[$k]['member_goods_price'] = bcsub($oldMGoodsPrice, $promInfo['expression'], 2);
                                        if (isset($v['goods_pv'])) {
                                            $this->payList[$k]['goods_pv'] = bcmul($v['goods_pv'], ($pay_list[$k]['member_goods_price'] / $oldMGoodsPrice), 2);
                                        }
                                    }
                                }
                            }
                        }
                        break;
                    default:
                        break;
                }
                $this->orderPromIds['goods_prom'][] = $promId;
                $goodsPromAmount = bcadd($goodsPromAmount, $promAmount, 2);
                $this->goodsPromAmount = bcadd($this->goodsPromAmount, $promAmount, 2);
            }
        }
        switch ($output) {
            case 'log':
                $this->orderPromAmount = bcadd($this->orderPromAmount, $goodsPromAmount, 2);
                $this->orderAmount = bcsub($this->orderAmount, $goodsPromAmount, 2);
                break;
            case 'amount':
                return $goodsPromAmount;
            case 'prom_info':
                return [];
        }
    }

    /**
     * 获取实际上使用的电子币
     *
     * @return int
     */
    public function getUserElectronic()
    {
        return $this->userElectronic;
    }

    /**
     * 获取订单总价.
     *
     * @return int
     */
    public function getTotalAmount()
    {
        return number_format($this->totalAmount, 2, '.', '');
    }

    /**
     * 获取订单应付金额.
     *
     * @return int
     */
    public function getOrderAmount()
    {
        return number_format($this->orderAmount, 2, '.', '');
    }

    /**
     * 获取实际上使用的积分抵扣金额.
     *
     * @return float
     */
    public function getIntegralMoney()
    {
        return $this->integralMoney;
    }

    /**
     * 获取实际上使用的积分.
     *
     * @return float|int
     */
    public function getPayPoints()
    {
        return $this->payPoints;
    }

    /**
     * 获取物流费.
     *
     * @return int
     */
    public function getShippingPrice()
    {
        return $this->shippingPrice;
    }

    /**
     *  获取优惠券费.
     *
     * @return int
     */
    public function getCouponPrice()
    {
        return $this->couponPrice;
    }

    /**
     * 商品总价.
     *
     * @return int
     */
    public function getGoodsPrice()
    {
        return $this->goodsPrice;
    }

    /**
     * 获取用户.
     *
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    public function getPayList()
    {
        return $this->payList;
    }

    public function getGiftGoodsList()
    {
        return $this->gift_goods_list;
    }

    public function getPromGiftList()
    {
        return $this->promGiftList;
    }

    public function getExtraGoodsIds()
    {
        return $this->extra_goods_ids;
    }

    public function getCouponId()
    {
        return $this->couponId;
    }

    public function getCouponIdRe()
    {
        return $this->couponIdRe;
    }

    public function getOrderPromIds()
    {
        return $this->orderPromIds;
    }

    public function getOrderPromAmount()
    {
        return $this->orderPromAmount;
    }

    public function getGoodsPromAmount()
    {
        return $this->goodsPromAmount;
    }

    public function getOrderPv()
    {
        return $this->orderPv;
    }

    public function getOrderSplit()
    {
        if (!empty($this->order1) || !empty($this->order2)) {
            return ['order1' => $this->order1, 'order2' => $this->order2];
        }
        return [];
    }

    public function getOrderSplitGoods()
    {
        return ['order1_goods' => $this->order1Goods, 'order2_goods' => $this->order2Goods];
    }

    public function toArray()
    {
        $returnData = [
            'shipping_price' => $this->shippingPrice,
            'coupon_price' => $this->couponPrice,
            'user_electronic' => bcadd($this->userElectronic, 0, 2),
            'integral_money' => $this->integralMoney,
            'pay_points' => $this->payPoints,
            'order_amount' => bcadd($this->orderAmount, 0, 2),
            'total_amount' => bcadd($this->totalAmount, 0, 2),
            'goods_price' => bcadd($this->goodsPrice, 0, 2),
            'extra_price' => $this->extra_price,
            'order_prom_amount' => $this->orderPromAmount,
            'gift_goods_list' => $this->gift_goods_list,
            'extra_goods_list' => $this->extra_goods_list,
            'gift_record_list' => $this->giftLogic->getRewardInfo(),
            'prom_title_data' => array_values($this->promTitleData),
            'order_pv' => $this->orderPv
        ];
        return $returnData;
    }
}
