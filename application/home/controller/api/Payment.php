<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller\api;

use think\Db;

class Payment
{
    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code
    public $web;

    /**
     * 析构流函数.
     */
    public function __construct()
    {
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];

        if (!empty($pay_radio)) {
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        } else { // 第三方 支付商返回
            //file_put_contents('./a.html',$_GET,FILE_APPEND);
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }

        $this->web = I('get.web', '');

        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents('php://input');
        if (empty($this->pay_code)) {
            return json(['status' => 0, 'msg' => 'pay_code 不能为空', 'result' => null]);
        }
        // 导入具体的支付类文件
        include_once "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php";
        $code = '\\'.$this->pay_code; //
        $this->payment = new $code();
    }

    /**
     * tpshop 提交支付方式.
     */
    public function getCode()
    {
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header('Content-type:text/html;charset=utf-8');
        $order_id = I('order_id/d'); // 订单id
        session('order_id', $order_id); // 最近支付的一笔订单 id
        if (!session('user')) {
            return json(['status' => 0, 'msg' => '请先登录', 'result' => null]);
        }
        $order = Db::name('Order')->where(['order_id' => $order_id])->find();
        if (empty($order) || $order['order_status'] > 1) {
            return json(['status' => 0, 'msg' => '非法操作！', 'result' => null]);
        }
        if (time() - $order['add_time'] >= 3480) {
            return json(['status' => 0, 'msg' => '此订单，将在2分钟内被作废，不能支付，请重新下单!', 'result' => null]);
        }
        if (1 == $order['pay_status']) {
            return json(['status' => 0, 'msg' => '此订单，已完成支付!', 'result' => null]);
        }
        // 修改订单的支付方式
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField('code,name');
        M('order')->where('order_id', $order_id)->save(['pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code], 'prepare_pay_time' => time()]);

        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $payBody = getPayBody($order_id);
        $config_value['body'] = $payBody;

        //微信JS支付
        if ('weixinJsApi' == $this->pay_code && $_SESSION['openid'] && strstr(session('server')['HTTP_USER_AGENT'], 'MicroMessenger')) {
            // if($this->pay_code == 'weixin' && $_SESSION['openid'] && $this->web == 'weixin'){
            $return['type'] = 'JSAPI';
            $code_str = $this->payment->unifiedOrder($order, $config_value);
            if ('FAIL' == $code_str['result_code']) {
                return json(['status' => -1, 'msg' => $code_str['err_code_des'], 'result' => null]);
            }
        } else {
            $return['type'] = 'NATIVE';
            $code_str = $this->payment->get_code($order, $config_value);
        }
        $return['pay_code'] = $this->pay_code;
        $return['result'] = $code_str;
        $return['order_id'] = $order_id;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function getPay()
    {
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header('Content-type:text/html;charset=utf-8');
        $order_id = I('order_id/d'); // 订单id
        session('order_id', $order_id); // 最近支付的一笔订单 id
        // 修改充值订单的支付方式
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField('code,name');

        M('recharge')->where('order_id', $order_id)->save(['pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]]);
        $order = M('recharge')->where('order_id', $order_id)->find();
        if (1 == $order['pay_status']) {
            return json(['status' => 0, 'msg' => '此订单，已完成支付!', 'result' => null]);
        }
        $pay_radio = $_REQUEST['pay_radio'];
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $order['order_amount'] = $order['account'];
        $code_str = $this->payment->get_code($order, $config_value);
        //微信JS支付
        if ('weixin' == $this->pay_code && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $code_str = $this->payment->getJSAPI($order, $config_value);
        }
        $return['code_str'] = $code_str;
        $return['order_id'] = $order_id;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    // 服务器点对点 // http://www.tp-shop.cn/index.php/Home/Payment/notifyUrl
    public function notifyUrl()
    {
        $this->payment->response();
        exit();
    }

    // 页面跳转 // http://www.tp-shop.cn/index.php/Home/Payment/returnUrl
    public function returnUrl()
    {
        $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';

        if (false !== stripos($result['order_sn'], 'recharge')) {
            $order = M('recharge')->where('order_sn', $result['order_sn'])->find();
            $return['order'] = $order;
            if (1 == $result['status']) {
                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            }

            return json(['status' => 0, 'msg' => 'faile', 'result' => null]);
            exit();
        }

        $order = M('order')->where('order_sn', $result['order_sn'])->find();
        if (empty($order)) { // order_sn 找不到 根据 order_id 去找
            $order_id = session('order_id'); // 最近支付的一笔订单 id
            $order = M('order')->where('order_id', $order_id)->find();
        }

        $return['order'] = $order;
        if (1 == $result['status']) {
            return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
        }

        return json(['status' => 0, 'msg' => 'faile', 'result' => null]);
    }

    public function refundBack()
    {
        $this->payment->refund_respose();
        exit();
    }

    public function transferBack()
    {
        $this->payment->transfer_response();
        exit();
    }
}
