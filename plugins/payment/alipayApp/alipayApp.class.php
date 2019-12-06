<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

//namespace plugins\payment\alipay;

use app\admin\logic\RefundLogic;
use think\Model;
use think\Request;

/**
 * 支付 逻辑定义
 * Class AlipayPayment.
 */
class alipayApp
{
    public $alipay_config = []; // 支付宝支付配置参数

    /**
     * 析构流函数.
     */
    public function __construct()
    {
        $paymentPlugin = M('Plugin')->where("code='alipay' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $this->alipay_config = $config_value;
    }

    /**
     * 生成支付代码
     *
     * @param array $order 订单信息
     */
    public function get_code($order)
    {
        $shop_info = tpCache('shop_info');
        $shop_info && $store_name = $shop_info['store_name'];
        empty($store_name) ? $store_name = '圃美多订单' : $store_name = $store_name . '订单';

        require_once 'aop/AopClient.php';
        $aop = new AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $this->alipay_config['appId'];
        $aop->rsaPrivateKey = $this->alipay_config['rsaPrivateKey'];
        $aop->alipayrsaPublicKey = $this->alipay_config['alipayrsaPublicKey'];
        $aop->apiVersion = '1.0';
        $aop->signType = $this->alipay_config['signType'];
        $aop->postCharset = $this->alipay_config['charset'];
        $aop->format = $this->alipay_config['format'];

        require_once 'aop/request/AlipayTradeAppPayRequest.php';
        $request = new AlipayTradeAppPayRequest();
        $request->setBizContent(
            "{"
            . "\"out_trade_no\": \"{$order['order_sn']}\","
            . "\"total_amount\": \"{$order['order_amount']}\","
            . "\"subject\": \"圃美多APP端支付\","
            . "\"body\": \"{$store_name}{$order['order_sn']}\","
            . "\"timeout_express\": \"30m\","
            . "\"product_code\": \"QUICK_MSECURITY_PAY\""
            . "}"
        );
        $request->setNotifyUrl(SITE_URL . '/index.php/Home/api.Pay/notifyUrl/pay_code/alipayApp');

        $result = $aop->sdkExecute($request);
        if ($result) {
            return ['status' => 1, 'result' => $result];
        }
        return ['status' => 0, 'msg' => '支付宝请求失败'];
    }

    /**
     * 服务器点对点响应操作给支付接口方调用.
     */
    public function response()
    {
        require_once 'aop/AopClient.php';

        $aop = new AopClient();
        $aop->alipayrsaPublicKey = trim($this->alipay_config['alipayrsaPublicKey']);
        $flag = $aop->rsaCheckV1($_POST, null, 'RSA2');
//        $result = $flag ? 1 : 2;
//        file_put_contents('response/alipayPcResponse.log', '执行日期：' . strftime('%Y%m%d%H%M%S', time()) . "\n", FILE_APPEND | LOCK_EX);
//        file_put_contents('response/alipayPcResponse.log', '执行数据：' . json_encode($_POST) . "\n", FILE_APPEND | LOCK_EX);
//        file_put_contents('response/alipayPcResponse.log', '执行结果：' . $result . "\n", FILE_APPEND | LOCK_EX);

        if ($flag) {
            // 验证成功
            $order_sn = $out_trade_no = $_POST['out_trade_no']; //商户订单号
            $trade_no = $_POST['trade_no']; //支付宝交易号
//            $trade_status = $_POST['trade_status']; //交易状态
            if (stripos($order_sn, 'recharge')) {
                // 用户在线充值
                $order_amount = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->value('account');
            } else {
                // 购物订单
                $order_amount = M('order')->where(['order_sn' => $order_sn])->value('order_amount');
            }
            if ($order_amount != $_POST['total_amount']) {
                // 验证失败
                exit('fail');
            }
            switch ($_POST['trade_status']) {
                case 'TRADE_FINISHED':
                    // 支付宝解释: 交易成功且结束，即不可再做任何操作。
                case 'TRADE_SUCCESS':
                    // 支付宝解释: 交易成功，且可对该交易做操作，如：多级分润、退款等。
                    update_pay_status($order_sn, ['transaction_id' => $trade_no]); // 修改订单支付状态
                    break;
            }
            echo 'success'; // 告诉支付宝处理成功
        } else {
            // 验证失败
            echo 'fail';
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

    //支付宝批量付款到支付宝账户有密接口接口
    // 支付宝批量申请提现转款
    public function transfer($data)
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    // 批量申请提现转账回调
    public function transfer_response()
    {
        require_once 'lib/alipay_notify.class.php';  // 请求返回
        //计算得出通知验证结果
        $alipayNotify = new AlipayNotify($this->alipay_config); // 使用支付宝原生自带的类和方法 这里只是引用了一下 而已
        $verify_result = $alipayNotify->verifyNotify();
        if ($verify_result) {
            //返回数据格式：0315001^gonglei1@163.com^龚本林^20.00^S^null^200810248427067^20081024143652|
            $success_details = $_POST['success_details'];
            if ($success_details) {
                $sdata = explode('|', $success_details);
                foreach ($sdata as $val) {
                    $pay_arr = explode('^', $val);
                    $pay_id[] = $pay_arr[0];
                }
                $withdrawals = M('withdrawals')->where(['id' => ['in', $pay_id]])->select();
                foreach ($withdrawals as $wd) {
                    accountLog($wd['user_id'], ($wd['money'] * -1), 0, '平台处理用户提现申请');
                    $rdata = ['type' => 1, 'money' => $wd['money'], 'log_type_id' => $wd['id'], 'user_id' => $wd['user_id']];
                    expenseLog($rdata);
                }
                M('withdrawals')->where(['id' => ['in', $pay_id]])->save(['pay_time' => strtotime($pay_arr[7]), 'status' => 2, 'pay_code' => $pay_arr[6]]);
            } else {
                //失败数据格式：0315006^xinjie_xj@163.com^星辰公司1^20.00^F^TXN_RESULT_TRANSFER_OUT_CAN_NOT_EQUAL_IN^200810248427065^20081024143651|
                //格式为：流水号^收款方账号^收款账号姓名^付款金额^失败标识(F)^失败原因^支付宝内部流水号^完成时间。
                //批量付款数据中转账失败的详细信息
                $fail_details = $_POST['fail_details'];
                $fdata = explode('|', $fail_details);
                foreach ($fdata as $val) {
                    $pay_arr = explode('^', $val);
                    $update = ['error_code' => $pay_arr[5], 'pay_time' => strtotime($pay_arr[7]), 'status' => 3, 'pay_code' => $pay_arr[6]];
                    M('withdrawals')->where(['id' => $pay_arr[0]])->save($update);
                }
            }
            echo 'success'; //告诉支付宝处理成功
        } else {
            $verify_result = print_r($verify_result);
            error_log($verify_result, 3, 'pay.log');
        }
    }

    //支付宝即时到账批量退款有密接口接口
    // 支付宝退款原路退回
    public function payment_refund($data)
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    // 退款原路回调
    public function refund_respose()
    {
        require_once 'lib/alipay_notify.class.php';  // 请求返回
        //计算得出通知验证结果
        $alipayNotify = new AlipayNotify($this->alipay_config); // 使用支付宝原生自带的类和方法 这里只是引用了一下 而已
        $verify_result = $alipayNotify->verifyNotify();
        if ($verify_result) {
            $batch_no = $_POST['batch_no'];
            //批量退款数据中转账成功的笔数
            $success_num = $_POST['success_num'];
            if (intval($success_num) > 0) {
                //返回成功数据格式：2014040311001004370000361525^80^SUCCESS$jax_chuanhang@alipay.com^2088101003147483^0.01^SUCCESS
                $result_details = $_POST['result_details'];
                $res = explode('^', $result_details);
                $batch_no = $_POST['batch_no'];
                $rec_str = substr($batch_no, 12);
                if ('SUCCESS' == $res[2]) {
                    $rec_id = substr($rec_str, 1);
                    $refundLogic = new RefundLogic();
                    if (false !== stripos($rec_str, 'r')) {
                        $refundLogic->updateRefundGoods($rec_id); //订单商品售后退款原路退回
                    } else {
                        $order = M('order')->where(['order_id' => $rec_id])->find();
                        $refundLogic->updateRefundOrder($rec_id); //订单整单申请原路退款
                    }
                }
            }
            echo 'success'; //告诉支付宝处理成功
        } else {
            $verify_result = print_r($verify_result);
            error_log($verify_result, 3, 'pay.log');
        }
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
