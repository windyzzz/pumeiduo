<?php

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

        // 分销开关全局
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

//        if ($order['order_pv'] > 0) {
//            // 查看订单商品是否有非自营产品
//            $isSelfSales = true;
//            $orderGoods = M('order_goods og')->join('goods g', 'g.goods_id = og.goods_id')
//                ->where(['og.order_id' => $order['order_id']])->field('g.cat_id, g.extend_cat_id, og.rec_id, og.goods_id, og.goods_pv')->select();
//            foreach ($orderGoods as $key => $goods) {
//                if (!in_array($goods['cat_id'], [903, 888]) && !in_array($goods['extend_cat_id'], [903, 888])) {
//                    $isSelfSales = false;
//                }
//                unset($orderGoods[$key]['cat_id']);
//                unset($orderGoods[$key]['extend_cat_id']);
//            }
//            if ($isSelfSales) {
//                // 通知代理商系统记录
//                include_once "plugins/Tb.php";
//                $TbLogic = new \Tb();
//                $TbLogic->add_tb(1, 11, $order['order_id'], 0);
//            }
//        }
    }
}
