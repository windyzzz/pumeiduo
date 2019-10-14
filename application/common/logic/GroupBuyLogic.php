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
use app\common\model\GroupBuy;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use think\db;

/**
 * 团购逻辑定义
 * Class CatsLogic.
 */
class GroupBuyLogic extends Prom
{
    protected $GroupBuy; //团购模型
    protected $goods; //商品模型
    protected $specGoodsPrice; //商品规格模型

    public function __construct($goods, $specGoodsPrice)
    {
        parent::__construct();
        $this->goods = $goods;
        $this->specGoodsPrice = $specGoodsPrice;
        if ($this->specGoodsPrice) {
            //活动商品有规格，规格和活动是一对一
            $this->GroupBuy = GroupBuy::get($specGoodsPrice['prom_id']);
        } else {
            //活动商品没有规格，活动和商品是一对一
            $this->GroupBuy = GroupBuy::get($this->goods['prom_id']);
        }
        if ($this->GroupBuy) {
            //每次初始化都检测活动是否失效，如果失效就更新活动和商品恢复成普通商品
            if ($this->checkActivityIsEnd() && 0 == $this->GroupBuy['is_end']) {
                if ($this->specGoodsPrice) {
                    Db::name('spec_goods_price')->where('item_id', $this->specGoodsPrice['item_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    $goodsPromCount = Db::name('spec_goods_price')->where('goods_id', $this->specGoodsPrice['goods_id'])->where('prom_type', '>', 0)->count('item_id');
                    if (0 == $goodsPromCount) {
                        Db::name('goods')->where('goods_id', $this->specGoodsPrice['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    }
                    unset($this->specGoodsPrice);
                    $this->specGoodsPrice = SpecGoodsPrice::get($specGoodsPrice['item_id']);
                } else {
                    Db::name('goods')->where('goods_id', $this->GroupBuy['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                }
                $this->GroupBuy->is_end = 1;
                $this->GroupBuy->save();
                unset($this->goods);
                $this->goods = Goods::get($goods['goods_id']);
            }
        }
    }

    /**
     * 获取团购剩余库存.
     */
    public function getPromotionSurplus()
    {
        return $this->GroupBuy['goods_num'] - $this->GroupBuy['buy_num'];
    }

    public function getPromModel()
    {
        return $this->GroupBuy;
    }

    /**
     * 获取虚拟参与人数.
     *
     * @return number
     */
    public function getVirtualNum()
    {
        return $this->GroupBuy['virtual_num'] + $this->GroupBuy['buy_num'];
    }

    /**
     * 活动是否正在进行.
     *
     * @return bool
     */
    public function checkActivityIsAble()
    {
        if (empty($this->GroupBuy)) {
            return false;
        }
        if (time() > $this->GroupBuy['start_time'] && time() < $this->GroupBuy['end_time'] && 0 == $this->GroupBuy['is_end']) {
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
        if (empty($this->GroupBuy)) {
            return true;
        }
        if ($this->GroupBuy['buy_num'] >= $this->GroupBuy['goods_num']) {
            return true;
        }
        if (time() > $this->GroupBuy['end_time']) {
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
        if ($this->specGoodsPrice) {
            //活动商品有规格，规格和活动是一对一
            $activityGoods = $this->specGoodsPrice;
        } else {
            //活动商品没有规格，活动和商品是一对一
            $activityGoods = $this->goods;
        }
        $activityGoods['activity_title'] = $this->GroupBuy['title'];
        $activityGoods['market_price'] = $this->goods['shop_price'];
        $activityGoods['shop_price'] = $this->GroupBuy['price'];
        $activityGoods['store_count'] = $this->GroupBuy['store_count'];
        $activityGoods['start_time'] = $this->GroupBuy['start_time'];
        $activityGoods['group_goods_num'] = $this->GroupBuy['group_goods_num'];
        $activityGoods['end_time'] = $this->GroupBuy['end_time'];
        $activityGoods['buy_num'] = $this->GroupBuy['buy_num'];
        $activityGoods['intro'] = $this->GroupBuy['intro'];
        $activityGoods['goods_num'] = $this->GroupBuy['goods_num'];
        $activityGoods['is_sale_out'] = $this->GroupBuy['goods_num'] <= $this->GroupBuy['buy_num'] ? 1 : 0;
        $activityGoods['people_num'] = $this->GroupBuy['group_goods_num'] - ($this->GroupBuy['order_num'] % $this->GroupBuy['group_goods_num']);
        $activityGoods['can_integral'] = $this->GroupBuy['can_integral'];
        $activityGoods['precent'] = round($this->GroupBuy['order_num'] % $this->GroupBuy['group_goods_num'] / $this->GroupBuy['group_goods_num'], 2);
        $activityGoods['virtual_num'] = $this->GroupBuy['virtual_num'] + $this->GroupBuy['order_num'];
        $activityGoods['exchange_integral'] = $this->goods['exchange_integral'];

        return $activityGoods;
    }

    /**
     * 该活动是否已经失效.
     */
    public function IsAble()
    {
        if (empty($this->GroupBuy)) {
            return false;
        }
        if ($this->GroupBuy['buy_num'] >= $this->GroupBuy['goods_num']) {
            return false;
        }
        if (time() > $this->GroupBuy['end_time']) {
            return false;
        }
        if (1 == $this->GroupBuy['is_end']) {
            return false;
        }

        return true;
    }

    /**
     * 团购商品立即购买.
     *
     * @param $buyGoods
     *
     * @return mixed
     *
     * @throws TpshopException
     */
    public function buyNow($buyGoods)
    {
        //活动是否已经结束
        if (1 == $this->GroupBuy['is_end'] || empty($this->GroupBuy)) {
            throw new TpshopException('团购商品立即购买', 0, ['status' => 0, 'msg' => '团购活动已结束', 'result' => '']);
        }
        if ($this->checkActivityIsAble()) {
            $groupBuyPurchase = $this->GroupBuy['goods_num'] - $this->GroupBuy['buy_num']; //团购剩余库存
            if ($buyGoods['goods_num'] > $groupBuyPurchase) {
                throw new TpshopException('团购商品立即购买', 0, ['status' => 0, 'msg' => '商品库存不足，剩余'.$groupBuyPurchase, 'result' => '']);
            }
            $member_goods_price = $this->GroupBuy['price'];
            $use_integral = 0;
            if (1 == $this->GroupBuy['can_integral']) {
                $member_goods_price = $this->GroupBuy['price'] - $buyGoods['goods']['exchange_integral'];
                $use_integral = $buyGoods['goods']['exchange_integral'];
            }
            $buyGoods['can_integral'] = $this->GroupBuy['can_integral'];
            $buyGoods['member_goods_price'] = $member_goods_price;
            $buyGoods['use_integral'] = $use_integral;

            // $buyGoods['member_goods_price'] = $this->GroupBuy['price'];
            $buyGoods['prom_type'] = 2;
            $buyGoods['prom_id'] = $this->GroupBuy['id'];
        }

        return $buyGoods;
    }
}
