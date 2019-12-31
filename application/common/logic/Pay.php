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
use app\common\model\CouponList;
use app\common\util\TpshopException;
use think\Db;

/**
 * 计算价格类
 * Class CatsLogic.
 */
class Pay
{
    protected $payList;
    protected $promGiftList;
    protected $userId;
    protected $user;

    private $totalAmount = '0'; //订单总价
    private $orderAmount = '0'; //应付金额
    private $shippingPrice = '0'; //物流费
    private $goodsPrice = '0'; //商品总价
    private $orderPromPrice = '0'; //订单优惠促销商品总价
    private $cutFee = '0'; //共节约多少钱
    private $totalNum = '0'; // 商品总共数量
    private $integralMoney = '0'; //积分抵消金额
    private $userElectronic = '0'; //使用电子币
    private $payPoints = '0'; //使用积分
    private $couponPrice = '0'; //优惠券抵消金额

    private $orderPromId; //订单优惠ID
    private $orderPromAmount = '0'; //订单优惠金额
    private $couponId;

    private $giftLogic;
    private $gift2Logic;
    private $gift_goods_list; // 赠品商品列表
    private $gift2_goods_list; // 赠品商品列表

    private $extraLogic;
    private $extra_goods_list; // 加价购商品列表
    private $extra_reward; // 加价购商品记录列表

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
                    $goods_list[$goodsCursor]['member_goods_price'] = $discount * $goods_list[$goodsCursor]['goods_price'];
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
            $this->payList[$payCursor]['goods_fee'] = bcmul($this->payList[$payCursor]['goods_num'], $this->payList[$payCursor]['member_goods_price'], 2);    // 小计
            $this->goodsPrice = bcadd($this->goodsPrice, $this->payList[$payCursor]['goods_fee'], 2); // 商品总价
            if (array_key_exists('market_price', $this->payList[$payCursor])) {
                $this->cutFee = bcadd($this->cutFee, bcmul($this->payList[$payCursor]['goods_num'], bcsub($this->payList[$payCursor]['market_price'], $this->payList[$payCursor]['member_goods_price'], 2), 2), 2); // 共节约
            }
            $this->totalNum = bcadd($this->totalNum, $this->payList[$payCursor]['goods_num'], 2);
            if (isset($this->payList[$payCursor]['is_order_prom']) && $this->payList[$payCursor]['is_order_prom'] == 1) {
                $this->orderPromPrice = bcadd($this->orderPromPrice, bcmul($this->payList[$payCursor]['goods_num'], $this->payList[$payCursor]['member_goods_price'], 2), 2);
            }
        }
        $this->orderAmount = $this->goodsPrice;
        $this->totalAmount = $this->goodsPrice;
    }

    /**
     * 设置用户ID.
     *
     * @throws TpshopException
     *
     * @param $user_id
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
     * 使用积分.
     *
     * @throws TpshopException
     *
     * @param $pay_points
     * @param $is_exchange |是否有使用积分兑换商品流程
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
                    $point_limit = $this->orderAmount * $point_rate * $use_percent_point;
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
                    $this->integralMoney = $pay_points / $point_rate; //总积分兑换成的金额
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
     * @throws TpshopException
     *
     * @param $user_electronic
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
                if ($buy_num + $v['goods_num'] > $goods_info['limit_buy_num']) {
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
                    ->where('item_id', $v['item_id'])
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

                        if ($buy_num + $v['goods_num'] > $group_activity['buy_limit']) {
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

    public function activity()
    {
        // 赠品活动
        $this->giftLogic->setUserId($this->userId);
        $this->giftLogic->setMoney($this->goodsPrice);
        $this->giftLogic->setGoodsList($this->payList);
        $goods_list = $this->giftLogic->getGoodsList();
        if ($goods_list) {
            $this->gift_goods_list = array_values($goods_list);
//            $this->promGiftList = array_values($goods_list);
            $this->payList = array_merge($this->payList, $goods_list);
        }
    }

    public function activityRecord($order)
    {
        $this->giftLogic->setOrder($order);
        $this->giftLogic->record();

        $this->extraLogic->setOrder($order);
        $this->extraLogic->setRewardInfo($this->extra_reward);

        $this->extraLogic->record();
    }

    public function activity2($orderPromPrice = '0')
    {
        if ($this->payList) {
            $goods_list = $this->activity2_goods($this->payList, $orderPromPrice != '0' ? $orderPromPrice : $this->totalAmount);
            $this->payList = $goods_list;
        }
    }

    function activity2_goods($goods_list, $orderPromPrice = '0')
    {
        $orderPromId1 = [];
        $orderPromId2 = [];
        $giftGoods = [];
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
                $v['item_id'] = M('spec_goods_price')->where(['goods_id' => $v['goods_id'], 'key' => $v['spec_key']])->value('item_id');
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

                    $gift2_goods_list[$key]['goods_num'] = $stock * $gift2_goods_list[$key]['goods_num'];
                    $gift2_goods_list[$key]['goods_id'] = $goods['goods_id'];
                    $gift2_goods_list[$key]['goods_sn'] = $goods['goods_sn'];
                    $gift2_goods_list[$key]['goods_name'] = $goods['goods_name'];
                    $gift2_goods_list[$key]['sku'] = $goods['sku'];
                    $gift2_goods_list[$key]['trade_type'] = $goods['trade_type'];
                    $gift2_goods_list[$key]['prom_type'] = 9;
                    $gift2_goods_list[$key]['prom_id'] = $val['id'];
                    $gift2_goods_list[$key]['original_img'] = $goods['original_img'];

                    if ($val['item_id']) {
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
//                    $goods_list[$k]['gift2_goods'] = $gift2_goods_list;
                    $giftGoods = $gift2_goods_list;
                }
            }
            if ($orderPromPrice > 0) {
                $itemId = isset($v['item_id']) ? $v['item_id'] : 0;
                // 订单优惠促销（查看是否有赠送商品）
                $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
                    ->where(['opg.type' => 1, 'goods_id' => $v['goods_id'], 'item_id' => $itemId])
                    ->where(['op.type' => ['in', '0, 2'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
                    ->field('order_prom_id, title, order_price')->find();
                if (!empty($orderProm) && !in_array($orderProm['order_prom_id'], $orderPromId1)) {
                    if ($orderPromPrice >= $orderProm['order_price']) {
                        // 订单价格满足要求
                        $giftGoodsList = Db::name('order_prom_goods opg')->join('goods g', 'g.goods_id = opg.goods_id')
                            ->join('spec_goods_price sgp', 'sgp.item_id = opg.item_id', 'LEFT')->where(['opg.order_prom_id' => $orderProm['order_prom_id'], 'opg.type' => 2])
                            ->field('opg.goods_id, opg.item_id, opg.goods_num, g.goods_sn, g.goods_name, g.goods_remark, g.sku, g.trade_type, g.prom_type, g.prom_id, g.original_img, sgp.key spec_key, sgp.key_name spec_key_name')
                            ->select();
                        if ($giftGoodsList) {
//                            if (isset($goods_list[$k]['gift2_goods'])) {
//                                $goods_list[$k]['gift2_goods'] = array_merge($goods_list[$k]['gift2_goods'][], $giftGoodsList);
//                            } else {
//                                $goods_list[$k]['gift2_goods'] = $giftGoodsList;
//                            }
                            if (!empty($giftGoods)) {
                                $giftGoods = array_merge($giftGoods, $giftGoodsList);
                            } else {
                                $giftGoods = $giftGoodsList;
                            }
                        }
                        $goods_list[$k]['prom_id'] = $orderProm['order_prom_id'];
                        $goods_list[$k]['prom_type'] = 7;   // 订单合购优惠
                        $goods_list[$k]['prom_title'] = $orderProm['title'];
                        $orderPromId1[] = $orderProm['order_prom_id'];
                    }
                }
                // 订单优惠促销（查看是否有优惠价格）
//                $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
//                    ->where(['opg.type' => 1, 'goods_id' => $v['goods_id'], 'item_id' => $itemId])
//                    ->where(['op.type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
//                    ->field('order_prom_id, order_price, discount_price')->find();
//                if (!empty($orderProm) && !in_array($orderProm['order_prom_id'], $orderPromId2)) {
//                    if ($cartPrice >= $orderProm['order_price'] && $orderProm['order_price'] != '0') {
//                        // 订单价格满足要求
//                        $discountPrice += $orderProm['discount_price'];
//                    }
//                    $orderPromId2[] = $orderProm['order_prom_id'];
//                }
            }
            if ($k == count($goods_list) - 1 && !empty($giftGoods)) {
                $goods_list[$k]['gift2_goods'] = $giftGoods;
            }
        }
        return $goods_list;
    }


    public function activity2New($orderPromPrice = '0')
    {
        if ($this->payList) {
            $goods_list = $this->activity2_goods_new($this->payList, $orderPromPrice != '0' ? $orderPromPrice : $this->totalAmount);
            $this->payList = $goods_list;
        }
    }

    function activity2_goods_new($goods_list, $orderPromPrice = '0')
    {
        $orderPromId = [];
        $giftGoods = [];
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
                $v['item_id'] = M('spec_goods_price')->where(['goods_id' => $v['goods_id'], 'key' => $v['spec_key']])->value('item_id');
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

                    $gift2_goods_list[$key]['goods_num'] = $stock * $gift2_goods_list[$key]['goods_num'];
                    $gift2_goods_list[$key]['goods_id'] = $goods['goods_id'];
                    $gift2_goods_list[$key]['goods_sn'] = $goods['goods_sn'];
                    $gift2_goods_list[$key]['goods_name'] = $goods['goods_name'];
                    $gift2_goods_list[$key]['sku'] = $goods['sku'];
                    $gift2_goods_list[$key]['trade_type'] = $goods['trade_type'];
                    $gift2_goods_list[$key]['prom_type'] = 9;
                    $gift2_goods_list[$key]['prom_id'] = $val['id'];
                    $gift2_goods_list[$key]['original_img'] = $goods['original_img'];

                    if ($val['item_id']) {
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
            if ($orderPromPrice > 0) {
                $itemId = isset($v['item_id']) ? $v['item_id'] : 0;
                // 订单优惠促销（查看是否有赠送商品）
                $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
                    ->where(['opg.type' => 1, 'goods_id' => $v['goods_id'], 'item_id' => $itemId])
                    ->where(['op.type' => ['in', '0, 2'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
                    ->field('order_prom_id, title, order_price')->find();
                if (!empty($orderProm) && !in_array($orderProm['order_prom_id'], $orderPromId)) {
                    if ($orderPromPrice >= $orderProm['order_price']) {
                        $giftGoods[$orderProm['order_prom_id']] = [
                            'prom_id' => $orderProm['order_prom_id'],
                            'title' => $orderProm['title'] . '，获赠以下赠品：'
                        ];
                        // 订单价格满足要求
                        $giftGoodsList = Db::name('order_prom_goods opg')->join('goods g', 'g.goods_id = opg.goods_id')
                            ->join('spec_goods_price sgp', 'sgp.item_id = opg.item_id', 'LEFT')->where(['opg.order_prom_id' => $orderProm['order_prom_id'], 'opg.type' => 2])
                            ->field('opg.goods_id, opg.item_id, opg.goods_num, g.goods_sn, g.goods_name, g.goods_remark, g.original_img, sgp.key_name spec_key_name')
                            ->select();
                        $giftGoods[$orderProm['order_prom_id']]['goods_list'] = $giftGoodsList;
                        $orderPromId[] = $orderProm['order_prom_id'];
                    }
                }
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
        $this->payList;
        $this->orderPromPrice;
        $orderPromId = [];
        foreach ($this->payList as $k => $v) {
            if ($this->orderPromPrice > 0) {
                $itemId = isset($v['item_id']) ? $v['item_id'] : 0;
                // 订单优惠促销（查看是否有优惠价格）
                $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
                    ->where(['opg.type' => 1, 'goods_id' => $v['goods_id'], 'item_id' => $itemId])
                    ->where(['op.type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
                    ->field('order_prom_id, order_price, discount_price')->find();
                if (!empty($orderProm) && !in_array($orderProm['order_prom_id'], $orderPromId)) {
                    if ($this->orderPromPrice >= $orderProm['order_price']) {
                        // 订单价格满足要求
                        $this->orderPromPrice = bcsub($this->orderPromPrice, $orderProm['discount_price'], 2);
                        $this->orderAmount = bcsub($this->orderAmount, $orderProm['discount_price'], 2);
                        $this->totalAmount = bcsub($this->totalAmount, $orderProm['discount_price'], 2);
                        $this->orderPromAmount = bcadd($this->orderPromAmount, $orderProm['discount_price'], 2);
                    }
                    $this->payList[$k]['prom_id'] = $orderProm['order_prom_id'];
                    $this->payList[$k]['prom_type'] = 7;    // 订单合购优惠
                    $orderPromId[] = $orderProm['order_prom_id'];
                }
            }
        }
    }

    /**
     * 计算商品订单优惠促销
     * @param $goodsList
     * @param $goodsPrice
     * @return float|mixed
     */
    public function calcGoodsOrderProm($goodsList, $goodsPrice)
    {
        $orderPromId = [];
        $goodsDiscount = '0';
        foreach ($goodsList as $k => $v) {
            if ($goodsPrice > 0) {
                if (!empty($v['spec_key'])) {
                    $itemId = M('spec_goods_price')->where(['goods_id' => $v['goods_id'], 'key' => $v['spec_key']])->value('item_id');
                } else {
                    $itemId = '0';
                }
                // 订单优惠促销（查看是否有优惠价格）
                $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
                    ->where(['opg.type' => 1, 'goods_id' => $v['goods_id'], 'item_id' => $itemId])
                    ->where(['op.type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
                    ->field('order_prom_id, order_price, discount_price')->find();
                if (!empty($orderProm) && !in_array($orderProm['order_prom_id'], $orderPromId)) {
                    if ($goodsPrice >= $orderProm['order_price']) {
                        // 订单价格满足要求
                        $goodsDiscount += $orderProm['discount_price'];
                    }
                    $orderPromId[] = $orderProm['order_prom_id'];
                }
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

                $this->orderAmount += $av['goods_num'] * $buyGoods['member_goods_price'];
                $this->goodsPrice += $av['goods_num'] * $buyGoods['member_goods_price'];
                $this->totalAmount += $av['goods_num'] * $buyGoods['member_goods_price'];

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
    public function activityPayBeforeNew($extraGoods = [])
    {
        $extra_goods_list = convert_arr_key($this->extra_goods_list, 'goods_id');
        foreach ($extraGoods as $key => $extra) {
            if (!isset($this->extra_goods_list[$extra['goods_id']])) {
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '请求参数非法']);
            }
            if ($extra_goods_list[$extra['goods_id']]['store_count'] < 1) {
                // 库存不足
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '加价购商品库存不足']);
            }
            if ($extra_goods_list[$extra['goods_id']]['goods_num'] < $extra['goods_num']) {
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => '超出购买加价购商品数量']);
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

            $this->orderAmount += $av['goods_num'] * $buyGoods['member_goods_price'];
            $this->goodsPrice += $av['goods_num'] * $buyGoods['member_goods_price'];
            $this->totalAmount += $av['goods_num'] * $buyGoods['member_goods_price'];

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


    /**
     * 使用电子币
     *
     * @throws TpshopException
     *
     * @param $user_electronic
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
                $this->orderAmount = $this->orderAmount - $this->userElectronic;
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
        $this->orderAmount = $this->orderAmount - $cut_money;
    }

    /**
     * 使用优惠券.
     *
     * @param $coupon_id
     */
    public function useCouponById($coupon_id, $getPayList = array())
    {
        if ($coupon_id > 0) {
//            list($prom_type, $prom_id) = $this->getPromInfo();
//            if ($prom_type != 6 && $prom_id > 0) {
//                throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => '现金券不能参与活动商品使用！', 'result' => ['']]);
//            }
            $couponList = new CouponList();
            $where = array(
                'uid' => $this->user['user_id'],
                'cid' => $coupon_id
            );
            $userCoupon = $couponList->where($where)->find();
            if ($userCoupon) {
                $coupon = Db::name('coupon')->where(['id' => $userCoupon['cid'], 'status' => 1])->find(); // 获取有效优惠券类型表
                if ($coupon) {
                    $canCoupon = true;
                    if ($coupon['is_usual'] == '0') {
                        // 不可以叠加优惠
                        if ($this->orderPromAmount > 0) {
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
                                foreach ($getPayList as $k => $v) {
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
            }
        }
    }

    public function useCouponByIdRe($coupon_id_str)
    {
        if ($coupon_id_str) {
//            list($prom_type, $prom_id) = $this->getPromInfo();
//            if ($prom_type != 6 && $prom_id > 0) {
//                throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => '现金券不能参与活动商品使用！', 'result' => ['']]);
//            }
            $coupon_id_arr = explode(',', $coupon_id_str);
            $coupon_ids_arr = array();
            foreach ($coupon_id_arr as $kfdd => $coupon_id) {
                $where = array(
                    'uid' => $this->user['user_id'],
                    'cid' => $coupon_id
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
                            $buyGoods = $cartLogic->buyNow();
                            $buyGoods['member_goods_price'] = '0';
                            $buyGoods['use_integral'] = '0';
                            $buyGoods['re_id'] = $coupon_id;
                            $extra_list[$ak] = $buyGoods;
                        }
                        $this->payList = array_merge($this->payList, $extra_list);
                    }
                    $this->couponIdRe = implode(',', $coupon_ids_arr);
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
                // throw new TpshopException("计算订单价格",0,['status'=>-1,'msg'=>'订单中部分商品不支持对当前地址的配送请返回购物车修改','result'=>['goods_shipping'=>$checkGoodsShipping]]);
            }
        }
        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费

        $ln = bcsub($this->goodsPrice, $this->orderPromAmount, 2);

        if ($ln < $freight_free || 0 == $freight_free) {
            $this->shippingPrice = $GoodsLogic->getFreight($this->payList, $district_id);
            $this->orderAmount = bcadd($this->orderAmount, $this->shippingPrice, 2);
            $this->totalAmount = bcadd($this->totalAmount, $this->shippingPrice, 2);
        } else {
            $this->shippingPrice = '0';
        }
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
    public function orderPromotion()
    {
        $time = time();
        $order_prom_where = ['type' => ['lt', 2], 'end_time' => ['gt', $time], 'start_time' => ['lt', $time], 'money' => ['elt', $this->goodsPrice]];
        $orderProm = Db::name('prom_order')->where($order_prom_where)->order('money desc')->find();
        if ($orderProm) {
            if (0 == $orderProm['type']) {
                $expressionAmount = round($this->goodsPrice * $orderProm['expression'] / 100, 2); //满额打折
                $this->orderPromAmount = round($this->goodsPrice - $expressionAmount, 2);
                $this->orderPromId = $orderProm['id'];
            } elseif (1 == $orderProm['type']) {
                $this->orderPromAmount = $orderProm['expression'];
                $this->orderPromId = $orderProm['id'];
            }
        }
        $this->orderAmount = $this->orderAmount - $this->orderPromAmount;
    }

    /**
     * 使用优惠促销
     */
    public function goodsPromotion($goodsList = [], $isOrder = true, $output = 'log')
    {
        $user_info = $this->getUser();
        $pay_list = !empty($goodsList) ? $goodsList : $this->payList;

        //1.分销--会员升级区商品购买顺序
        //2.商品限购
        //3.超值套组
        //4.团购
        //5.加价购
//        $district_level = [];
        $goodsPromAmount = '0';

        foreach ($pay_list as $k => $v) {

            $goods_info = M('goods')->where(array('goods_id' => $v['goods_id']))->find();

            $goods_tao_grade = M('goods_tao_grade')
                ->alias('g')
                ->field('pg.id, pg.type, pg.goods_num, pg.goods_price, pg.buy_limit, pg.expression')
                ->where(array('g.goods_id' => $v['goods_id']))
                ->join('prom_goods pg', "g.promo_id = pg.id and pg.group like '%" . $user_info['distribut_level'] . "%' and pg.start_time <= " . NOW_TIME . " and pg.end_time >= " . NOW_TIME . " and pg.is_end = '0' and pg.is_open = 1 and pg.min_num <= " . $v["goods_num"])
                ->select();

            $is_can_buy = true;

            if ($goods_tao_grade) {
                foreach ($goods_tao_grade as $key => $group_activity) {
                    if ($this->orderPromId == $group_activity['id']) {
                        continue;
                    }
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
                        if ($buy_num + $v['goods_num'] > $group_activity['buy_limit']) {
                            $is_can_buy = false;
                            break;
                        }
                    }
//                    $this->payList[$k]['prom_id'] = $group_activity['id'];
//                    $this->payList[$k]['prom_type'] = 10;

                    $promAmount = '0';
                    switch ($group_activity['type']) {
                        case 0:
                            // 直接打折
                            $member_goods_price = bcdiv(bcmul($v['member_goods_price'], $group_activity['expression'], 2), 100, 2);
                            $promAmount = bcmul(bcsub($v['member_goods_price'], $member_goods_price, 2), $v['goods_num'], 2);

                            $this->payList[$k]['member_goods_price'] = $member_goods_price;
                            $this->orderPromId = $group_activity['id'];
                            break;
                        case 1:
                            // 减价优惠
                            $member_goods_price = bcsub($v['member_goods_price'], $group_activity['expression'], 2);
                            $promAmount = bcmul(bcsub($v['member_goods_price'], $member_goods_price, 2), $v['goods_num'], 2);

                            $this->payList[$k]['member_goods_price'] = $member_goods_price;
                            $this->orderPromId = $group_activity['id'];
                            break;
                        case 4:
                            // 满打折
                            if ($v['goods_num'] >= $group_activity['goods_num']) {
                                $member_goods_price = bcdiv(bcmul($v['member_goods_price'], $group_activity['expression'], 2), 100, 2);
                                $promAmount = bcmul(bcsub($v['member_goods_price'], $member_goods_price, 2), $v['goods_num'], 2);

                                $this->payList[$k]['member_goods_price'] = $member_goods_price;
                                $this->orderPromId = $group_activity['id'];
                            }
                            break;
                        case 5:
                            // 满优惠
                            if ($v['member_goods_price'] >= $group_activity['goods_price']) {
                                $member_goods_price = bcsub($v['member_goods_price'], $group_activity['expression'], 2);
                                $promAmount = bcmul(bcsub($v['member_goods_price'], $member_goods_price, 2), $v['goods_num'], 2);

                                $this->payList[$k]['member_goods_price'] = $member_goods_price;
                                $this->orderPromId = $group_activity['id'];
                            }
                            break;
                        default:
                            $promAmount = '0';
                    }
                    $goodsPromAmount = bcadd($goodsPromAmount, $promAmount, 2);
                }
            }
        }

        if (!$is_can_buy) {
            throw new TpshopException('计算订单价格', 0, ['status' => -1, 'msg' => "超出活动商品：【{$goods_info['goods_name']}】 限购数量， 每人限购 {$group_activity['buy_limit']} 件", 'result' => '']);
        }

        switch ($output) {
            case 'log':
                $this->orderPromAmount = bcadd($this->orderPromAmount, $goodsPromAmount, 2);
                $this->orderAmount = bcsub($this->orderAmount, $goodsPromAmount, 2);
//                $this->goodsPrice = bcsub($this->goodsPrice, $goodsPromAmount, 2);
//                $this->totalAmount = bcsub($this->totalAmount, $goodsPromAmount, 2);
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

    public function getPromGiftList()
    {
        return $this->promGiftList;
    }

    public function getCouponId()
    {
        return $this->couponId;
    }

    public function getCouponIdRe()
    {
        return $this->couponIdRe;
    }

    public function getOrderPromAmount()
    {
        return $this->orderPromAmount;
    }

    public function getOrderPromId()
    {
        return $this->orderPromId;
    }

    public function toArray()
    {
        return [
            'shipping_price' => $this->shippingPrice,
            'coupon_price' => $this->couponPrice,
            'user_electronic' => bcadd($this->userElectronic, 0, 2),
            'integral_money' => $this->integralMoney,
            'pay_points' => $this->payPoints,
            'order_amount' => bcadd($this->orderAmount, 0, 2),
            'total_amount' => bcadd($this->totalAmount, 0, 2),
            'goods_price' => bcadd($this->goodsPrice, 0, 2),
            'order_prom_amount' => $this->orderPromAmount,
            'gift_goods_list' => $this->gift_goods_list,
            'extra_goods_list' => $this->extra_goods_list,
            'gift_record_list' => $this->giftLogic->getRewardInfo(),
        ];
    }
}
