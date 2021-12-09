<?php

namespace app\common\logic\pv;

use think\Db;

class UserOrderPvLogic
{
    public function getPvByUserLevel($userLevel,$year = null,$month = null, $day = null){

        $group = ['chain_user_id'];
        $where = [
            'level' => $userLevel
        ];

        if (!empty($year)){
            $group[] = 'year';
            $where['year'] = $day;
        }
        if (!empty($month)){
            $group[] = 'month';
            $where['month'] = $day;
        }
        if (!empty($day)){
            $group[] = 'day';
            $where['day'] = $day;
        }

        return Db::name("users u")->join('user_order_pv_log pv','u.user_id=pv.chain_user_id','LEFT')
            ->group(implode(",",$group))
            ->where($where)
            ->field("u.user_id,pv.year,pv.month,pv.day,SUM(pv.pv) total_pv,SUM(pv.team_pv) total_team_pv")
            ->select();
    }
}