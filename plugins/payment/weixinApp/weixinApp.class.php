<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class weixinApp
{
    private $config_value;

    /**
     * 析构流函数.
     */
    public function __construct()
    {
        require_once 'lib/WxPay.Api.php'; // 微信扫码支付demo 中的文件
        require_once 'example/WxPay.NativePay.php';
        require_once 'example/WxPay.JsApiPay.php';
        require_once 'example/WxPay.AppPay.php';

        $paymentPlugin = M('Plugin')->where(['code' => 'weixinApp', 'type' => 'payment'])->find(); // 找到微信支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $this->config_value = $config_value;
        WxPayConfig::$appid = $config_value['appid']; // * APPID：绑定支付的APPID（必须配置，开户邮件中可查看）
        WxPayConfig::$mchid = $config_value['mchid']; // * MCHID：商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$smchid = isset($config_value['smchid']) ? $config_value['smchid'] : ''; // * SMCHID：服务商商户号（必须配置，开户邮件中可查看）
        WxPayConfig::$key = $config_value['key']; // KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）
        WxPayConfig::$appsecret = $config_value['appsecret']; // 公众帐号secert（仅JSAPI支付的时候需要配置)，
    }

    /*
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $config    支付方式信息
     */
    public function get_code($order)
    {
//        if (false !== stripos($order['order_sn'], 'recharge')) {
//            $go_url = U('Mobile/User/points', ['type' => 'recharge']);
//            $back_url = U('Mobile/User/recharge', ['order_id' => $order['order_id']]);
//        } else {
//            $go_url = U('Mobile/Order/order_detail', ['id' => $order['order_id']]);
//            $back_url = U('Mobile/Cart/cart4', ['order_id' => $order['order_id']]);
//        }
        //①、获取用户openid
        //$openId = $tools->GetOpenid();
//        $openId = $_SESSION['openid'];

        //②、统一下单
        require_once 'lib/WxPay.Data.php';
        $input = new WxPayUnifiedOrder();
        $input->SetBody('支付订单：' . $order['order_sn']);
        $input->SetAttach('weixinApp');
        $input->SetOut_trade_no($order['order_sn']);
        $input->SetTotal_fee($order['order_amount'] * 100);
        $input->SetTime_start(date('YmdHis'));
        $input->SetTime_expire(date('YmdHis', time() + 600));
        $input->SetGoods_tag('tp_wx_app_pay');
        $input->SetTrade_type('APP');
        $input->SetNotify_url(SITE_URL . '/index.php/Home/api.Pay/notifyUrl/pay_code/weixinApp');
//        $input->SetOpenid($openId);

        require_once 'lib/WxPay.Api.php';
        $order2 = WxPayApi::unifiedOrder($input);
        if ($order2['return_code'] == 'FAIL') {
            return ['status' => 0, 'msg' => $order2['return_msg']];
        } elseif ($order2['result_code'] == 'FAIL') {
            return ['status' => 0, 'msg' => $order2['err_code_des']];
        }

        $tools = new AppPay();
        $AppParameters = $tools->GetAppParameters($order2); // 二次签名
        $param = json_decode($AppParameters, true);
//        $order2['noncestr'] = $param['nonceStr'];
//        $order2['timestamp'] = $param['timeStamp'];
//        $order2['package'] = 'Sign=WXPay';
//        $order2['partnerid'] = $this->config_value['mchid'];
//        $order2['new_sign'] = $param['paySign'];

        return ['status' => 1, 'result' => $param];
    }

    /**
     * 服务器点对点响应操作给支付接口方调用.
     */
    public function response()
    {
        require_once 'example/notify.php';
        $notify = new PayNotifyCallBack();
        $notify->Handle(false);
    }

    /**
     * 页面跳转响应操作给支付接口方调用.
     */
    public function respond2()
    {
        // 微信扫码支付这里没有页面返回
    }

    public function unifiedOrder($order)
    {
        if (false !== stripos($order['order_sn'], 'recharge')) {
            $go_url = U('Mobile/User/points', ['type' => 'recharge']);
            $back_url = U('Mobile/User/recharge', ['order_id' => $order['order_id']]);
        } else {
            $go_url = U('Mobile/Order/order_detail', ['id' => $order['order_id']]);
            $back_url = U('Mobile/Cart/cart4', ['order_id' => $order['order_id']]);
        }
        //①、获取用户openid
        $tools = new JsApiPay();
        //$openId = $tools->GetOpenid();
        $openId = $_SESSION['openid'];
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody('支付订单：' . $order['order_sn']);
        $input->SetAttach('weixinApp');
        $input->SetOut_trade_no($order['order_sn']);
        $input->SetTotal_fee($order['order_amount'] * 100);
        $input->SetTime_start(date('YmdHis'));
        $input->SetTime_expire(date('YmdHis', time() + 600));
        $input->SetGoods_tag('tp_wx_pay');
        $input->SetNotify_url(SITE_URL . '/index.php/Home/Payment/notifyUrl/pay_code/weixinApp');
        $input->SetTrade_type('APP');
        $input->SetOpenid($openId);
        $order2 = WxPayApi::unifiedOrder($input);
        $jsApiParameters = $tools->GetJsApiParameters($order2);
        $param = json_decode($jsApiParameters, true);

        $order2['nonce_str'] = $param['nonceStr'];

        $order2['timeStamp'] = $param['timeStamp'];
        $order2['sign'] = $param['paySign'];

        return $order2;
    }

    public function getJSAPI($order)
    {
        if (false !== stripos($order['order_sn'], 'recharge')) {
            $go_url = U('Mobile/User/points', ['type' => 'recharge']);
            $back_url = U('Mobile/User/recharge', ['order_id' => $order['order_id']]);
        } else {
            $go_url = U('Mobile/Order/order_detail', ['id' => $order['order_id']]);
            $back_url = U('Mobile/Cart/cart4', ['order_id' => $order['order_id']]);
        }
        //①、获取用户openid
        $tools = new JsApiPay();
        //$openId = $tools->GetOpenid();
        $openId = $_SESSION['openid'];
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody('支付订单：' . $order['order_sn']);
        $input->SetAttach('weixinApp');
        $input->SetOut_trade_no($order['order_sn']);
        $input->SetTotal_fee($order['order_amount'] * 100);
        $input->SetTime_start(date('YmdHis'));
        $input->SetTime_expire(date('YmdHis', time() + 600));
        $input->SetGoods_tag('tp_wx_pay');
        $input->SetNotify_url(SITE_URL . '/index.php/Home/Payment/notifyUrl/pay_code/weixinApp');
        $input->SetTrade_type('JSAPI');
        $input->SetOpenid($openId);
        $order2 = WxPayApi::unifiedOrder($input);
        //echo '<font color="#f00"><b>统一下单支付单信息</b></font><br/>';

        $jsApiParameters = $tools->GetJsApiParameters($order2);

        $html = <<<EOF
	<script type="text/javascript">
	//调用微信JS api 支付
	function jsApiCall()
	{
		WeixinJSBridge.invoke(
			'getBrandWCPayRequest',$jsApiParameters,
			function(res){
				//WeixinJSBridge.log(res.err_msg);
				 if(res.err_msg == "get_brand_wcpay_request:ok") {
				    location.href='$go_url';
				 }else{
				 	alert(res.err_code+res.err_desc+res.err_msg);
				    location.href='$back_url';
				 }
			}
		);
	}

	function callpay()
	{
		if (typeof WeixinJSBridge == "undefined"){
		    if( document.addEventListener ){
		        document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
		    }else if (document.attachEvent){
		        document.attachEvent('WeixinJSBridgeReady', jsApiCall);
		        document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
		    }
		}else{
		    jsApiCall();
		}
	}
	callpay();
	</script>
EOF;

        return $html;
    }

    // 微信提现批量转账
    public function transfer($data)
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 将一个数组转换为 XML 结构的字符串.
     *
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root
     *
     * @return string XML 结构的字符串
     */
    public function array2xml($arr, $level = 1)
    {
        $s = 1 == $level ? '<xml>' : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);

        return 1 == $level ? $s . '</xml>' : $s;
    }

    public function http_post($url, $param, $wxchat)
    {
        $oCurl = curl_init();
        if (false !== stripos($url, 'https://')) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        }
        if (is_string($param)) {
            $strPOST = $param;
        } else {
            $aPOST = [];
            foreach ($param as $key => $val) {
                $aPOST[] = $key . '=' . urlencode($val);
            }
            $strPOST = join('&', $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        if ($wxchat) {
            curl_setopt($oCurl, CURLOPT_SSLCERT, dirname(THINK_PATH) . $wxchat['api_cert']);
            curl_setopt($oCurl, CURLOPT_SSLKEY, dirname(THINK_PATH) . $wxchat['api_key']);
            curl_setopt($oCurl, CURLOPT_CAINFO, dirname(THINK_PATH) . $wxchat['api_ca']);
        }
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (200 == intval($aStatus['http_code'])) {
            return $sContent;
        }

        return false;
    }

    //查询订单
    public function Queryorder($transaction_id)
    {
        $input = new WxPayOrderQuery();
        $input->SetTransaction_id($transaction_id);
        $result = WxPayApi::orderQuery($input);

        return $result;
    }

    // 微信订单退款原路退回
    public function refund($order, $refund_money)
    {
        // $query = $this->Queryorder($order['transaction_id']);
        // dump($query);
        // exit;
        // dump($order);
        // dump($refund_money);
        // exit;
        require_once 'lib/WxPay.Data.php';
        $input = new WxPayRefund();
        $input->SetAppid($this->config_value['appid']);
        $input->SetMch_id($this->config_value['mchid']);
        // $input->SetDevice_info(); // 设置微信支付分配的终端设备号，与下单一致
        // $input->SetNonce_str(); // 设置随机字符串，不长于32位。推荐随机数生成算法
        $input->SetTransaction_id($order['transaction_id']); // 设置微信订单号
        // $input->SetOut_trade_no($order['order_sn']); // 设置商户系统内部的订单号,transaction_id、out_trade_no二选一，如果同时存在优先级：transaction_id>
        $input->SetOut_refund_no($order['order_sn'] . rand(100, 999)); // 设置商户系统内部的退款单号，商户系统内部唯一，同一退款单号多次请求只退一笔
        $input->SetTotal_fee($order['order_amount'] * 100); // 设置订单总金额，单位为分，只能为整数，详见支付金额
        $input->SetRefund_fee($refund_money * 100); // 设置退款总金额，订单总金额，单位为分，只能为整数，详见支付金额
        // $input->SetRefund_fee_type(); // 设置货币类型，符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型
        $input->SetOp_user_id($this->config_value['mchid']); // 设置操作员帐号, 默认为商户号

        require_once 'lib/WxPay.Api.php';
        $result = WxPayApi::refund($input);

        return $result;
    }
}
