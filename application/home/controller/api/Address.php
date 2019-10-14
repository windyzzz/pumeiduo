<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller\api;

use think\Db;

class Address
{
    /*
     * 获取地区
     */
    public function getRegion()
    {
        $parent_id = I('get.parent_id/d', 0);
        $data = M('region2')->where('parent_id', $parent_id)->select();

        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }

    /**
     * 获取省
     */
    public function getProvince()
    {
        $province = Db::name('region2')->field('id,name')->where(['level' => 1])->cache(true)->select();

        return json(['status' => 1, 'msg' => 'success', 'result' => $province]);
    }

    /**
     * 获取市或者区.
     */
    public function getRegionByParentId()
    {
        $parent_id = I('get.parent_id', 0);
        $res = ['status' => 0, 'msg' => '获取失败，参数错误', 'result' => ''];
        if ($parent_id) {
            $region_list = Db::name('region2')->field('id,name')->where(['parent_id' => $parent_id])->select();
            $res = ['status' => 1, 'msg' => '获取成功', 'result' => $region_list];
        }

        return json($res);
    }
}
