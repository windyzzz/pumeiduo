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

use app\common\model\FreightConfig;
use app\common\model\FreightRegion;
use app\common\model\FreightTemplate;
use app\common\model\Goods;
use app\common\util\TpshopException;
use think\Model;

/**
 * 运费 逻辑定义
 * Class CatsLogic.
 */
class FreightLogic extends Model
{
    protected $goods;                   // 商品模型
    protected $regionId;                // 地址
    protected $goodsNum;                // 件数
    private $freightTemplate;
    private $freight = 0;               // 启用商城免运费设置的运费
    private $outSettingFreight = 0;     // 不启用商城免运费设置的运费
    private $freightGoodsPrice = 0;     // 启用商城免运费设置的商品价格

    /**
     * 包含一个商品模型.
     *
     * @param $goods
     */
    public function setGoodsModel($goods)
    {
        $this->goods = $goods;
        $FreightTemplate = new FreightTemplate();
        $this->freightTemplate = $FreightTemplate->where(['template_id' => $this->goods['template_id']])->find();
    }

    /**
     * 设置地址id.
     *
     * @param $regionId
     */
    public function setRegionId($regionId)
    {
        $this->regionId = $regionId;
    }

    /**
     * 设置商品数量.
     *
     * @param $goodsNum
     */
    public function setGoodsNum($goodsNum)
    {
        $this->goodsNum = $goodsNum;
    }

    /**
     * 进行一系列运算.
     *
     * @throws TpshopException
     */
    public function doCalculation()
    {
        $this->freight = 0;
        $this->outSettingFreight = 0;
        $this->freightGoodsPrice = 0;
        if (0 == $this->goods['is_free_shipping']) {
            $freightRegion = $this->getFreightRegion();
            $freightConfig = $this->getFreightConfig($freightRegion);
            //计算价格
            switch ($this->freightTemplate['type']) {
                case 1:
                    //按重量
                    $total_unit = $this->goods['total_weight'] ? $this->goods['total_weight'] : $this->goods['weight'] * $this->goodsNum; //总重量
                    break;
                case 2:
                    //按体积
                    $total_unit = $this->goods['total_volume'] ? $this->goods['total_volume'] : $this->goods['volume'] * $this->goodsNum; //总体积
                    break;
                default:
                    //按件数
                    $total_unit = $this->goodsNum;
            }
            if ($this->freightTemplate['is_out_setting'] == 0) {
                // 启用商城免运费设置
                $this->freight = $this->getFreightPrice($total_unit, $freightConfig);
                $this->freightGoodsPrice = bcsub($this->goods['member_goods_price'], bcmul($this->goodsNum, $this->goods['each_order_prom_amount'], 2), 2);
            } else {
                // 不启用商城免运费设置
                $this->outSettingFreight = $this->getFreightPrice($total_unit, $freightConfig);
            }
        } else {
            $this->freightGoodsPrice = $this->goods['member_goods_price'];
        }
    }

    /**
     * 是否支持配送
     *
     * @return bool|true
     */
    public function checkShipping()
    {
        if (0 == $this->goods['is_free_shipping']) {
            $freightRegion = $this->getFreightRegion();
            $freightConfig = $this->getFreightConfig($freightRegion);
            if (empty($freightConfig)) {
                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * 获取运费.
     *
     * @return int
     */
    public function getFreight()
    {
        return $this->freight;
    }

    /**
     * 获取运费（不按照商城免运费设置）
     *
     * @return int
     */
    public function getOutSettingFreight()
    {
        return $this->outSettingFreight;
    }

    /**
     * 获取订单商品价格
     *
     * @return int
     */
    public function getFreightGoodsPrice()
    {
        return $this->freightGoodsPrice;
    }

    /**
     * 根据总量和配置信息获取运费.
     *
     * @param $total_unit
     * @param $freight_config
     *
     * @return mixed
     */
    private function getFreightPrice($total_unit, $freight_config)
    {
        $total_unit = floatval($total_unit);
        if ($total_unit > $freight_config['first_unit']) {
            $average = ceil(bcdiv(bcsub($total_unit, $freight_config['first_unit'], 2), $freight_config['continue_unit'], 2));
            $freight_price = bcadd($freight_config['first_money'], bcmul($freight_config['continue_money'], $average, 2), 2);
        } else {
            $freight_price = $freight_config['first_money'];
        }
        // 该运费模板是否有设置满优惠邮费
        switch ($freight_config['discount_type']) {
            case 1:
                // 数量
                if ($this->goodsNum >= number_format($freight_config['discount_condition'])) {
                    $freight_price = $freight_config['discount_money'];
                }
                break;
        }

        return $freight_price;
    }

    /**
     * @param $freightRegion
     *
     * @return array|false|null|\PDOStatement|string|Model
     */
    private function getFreightConfig($freightRegion)
    {
        //还找不到就去看下模板是否启用默认配置
        if (empty($freightRegion)) {
            if (1 == $this->freightTemplate['is_enable_default']) {
                $FreightConfig = new FreightConfig();
                $freightConfig = $FreightConfig->where(['template_id' => $this->goods['template_id'], 'is_default' => 1])->find();

                return $freightConfig;
            }

            return null;
        }

        return $freightRegion['freightConfig'];
    }

    /**
     * 获取区域配置.
     */
    private function getFreightRegion()
    {
        //先根据$region_id去查找
        $FreightRegion = new FreightRegion();
        $freight_region_where = ['template_id' => $this->goods['template_id'], 'region_id' => $this->regionId];
        $freightRegion = $FreightRegion->where($freight_region_where)->find();
        if (!empty($freightRegion)) {
            return $freightRegion;
        }
        $parent_region_id = $this->getParentRegionList($this->regionId);
        $parent_freight_region_where = ['template_id' => $this->goods['template_id'], 'region_id' => ['IN', $parent_region_id]];
        $freightRegion = $FreightRegion->where($parent_freight_region_where)->order('region_id asc')->find();

        return $freightRegion;
    }

    /**
     * 寻找Region_id的父级id.
     *
     * @param $cid
     *
     * @return array
     */
    public function getParentRegionList($cid)
    {
        //$pids = '';
        $pids = [];
        $parent_id = M('region2')->cache(true)->where(['id' => $cid])->getField('parent_id');
        if (0 != $parent_id) {
            //$pids .= $parent_id;
            array_push($pids, $parent_id);
            $npids = $this->getParentRegionList($parent_id);
            if (!empty($npids)) {
                //$pids .= ','.$npids;
                $pids = array_merge($pids, $npids);
            }
        }

        return $pids;
    }
}
