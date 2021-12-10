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

    /**
     * 设置查询时间
     * @param null $year string eg:2021
     * @param null $month string eg:2021-01
     * @param null $day string eg:2021-01-02
     * @return $this
     */
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

    public function resetWhere(){
        $this->where = [];
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

    /**
     * 根据代数获取用户PV
     * @param $chainNumber
     * @return bool|false|\PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
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

    /**
     * 获取公司用户近两个月的伞下业绩汇总
     * @param $time
     * @return bool|false|\PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserNearly2MonthPv($time){
        $whereIn = [
            strtotime(date('Y-m',$time).' -1 month'),
            strtotime(date('Y-m',$time).' -2 month'),
        ];
        return Db::name("users u")->join('user_order_pv_log pv','u.user_id=pv.chain_user_id','LEFT')
            ->group('pv.chain_user_id,pv.month')
            ->whereIn('month',$whereIn)
            ->field("u.user_id,pv.year,pv.month,pv.day,SUM(pv.pv) total_pv,SUM(pv.team_pv) total_team_pv")
            ->select();
    }

    /**
     * 新增业绩记录
     * @param $userId
     * @param $pv
     * @param int $orderPrice
     * @param int $orderId
     * @param null $addTime
     * @return bool|int|string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function addLog(
        $userId,
        $pv,
        $orderPrice = 0,
        $orderId = 0,
        $orderSn = "",
        $addTime = null
    ){
        $time = $addTime?$addTime:time();
        $chain = Db::name('users u')->join('user_chain chain','u.user_id=chain.user_id')
            ->where('u.user_id',$userId)
            ->field('u.distribut_level,u.user_name,u.mobile,u.user_id,chain.referee_ids,u.first_leader,u.second_leader,u.third_leader')
            ->find();
        if (!$chain){
            return false;
        }

        $insert = [];

        $base = [
            'order_id'    => $orderId,
            'user_id'     => $userId,
            'user_level'  => $chain['distribut_level'],
            'user_mobile' => $chain['mobile'],
            'user_name'   => $chain['user_name'],
            'order_price' => $orderPrice,
            'order_sn'    => $orderSn?$orderSn:'',
            'add_time'    => $time,
            'year'        => date('Y', $time),
            'month'       => date('Y-m', $time),
            'day'         => date('Y-m-d', $time),
        ];
        $refereeChain = trim(trim($chain['referee_ids']),',');

        $userSelf = [
            'chain_user_id'         => $userId,
            'chain_user_level'      => $chain['distribut_level'],
            'chain_user_mobile'     => $chain['mobile'],
            'chain_user_name'       => $chain['user_name'],
            'pv'                    => $pv,
            'team_pv'               => $pv,
            'chain_user_generation' => 0,
            'parent_chain'          => $chain['referee_ids'],
            'first_leader'          => $chain['first_leader'],
            'second_leader'         => $chain['second_leader'],
            'third_leader'          => $chain['third_leader'],
        ];

        $insert[] = array_merge($base,$userSelf);

        if (empty($refereeChain)){
            return Db::name('user_order_pv_log')->insertAll($insert);
        }
        $refereeChain = array_reverse(explode(',',$refereeChain));
        $chainUserData = Db::name('users u')
            ->join('user_chain chain','u.user_id=chain.user_id')
            ->whereIn('u.user_id',$refereeChain)
            ->column(
                'u.distribut_level,u.user_name,u.mobile,u.user_id,chain.referee_ids,u.first_leader,u.second_leader,u.third_leader',
                'u.user_id'
            );
        dump($refereeChain);
        foreach ($refereeChain as $chainItem){
            $chainUser = [
                'chain_user_id'         => $chainItem,
                'chain_user_level'      => $chainUserData[$chainItem]['distribut_level'],
                'chain_user_mobile'     => $chainUserData[$chainItem]['mobile'],
                'chain_user_name'       => $chainUserData[$chainItem]['user_name'],
                'pv'                    => 0,
                'team_pv'               => $pv,
                'chain_user_generation' => count($insert),
                'parent_chain'          => $chainUserData[$chainItem]['referee_ids'],
                'first_leader'          => $chainUserData[$chainItem]['first_leader'],
                'second_leader'         => $chainUserData[$chainItem]['second_leader'],
                'third_leader'          => $chainUserData[$chainItem]['third_leader'],
            ];
            $insert[] = array_merge($base,$chainUser);
        }
        return Db::name('user_order_pv_log')->insertAll($insert);
    }
}