<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic\order;

use app\common\logic\CartLogic;
use app\common\logic\GiftLogic as GiftService;

class GiftLogic
{
    private $activityList;
    private $order;
    private $money;
    private $reward_info;
    private $user_id;
    private $categoryList;
    private $goodsList;

    public function __construct($id = 1)
    {
        $giftService = new GiftService();
        $this->activityList = $giftService->getAvailable();
    }

    public function setOrder($order)
    {
        $this->order = $order;
        if (!$this->user_id) {
            $this->user_id = $this->order['user_id'];
        }
    }

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    public function setGoodsList($goods_list)
    {
        $this->goodsList = $goods_list;
    }

    public function setMoney($money)
    {
        $this->money = $money;
    }

    public function getRewardInfo()
    {
        return $this->reward_info;
    }

    /**
     * 获得符合条件奖励，并做上奖励记录.
     *
     * @return array
     */
    public function getGoodsList()
    {
        $goods_list = [];


        if ($this->activityList)
        {

            // 1.先找出所有的商品的分类，相应信息存放在categoryList数组中
            foreach ($this->goodsList as $v)
            {
                $cat_id = M('goods')->where('goods_id', $v['goods_id'])->getField('cat_id');
                $category = M('GoodsCategory')->where('id', $cat_id)->getField('parent_id_path');
                $category = explode('_', $category);
                if (isset($category[1])) {
                    $this->categoryList[$category[1]] += $v['member_goods_price'] * $v['goods_num'];
                }
                if (isset($category[2])) {
                    $this->categoryList[$category[2]] += $v['member_goods_price'] * $v['goods_num'];
                }
                if (isset($category[3])) {
                    $this->categoryList[$category[3]] += $v['member_goods_price'] * $v['goods_num'];
                }
                $this->categoryList[0] += $v['member_goods_price'] * $v['goods_num'];

                $extend_cat_id = M('goods')->where('goods_id', $v['goods_id'])->getField('extend_cat_id');
                if($extend_cat_id){
                    $extend_category = M('GoodsCategory')->where('id', $extend_cat_id)->getField('parent_id_path');
                    $extend_category = explode('_', $extend_category);
                    if (isset($extend_category[1])) {
                        $this->categoryList[$extend_category[1]] += $v['member_goods_price'] * $v['goods_num'];
                    }
                    if (isset($extend_category[2])) {
                        $this->categoryList[$extend_category[2]] += $v['member_goods_price'] * $v['goods_num'];
                    }
                    if (isset($extend_category[3])) {
                        $this->categoryList[$extend_category[3]] += $v['member_goods_price'] * $v['goods_num'];
                    }
                }
            }

            $enable_list = [];

            foreach ($this->activityList as $k => $v)
            {

                if ($v['reward']) {

                    // 优先找全场通用的加价购活动
                    $count = count($v['reward']) - 1;
                    $v['price'] = $v['reward'][$count]['money'];

                    if (0 == $v['cat_id'] && $v['price'] <= $this->categoryList[0]) {
                        $enable_list[] = $v;
                        continue;
                    }

                    // 寻找对应分类且可行的活动

                    $enable_cat = explode(',', $v['cat_id']);

                    $enable_cat_2 = explode(',', $v['cat_id_2']);
                    $enable_cat_3 = explode(',', $v['cat_id_3']);
                    if ($enable_cat_2) {
                        foreach ($enable_cat_2 as $c2k => $c2v) {
                            if ($c2v > 0) {
                                unset($enable_cat[$c2k]);
                            }

                            if($c2v == 0){
                                unset($enable_cat_2[$c2k]);
                            }
                        }
                    }

                    if ($enable_cat_3) {
                        foreach ($enable_cat_3 as $c3k => $c3v) {
                            if ($c3v > 0) {
                                unset($enable_cat[$c3k]);
                                unset($enable_cat_2[$c3k]);
                            }
                            if($c3v == 0){
                                unset($enable_cat_3[$c3k]);
                            }
                        }
                    }

                    $enable_cat = array_merge($enable_cat, $enable_cat_2, $enable_cat_3);
                    $price = 0;

                    foreach ($this->categoryList as $ck => $cv) {
                        if (in_array($ck, $enable_cat)) {
                            $price += $this->categoryList[$ck];
                            if ($price >= $v['price']) {
                                $enable_list[] = $v;
                                break;
                            }
                        }
                    }
                }
            }



            if(empty($enable_list)){
                return [];
            }

            foreach ($enable_list as $k => $v) {
                if ($v['reward']) {
                    foreach ($v['reward'] as $rk => $rv) {
                        // 符合奖励条件
                        if ($rv['money'] <= $this->money) {
                            $goods_list[] = [
                                'goods_id' => $v['goods_id'],
                                'item_id' => $v['item_id'],
                                'goods_num' => $rv['reward_num'],
                            ];
                            // 奖励记录
                            $this->reward_info[] = [
                                'gift_id' => $v['id'],
                                'user_id' => $this->user_id,
                                'gift_title' => $v['title'],
                                'gift_reward_id' => $rv['reward_id'],
                                'gift_reward_desc' => $rv['description'],
                                'reward_goods_id' => $v['goods_id'],
                                'reward_item_id' => $v['item_id'],
                                'reward_num' => $rv['reward_num'],
                                'type' => 1,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        // 相同规格奖励商品数量累加,商品数据处理
        if ($goods_list) {
            $arr = [];
            foreach ($goods_list as $lk => $lv) {
                if (isset($arr[$lv['goods_id'].'-'.$lv['item_id']])) {
                    $arr[$lv['goods_id'].'-'.$lv['item_id']]['goods_num'] += $lv['goods_num'];
                } else {
                    $arr[$lv['goods_id'].'-'.$lv['item_id']] = $lv;
                }
            }

            unset($goods_list);
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($this->user_id);

            foreach ($arr as $ak => $av) {
                $cartLogic->setGoodsModel($av['goods_id']);
                $cartLogic->setSpecGoodsPriceModel($av['item_id']);
                $cartLogic->setGoodsBuyNum($av['goods_num']);
                $cartLogic->setType(2);
                $cartLogic->setCartType(0);
                $buyGoods = $cartLogic->buyNow();
                $arr[$ak] = $buyGoods;
            }

            $goods_list = $arr;
        }

        // 返回奖励商品列表
        return $goods_list;
    }

    // 记录
    public function record()
    {
        if ($this->reward_info) {
            foreach ($this->reward_info as $rk => $rv) {
                $this->reward_info[$rk]['order_sn'] = $this->order['order_sn'];
                $this->reward_info[$rk]['created_at'] = time();
                M('gift_log')->insert($this->reward_info[$rk]);
                M('gift_reward')->where('reward_id', $rv['gift_reward_id'])->update([
                    'order_num' => ['exp', 'order_num+1'],
                    'buy_num' => ['exp', 'buy_num+'.$rv['reward_num']],
                ]);
            }
        }
    }

    public function returnReward()
    {
        if ($this->order) {
            $list = M('gift_log')->where('order_sn', $this->order['order_sn'])->select();
            if ($list) {
                foreach ($list as $v) {
                    M('gift_log')->where('id', $v['id'])->update(['type' => 2]);
                    M('gift_reward')->where('reward_id', $v['gift_reward_id'])->update([
                        'order_num' => ['exp', 'order_num-1'],
                        'buy_num' => ['exp', 'buy_num-'.$v['reward_num']],
                    ]);
                }
            }
        }
    }
}
