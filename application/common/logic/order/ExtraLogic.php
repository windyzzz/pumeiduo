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

use app\common\logic\ExtraLogic as ExtraService;
use app\common\util\TpshopException;

class ExtraLogic
{
    const SELF_CAT = 882;

    private $activityList;
    private $order;
    private $money;
    private $reward_info;
    private $user_id;
    private $goodsList;
    private $categoryList;

    public function __construct()
    {
        $extraService = new ExtraService();
        $this->activityList = $extraService->getAvailable();
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

    public function setRewardInfo($reward_info)
    {
        $this->reward_info = $reward_info;
    }

    /**
     * 获得符合条件奖励，并做上奖励记录.
     *
     * @return array
     *
     * @throws TpshopException
     */
    public function getGoodsList()
    {
        $goods_list = [];
        if ($this->activityList && $this->goodsList) {
            // 1.先找出所有的商品的分类，相应信息存放在categoryList数组中
            foreach ($this->goodsList as $v) {
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
                if ($extend_cat_id) {
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
//                    $this->categoryList[0] += $v['member_goods_price'] * $v['goods_num'];
                }

            }

            $enable_list = [];
            foreach ($this->activityList as $k => $v) {
                if ($v['extra_reward']) {
                    // 优先找全场通用的加价购活动

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


            if (empty($enable_list)) {
                return [];
            }
            // 在可用的活动中根据 优先级顺序排序 优先级顺序（活动价格，是否全场，是否自营，开始时间）
            $activity = [];
            $length = count($enable_list);

            if ($length >= 2) {
                for ($i = 0; $i < $length; ++$i) {
                    for ($j = $i + 1; $j < $length; ++$j) {
                        if ($enable_list[$i]['price'] < $enable_list[$j]['price']) {
                            $a = $enable_list[$j];
                            $enable_list[$j] = $enable_list[$i];
                            $enable_list[$i] = $a;
                        }
                    }
                    if (1 == $i) {
                        break;
                    }
                }
                $activity = $enable_list[0];
                if ($enable_list[0]['price'] == $enable_list[1]['price']) { // 价格相等比较是否全场，全场优先
                    if ($enable_list[0]['cat_id'] == $enable_list[1]['cat_id']) { // 是否自营相等比较开始时间，开始时间较前的优先
                        if ($enable_list[0]['start_time'] == $enable_list[1]['start_time']) {
                            throw new TpshopException('计算订单价格', 0, ['status' => -50, 'msg' => '活动数据错误,无法确定活动', 'result' => '']);
                        }
                        if ($enable_list[0]['start_time'] > $enable_list[1]['start_time']) {
                            $activity = $enable_list[1];
                        }
                    } else {
                        if (0 == $enable_list[0]['cat_id']) {
                            $activity = $enable_list[0];
                        } elseif (0 == $enable_list[1]['cat_id']) {
                            $activity = $enable_list[1];
                        } else { // 是否全场相等比较自营，自营优先
                            $self_cat_arr = getCatGrandson(self::SELF_CAT);

                            $is_enable_cat = explode(',', $enable_list[0]['cat_id']);
                            $is_enable_cat_2 = explode(',', $enable_list[0]['cat_id_2']);
                            $is_enable_cat_3 = explode(',', $enable_list[0]['cat_id_3']);
                            if ($is_enable_cat_2) {
                                foreach ($is_enable_cat_2 as $c2k => $c2v) {
                                    if ($c2v > 0) {
                                        unset($is_enable_cat[$c2k]);
                                    }
                                    if ($c2v == 0) {
                                        unset($is_enable_cat_2[$c2k]);
                                    }
                                }
                            }

                            if ($is_enable_cat_3) {
                                foreach ($is_enable_cat_3 as $c3k => $c3v) {
                                    if ($c3v > 0) {
                                        unset($is_enable_cat[$c3k]);
                                        unset($is_enable_cat_2[$c3k]);
                                    }
                                    if ($c3v == 0) {
                                        unset($is_enable_cat_3[$c3k]);
                                    }
                                }
                            }
                            $is_enable_cat = array_merge($is_enable_cat, $is_enable_cat_2, $is_enable_cat_3);

                            $ss_enable_cat = explode(',', $enable_list[1]['cat_id']);
                            $ss_enable_cat_2 = explode(',', $enable_list[1]['cat_id_2']);
                            $ss_enable_cat_3 = explode(',', $enable_list[1]['cat_id_3']);

                            if ($ss_enable_cat_2) {
                                foreach ($ss_enable_cat_2 as $c2k => $c2v) {
                                    if ($c2v > 0) {
                                        unset($ss_enable_cat[$c2k]);
                                    }
                                }
                            }

                            if ($ss_enable_cat_3) {
                                foreach ($ss_enable_cat_3 as $c3k => $c3v) {
                                    if ($c3v > 0) {
                                        unset($ss_enable_cat[$c3k]);
                                        unset($ss_enable_cat_2[$c3k]);
                                    }
                                }
                            }
                            $ss_enable_cat = array_merge($ss_enable_cat, $ss_enable_cat_2, $ss_enable_cat_3);

                            foreach ($self_cat_arr as $v) {
                                if (!in_array($v, $is_enable_cat) && in_array($v, $ss_enable_cat)) {
                                    $activity = $enable_list[1];
                                } else { // 是否自营相等比较开始时间，开始时间较前的优先
                                    if ($enable_list[0]['start_time'] > $enable_list[1]['start_time']) {
                                        $activity = $enable_list[1];
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $activity = $enable_list[0];
            }

            // 相同规格奖励商品数量累加,商品数据处理
            $goods_list = [];
            if ($activity['extra_reward']) {
                $arr = [];
                foreach ($activity['extra_reward'] as $ak => $av) {
                    $goods_info = M('goods')->field('goods_id,goods_name,goods_remark,store_count,original_img,shop_price as goods_price,exchange_integral')->where('goods_id', $av['goods_id'])->find();
//                    $goods_info['goods_price'] = $av['goods_price'];
//                    $goods_info['goods_price'] = $goods_info['goods_price'] - $goods_info['exchange_integral'];
                    $goods_info['goods_num'] = $av['goods_num'];
                    $goods_info['buy_limit'] = $av['buy_limit'];
                    $goods_info['prom_type'] = 6;
                    $goods_info['prom_id'] = $av['extra_id'];
                    $goods_info['extra_reward_id'] = $av['reward_id'];
                    $goods_info['extra_title'] = $activity['title'];
                    $goods_info['num'] = 1;
                    $goods_info['can_integral'] = $av['can_integral'];
                    $arr[$ak] = $goods_info;
                }
                $goods_list = $arr;
            }

            // 返回奖励商品列表
            return $goods_list;
        }

        return [];
    }

    // 记录
    public function record()
    {
        if ($this->reward_info) {
            foreach ($this->reward_info as $rk => $rv) {
                $this->reward_info[$rk]['order_sn'] = $this->order['order_sn'];
                $this->reward_info[$rk]['created_at'] = time();
                M('extra_log')->insert($this->reward_info[$rk]);
            }
        }
        if (0 == $this->order['order_amount']) {
            $this->doOrderPayAfter();
        }
    }

    public function returnReward()
    {
        if ($this->order) {
            $list = M('extra_log')->where('order_sn', $this->order['order_sn'])->select();
            if ($list) {
                foreach ($list as $v) {
                    M('extra_log')->where('id', $v['id'])->update(['status' => 2]);
                    if ($this->order['pay_status'] > 0) {
                        M('extra_reward')->where('reward_id', $v['extra_reward_id'])->update([
                            'buy_num' => ['exp', 'buy_num-' . $v['reward_num']],
                        ]);
                    }
                }
            }
        }
    }

    public function doOrderPayAfter()
    {
        if ($this->order) {
            $list = M('extra_log')->where('order_sn', $this->order['order_sn'])->select();
            if (!empty($list)) {
                foreach ($list as $v) {
                    M('extra_log')->where('id', $v['id'])->update(['status' => 1]);
                    M('extra_reward')->where('reward_id', $v['extra_reward_id'])->update([
                        'buy_num' => ['exp', 'buy_num+' . $v['reward_num']],
                    ]);
                }
            }
        }
    }
}
