<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

use think\Db;
use think\Model;

/**
 * 拼团活动逻辑类.
 */
class TeamActivityLogic extends Model
{
    protected $team; //拼团模型
    protected $teamFound; //团长模型

    public function setTeam($team)
    {
        $this->team = $team;
    }

    public function setTeamFound($teamFound)
    {
        $this->teamFound = $teamFound;
    }

    /**
     * 抽奖.
     *
     * @return array
     *
     * @throws \think\Exception
     */
    public function lottery()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 拼团退款.
     *
     * @return array
     *
     * @throws \think\Exception
     */
    public function refundFound()
    {
        if (empty($this->teamFound)) {
            return ['status' => 0, 'msg' => '找不到拼单', 'result' => ''];
        }
        if (empty($this->teamFound->order)) {
            return ['status' => 0, 'msg' => '找不到拼单的订单', 'result' => ''];
        }
        if (3 != $this->teamFound->status) {
            return ['status' => 0, 'msg' => '拼单状态不符合退款需求', 'result' => ''];
        }
        if (0 == $this->teamFound->order->pay_status) {
            return ['status' => 0, 'msg' => '拼单订单状态不符合退款需求', 'result' => ''];
        }
        $teamOrderId = []; //拼团Order_id集合
        array_push($teamOrderId, $this->teamFound->order_id);
        $teamFollow = $this->teamFound->teamFollow()->where(['status' => 1])->select(); //拼单成功的会员
        if ($teamFollow) {
            $followOrderId = get_arr_column($teamFollow, 'order_id'); //会员拼单成功的order_id
            $teamOrderId = array_merge($teamOrderId, $followOrderId);
        }
        $orderRefund = Db::name('order')->where('order_id', 'IN', $teamOrderId)->update(['order_status' => 3]); //订单取消,平台后台处理退款
        $orderLogic = new OrderLogic();
        $TeamOrderList = Db::name('order')->where('order_id', 'IN', $teamOrderId)->select();
        if ($TeamOrderList) {
            foreach ($TeamOrderList as $orderKey => $orderVal) {
                $orderLogic->orderActionLog($orderVal['order_id'], '取消订单', '拼团退款');
            }
        }
        if (false !== $orderRefund) {
            return ['status' => 1, 'msg' => '拼团退款已提交至平台，坐等审核', 'result' => ''];
        }

        return ['status' => 0, 'msg' => '拼团退款失败', 'result' => ''];
    }
}
