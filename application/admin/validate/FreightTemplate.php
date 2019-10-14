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

class FreightTemplate extends Validate
{
    // 验证规则
    protected $rule = [
        ['template_name', 'require|max:10|unique:freight_template'],
        ['type', 'require'],
        ['config_list', 'require|checkConfigList'],
    ];
    //错误信息
    protected $message = [
        'template_name.require' => '请填写模板名称',
        'template_name.max' => '模板名称不能超过10个字符',
        'template_name.unique' => '已有重名的模板名称',
        'type.require' => '请选择计价方式',
        'config_list.require' => '请添加配送区域',
    ];

    /**
     * 检查用户使用时间.
     *
     * @param $value
     * @param $rule
     * @param $data
     *
     * @return bool
     */
    protected function checkConfigList($value, $rule, $data)
    {
        $config_list_length = count($value);
        if (0 == $data['type']) {
            for ($i = 0; $i < $config_list_length; ++$i) {
                if (0 == ((int) $value[$i]['first_unit']) || 0 == ((int) $value[$i]['continue_unit'])) {
                    return '件数必须大于0';
                }
            }
        }
        $arr_recursive = [];
        for ($i = 0; $i < $config_list_length; ++$i) {
            if (!empty($value[$i]['area_ids'])) {
                $temp = explode(',', $value[$i]['area_ids']);
                $arr_recursive = array_merge($temp, $arr_recursive);
            }
        }
        $arr_recursive_length = count($arr_recursive);
        $arr_unique = array_unique($arr_recursive);
        $arr_unique_length = count($arr_unique);
        if ($arr_recursive_length != $arr_unique_length) {
            return '配送区域存在重复区域';
        }

        return true;
    }
}
