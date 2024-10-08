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

use app\common\model\FlashSale;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use think\db;

/**
 * 秒杀逻辑定义
 * Class CatsLogic.
 */
class FlashSaleLogic extends Prom
{
    protected $flashSale; //抢购活动模型
    protected $goods; //商品模型
    protected $specGoodsPrice; //商品规格模型

    public function __construct($goods, $specGoodsPrice)
    {
        parent::__construct();
        $this->goods = $goods;
        $this->specGoodsPrice = $specGoodsPrice;
        if ($this->specGoodsPrice) {
            //活动商品有规格，规格和活动是一对一
            $this->flashSale = FlashSale::get($specGoodsPrice['prom_id']);
        } else {
            //活动商品没有规格，活动和商品是一对一
            $this->flashSale = FlashSale::get($goods['prom_id']);
        }
        if ($this->flashSale) {
            //每次初始化都检测活动是否结束，如果失效就更新活动和商品恢复成普通商品
            if ($this->checkActivityIsEnd() && 0 == $this->flashSale['is_end']) {
                if ($this->specGoodsPrice) {
                    Db::name('spec_goods_price')->where('item_id', $this->specGoodsPrice['item_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    $goodsPromCount = Db::name('spec_goods_price')->where('goods_id', $this->specGoodsPrice['goods_id'])->where('prom_type', '>', 0)->count('item_id');
                    if (0 == $goodsPromCount) {
                        Db::name('goods')->where('goods_id', $this->specGoodsPrice['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    }
                    unset($this->specGoodsPrice);
                    $this->specGoodsPrice = SpecGoodsPrice::get($specGoodsPrice['item_id']);
                } else {
                    Db::name('goods')->where('goods_id', $this->flashSale['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                }
                $this->flashSale->is_end = 1;
                $this->flashSale->save();
                unset($this->goods);
                $this->goods = Goods::get($goods['goods_id']);
            }
        }
    }

    /**
     * 活动是否正在进行.
     *
     * @return bool
     */
    public function checkActivityIsAble()
    {
        if (empty($this->flashSale)) {
            return false;
        }
        if (time() > $this->flashSale['start_time'] && time() < $this->flashSale['end_time'] && 0 == $this->flashSale['is_end']) {
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
        if (empty($this->flashSale)) {
            return true;
        }
        if ($this->flashSale['buy_num'] >= $this->flashSale['goods_num']) {
            return true;
        }
        if (time() > $this->flashSale['end_time']) {
            return true;
        }

        return false;
    }

    /**
     * 获取用户抢购已购商品数量.
     *
     * @param $user_id
     *
     * @return float|int
     */
    public function getUserFlashOrderGoodsNum($user_id)
    {
        $orderWhere = [
            'user_id' => $user_id,
            'order_status' => ['<>', 3],
            'add_time' => ['between', [$this->flashSale['start_time'], $this->flashSale['end_time']]],
        ];
        $order_id_arr = Db::name('order')->where($orderWhere)->getField('order_id', true);
        if ($order_id_arr) {
            $orderGoodsWhere = ['prom_id' => $this->flashSale['id'], 'prom_type' => 1, 'order_id' => ['in', implode(',', $order_id_arr)]];
            $goods_num = DB::name('order_goods')->where($orderGoodsWhere)->sum('goods_num');
            if ($goods_num) {
                return $goods_num;
            }

            return 0;
        }

        return 0;
    }

    /**
     * 获取用户剩余抢购商品数量.
     *
     * @author lxl 2017-5-11
     *
     * @param $user_id |用户ID
     *
     * @return mixed
     */
    public function getUserFlashResidueGoodsNum($user_id)
    {
        $residue_num = $this->flashSale['goods_num'] - $this->flashSale['buy_num']; //剩余库存
        if ($this->flashSale['buy_limit'] == 0) {
            // 没有限制购买数量
            return $residue_num;
        }
        $purchase_num = $this->getUserFlashOrderGoodsNum($user_id); //用户抢购已购商品数量
        //限购 > 已购
        $residue_buy_limit = $this->flashSale['buy_limit'] - $purchase_num;
        if ($residue_buy_limit > $residue_num) {
            return $residue_num;
        }

        return $residue_buy_limit;
    }

    /**
     * 获取单个抢购活动.
     *
     * @return static
     */
    public function getPromModel()
    {
        return $this->flashSale;
    }

    /**
     * 获取商品原始数据.
     *
     * @return static
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
        if ($this->specGoodsPrice) {
            //活动商品有规格，规格和活动是一对一
            $activityGoods = $this->specGoodsPrice;
        } else {
            //活动商品没有规格，活动和商品是一对一
            $activityGoods = $this->goods;
        }
        $activityGoods['activity_title'] = $this->flashSale['title'];
        $activityGoods['market_price'] = $this->goods['shop_price'];
        $activityGoods['shop_price'] = $this->flashSale['price'];
        $activityGoods['store_count'] = $this->flashSale['store_count'];
        $activityGoods['start_time'] = $this->flashSale['start_time'];
        $activityGoods['end_time'] = $this->flashSale['end_time'];
        $activityGoods['buy_limit'] = $this->flashSale['buy_limit'];
        $activityGoods['can_integral'] = $this->flashSale['can_integral'];
        $activityGoods['virtual_num'] = 0;
        $activityGoods['exchange_integral'] = $this->goods['exchange_integral'];

        return $activityGoods;
    }

    /**
     * 该活动是否已经失效.
     */
    public function IsAble()
    {
        if (empty($this->flashSale)) {
            return false;
        }
        if (1 == $this->flashSale['is_end']) {
            return false;
        }
        if ($this->flashSale['buy_num'] >= $this->flashSale['goods_num']) {
            return false;
        }
        if (time() > $this->flashSale['end_time']) {
            return false;
        }

        return true;
    }

    /**
     * 抢购商品立即购买.
     *
     * @param $buyGoods
     * @param $buyType |购买方式
     *
     * @return mixed
     *
     * @throws TpshopException
     */
    public function buyNow($buyGoods, $buyType, $passAuth)
    {
        if ($this->checkActivityIsAble()) {
            if ($this->flashSale['buy_limit'] != 0 && $buyGoods['goods_num'] > $this->flashSale['buy_limit'] && !$passAuth) {
                throw new TpshopException('抢购商品立即购买', 0, ['status' => 0, 'msg' => '每人限购' . $this->flashSale['buy_limit'] . '件', 'result' => '']);
            }
        }
        $userFlashOrderGoodsNum = $this->getUserFlashOrderGoodsNum($buyGoods['user_id']); //获取用户抢购已购商品数量
        $userBuyGoodsNum = $buyGoods['goods_num'] + $userFlashOrderGoodsNum;
        if ($this->flashSale['buy_limit'] != 0 && $userBuyGoodsNum > $this->flashSale['buy_limit'] && !$passAuth) {
            throw new TpshopException('抢购商品立即购买', 0, ['status' => 0, 'msg' => '每人限购' . $this->flashSale['buy_limit'] . '件，您已下单' . $userFlashOrderGoodsNum . '件', 'result' => '']);
        }
        $flashSalePurchase = $this->flashSale['goods_num'] - $this->flashSale['buy_num']; //抢购剩余库存
        if ($buyGoods['goods_num'] > $flashSalePurchase && !$passAuth) {
            throw new TpshopException('抢购商品立即购买', 0, ['status' => 0, 'msg' => '商品库存不足，剩余' . $flashSalePurchase, 'result' => '']);
        }
        $member_goods_price = $this->flashSale['price'];
        $use_integral = 0;
        if (1 == $this->flashSale['can_integral'] && $buyType == 1) {
            $member_goods_price = $this->flashSale['price'] - $buyGoods['goods']['exchange_integral'];
            $use_integral = $buyGoods['goods']['exchange_integral'];
        }
        $buyGoods['can_integral'] = $this->flashSale['can_integral'];
        $buyGoods['member_goods_price'] = $member_goods_price;
        $buyGoods['use_integral'] = $use_integral;
        $buyGoods['prom_type'] = 1;
        $buyGoods['prom_id'] = $this->flashSale['id'];
        $buyGoods['goods']['retail_pv'] = bcmul($buyGoods['goods']['retail_pv'], ($buyGoods['member_goods_price'] / $buyGoods['goods_price']), 2);
        $buyGoods['goods']['integral_pv'] = bcmul($buyGoods['goods']['integral_pv'], ($buyGoods['member_goods_price'] / $buyGoods['goods_price']), 2);

        return $buyGoods;
    }
}
