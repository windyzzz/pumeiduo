<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class AlipayApp
{
    public $config_value = []; // 支付宝支付配置参数

    public function __construct()
    {
        $paymentPlugin = M('Plugin')->where("code='alipayApp' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $this->config_value = $config_value;
    }

    public function get_code($order)
    {
        $shop_info = tpCache('shop_info');
        $shop_info && $store_name = $shop_info['store_name'];
        empty($store_name) ? $store_name = '圃美多订单' : $store_name = $store_name.'订单';

        require_once 'aop/AopClient.php';

        $aop = new AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $this->config_value['appId'];
        $aop->rsaPrivateKey = $this->config_value['rsaPrivateKey'];
        $aop->format = 'json';
        $aop->charset = 'UTF-8';
        $aop->signType = 'RSA2';
        $aop->alipayrsaPublicKey = $this->config_value['alipayrsaPublicKey'];

        require_once 'aop/request/AlipayTradeAppPayRequest.php';
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent = "{\"body\":\"{$store_name}\","
                        .'"subject": "App支付测试",'
                        ."\"out_trade_no\": \"{$order['order_sn']}\","
                        .'"timeout_express": "30m",'
                        .'"total_amount": "0.01",'
                        .'"product_code":"QUICK_MSECURITY_PAY"'
                        .'}';
//        $request->setNotifyUrl(SITE_URL.U('Payment/notifyUrl',array('pay_code'=>'alipayApp')));
        $request->setNotifyUrl(SITE_URL.'/index.php/Home/Payment/notifyUrl/pay_code/alipayApp');
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);

        return $response;
        //htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
        // echo htmlspecialchars($response);//就是orderString 可以直接给客户端请求，无需再做处理。
    }

    public function response()
    {
        require_once 'aop/AopClient.php';

        $aop = new AopClient();
        $aop->alipayrsaPublicKey = trim($this->config_value['alipayrsaPublicKey']);
        $flag = $aop->rsaCheckV1($_POST, null, 'RSA2');
        $result = $flag ? 1 : 2;
        file_put_contents('response/alipayAppResponse.log', '执行日期：'.strftime('%Y%m%d%H%M%S', time())."\n", FILE_APPEND | LOCK_EX);
        file_put_contents('response/alipayAppResponse.log', '执行数据：'.json_encode($_POST)."\n", FILE_APPEND | LOCK_EX);
        file_put_contents('response/alipayAppResponse.log', '执行结果：'.$result."\n", FILE_APPEND | LOCK_EX);
        if ($flag) { //验证成功
                $order_sn = $out_trade_no = $_POST['out_trade_no']; //商户订单号
                $trade_no = $_POST['trade_no']; //支付宝交易号
                $trade_status = $_POST['trade_status']; //交易状态

                //用户在线充值
            if (false !== stripos($order_sn, 'recharge')) {
                $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
            } else {
                $order_amount = M('order')->where(['order_sn' => "$order_sn"])->value('order_amount');
            }
            if ($order_amount != $_POST['total_amount']) {
                exit('fail');
            } //验证失败

            // 支付宝解释: 交易成功且结束，即不可再做任何操作。
            if ('TRADE_FINISHED' == $_POST['trade_status']) {
                update_pay_status($order_sn, ['transaction_id' => $trade_no]); // 修改订单支付状态
            }
            //支付宝解释: 交易成功，且可对该交易做操作，如：多级分润、退款等。
            elseif ('TRADE_SUCCESS' == $_POST['trade_status']) {
                update_pay_status($order_sn, ['transaction_id' => $trade_no]); // 修改订单支付状态
            }
            echo 'success'; // 告诉支付宝处理成功
        } else {
            echo 'fail'; //验证失败
        }
    }
}
