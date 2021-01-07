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
    private $promGoodsMoney;
    private $flashSaleGoodsMoney;
    private $reward_info;
    private $user_id;
    private $categoryList = [];
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

    public function setPromGoodsMoney($money)
    {
        $this->promGoodsMoney = $money;
    }

    public function setFlashSaleGoodsMoney($money)
    {
        $this->flashSaleGoodsMoney = $money;
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
        $rewardGet = [];            // 符合奖励的前提配置
        $rewardMaxSet = [];         // 符合奖励的最大金额配置（商品种类）
        if ($this->activityList) {
            foreach ($this->goodsList as $k => $v) {
                $goods = M('goods')->where('goods_id', $v['goods_id'])->field('cat_id, is_abroad, is_supply, is_agent')->find();
                if ($goods['is_abroad'] == 1) {
                    $goodsNature = 2;
                } elseif ($goods['is_supply'] == 1) {
                    $goodsNature = 3;
                } elseif ($goods['is_agent'] == 1) {
                    $goodsNature = 4;
                } else {
                    $goodsNature = 1;
                }
                // 找出所有的商品的分类，相应信息存放在categoryList数组中
                $this->categoryList[0][$k]['goods_nature'] = $goodsNature;
                $this->categoryList[0][$k]['price'] += $v['member_goods_price'] * $v['goods_num'];
                $category = M('GoodsCategory')->where('id', $goods['cat_id'])->getField('parent_id_path');
                $category = explode('_', $category);
                if (isset($category[1])) {
                    $this->categoryList[$category[1]][$k]['goods_nature'] = $goodsNature;
                    $this->categoryList[$category[1]][$k]['price'] += $v['member_goods_price'] * $v['goods_num'];
                }
                if (isset($category[2])) {
                    $this->categoryList[$category[2]][$k]['goods_nature'] = $goodsNature;
                    $this->categoryList[$category[2]][$k]['price'] += $v['member_goods_price'] * $v['goods_num'];
                }
                if (isset($category[3])) {
                    $this->categoryList[$category[3]][$k]['goods_nature'] = $goodsNature;
                    $this->categoryList[$category[3]][$k]['price'] += $v['member_goods_price'] * $v['goods_num'];
                }
                $extend_cat_id = M('goods')->where('goods_id', $v['goods_id'])->getField('extend_cat_id');
                if ($extend_cat_id) {
                    $extend_category = M('GoodsCategory')->where('id', $extend_cat_id)->getField('parent_id_path');
                    $extend_category = explode('_', $extend_category);
                    if (isset($extend_category[1])) {
                        $this->categoryList[$extend_category[1]][$k]['goods_nature'] = $goodsNature;
                        $this->categoryList[$extend_category[1]][$k]['price'] += $v['member_goods_price'] * $v['goods_num'];
                    }
                    if (isset($extend_category[2])) {
                        $this->categoryList[$extend_category[2]][$k]['goods_nature'] = $goodsNature;
                        $this->categoryList[$extend_category[2]][$k]['price'] += $v['member_goods_price'] * $v['goods_num'];
                    }
                    if (isset($extend_category[3])) {
                        $this->categoryList[$extend_category[3]][$k]['goods_nature'] = $goodsNature;
                        $this->categoryList[$extend_category[3]][$k]['price'] += $v['member_goods_price'] * $v['goods_num'];
                    }
                }
            }
            // 处理分类数组
            $categoryList = [];
            foreach ($this->categoryList as $key => $value) {
                foreach ($value as $v) {
                    if (isset($categoryList[$key][$v['goods_nature']])) {
                        $categoryList[$key][$v['goods_nature']] += $v['price'];
                    } else {
                        $categoryList[$key][$v['goods_nature']] = $v['price'];
                    }
                }
            }
            $enable_list = [];
            foreach ($this->activityList as $k => $v) {
                if ($v['reward']) {
                    $count = count($v['reward']) - 1;
                    $v['price'] = $v['reward'][$count]['money'];
                    if ($v['goods_nature'] == 0) {
                        // 全场通用活动
                        $allCategoryPrice = 0;
                        foreach ($categoryList[0] as $value) {
                            $allCategoryPrice += $value;
                        }
                        if (0 == $v['cat_id'] && $v['price'] <= $allCategoryPrice) {
                            $enable_list[] = $v;
                            continue;
                        }
                        // 对应分类且可行的活动
                        $enable_cat = explode(',', $v['cat_id']);
                        $enable_cat_2 = explode(',', $v['cat_id_2']);
                        $enable_cat_3 = explode(',', $v['cat_id_3']);
                        if ($enable_cat_2) {
                            foreach ($enable_cat_2 as $c2k => $c2v) {
                                if ($c2v > 0) {
                                    unset($enable_cat[$c2k]);
                                }
                                if ($c2v == 0) {
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
                                if ($c3v == 0) {
                                    unset($enable_cat_3[$c3k]);
                                }
                            }
                        }
                        $enable_cat = array_merge($enable_cat, $enable_cat_2, $enable_cat_3);
                        $price = 0;
                        foreach ($categoryList as $ck => $cv) {
                            if (in_array($ck, $enable_cat)) {
                                foreach ($cv as $cvv) {
                                    $price += $cvv;
                                }
                                if ($price >= $v['price']) {
                                    $enable_list[] = $v;
                                    break;
                                }
                            }
                        }
                    } else {
                        // 对应分类且可行的活动
                        $enable_cat = explode(',', $v['cat_id']);
                        $enable_cat_2 = explode(',', $v['cat_id_2']);
                        $enable_cat_3 = explode(',', $v['cat_id_3']);
                        if ($enable_cat_2) {
                            foreach ($enable_cat_2 as $c2k => $c2v) {
                                if ($c2v > 0) {
                                    unset($enable_cat[$c2k]);
                                }
                                if ($c2v == 0) {
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
                                if ($c3v == 0) {
                                    unset($enable_cat_3[$c3k]);
                                }
                            }
                        }
                        $enable_cat = array_merge($enable_cat, $enable_cat_2, $enable_cat_3);
                        $price = 0;
                        foreach ($categoryList as $ck => $cv) {
                            foreach ($cv as $ckk => $cvv) {
                                if ($v['goods_nature'] == $ckk && in_array($ck, $enable_cat)) {
                                    $price += $cvv;
                                }
                            }
                            if ($price >= $v['price']) {
                                $enable_list[] = $v;
                                break;
                            }
                        }
                    }
                }
            }
            if (empty($enable_list)) {
                return [];
            }
            foreach ($enable_list as $k => $v) {
                if ($v['reward']) {
                    foreach ($v['reward'] as $rk => $rv) {
                        $canReward = false;
                        if ($v['is_usual'] == 1 && $rv['money'] <= $this->money) {
                            $canReward = true;
                        } elseif ($v['is_usual'] == 0 && $rv['money'] <= bcsub($this->money, bcadd($this->promGoodsMoney, $this->flashSaleGoodsMoney, 2), 2)) {
                            $canReward = true;
                        }
                        if ($canReward) {
                            // 符合奖励条件
                            $goods_list[] = [
                                'reward_id' => $rv['reward_id'],
                                'reward_money' => $rv['money'],
                                'description' => $rv['description'],
                                'goods_nature' => $v['goods_nature'],
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
                            $rewardGet[] = [
                                'goods_nature' => $v['goods_nature'],
                                'money' => $rv['money'],
                                'goods_id' => $v['goods_id']
                            ];
                            if (isset($rewardMaxSet[$v['goods_nature']]) && $rewardMaxSet[$v['goods_nature']] < $rv['money']) {
                                $rewardMaxSet[$v['goods_nature']] = $rv['money'];
                            } else {
                                $rewardMaxSet[$v['goods_nature']] = $rv['money'];
                            }
                            break;
                        }
                    }
                }
            }
        }
        foreach ($rewardGet as $k => $reward) {
            if (isset($rewardMaxSet[$reward['goods_nature']]) && $reward['money'] < $rewardMaxSet[$reward['goods_nature']]) {
                unset($rewardGet[$k]);
            }
        }
        // 相同规格奖励商品数量累加,商品数据处理
        if ($goods_list) {
            $arr = [];
            foreach ($goods_list as $lk => $lv) {
                /*
                 * 1.不同金额设置，不同（相同）赠品，不叠加
                 * 2.相同金额设置，不同（相同）赠品，可叠加
                 */
                foreach ($rewardGet as $reward) {
                    if ($lv['goods_nature'] == $reward['goods_nature'] && $lv['reward_money'] != $reward['money']) {
                        continue 2;
                    }
                }
                if (isset($arr[$lv['goods_id'] . '-' . $lv['item_id']])) {
                    $arr[$lv['goods_id'] . '-' . $lv['item_id']]['goods_num'] += $lv['goods_num'];
                } else {
                    $arr[$lv['goods_id'] . '-' . $lv['item_id']] = $lv;
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
                $buyGoods = $cartLogic->buyNow(false, false, true);
                $buyGoods['gift_reward_id'] = $av['reward_id'];
                $buyGoods['gift_description'] = $av['description'];
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
                    'buy_num' => ['exp', 'buy_num+' . $rv['reward_num']],
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
                        'buy_num' => ['exp', 'buy_num-' . $v['reward_num']],
                    ]);
                }
            }
        }
    }
}
