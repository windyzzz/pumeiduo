<?php

namespace app\admin\validate;

use think\Validate;

class Ad extends Validate
{
    // 验证规则
    protected $rule = [
        ['show_path', 'require'],
        ['start_time', 'require|checkStartTime'],
        ['end_time', 'require'],
    ];
    // 错误信息
    protected $message = [
        'show_path.require' => '请上传弹窗图片',
        'start_time.require' => '请选择开始时间',
        'start_time.checkStartTime' => '开始时间必须小于结束时间',
        'end_time.require' => '请选择结束时间',
    ];
    // 验证场景
    protected $scene = [
        'popup' => [
            'show_path' => 'require',
            'start_time' => 'require|checkStartTime',
            'end_time' => 'require',
        ],
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
    protected function checkStartTime($value, $rule, $data)
    {
        return ($value >= $data['end_time']) ? false : true;
    }
}
