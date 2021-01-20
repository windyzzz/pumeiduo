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
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function getInfo($code, $userId)
    {
        if (preg_match('/^PQ[0-9]{8}$/', $code)) {
            /*
             * 扫码优惠活动
             */
            $prom = M('prom_qrcode')->where(['code' => $code, 'is_open' => 1, 'is_del' => 0])->find();
            if (!$prom) throw new Exception('活动不存在');
            if ($prom['start_time'] > NOW_TIME) throw new Exception('活动未开始');
            if ($prom['end_time'] < NOW_TIME) throw new Exception('活动已结束');
            // 扫码记录判断
            if (M('prom_qrcode_log')->where(['user_id' => $userId, 'prom_id' => $prom['id']])->find()) {
                throw new Exception('您已经领取过了');
            }
            switch ($prom['reward_type']) {
                case 1:
                    $couponIds = $prom['reward_content'];
                    // 发放优惠券
                    $res = (new CouponLogic())->receive($couponIds, $userId, true);
                    if ($res['status'] == 0) {
                        throw new Exception($res['msg']);
                    }
                    break;
                case 2:
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
            return ['status' => 1, 'msg' => '领取成功'];
        } else {
            throw new Exception('兑换码错误');
        }
    }
}
