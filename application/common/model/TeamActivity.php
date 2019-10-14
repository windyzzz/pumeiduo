<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\model;

use think\Model;
use think\Request;

class TeamActivity extends Model
{
    public function specGoodsPrice()
    {
//        return $this->hasOne('specGoodsPrice','item_id','item_id')->bind(['price']);
        return $this->hasOne('specGoodsPrice', 'item_id', 'item_id');
    }

    public function goods()
    {
        return $this->hasOne('goods', 'goods_id', 'goods_id')->bind(['shop_price']);
    }

    public function teamFound()
    {
        return $this->hasMany('teamFound', 'team_id', 'team_id');
    }

    public function getTeamTypeDescAttr($value, $data)
    {
        $status = config('TEAM_TYPE');

        return $status[$data['team_type']];
    }

    public function getTimeLimitHoursAttr($value, $data)
    {
        return $data['time_limit'] / 3600;
    }

    //分享链接
    public function getBdUrlAttr($value, $data)
    {
        return U('Mobile/Team/info', ['goods_id' => $data['goods_id'], 'team_id' => $data['team_id']], '', true);
    }

    public function getBdPicAttr($value, $data)
    {
        $request = Request::instance();

        return $request->domain().$data['share_img'];
    }

    public function getLotteryUrlAttr($value, $data)
    {
        return U('Mobile/Team/lottery', ['team_id' => $data['team_id']], '', true);
    }

    public function getStatusDescAttr($value, $data)
    {
        $status = ['关闭', '启用'];

        return $status[$data['status']];
    }

    public function getVirtualSaleNumAttr($value, $data)
    {
        return $data['virtual_num'] + $data['sales_sum'];
    }

    /**
     * 前台显示拼团详情.
     */
    public function getFrontStatusDescAttr($value, $data)
    {
        if (1 != $data['status']) {
            return '活动未上架';
        }
        if (2 == $data['team_type']) {
            if (1 == $data['is_lottery']) {
                return '已开奖';
            }

            return '拼团中';
        }

        return '拼团中';
    }

    public function setTimeLimitAttr($value, $data)
    {
        return $value * 3600;
    }

    public function setBonusAttr($value, $data)
    {
        return (1 != $data['team_type']) ? 0 : $value;
    }

    public function setBuyLimitAttr($value, $data)
    {
        return (2 == $data['team_type']) ? 1 : $value;
    }
}
