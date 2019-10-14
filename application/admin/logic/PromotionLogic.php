<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

use app\common\model\Gift;
use app\common\model\Promotion;
use think\Db;
use think\Exception;

class PromotionLogic
{
    private $model;
    private $giftModel;
    public $giftCount = 0;
    public $error = '';

    public function __construct()
    {
        $this->model = new Promotion();
        $this->giftModel = new Gift();
    }

    public function getGiftList()
    {
        $list = $this->giftModel->with(['goods', 'specGoodsPrice', 'reward'])->select();
        foreach ($list as $k => $v) {
            $buy_num = 0;
            if ($v['reward']) {
                foreach ($v['reward'] as $rv) {
                    $buy_num += $rv['buy_num'];
                }
            }
            $list[$k]['total_buy_num'] = $buy_num;
        }

        return $list;
    }

    public function getGiftCount()
    {
        if (!$this->giftCount) {
            $this->giftCount = $this->giftModel->count();
        }

        return $this->giftCount;
    }

    /**
     * 新增赠品活动.
     *
     * @param $data
     *
     * @return bool
     */
    public function giftStore($data)
    {

        Db::startTrans();
        try {
            if (0 == $data['type']) {
                $data['cat_id'] = [0];
                $data['cat_id_2'] = [0];
                $data['cat_id_3'] = [0];
            }
            $this->giftModel->save($data);
            $gift_id = $this->giftModel->id;
            $success_num = true;
            if (!empty($data['reward'])) {
                foreach ($data['reward'] as $k => $v) {
                    $data['reward'][$k]['gift_id'] = $gift_id;
                }
                $success_num = Db::name('gift_reward')->strict(false)->insertAll($data['reward']);
            }
            if (!$gift_id || !$success_num) {
                throw new Exception('服务器错误,新增赠品失败.');
            }
        } catch (Exception $e) {
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }

    /**
     * 删除赠品活动.
     *
     * @param $id
     *
     * @return bool
     */
    public function giftDelete($id)
    {
        Db::startTrans();
        try {
            $this->giftModel->where('id', $id)->delete();
            Db::name('gift_reward')->where('gift_id', $id)->delete();
        } catch (Exception $e) {
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }

    public function getGiftLogById($id)
    {
        return Db::name('gift_log')
            ->field('gl.*,g.goods_name')
            ->alias('gl')
            ->join('__GIFT__ g', 'g.id = gl.gift_id', 'LEFT')
            ->where('gift_id', $id)
            ->order('id desc')
            ->select();
    }

    /**
     * 获取赠品活动详情.
     *
     * @param $id
     *
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getGiftById($id)
    {
        return $this->giftModel->with(['reward' => function ($query) {
            $query->order('money');
        }])->where('id', $id)->find();
    }

    /**
     * 更新赠品活动.
     *
     * @param $data
     *
     * @return bool
     */
    public function giftUpdate($data)
    {
        Db::startTrans();
        try {
            $gift_id = $data['id'];
            $this->giftModel->update($data);
            $reward = I('reward/a');

            if ($reward) {
                $rewardArr = M('GiftReward')->where("gift_id = $gift_id")->getField('reward_id,money,reward_num,description'); // 查出所有已经存在的图片

                foreach ($rewardArr as $key => $val) {
                    if (!in_array($val, $reward)) {
                        M('GiftReward')->where("reward_id = {$key}")->delete();
                    }
                }

                foreach ($reward as $key => $val) {
                    if (null == $val) {
                        continue;
                    }
                    if (!in_array($val, $rewardArr)) {
                        $val['gift_id'] = $gift_id;
                        M('GiftReward')->insert($val);
                    }
                }
            } else {
                M('GiftReward')->where("gift_id = {$gift_id}")->delete();
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }

    public function getById($id)
    {
        return $this->model->with(['PromotionReward' => function ($query) {
            $query->order('reward_id');
        }, 'goods', 'specGoodsPrice'])->find($id);
    }

    public function store($data)
    {
        return $this->model->update($data);
    }

    public function afterSave($Promotion_id)
    {
        // 超值套组
        $reward = I('reward/a');

        if ($reward) {
            $rewardArr = M('PromotionReward')->where("Promotion_id = $Promotion_id")->getField('reward_id,buy_num,store_count,reward_interval,reward_type'); // 查出所有已经存在的图片

            foreach ($rewardArr as $key => $val) {
                if (!in_array($val, $reward)) {
                    M('PromotionReward')->where("reward_id = {$key}")->delete();
                }
            }

            $clounm = 'order_num';

            if (2 == $Promotion_id) {
                $clounm = 'invite_num';
            }
            $Promotion = M('Promotion')->find($Promotion_id);
            foreach ($reward as $key => $val) {
                if (null == $val) {
                    continue;
                }
                if (!in_array($val, $rewardArr)) {
                    $val['Promotion_id'] = $Promotion_id;
                    M('PromotionReward')->insert($val); // 实例化User对象

                    // 为目前正在进行的用户任务进行修改
                    $user_Promotion = M('UserPromotion')
                          ->where('Promotion_reward_id', $val['reward_id'])
                          ->where('status', 'neq', 1)
                          ->where('Promotion_id', $Promotion_id)
                          ->select();
                    if ($user_Promotion) {
                        foreach ($user_Promotion as $k => $v) {
                            $update = [];
                            $update['target_num'] = $val[$clounm];
                            // $update['Promotion_reward_desc'] = $val['description'];
                            if ($val[$clounm] <= $v['finish_num']) {
                                $update['status'] = 1;
                                $update['finished_at'] = time();
                                $order_sn_list = explode(',', $v['order_sn_list']);
                                $order_sn = end($order_sn_list);

                                $reward_price = '0.00';
                                $reward_num = 0;
                                $reward_coupon_id = 0;
                                if (1 == $val['reward_type']) {
                                    $reward_num = $val['reward_num'];
                                } elseif (2 == $val['reward_type']) {
                                    $reward_price = $val['reward_price'];
                                } else {
                                    $reward_coupon_id = $val['reward_coupon_id'];
                                }
                                PromotionLog($v['user_id'], $Promotion, $val, $order_sn, $reward_price, $reward_num, 1, 0, $reward_coupon_id, $v['id']);
                            }
                            M('UserPromotion')->where('id', $v['id'])->update($update);
                        }
                    }
                }
            }
        } else {
            M('PromotionReward')->where("Promotion_id = {$Promotion_id}")->delete();
        }
    }
}
