<?php

namespace app\common\model;

use think\Model;

class CateActivity extends Model
{
    /*
     * 状态描述
     */
    public function getStatusDescAttr($value, $data)
    {
        if (1 == $data['is_end']) {
            return '已结束';
        }
        if ($data['is_open'] == 0) {
            return '已暂停';
        }
        if ($data['start_time'] > time()) {
            return '未开始';
        } elseif ($data['start_time'] < time() && $data['end_time'] > time()) {
            return '进行中';
        }

        return '已过期';
    }
}
