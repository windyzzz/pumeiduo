<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\validate;

use think\Db;
use think\Validate;

class Goods extends Validate
{
    // 验证规则
    protected $rule = [
        'goods_id' => 'checkGoodsId',
        //'goods_name' => 'require|min:3|max:150|unique:goods',
        'cat_id' => 'number|gt:0',
        //'goods_sn' => 'unique:goods|max:20',
        'shop_price' => ['require', 'regex' => '([0-9]\d*(\.\d*[1-9])?)|(0\.\d*[1-9])'],
        // 'market_price'          =>'require|regex:\d{1,10}(\.\d{1,2})?$|checkMarketPrice',
        'ctax_price' => 'require|regex:\d{1,10}(\.\d{1,2})?$|checkTaxPrice',
        'stax_price' => 'require|regex:\d{1,10}(\.\d{1,2})?$|checkTaxPrice',
        'weight' => 'regex:\d{1,10}(\.\d{1,2})?$',
        'give_integral' => 'regex:^\d+$',
        'is_virtual' => 'checkVirtualIndate',
        // 'exchange_integral'     =>'checkExchangeIntegral',
        //'store_count' => 'checkStoreCount',
        'is_free_shipping' => 'require|checkShipping',
    ];
    //错误信息
    protected $message = [
        'goods_name.require' => '商品名称必填',
        'goods_name.min' => '名称长度至少3个字符',
        'goods_name.max' => '名称长度至多50个汉字',
        'goods_name.unique' => '商品名称重复',
        'cat_id.number' => '商品分类必须填写',
        'cat_id.gt' => '商品分类必须选择',
        'goods_sn.unique' => '商品货号重复',
        'goods_sn.max' => '商品货号超过长度限制',
        'goods_num.checkGoodsNum' => '抢购数量不能大于库存数量',
        'shop_price.require' => '本店售价必须',
        'shop_price.regex' => '本店售价格式不对',
        // 'market_price.require'                          => '市场价格必填',
        'ctax_price.require' => '现金不含税价必填',
        'ctax_price.regex' => '现金不含税价格式不对',
        'ctax_price.checkTaxPrice' => '现金不含税价不得大于本店价',
        'stax_price.require' => '商品不含税价必填',
        'stax_price.regex' => '商品不含税价式不对',
        'stax_price.checkTaxPrice' => '商品不含税价不得大于本店价',
        // 'market_price.regex'                            => '市场价格式不对',
        // 'market_price.checkMarketPrice'                 => '市场价不得小于本店价',
        'store_count.checkStoreCount' => '商品设置库存太大，对应套组商品库存不足',
        'weight.regex' => '重量格式不对',
        'give_integral.regex' => '赠送积分必须是正整数',
        'exchange_integral.checkExchangeIntegral' => '积分抵扣金额不能超过商品总额',
        'is_virtual.checkVirtualIndate' => '虚拟商品有效期不得小于当前时间',
        'is_free_shipping.require' => '请选择商品是否包邮',
    ];

    /**
     * 检查积分兑换.
     *
     * @author dyr
     *
     * @return bool
     */
    protected function checkExchangeIntegral($value, $rule, $data)
    {
        if ($value > 0) {
            $goods = Db::name('goods')->where('goods_id', $data['goods_id'])->find();
            if (!empty($goods)) {
                if ($goods['prom_type'] > 0) {
                    return '该商品参与了其他活动。设置兑换积分无效，请设置为零';
                }
            }
        }
        $point_rate_value = tpCache('shopping.point_rate');
        if ($data['item']) {
            $count = count($data['item']);
            $item_arr = array_values($data['item']);
            $minPrice = $item_arr[0]['price'];
            for ($i = 0; $i < ($count - 1); ++$i) {
                if ($item_arr[$i + 1]['price'] < $minPrice) {
                    $minPrice = $item_arr[$i + 1]['price'];
                }
            }
            $goods_price = $minPrice;
        } else {
            $goods_price = $data['shop_price'];
        }

        $point_rate_value = empty($point_rate_value) ? 0 : $point_rate_value;
        if ($value > ($goods_price * $point_rate_value)) {
            return '积分抵扣金额不能超过商品总额';
        }

        return true;
    }

    /**
     * 检查库存是否符合规格
     *
     * @param $value
     *
     * @return bool
     */
    protected function checkStoreCount($value)
    {
        $goodsSeries = I('goodsSeries/a');
        if ($goodsSeries) {
            foreach ($goodsSeries as $k => $v) {
                $info = M('goods')->field('goods_id,goods_name,store_count')->where('goods_id', $v['g_id'])->find();
                if ($v['item_id'] > 0) {
                    $spec_goods = M('SpecGoodsPrice')->where('item_id', $v['item_id'])->find();
                    if ($value * $v['g_number'] > $spec_goods['store_count']) {
                        return '套组中的商品 【'.$info['goods_name'].','.$spec_goods['key_name'].'】 库存不够，不能设置这么大的库存';
                    }
                } else {
                    if ($value * $v['g_number'] > $info['store_count']) {
                        return '套组中的商品 【'.$info['goods_name'].'】 库存不够，不能设置这么大的库存'.$info['store_count'];
                    }
                }
            }
        }

        return true;
    }

    /**
     * 检查是否有商品规格参加活动，若有则不能编辑商品
     *
     * @param $value
     *
     * @return bool
     */
    protected function checkGoodsId($value)
    {
        $spec_goods_price = Db::name('spec_goods_price')->where('goods_id', $value)->where('prom_type', 'gt', 0)->find();
        if ($spec_goods_price) {
            return '该商品规格：'.$spec_goods_price['key_name'].'正在参与活动，不能编辑该商品信息';
        }
//        $goods= Db::name('goods')->where('goods_id',$value)->find();
//        if($goods['prom_type'] > 0){
//            return '该商品规格正在参与活动，不能编辑该商品信息';
//        }
        return true;
    }

    /**
     * 检查市场价.
     *
     * @param $value
     * @param $rule
     * @param $data
     *
     * @return bool
     */
    protected function checkMarketPrice($value, $rule, $data)
    {
        if ($value < $data['shop_price']) {
            return true;
        }

        return true;
    }

    /**
     * 检查不含税价.
     *
     * @param $value
     * @param $rule
     * @param $data
     *
     * @return bool
     */
    protected function checkTaxPrice($value, $rule, $data)
    {
        if ($value <= $data['shop_price']) {
            return true;
        }

        return false;
    }

    /**
     * 检查虚拟商品有效时间.
     *
     * @param $value
     * @param $rule
     * @param $data
     *
     * @return bool
     */
    protected function checkVirtualIndate($value, $rule, $data)
    {
        $virtualIndate = strtotime($data['virtual_indate']);
        if (1 == $value && time() > $virtualIndate) {
            return false;
        }

        return true;
    }

    protected function checkShipping($value, $rule, $data)
    {
        if (0 == $value && empty($data['template_id'])) {
            return '请选择运费模板';
        }

        return true;
    }
}
