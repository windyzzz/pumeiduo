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

class Coupon extends Validate
{
    // 验证规则
    protected $rule = [
        ['type', 'require'],
        ['name', 'require|unique:coupon,name^type'],
//        ['money', 'require'],
//        ['condition', 'require|checkCondition'],
        ['createnum', 'require'],
        ['send_start_time', 'require|checkSendTime'],
        ['send_end_time', 'require'],
        ['use_start_time', 'checkUserTime'],
    ];
    //错误信息
    protected $message = [
        'type.require' => '请选择发放类型',
        'name.require' => '优惠券名称必须',
        'name.unique' => '已有相同类型的优惠券名称',
        'money.require' => '请填写优惠券面额',
        'condition.require' => '请填写消费金额',
        'condition.checkCondition' => '消费金额不能小于或等于优惠券金额',
        'createnum.require' => '请填写发放数量',
        'send_start_time.require' => '请选择发放开始日期',
        'send_start_time.checkSendTime' => '发放结束日期不得小于发放开始日期',
        'send_end_time.require' => '请选择发放结束日期',
        'use_start_time.checkUserTime' => '使用结束日期不得小于使用开始日期',
    ];

    /**
     * 检查发放日期
     *
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     *
     * @return bool|string
     */
    protected function checkSendTime($value, $rule, $data)
    {
        return ($value >= $data['send_end_time']) ? false : true;
    }

    /**
     * 检查用户使用时间.
     *
     * @param $value
     * @param $rile
     * @param $data
     *
     * @return bool
     */
    protected function checkUserTime($value, $rile, $data)
    {
        return ($value >= $data['use_end_time']) ? false : true;
    }

    /**
     * 检查消费金额.
     *
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     *
     * @return bool|string
     */
    protected function checkCondition($value, $rule, $data)
    {
        return ($value < $data['money']) ? false : true;
    }
}
