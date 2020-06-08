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

use app\common\model\Goods;
use app\common\model\PromGoods;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use think\db;

/**
 * 促销商品逻辑定义
 * Class CatsLogic.
 */
class PromGoodsLogic extends Prom
{
    protected $promGoods; //促销活动模型
    protected $goods; //商品模型
    protected $specGoodsPrice; //商品规格模型

    public function __construct($goods, $specGoodsPrice)
    {
        parent::__construct();
        $this->goods = $goods;
        $this->specGoodsPrice = $specGoodsPrice;
        //活动和规格是一对多的关系
        if ($this->specGoodsPrice) {
            //活动商品有规格，活动和规格是一对多
            $this->promGoods = PromGoods::get($specGoodsPrice['prom_id']);
        } else {
            //活动商品没有规格，活动和规格是一对多
            $this->promGoods = PromGoods::get($goods['prom_id']);
        }
        if ($this->promGoods) {
            //每次初始化都检测活动是否失效，如果失效就更新活动和商品恢复成普通商品
            if ($this->checkActivityIsEnd() && 0 == $this->promGoods['is_end']) {
                Db::name('goods')->where(['prom_type' => 3, 'prom_id' => $this->goods['prom_id']])->save(['prom_type' => 0, 'prom_id' => 0]);
                if ($this->specGoodsPrice) {
                    Db::name('spec_goods_price')->where(['prom_type' => 3, 'prom_id' => $this->specGoodsPrice['prom_id']])->save(['prom_type' => 0, 'prom_id' => 0]);
                    unset($this->specGoodsPrice);
                    $this->specGoodsPrice = SpecGoodsPrice::get($specGoodsPrice['item_id'], '', true);
                }
                $this->promGoods->is_end = 1;
                $this->promGoods->save();
                unset($this->goods);
                $this->goods = Goods::get($goods['goods_id']);
            }
        }
    }

    public function getPromModel()
    {
        return $this->promGoods;
    }

    /**
     * 计算促销价格。
     *
     * @param $price |原价或者规格价格
     * @param $num | 购买数量（包括购物车原本拥有的）
     *
     * @return float
     */
    public function getPromotionPrice($price, $num = 0)
    {
        switch ($this->promGoods['type']) {
            case 0:
                // 打折优惠
                $promotionPrice = bcdiv(bcmul($price, $this->promGoods['expression'], 2), 100, 2);
                break;
            case 1:
                // 减价优惠
                $promotionPrice = bcsub($price, $this->promGoods['expression'], 2);
                break;
            case 2:
                // 固定金额
                $promotionPrice = $this->promGoods['expression'];
                break;
            case 4:
                // 满打折优惠
                if ($num >= $this->promGoods['goods_num']) {
                    $promotionPrice = bcdiv(bcmul($price, $this->promGoods['expression'], 2), 100, 2);
                } else {
                    $promotionPrice = $price; // 原价
                }
                break;
            case 5:
                // 满减价优惠
                if ($price >= $this->promGoods['goods_price']) {
                    $promotionPrice = bcsub($price, $this->promGoods['expression'], 2);
                } else {
                    $promotionPrice = $price; // 原价
                }
                break;
            default:
                $promotionPrice = $price; // 原价
                break;
        }
        $promotionPrice = ($promotionPrice > 0 ? $promotionPrice : '0.00'); //防止出现负数
        return $promotionPrice;
    }

    /**
     * 活动是否正在进行.
     *
     * @return bool
     */
    public function checkActivityIsAble()
    {
        if (empty($this->promGoods)) {
            return false;
        }
        if (time() > $this->promGoods['start_time'] && time() < $this->promGoods['end_time'] && 0 == $this->promGoods['is_end']) {
            return true;
        }

        return false;
    }

    /**
     * 活动是否结束
     *
     * @return bool
     */
    public function checkActivityIsEnd()
    {
        if (empty($this->promGoods)) {
            return true;
        }
        if (time() > $this->promGoods['end_time']) {
            return true;
        }

        return false;
    }

    /**
     * 获取商品原始数据.
     *
     * @return Goods
     */
    public function getGoodsInfo()
    {
        return $this->goods;
    }

    /**
     * 获取商品转换活动商品的数据.
     *
     * @return static
     */
    public function getActivityGoodsInfo()
    {
        $activityGoods = $this->goods;
        $activityGoods['activity_title'] = $this->promGoods['title'];
        $activityGoods['market_price'] = $this->goods['market_price'];
        $activityGoods['start_time'] = $this->promGoods['start_time'];
        $activityGoods['end_time'] = $this->promGoods['end_time'];
        //有规格
        if ($this->specGoodsPrice) {
            $activityGoods['shop_price'] = $this->getPromotionPrice($this->specGoodsPrice['price']);
            $activityGoods['store_count'] = $this->specGoodsPrice['store_count'];
            //如果价格有变化就将市场价等于商品规格价。
            if ($activityGoods['shop_price'] != $this->specGoodsPrice['price']) {
                $activityGoods['market_price'] = $this->specGoodsPrice['price'];
            }
            $activityGoods['store_count'] = $this->specGoodsPrice['store_count'];
        } else {
            $activityGoods['shop_price'] = $this->getPromotionPrice($this->goods['shop_price']);
            //如果价格有变化就将市场价等于商品规格价。
            if ($activityGoods['shop_price'] != $this->goods['shop_price']) {
                $activityGoods['market_price'] = $this->specGoodsPrice['price'];
            }
        }
        $activityGoods['prom_detail'] = $this->promGoods['prom_detail'];

        return $activityGoods;
    }

    /**
     * 该活动是否已经失效.
     */
    public function IsAble()
    {
        if (empty($this->promGoods)) {
            return false;
        }
        if (time() > $this->promGoods['end_time']) {
            return false;
        }
        if (1 == $this->promGoods['is_end']) {
            return false;
        }

        return true;
    }

    /*    /**
         * @param $buyGoods
         * @return array
         *
        public function buyNow($buyGoods){
            if(!$this->checkActivityIsEnd() && $this->checkActivityIsAble()){
                $buyGoods['member_goods_price'] = $this->getPromotionPrice($buyGoods['member_goods_price']);
            }
            $buyGoods['prom_type'] = 1;
            $buyGoods['prom_id'] = $this->promGoods['id'];
            return array('status' => 1, 'msg' => 'success', 'result' => ['buy_goods'=>$buyGoods]);
        }*/

    /**
     * 促销商品立即购买.
     *
     * @param $buyGoods
     * @param $buyType |购买方式
     *
     * @return mixed
     *
     * @throws TpshopException
     */
    public function buyNow($buyGoods, $buyType, $passAuth = false)
    {
        if (!$this->checkActivityIsEnd() && $this->checkActivityIsAble() && !$passAuth) {
            if ($this->promGoods['buy_limit'] != 0 && $buyGoods['goods_num'] > $this->promGoods['buy_limit']) {
                throw new TpshopException('促销商品立即购买', 0, ['status' => 0, 'msg' => '每人限购' . $this->promGoods['buy_limit'] . '件', 'result' => '']);
            }
//            $buyGoods['member_goods_price'] = $this->getPromotionPrice($buyGoods['member_goods_price']);
        }
        $residue_buy_limit = $this->getPromoGoodsResidueGoodsNum($buyGoods['user_id']); //获取用户还能购买商品数量
        $userPromOrderGoodsNum = $this->getUserPromOrderGoodsNum($buyGoods['user_id']); //获取用户已购商品数量
        $userBuyGoodsNum = $buyGoods['goods_num'] + $userPromOrderGoodsNum;  //已经下单+要买
        if ($this->promGoods['buy_limit'] != 0 && $userBuyGoodsNum > $this->promGoods['buy_limit'] && !$passAuth) {
            throw new TpshopException('促销商品立即购买', 0, ['status' => 0, 'msg' => '每人限购' . $this->promGoods['buy_limit'] . '件，您已下单' . $userPromOrderGoodsNum . '件', 'result' => '']);
        }
        if ($buyGoods['goods_num'] > $residue_buy_limit && !$passAuth) {  //不算购物车的
            throw new TpshopException('促销商品立即购买', 0, ['status' => 0, 'msg' => '商品库存不足，你只能购买' . $residue_buy_limit, 'result' => '']);
        }
        $buyGoods['prom_type'] = 3;
        $buyGoods['prom_id'] = $this->promGoods['id'];

        return $buyGoods;
    }

    /**
     * 获取用户抢购已购商品数量.
     *
     * @param $user_id
     *
     * @return float|int
     */
    public function getUserPromOrderGoodsNum($user_id)
    {
        $orderWhere = [
            'user_id' => $user_id,
            'order_status' => ['<>', 3],
            'add_time' => ['between', [$this->promGoods['start_time'], $this->promGoods['end_time']]],
        ];
        $order_id_arr = Db::name('order')->where($orderWhere)->getField('order_id', true);
        if ($order_id_arr) {
            $orderGoodsWhere = [
                'prom_id' => $this->promGoods['id'],
                'prom_type' => 3,
                'order_id' => ['in', implode(',', $order_id_arr)],
                'goods_id' => $this->goods['goods_id'],
            ];
            if ($this->specGoodsPrice) {
                $orderGoodsWhere['spec_key'] = $this->specGoodsPrice['key'];
            }
            $goods_num = DB::name('order_goods')->where($orderGoodsWhere)->sum('goods_num');
            if ($goods_num) {
                return $goods_num;
            }

            return 0;
        }

        return 0;
    }

    /**
     * 获取用户剩余购买促销商品数量.
     *
     * @param $user_id
     *
     * @return mixed
     */
    public function getPromoGoodsResidueGoodsNum($user_id)
    {
        $user_purchase_num = $this->getUserPromOrderGoodsNum($user_id); //用户已购商品数量
        //限购 > 已购
        $store_count = $this->goods['store_count'];  //剩余库存
        if ($this->specGoodsPrice) {
            $store_count = $this->specGoodsPrice['store_count'];
        }
        if ($this->promGoods['buy_limit'] == 0) {
            return $store_count;
        } else {
            $residue_buy_limit = $this->promGoods['buy_limit'] - $user_purchase_num;  //用户还能买的数量
            if ($residue_buy_limit > $store_count) {
                return $store_count;
            }
            return $residue_buy_limit;
        }
    }
}
