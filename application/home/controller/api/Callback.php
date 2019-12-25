<?php

namespace app\home\controller\api;


class Callback
{
    /**
     * 微信支付回调
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function wechatApp()
    {
        // 返回成功xml
        $resSuccessXml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        // 返回失败xml
        $resFailXml = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[fail]]></return_msg></xml>';

        // 获取接口数据，如果$_REQUEST拿不到数据，则使用file_get_contents函数获取
        $post = $_REQUEST;
        if ($post == null) {
            $post = file_get_contents("php://input");
        }
        if ($post == null) {
            $post = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        }
        if (empty($post) || $post == null || $post == '') {
            // 阻止微信接口反复回调接口
            echo $resFailXml;
            exit('Notify 非法回调');
        }

        libxml_disable_entity_loader(true); // 禁止引用外部xml实体

        //$xml = simplexml_load_string($post, 'SimpleXMLElement', LIBXML_NOCDATA);  // XML转数组
        $xml_object = simplexml_load_string($post); // 将文件转换成 对象
        $xml_json = json_encode($xml_object);   // 将对象转换个JSON
        $post_data = json_decode($xml_json, true);  // 将json转换成数组

        /** 解析出来的数组
         *Array
         * (
         * [appid] => wx1c870c0145984d30
         * [bank_type] => CFT
         * [cash_fee] => 100
         * [fee_type] => CNY
         * [is_subscribe] => N
         * [mch_id] => 1297210301
         * [nonce_str] => gkq1x5fxejqo5lz5eua50gg4c4la18vy
         * [openid] => olSGW5BBvfep9UhlU40VFIQlcvZ0
         * [out_trade_no] => fangchan_588796
         * [result_code] => SUCCESS
         * [return_code] => SUCCESS
         * [sign] => F6890323B0A6A3765510D152D9420EAC
         * [time_end] => 20180626170839
         * [total_fee] => 100
         * [trade_type] => JSAPI
         * [transaction_id] => 4200000134201806265483331660
         * )
         **/

        // 订单号
        $orderSn = isset($post_data['out_trade_no']) && !empty($post_data['out_trade_no']) ? $post_data['out_trade_no'] : 0;
        // 查询订单信息
        $orderInfo = M('order')->where(['order_sn' => $orderSn])->find();
        M('order')->where(['order_id', $orderInfo['order_id']])->update(['transaction_id' => $post_data['transaction_id']]);
        echo $resSuccessXml;
        if ($orderInfo) {
            $paymentPlugin = M('Plugin')->where(['code' => 'weixinApp', 'type' => 'payment'])->find(); // 找到微信支付插件的配置
            $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化

            // 平台支付key
            $wxpay_key = $config_value['key'];

            // 接收到的签名
            $post_sign = $post_data['sign'];
            unset($post_data['sign']);

            // 重新生成签名
            $newSign = $this->wechatMakeSign($post_data, $wxpay_key);

            // 签名统一，则更新数据库
            if ($post_sign == $newSign) {
                M('order')->where(['order_id', $orderInfo['order_id']])->update(['transaction_id' => $post_data['transaction_id']]);
//        update_pay_status($orderInfo['order_sn'], ['transaction_id' => $post_data['transaction_id']]); // 修改订单支付状态
                echo $resSuccessXml;
            }
        } else {
            echo $resFailXml;
        }
    }

    function wechatMakeSign($params, $key)
    {
        // 签名步骤一：按字典序排序数组参数
        ksort($params);
        $string = ToUrlParams($params);  //参数进行拼接key=value&k=v
        // 签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $key;
        // 签名步骤三：MD5加密
        $string = md5($string);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
}