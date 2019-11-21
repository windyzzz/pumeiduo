<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\model;

use app\common\logic\FlashSaleLogic;
use app\common\logic\GroupBuyLogic;
use app\common\logic\PromGoodsLogic;
use think\Model;

class Cart extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function promGoods()
    {
        return $this->hasOne('PromGoods', 'id', 'prom_id')->cache(true, 10);
    }

    public function goods()
    {
        return $this->hasOne('Goods', 'goods_id', 'goods_id')->cache(true, 10);
    }

    public function getSpecKeyNameArrAttr($value, $data)
    {
        if ($data['spec_key_name']) {
            $specKeyNameArr = explode(' ', $data['spec_key_name']);

            return $specKeyNameArr;
        }

        return [];
    }

    /**
     * 商品优惠总额.
     *
     * @param $value
     * @param $data
     *
     * @return mixed
     */
    public function getGoodsFeeAttr($value, $data)
    {
        return  round($data['goods_num'] * $data['member_goods_price'], 2);
    }

    /**
     * 商品总额.
     *
     * @param $value
     * @param $data
     *
     * @return mixed
     */
    public function getTotalFeeAttr($value, $data)
    {
        return round($data['goods_num'] * $data['goods_price'], 2);
    }

    /**
     * 商品总额优惠.
     *
     * @param $value
     * @param $data
     *
     * @return mixed
     */
    public function getCutFeeAttr($value, $data)
    {
        return round(($data['goods_num'] * ($data['goods_price'] - $data['member_goods_price'])), 2);
    }

    /**
     * 限购数量.
     *
     * @param $value
     * @param $data
     *
     * @return mixed
     */
    public function getLimitNumAttr($value, $data)
    {
        $spec_goods_price = null;
        $goods = Goods::get($data['goods_id'], '', 20);
        //有规格
        if ($data['spec_key']) {
            $spec_goods_price = SpecGoodsPrice::get(['goods_id' => $data['goods_id'], 'key' => $data['spec_key']]);
            if (1 == $data['prom_type']) {
                $FlashSaleLogic = new FlashSaleLogic($goods, $spec_goods_price);
                $limitNum = $FlashSaleLogic->getUserFlashResidueGoodsNum($data['user_id']);
            } elseif (2 == $data['prom_type']) {
                $groupBuyLogic = new GroupBuyLogic($goods, $spec_goods_price);
                $limitNum = $groupBuyLogic->getPromotionSurplus($data['user_id']); //团购剩余库存
            } elseif (3 == $data['prom_type']) {
                $promoGoodsLogic = new PromGoodsLogic($goods, $spec_goods_price);
                $limitNum = $promoGoodsLogic->getPromoGoodsResidueGoodsNum($data['user_id']);
            } else {
                $limitNum = $spec_goods_price['store_count'];
            }
        } else {
            //没有规格
            if (1 == $data['prom_type']) {
                $FlashSaleLogic = new FlashSaleLogic($goods, null);
                $limitNum = $FlashSaleLogic->getUserFlashResidueGoodsNum($data['user_id']);
            } elseif (2 == $data['prom_type']) {
                $groupBuyLogic = new GroupBuyLogic($goods, null);
                $limitNum = $groupBuyLogic->getPromotionSurplus(); //团购剩余库存
            } elseif (3 == $data['prom_type']) {
                $promoGoodsLogic = new PromGoodsLogic($goods, null);
                $limitNum = $promoGoodsLogic->getPromoGoodsResidueGoodsNum($data['user_id']);
            } else {
                $limitNum = $goods['store_count'];
            }
        }

        return $limitNum;
    }
}
