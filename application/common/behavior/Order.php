<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\behavior;

use app\common\logic\wechat\WechatUtil;
use think\Db;

class Order
{
    public function userAddOrder(&$order)
    {
        // 记录订单操作日志
        $action_info = [
            'order_id' => $order['order_id'],
            'action_user' => 0,
            'action_note' => '您提交了订单，请等待系统确认',
            'status_desc' => '提交订单', //''
            'log_time' => time(),
        ];
        Db::name('order_action')->add($action_info);

        //分销开关全局
        $distribut_switch = tpCache('distribut.switch');
        if (1 == $distribut_switch && file_exists(APP_PATH.'common/logic/DistributLogic.php')) {
            $distributLogic = new \app\common\logic\DistributLogic();
            $distributLogic->rebateLog($order); // 生成分成记录
        }

        // 如果有微信公众号 则推送一条消息到微信
        $user = Db::name('OauthUsers')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ($user) {
            $wx_content = "您刚下了一笔订单，还未支付，请尽快支付。\n订单编号:{$order['order_sn']}";
            $wechat = new WechatUtil();
            $wechat->sendMsg($user['openid'], 'text', $wx_content);
        }

//        //用户下单, 发送短信给商家
//        $res = checkEnableSendSms('3');
//        if ($res && 1 == $res['status']) {
//            $sender = tpCache('shop_info.mobile');
//            $params = ['consignee' => $order['consignee'], 'mobile' => $order['mobile']];
//            sendSms('3', $sender, $params);
//        }
    }
}
