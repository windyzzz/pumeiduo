<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\model;

use think\Db;
use think\Model;

class Goods extends Model
{
    /**
     * 一个商品对应多个规格
     */
    public function specGoodsPrice()
    {
        return $this->hasMany('SpecGoodsPrice', 'goods_id', 'goods_id');
    }

    /**
     * 后置操作方法
     * 自定义的一个函数 用于数据保存后做的相应处理操作, 使用时手动调用.
     *
     * @param int $goods_id 商品id
     */
    public function afterSave($goods_id)
    {
        $goods_info = M('goods')->find($goods_id);
        $item_img = I('item_img/a');
        // 商品货号
        $goods_sn = 'P'.str_pad($goods_id, 7, '0', STR_PAD_LEFT);
        $this->where("goods_id = $goods_id and goods_sn = ''")->save(['goods_sn' => $goods_sn]); // 根据条件更新记录

        // 商品图片相册  图册
        $goods_images = I('goods_images/a');
        if (count($goods_images) > 1) {
            array_pop($goods_images); // 弹出最后一个
             $goodsImagesArr = M('GoodsImages')->where("goods_id = $goods_id")->getField('img_id,image_url'); // 查出所有已经存在的图片

             // 删除图片
            foreach ($goodsImagesArr as $key => $val) {
                if (!in_array($val, $goods_images)) {
                    M('GoodsImages')->where("img_id = {$key}")->delete();
                }
            }
            // 添加图片
            foreach ($goods_images as $key => $val) {
                if (null == $val) {
                    continue;
                }
                if (!in_array($val, $goodsImagesArr)) {
                    $data = ['goods_id' => $goods_id, 'image_url' => $val];
                    M('GoodsImages')->insert($data); // 实例化User对象
                }
            }
        }

        // 超值套组  - 关闭套组
        /*$goodsSeries = I('goodsSeries/a');
        if ($goodsSeries > 0) {
            // array_pop($goodsSeries); // 弹出最后一个
             $goodsSeriesArr = M('GoodsSeries')->where("goods_id = $goods_id")->getField('id,g_id,g_number,item_id'); // 查出所有已经存在的图片

             // 删除图片
            foreach ($goodsSeriesArr as $key => $val) {
                if (!in_array($val, $goodsSeries)) {
                    M('GoodsSeries')->where("id = {$key}")->delete();
                }
            }
            // 添加图片
            foreach ($goodsSeries as $key => $val) {
                if (null == $val) {
                    continue;
                }
                if (!in_array($val, $goodsSeriesArr)) {
                    $val['goods_id'] = $goods_id;
                    M('GoodsSeries')->insert($val); // 实例化User对象
                }
            }
        } else {
            M('GoodsSeries')->where("goods_id = {$goods_id}")->delete();
        }*/

        // 查看主图是否已经存在相册中
        $original_img = I('original_img');
        $c = M('GoodsImages')->where("goods_id = $goods_id and image_url = '{$original_img}'")->count();

        //@modify by wangqh fix:删除商品详情的图片(相册图刚好是主图时)删除的图片仍然在相册中显示. 如果主图存物理图片存在才添加到相册 @{
        $deal_orignal_img = str_replace('../', '', $original_img);
        $deal_orignal_img = trim($deal_orignal_img, '.');
        $deal_orignal_img = trim($deal_orignal_img, '/');
        if (0 == $c && $original_img && file_exists($deal_orignal_img)) { //@}
            M('GoodsImages')->add(['goods_id' => $goods_id, 'image_url' => $original_img]);
        }
        delFile(UPLOAD_PATH."goods/thumb/$goods_id"); // 删除缩略图

        // 商品规格价钱处理
        $goods_item = I('item/a');

        $eidt_goods_id = I('goods_id', 0);
        $specStock = Db::name('spec_goods_price')->where('goods_id = '.$goods_id)->getField('key,store_count,item_id,store_count,item_sn');
        if ($goods_item) {
            $keyArr = ''; //规格key数组
            foreach ($goods_item as $k => $v) {
                $keyArr .= $k.',';
                // 批量添加数据
                $v['price'] = trim($v['price']);
                $store_count = $v['store_count'] = trim($v['store_count']); // 记录商品总库存
                $v['sku'] = trim($v['sku']);
                $data = ['goods_id' => $goods_id, 'key' => $k, 'key_name' => $v['key_name'], 'price' => $v['price'], 'store_count' => $v['store_count'], 'sku' => $v['sku']];

                if ($item_img) {
                    $spec_key_arr = explode('_', $k);
                    foreach ($item_img as $key => $val) {
                        if (in_array($key, $spec_key_arr)) {
                            $data['spec_img'] = $val;
                            break;
                        }
                    }
                }

                // if (!empty($specStock[$k])) {
                //     Db::name('spec_goods_price')->where(['goods_id' => $goods_id, 'key' => $k])->update($data);
                // } else {
                //     Db::name('spec_goods_price')->insert($data);
                // }

                if (!empty($specStock[$k])) {
                    Db::name('spec_goods_price')->where(['goods_id' => $goods_id, 'key' => $k])->delete();
                    $data['item_id'] = $specStock[$k]['item_id'];
                    $data['item_sn'] = $specStock[$k]['item_sn'];
                }

                Db::name('spec_goods_price')->insert($data);

                if (!empty($specStock[$k]) && $v['store_count'] != $specStock[$k]['store_count'] && $eidt_goods_id > 0) {
                    $stock = $v['store_count'] - $specStock[$k]['store_count'];
                } else {
                    $stock = $v['store_count'];
                }
                //记录库存日志
                update_stock_log(session('admin_id'), $stock, ['goods_id' => $goods_id, 'goods_name' => I('goods_name'), 'spec_key_name' => $v['key_name']]);
                // 修改商品后购物车的商品价格也修改一下
                M('cart')->where("goods_id = $goods_id and spec_key = '$k'")->where('type', 2)->save([
                     'market_price' => $v['price'], //市场价
                     'goods_price' => $v['price'], // 本店价
                     'member_goods_price' => $v['price'], // 会员折扣价
                 ]);

                M('cart')->where("goods_id = $goods_id and spec_key = '$k'")->where('type', 1)->save([
                    'market_price' => $v['price'], //市场价
                    'goods_price' => $v['price'], // 本店价
                    'member_goods_price' => $v['price'] - $goods_info['exchange_integral'], // 会员折扣价
                    'use_integral' => $goods_info['exchange_integral'], // 会员折扣价
                ]);
            }
            if ($keyArr){//非一键待发  不可以删除
                Db::name('spec_goods_price')->where('goods_id', $goods_id)->whereNotIn('key', $keyArr)->delete();
            }
        }

        // 商品规格图片处理
        if (I('item_img/a')) {
            M('SpecImage')->where("goods_id = $goods_id")->delete(); // 把原来是删除再重新插入
            foreach (I('item_img/a') as $key => $val) {
                M('SpecImage')->insert(['goods_id' => $goods_id, 'spec_image_id' => $key, 'src' => $val]);
            }
        }
        refresh_stock($goods_id); // 刷新商品库存
    }
}
