<?php

namespace app\common\logic\pv;

use think\Collection;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class UserOrderPvLogic
{
    protected $where = [];

    protected $group = [];

    public function setDate($year = null,$month = null, $day = null){
        if (!empty($year)){
            $this->group[] = 'year';
            $this->where['year'] = $day;
        }
        if (!empty($month)){
            $this->group[] = 'month';
            $this->where['month'] = $day;
        }
        if (!empty($day)){
            $this->group[] = 'day';
            $this->where['day'] = $day;
        }
        return $this;
    }

    /**
     * 根据用户等级获取所有符合等级的用户小组业绩
     * @param $userLevel
     * @return bool|false|\PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getPvByUserLevel($userLevel){

        $group = array_merge(['chain_user_id'],$this->group);

        $where = array_merge([
            'level' => $userLevel
        ],$this->where);

        return Db::name("users u")->join('user_order_pv_log pv','u.user_id=pv.chain_user_id','LEFT')
            ->group(implode(",",$group))
            ->where($where)
            ->where('chain_user_generation','<=',1)
            ->field("u.user_id,pv.year,pv.month,pv.day,SUM(pv.pv) total_pv,SUM(pv.team_pv) total_team_pv")
            ->select();
    }

    public function getPvByUserChainNumber($chainNumber){
        $group = array_merge(['chain_user_id'],$this->group);

        $where = array_merge([
            'chain_user_generation' => $chainNumber
        ],$this->where);
        return Db::name("users u")->join('user_order_pv_log pv','u.user_id=pv.chain_user_id','LEFT')
            ->group(implode(",",$group))
            ->where($where)
            ->field("u.user_id,pv.year,pv.month,pv.day,SUM(pv.pv) total_pv,SUM(pv.team_pv) total_team_pv")
            ->select();
    }

    /**
     * 查询个人业绩
     * @param $userId
     * @return bool|float|int|string|null
     */
    public function getUserPv($userId){
        $where = array_merge(['chain_user_id'=>$userId,'chain_user_generation'=>0],$this->where);
        return Db::name('user_order_pv_log')->where($where)->sum('pv');
    }
}