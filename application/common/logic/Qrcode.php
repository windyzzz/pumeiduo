<?php

namespace app\common\logic;


use think\Exception;

class Qrcode
{
    /**
     * 获取扫码信息处理
     * @param $code
     * @param $userId
     * @return array
     */
    public function getInfo($code, $userId)
    {
        if (preg_match('/^PQ[0-9]{8}$/', $code)) {
            /*
             * 扫码优惠活动
             */
            $prom = M('prom_qrcode')->where(['code' => $code, 'is_del' => 0])->find();
            if (!$prom) throw new Exception('活动不存在');
            if ($prom['is_open'] != 1) throw new Exception('活动未开启');
            if ($prom['start_time'] > NOW_TIME) throw new Exception('活动未开始');
            if ($prom['end_time'] < NOW_TIME) throw new Exception('活动已结束');
            // 扫码记录判断
            if (M('prom_qrcode_log')->where(['user_id' => $userId, 'prom_id' => $prom['id']])->find()) {
                throw new Exception('您已经领取过了');
            }
            $couponList = [];
            $electronic = '0';
            switch ($prom['reward_type']) {
                case 1:
                    // 发放优惠券
                    $couponIds = $prom['reward_content'];
                    $couponLogic = new CouponLogic();
                    $res = $couponLogic->receive($couponIds, $userId, false, true);
                    if ($res['status'] == 0) {
                        throw new Exception($res['msg']);
                    }
                    $couponGetList = M('coupon')->where([
                        'send_start_time' => array('elt', NOW_TIME),
                        'send_end_time' => array('egt', NOW_TIME),
                        'id' => ['IN', $couponIds]
                    ])->select();
                    foreach ($couponGetList as $coupon) {
                        $res = $couponLogic->couponTitleDesc($coupon);
                        if (empty($res)) continue;
                        $couponList[] = [
                            'coupon_id' => $coupon['id'],
                            'use_type_desc' => $res['use_type_desc'],
                            'money' => floatval($coupon['money']) . '',
                            'title' => $coupon['name'],
                            'use_start_time' => date('Y.m.d', $coupon['use_start_time']),
                            'use_end_time' => date('Y.m.d', $coupon['use_end_time']),
                        ];
                    }
                    break;
                case 2:
                    // 发放电子币
                    $electronic = $prom['reward_content'];
                    accountLog($userId, 0, 0, '扫码优惠活动奖励', 0, 0, '', $electronic, 26);
                    break;
            }
            // 扫码记录
            M('prom_qrcode_log')->add([
                'user_id' => $userId,
                'prom_id' => $prom['id'],
                'reward_type' => $prom['reward_type'],
                'reward_content' => $prom['reward_content'],
                'add_time' => NOW_TIME
            ]);
            $return = [
                'reward_type' => $prom['reward_type'],
                'coupon' => [
                    'count' => count($couponList),
                    'list' => $couponList
                ],
                'electronic' => $electronic
            ];
            return ['status' => 1, 'result' => $return];
        } else {
            throw new Exception('兑换码错误');
        }
    }
}
