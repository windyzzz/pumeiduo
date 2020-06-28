<?php


namespace app\home\controller\api;

use think\Db;

class Pay extends Base
{
    protected $payment;    // 具体的支付类
    protected $pay_code;   //    具体的支付code
    protected $web;

    public function __construct()
    {
        parent::__construct();
        $this->pay_code = I('pay_code', '');
        if (empty($this->pay_code)) {
            return json(['status' => 0, 'msg' => 'pay_code不能为空']);
        }
        // 导入具体的支付类文件
        include_once "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php";
        $code = '\\' . $this->pay_code;
        $this->payment = new $code();
    }

    /**
     * 支付请求
     * @return \think\response\Json
     */
    public function getCode()
    {
        $orderId = I('order_id/d', '');
        $order = Db::name('Order')->where(['order_id' => $orderId])->find();
        if (empty($order) || $order['order_status'] > 1) {
            return json(['status' => 0, 'msg' => '非法操作！']);
        }
        if (time() - $order['add_time'] > 3600) {
            return json(['status' => 0, 'msg' => '此订单在一个小时内不支付已作废，不能支付，请重新下单!']);
        }
        if ($order['pay_status'] == 1) {
            return json(['status' => 0, 'msg' => '此订单，已完成支付!']);
        }
        // 请求支付
        $signCode = [
            'appid' => '',
            'noncestr' => '',
            'package' => '',
            'partnerid' => '',
            'prepayid' => '',
            'timestamp' => '',
            'paySign' => '',
            'payCode' => ''
        ];
        switch ($this->pay_code) {
            case 'alipayApp':
                // 支付宝
                $res = $this->payment->get_code($order);
                if ($res['status'] == 0) {
                    return json(['status' => 0, 'msg' => $res['msg']]);
                }
                $res = $res['result'];
                $signCode['payCode'] = $res;
                break;
            case 'weixinApp':
                // 微信
                if ($order['order_type'] == 2) {
                    // 海外购订单
                    // 导入具体的支付类文件
                    include_once "plugins/payment/weixinApp_2/weixinApp_2.class.php";
                    $code = '\\' . 'weixinApp_2';
                    $this->payment = new $code();
                }
                $res = $this->payment->get_code($order);
                if ($res['status'] == 0) {
                    return json(['status' => 0, 'msg' => $res['msg']]);
                }
                $res = $res['result'];
                $signCode['appid'] = $res['appid'];
                $signCode['noncestr'] = $res['noncestr'];
                $signCode['package'] = $res['package'];
                $signCode['partnerid'] = $res['partnerid'];
                $signCode['prepayid'] = $res['prepayid'];
                $signCode['timestamp'] = $res['timestamp'];
                $signCode['paySign'] = $res['paySign'];
                break;
            default:
                return json(['status' => 0, 'msg' => '请求失败！']);
        }
        // 修改订单的支付方式
        $payment = M('Plugin')->where(['code' => $this->pay_code])->value('name');
        M('order')->where('order_id', $orderId)->save(['pay_code' => $this->pay_code, 'pay_name' => $payment, 'prepare_pay_time' => time()]);

        return json(['status' => 1, 'msg' => 'success', 'result' => ['order_id' => $orderId, 'sign_code' => $signCode]]);
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
        if ($order['pay_status'] == 1) {
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
