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

use think\Validate;

/**
 * Description of Article.
 *
 * @author Administrator
 */
class Extra extends Validate
{
    //验证规则
    protected $rule = [
        'title' => 'require|checkEmpty',
        'price' => 'require|checkEmpty',
        'start_time' => 'require',
        'end_time' => 'require|checkEmpty|checkEndTime',
        'reward' => 'checkReward',
    ];

    //错误消息
    protected $message = [
        'title' => '活动标题不能为空',
        'price' => '价格不能为空',
        'start_time' => '开始时间不能为空',
        'end_time' => '结束时间不能为空',
        'end_time.checkEndTime' => '结束时间不能早于开始时间',
        'reward.checkReward' => '无法选择无库存或参与活动的商品作为加价购商品',
    ];

    protected function checkEmpty($value)
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * 检查结束时间.
     *
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     *
     * @return bool|string
     */
    protected function checkEndTime($value, $rule, $data)
    {
        return ($value < $data['start_time']) ? false : true;
    }

    /**
     * 检查加价购商品设置
     * @param $value
     * @return bool
     */
    protected function checkReward($value)
    {
        if (!empty($value)) {
            foreach ($value as $v) {
                $goods_info = M('goods')->field('store_count,prom_id,goods_name,goods_id')->where('goods_id', $v['goods_id'])->find();
                if ($goods_info['store_count'] < 1) {
                    return 'goods_id：'.$goods_info['goods_id'].'库存不足';
                }
                if (empty($v['reward_id']) && $goods_info['prom_id'] > 0) {
                    return 'goods_id：'.$goods_info['goods_id'].'参加活动';
                }
            }
        }
        return true;
    }
}
