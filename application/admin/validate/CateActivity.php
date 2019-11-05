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

class CateActivity extends Validate
{
    // 验证规则
    protected $rule = [
        ['title', 'require'],
        ['slogan', 'require'],
        ['banner', 'require'],
        ['goods', 'require'],
        ['start_time', 'require'],
        ['end_time', 'require|checkEndTime'],
    ];

    // 错误信息
    protected $message = [
        'title.require' => '活动标题必须',
        'slogan.require' => '活动标语必须',
        'banner.require' => '横幅必须',
        'goods.require' => '商品必须',
        'start_time.require' => '请选择开始时间',
        'end_time.require' => '请选择结束时间',
        'end_time.checkEndTime' => '结束时间不能早于开始时间',
    ];

    /**
     * 检查结束时间
     *
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     *
     * @return bool|string
     */
    protected function checkEndTime($value, $rule, $data)
    {
        return ($value < $data['start_time']) ? false : true;
    }

    /**
     * 该活动是否可以编辑.
     *
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     *
     * @return bool|string
     */
    protected function checkId($value, $rule, $data)
    {
        $isHaveOrder = Db::name('order_goods')->where(['prom_type' => 2, 'prom_id' => $value])->find();
        if ($isHaveOrder) {
            return '该活动已有用户下单购买不能编辑';
        }

        return true;
    }
}
