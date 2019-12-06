<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use think\Model;

/**
 * 支付 逻辑定义
 * Class AlipayPayment.
 */
class alipayMobile extends Model
{
    public $tableName = 'plugin'; // 插件表
    public $alipay_config = []; // 支付宝支付配置参数
    public $config_value = []; // 支付宝支付配置参数

    /**
     * 析构流函数.
     */
    public function __construct()
    {
        parent::__construct();
        unset($_GET['pay_code']);   // 删除掉 以免被进入签名
        unset($_REQUEST['pay_code']); // 删除掉 以免被进入签名

        $paymentPlugin = M('Plugin')->where("code='alipayMobile' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $this->config_value = $config_value;
        $this->alipay_config['alipay_pay_method'] = $config_value['alipay_pay_method']; // 1 使用担保交易接口  2 使用即时到帐交易接口s
        $this->alipay_config['app_id'] = $config_value['appid'];
        $this->alipay_config['partner'] = $config_value['alipay_partner']; //合作身份者id，以2088开头的16位纯数字
        $this->alipay_config['seller_email'] = $config_value['alipay_account']; //收款支付宝账号，一般情况下收款账号就是签约账号
        $this->alipay_config['key'] = $config_value['alipay_key']; //安全检验码，以数字和字母组成的32位字符
        $this->alipay_config['merchant_private_key'] = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzlG/WbwsqolQxjzAltUq0UNHJu5FxOo9bLkG6R9q1iKSOn1WRaX53F9FUsdv95OnKIPiNMNZRJMmpo93J2UP5VsCwlKn4QdEg5fC+2Rn69+emqoMPhnJdWRICWCznZ2J9al27R1ymYavJfTWTILdmEy5dQLmTN3KVNmisiKwLfT9MaJXcX2zE7vQ2SA5t+7EgOY2dxCIZiyzxdbxLs43+aN6qO0umszQqCls3pg06oPafQqLEFaQX2fgYTITTA8cXQ3tdgt4zQQzGd90d8RZb/8lb70LidrDMD+/BNC/0Woyt2sneGUcUAiPDV+KWmzkFfJxq58sn7fRAwAXcqsCaQIDAQAB';
        $this->alipay_config['alipay_public_key'] = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjpJVbOXeVQAErBl3fBMSFUHIc41IeuOIxXJ0SFIGnLK1qqJFsjuyfg6B2OAORXzs4B1i+o2bv9+YA+t3KyUbppAtnd/2Ee3ERDUhjUTmDSwyGhtpb31yiOdul2RQNp5ioZ7Gth0ar9J7ULhe5AyJmaLJo3nVVIPqtz6K+DjqD0ccdaJmjoqAYRF3QMQraR4WlEF8Kglv1hCUBgHekYlWQMvtFHuwRHPGdoXabKK3JbL24a0/I7X1AVjJj/LTOfKt9jFX8xDtXHSRnyIjfwAJL34F+2ODbWxCFxU8pt+k3K0d7ZHcP0xurRsRCJqaOJKnqg7ipt9SXunbucCPrRbn2QIDAQAB';
        $this->alipay_config['charset'] = $config_value['charset'];
        $this->alipay_config['gatewayUrl'] = $config_value['gatewayUrl'];

        //安全检验码，以数字和字母组成的32位字符
        $this->alipay_config['sign_type'] = strtoupper('MD5'); //签名方式 不需修改
        $this->alipay_config['input_charset'] = strtolower('utf-8'); //字符编码格式 目前支持 gbk 或 utf-8
        $this->alipay_config['cacert'] = getcwd() . '\\cacert.pem'; //ca证书路径地址，用于curl中ssl校验 //请保证cacert.pem文件在当前文件夹目录中
        $this->alipay_config['transport'] = 'http'; //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
    }

    /**
     * 生成支付代码
     *
     * @param array $order 订单信息
     * @param array $config_value 支付方式信息
     */
    public function get_code($order, $config_value)
    {
        $shop_info = tpCache('shop_info');
        $shop_info && $store_name = $shop_info['store_name'];
        empty($store_name) ? $store_name = 'TPshop订单' : $store_name = $store_name . '订单';

        // 接口类型
        $service = [
            1 => 'create_partner_trade_by_buyer', //使用担保交易接口
            2 => 'create_direct_pay_by_user', //使用即时到帐交易接口
        ];
        //构造要请求的参数数组，无需改动
        $parameter = [
            'partner' => trim($this->alipay_config['partner']), //合作身份者ID，签约账号，以2088开头由16位纯数字组成的字符串，查看地址：https://b.alipay.com/order/pidAndKey.htm
            'seller_id' => trim($this->alipay_config['partner']), //收款支付宝账号，以2088开头由16位纯数字组成的字符串，一般情况下收款账号就是签约账号
            'key' => trim($this->alipay_config['key']), // MD5密钥，安全检验码，由数字和字母组成的32位字符串，查看地址：https://b.alipay.com/order/pidAndKey.htm
            // "seller_email" => trim($this->alipay_config['seller_email']),
            'notify_url' => SITE_URL . U('Payment/notifyUrl', ['pay_code' => 'alipayMobile']), //服务器异步通知页面路径 //必填，不能修改
            'return_url' => SITE_URL . '/#/order/pay_result?order_id=' . $order['order_id'],  //页面跳转同步通知页面路径
            'sign_type' => strtoupper('MD5'), //签名方式
            'input_charset' => strtolower('utf-8'), //字符编码格式 目前支持utf-8
            'cacert' => getcwd() . '\\cacert.pem',
            'transport' => 'http', // //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
            'service' => 'alipay.wap.create.direct.pay.by.user',   // // 产品类型，无需修改
            'payment_type' => '1', // 支付类型 ，无需修改
            '_input_charset' => trim(strtolower($this->alipay_config['input_charset'])), //字符编码格式 目前支持 gbk 或 utf-8
            'out_trade_no' => $order['order_sn'], //商户订单号
            'subject' => $store_name, //订单名称，必填
            'total_fee' => $order['order_amount'], //付款金额
            'show_url' => 'http://pmdshop.melangame.com', //收银台页面上，商品展示的超链接，必填
        ];
        //  如果是支付宝网银支付
        if (!empty($config_value['bank_code'])) {
            $parameter['paymethod'] = 'bankPay'; // 若要使用纯网关，取值必须是bankPay（网银支付）。如果不设置，默认为directPay（余额支付）。
            $parameter['defaultbank'] = $config_value['bank_code'];
            $parameter['service'] = 'create_direct_pay_by_user';
        }
        //建立请求
        require_once 'lib/alipay_submit.class.php';
        $alipaySubmit = new AlipaySubmit($this->alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($parameter, 'get', '确认');

        return $html_text;
    }

    /**
     * 服务器点对点响应操作给支付接口方调用.
     */
    public function response()
    {
        require_once 'lib/alipay_notify.class.php';  // 请求返回
        //计算得出通知验证结果
        $alipayNotify = new AlipayNotify($this->alipay_config); // 使用支付宝原生自带的累 和方法 这里只是引用了一下 而已
        $verify_result = $alipayNotify->verifyNotify();

        if ($verify_result) { //验证成功
            $order_sn = $out_trade_no = $_POST['out_trade_no']; //商户订单号
            $trade_no = $_POST['trade_no']; //支付宝交易号
            $trade_status = $_POST['trade_status']; //交易状态

            //用户在线充值
            if (false !== stripos($order_sn, 'recharge')) {
                $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
            } else {
                $order_amount = M('order')->where(['order_sn' => "$order_sn"])->value('order_amount');
            }
            if ($order_amount != $_POST['price']) {
                exit('fail');
            } //验证失败

            // 支付宝解释: 交易成功且结束，即不可再做任何操作。
            if ('TRADE_FINISHED' == $_POST['trade_status']) {
                update_pay_status($order_sn, ['transaction_id' => $trade_no]); // 修改订单支付状态
            } //支付宝解释: 交易成功，且可对该交易做操作，如：多级分润、退款等。
            elseif ('TRADE_SUCCESS' == $_POST['trade_status']) {
                update_pay_status($order_sn, ['transaction_id' => $trade_no]); // 修改订单支付状态
            }
            echo 'success'; // 告诉支付宝处理成功
        } else {
            echo 'fail'; //验证失败
        }
    }

    /**
     * 页面跳转响应操作给支付接口方调用.
     */
    public function respond2()
    {
        require_once 'lib/alipay_notify.class.php';  // 请求返回
        //计算得出通知验证结果
        $alipayNotify = new AlipayNotify($this->alipay_config);
        $verify_result = $alipayNotify->verifyReturn();

        if ($verify_result) { //验证成功
            $order_sn = $out_trade_no = $_GET['out_trade_no']; //商户订单号
            $trade_no = $_GET['trade_no']; //支付宝交易号
            $trade_status = $_GET['trade_status']; //交易状态

            if ('TRADE_FINISHED' == $_GET['trade_status'] || 'TRADE_SUCCESS' == $_GET['trade_status']) {
                return ['status' => 1, 'order_sn' => $order_sn]; //跳转至成功页面
            }

            return ['status' => 0, 'order_sn' => $order_sn]; //跳转至失败页面
        }

        return ['status' => 0, 'order_sn' => $_GET['out_trade_no']]; //跳转至失败页面
    }

    public function refund($order, $refund_amount, $reason)
    {
        $shop_info = tpCache('shop_info');
        $shop_info && $store_name = $shop_info['store_name'];
        empty($store_name) ? $store_name = 'TPshop订单' : $store_name = $store_name . '订单';
        $date_time = date('Y-m-d H:i:s');
        $date = date('Ymd', strtotime($date_time));
        //构造要请求的参数数组，无需改动
        // create_direct_pay_by_user
        // alipay.wap.create.direct.pay.by.user
        // refund_fastpay_by_platform_pwd
        $parameter = [
            'service' => 'refund_fastpay_by_platform_pwd',   // // 产品类型，无需修改
            'partner' => trim($this->alipay_config['partner']), //合作身份者ID，签约账号，以2088开头由16位纯数字组成的字符串，查看地址：https://b.alipay.com/order/pidAndKey.htm
            '_input_charset' => trim(strtolower($this->alipay_config['input_charset'])), //字符编码格式 目前支持 gbk 或 utf-8
            // "sign_type"     => strtoupper('MD5'), //签名方式
            'notify_url' => SITE_URL . U('Payment/notifyUrl', ['pay_code' => 'alipayMobile']), //服务器异步通知页面路径 //必填，不能修改
            'seller_user_id' => trim($this->alipay_config['seller_email']),
            'refund_date' => $date_time,
            'batch_no' => $date . $order['order_sn'],
            'batch_num' => 1,
            'detail_data' => $order['transaction_id'] . '^' . $refund_amount . '^' . $reason,
            // 'seller_id'=> trim($this->alipay_config['partner']), //收款支付宝账号，以2088开头由16位纯数字组成的字符串，一般情况下收款账号就是签约账号
            // "key" => trim($this->alipay_config['key']), // MD5密钥，安全检验码，由数字和字母组成的32位字符串，查看地址：https://b.alipay.com/order/pidAndKey.htm

            // "return_url"    => SITE_URL.'/#/order/pay_result?order_id='.$order['order_id'],  //页面跳转同步通知页面路径

            // "input_charset" =>strtolower('utf-8'), //字符编码格式 目前支持utf-8
            // "cacert"    =>  getcwd().'\\cacert.pem',
            // "transport" => 'http', // //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http

            // "payment_type"  => "1", // 支付类型 ，无需修改

            // "out_trade_no"  => $order['order_sn'], //商户订单号
            // "subject"       => $store_name, //订单名称，必填
            // "total_fee" => $order['order_amount'], //付款金额
            // "show_url"  => "http://pmdshop.melangame.com", //收银台页面上，商品展示的超链接，必填
        ];
        //建立请求
        require_once 'lib/alipay_submit.class.php';
        $alipaySubmit = new AlipaySubmit($this->alipay_config);
        // $html_text = $alipaySubmit->buildRequestPara($parameter,"get", "确认");
        $html_text = $alipaySubmit->buildRequestForm($parameter, 'get', '确认');

        return $html_text;
    }

    public function refund1($order, $refund_amount, $reason)
    {
        $this->alipay_config['sign_type'] = $this->config_value['sign_type'];

        // $this->alipay_config['rsaPrivateKeyFilePath']     = __DIR__.'/alipay_public_key_sha256_2017060107396737.txt';
        require_once 'lib/AopClient/wappay/service/AlipayTradeService.php';
        require_once 'lib/AopClient/wappay/buildermodel/AlipayTradeRefundContentBuilder.php';
        require 'lib/AopClient/config.php';
        if (!empty($order['order_sn'])) {
            //商户订单号和支付宝交易号不能同时为空。 trade_no、  out_trade_no如果同时存在优先取trade_no
            //商户订单号，和支付宝交易号二选一
            $out_trade_no = trim($order['order_sn']);

            //支付宝交易号，和商户订单号二选一
            // $trade_no = trim($order['order_sn']);

            //退款金额，不能大于订单总金额
            $refund_amount = trim($refund_amount);

            //退款的原因说明
            $refund_reason = trim($reason);

            //标识一次退款请求，同一笔交易多次退款需要保证唯一，如需部分退款，则此参数必传。
            $out_request_no = trim($order['order_sn'] . rand(100, 999));

            $RequestBuilder = new AlipayTradeRefundContentBuilder();
            // $RequestBuilder->setTradeNo($trade_no);
            $RequestBuilder->setOutTradeNo($out_trade_no);
            $RequestBuilder->setRefundAmount($refund_amount);
            $RequestBuilder->setRefundReason($refund_reason);
            $RequestBuilder->setOutRequestNo($out_request_no);
            // dump($this->alipay_config);
            // exit;
            $Response = new AlipayTradeService($config);
            $result = $Response->Refund($RequestBuilder);

            return $result;
        }
    }
}
