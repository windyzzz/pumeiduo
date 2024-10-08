<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller;

use app\common\logic\supplier\OrderService;
use app\common\logic\Token as TokenLogic;
use app\common\logic\UsersLogic;
use think\Cache;
use think\cache\driver\Redis;
use think\Cookie;
use think\Db;
use think\Request;
use think\Session;
use think\Verify;

class Api extends Base
{
    public $send_scene;

    public function _initialize()
    {
        parent::_initialize();
//        session('user');
    }

    /*
     * 获取地区
     */
    public function getRegion()
    {
        $parent_id = I('get.parent_id/d');
        $selected = I('get.selected', 0);
        $data = M('region2')->where('parent_id', $parent_id)->select();
        $html = '';
        if ($data) {
            foreach ($data as $h) {
                if ($h['id'] == $selected) {
                    $html .= "<option value='{$h['id']}' selected>{$h['name']}</option>";
                }
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }

    public function getTwon()
    {
        $parent_id = I('get.parent_id/d');
        $data = M('region2')->where('parent_id', $parent_id)->select();
        $html = '';
        if ($data) {
            foreach ($data as $h) {
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        if (empty($html)) {
            echo '0';
        } else {
            echo $html;
        }
    }

    /**
     * 获取省
     */
    public function getProvince()
    {
        $province = Db::name('region2')->field('id,name')->where(['level' => 1])->cache(true)->select();
        $res = ['status' => 1, 'msg' => '获取成功', 'result' => $province];
        exit(json_encode($res));
    }

    /**
     * 获取市或者区.
     */
    public function getRegionByParentId()
    {
        $parent_id = input('parent_id');
        $res = ['status' => 0, 'msg' => '获取失败，参数错误', 'result' => ''];
        if ($parent_id) {
            $region_list = Db::name('region2')->field('id,name')->where(['parent_id' => $parent_id])->select();
            $res = ['status' => 1, 'msg' => '获取成功', 'result' => $region_list];
        }
        exit(json_encode($res));
    }

    /*
     * 获取下级分类
     */
    public function get_category()
    {
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
        $list = M('goods_category')->where('parent_id', $parent_id)->select();
        if ($list) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！', 'result' => $list]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '获取失败！']);
    }

    /**
     * 判断手机号是否已有账号
     * @return array|\think\response\Json
     */
    public function checkPhone()
    {
        $mobile = I('mobile', '');
        $scene = I('scene', 1);

        $userData = M('users')->where('mobile', $mobile)->field('user_id, is_lock, is_cancel')->select(); // 手机号登陆的情况下会有多个账号
        // 检验账号有效性
        $userId = 0;
        foreach ($userData as $user) {
            if ($user['is_cancel'] == 1) {
                continue;
            }
            if ($user['is_lock'] == 0) {
                $userId = $user['user_id'];
            }
        }
        switch ($scene) {
            case 1:
                // 注册 验证手机是否已存在
                if ($userId != 0) {
                    return json(['status' => 1, 'result' => ['state' => 0, 'msg' => '手机号已存在']]);
                } else {
                    return json(['status' => 1, 'result' => ['state' => 1, 'msg' => '手机号不存在']]);
                }
                break;
            case 8:
                // 授权登录绑定手机
                if ($userId != 0) {
                    //--- 账号已存在
                    // 账号是否已绑定了微信
                    if (M('oauth_users')->where(['user_id' => $userId])->find()) {
                        return json(['status' => 1, 'result' => ['state' => 0, 'msg' => '手机已绑定了微信账号']]);
                    }
                    return json(['status' => 1, 'result' => ['state' => 1, 'msg' => '手机已有账号，不需设置密码']]);
                } else {
                    //--- 账号不存在
                    return json(['status' => 1, 'result' => ['state' => 2, 'msg' => '手机没有账号，需要设置密码']]);
                }
                break;
            default:
                return json(['status' => 0, 'msg' => '场景错误']);
        }
    }

    /**
     * 前端发送短信方法: APP/WAP/PC 共用发送方法.
     */
    public function send_validate_code()
    {
        $this->send_scene = C('SEND_SCENE');

        $type = I('type');
        $scene = I('scene');    //发送短信验证码使用场景
        $mobile = I('mobile');
        $sender = I('send');
        $verify_code = I('verify_code');
        $mobile = !empty($mobile) ? $mobile : $sender;
        $session_id = I('unique_id', $this->userToken);
        S('scene_' . $scene . '_' . $session_id, $scene, 180);

//        $exist = M('users')->where(['mobile'=>$mobile,'is_lock'=>0])->find();
//        if(!$exist){
//            $return_arr = array('status'=>-1,'msg'=>'该手机号码未绑定，请到手机版商城个人设置中绑定手机号后再进行操作.');
//            ajaxReturn($return_arr);
//        }

        //注册
        if (1 == $scene && !empty($verify_code)) {
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_reg')) {
                ajaxReturn(['status' => -1, 'msg' => '图像验证码错误']);
            }
        }
        if ('email' == $type) {
            //发送邮件验证码
            $logic = new UsersLogic();
            $res = $logic->send_email_code($sender);
            ajaxReturn($res);
        } else {
            //验证手机格式
            if (!check_mobile($mobile)) {
                ajaxReturn(['status' => -1, 'msg' => '手机号填写错误']);
            }
            //发送短信验证码
            $res = checkEnableSendSms($scene);  // 检查是否能够发短信
            if (1 != $res['status']) {
                ajaxReturn($res);
            }
            /*//判断是否存在验证码
            $data = M('sms_log')->where(['mobile' => $mobile, 'session_id' => $session_id, 'status' => 1])->order('id DESC')->find();
            //获取时间配置
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 120;
            //120秒以内不可重复发送
            if ($data && (time() - $data['add_time']) < $sms_time_out) {
                $return_arr = ['status' => -1, 'msg' => $sms_time_out.'秒内不允许重复发送'];
                ajaxReturn($return_arr);
            }*/
            //随机一个验证码
            $code = rand(1000, 9999);
            $params['code'] = $code;

            //发送短信
            $resp = sendSms($scene, $mobile, $params, $session_id);

            if (1 == $resp['status']) {
                //发送成功, 修改发送状态位成功
                M('sms_log')->where(['mobile' => $mobile, 'code' => $code, 'session_id' => $session_id, 'status' => 0])->save(['status' => 1]);
                S('mobile_token_' . $mobile, $session_id, 180);
                $return_arr = ['status' => 1, 'msg' => '发送成功,请注意查收'];
            } else {
                $return_arr = ['status' => -1, 'msg' => '发送失败,' . $resp['msg']];
            }
            ajaxReturn($return_arr);
        }
    }

    /**
     * 验证短信验证码: APP/WAP/PC 共用发送方法.
     */
    public function check_validate_code()
    {
        $code = I('post.code');
        $mobile = I('mobile');
        $send = I('send');
        $sender = empty($mobile) ? $send : $mobile;
        $type = I('type');
        $session_id = I('unique_id', $this->userToken);
        $scene = I('scene', -1);

        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $sender, $type, $session_id, $scene);
        ajaxReturn($res);
    }

    /**
     * 检测手机号是否已经存在.
     */
    public function issetMobile()
    {
        $mobile = I('mobile', '0');
        $users = M('users')->where('mobile', $mobile)->find();
        if ($users) {
            exit('1');
        }

        exit('0');
    }

    public function issetMobileOrEmail()
    {
        $mobile = I('mobile', '0');
        $users = M('users')->where('email', $mobile)->whereOr('mobile', $mobile)->find();
        if ($users) {
            exit('1');
        }

        exit('0');
    }

    /**
     * 查询物流
     */
    public function queryExpress($request = [], $out = 'json')
    {
        $type = isset($request['shipping_code']) ? $request['shipping_code'] : I('shipping_code', '');
        $queryNo = isset($request['queryNo']) ? $request['queryNo'] : I('queryNo', '');
        if (!$queryNo) {
            switch ($out) {
                case 'json':
                    return json(['status' => -1, 'msg' => '参数有误', 'result' => '']);
                default:
                    return ['status' => -1, 'msg' => '参数有误', 'result' => ''];
            }
        }
        $host = 'https://kdwlcxf.market.alicloudapi.com';
        $path = '/kdwlcx';
        $method = 'GET';
        $appcode = '0e19cd48e5b6416c8491677adc8e9ae1';
        $headers = [];
        array_push($headers, 'Authorization:APPCODE ' . $appcode);

        $querys = "no=$queryNo";
        if ($type) {
            $querys = "no={$queryNo}&type={$type}";
        }
        $bodys = '';
        $url = $host . $path . '?' . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos('$' . $host, 'https://')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $output = curl_exec($curl);
        /*
        返回参数说明
        deliverystatus - 0.快递收件(揽件) 1.在途中 2.正在派件 3.已签收 4.派送失败 5.疑难件 6.退件签收
        */
        $data = json_decode($output, true);

        switch ($out) {
            case 'json':
                return json($data);
            default:
                return $data;
        }

        // $express_switch = tpCache('express.express_switch');
        // if($express_switch == 1){
        // 	require_once(PLUGIN_PATH . 'kdniao/kdniao.php');
        // 	$kdniao = new \kdniao();
        // 	$data['OrderCode'] = empty(I('order_sn')) ? date('YmdHis') : I('order_sn');
        // 	$data['ShipperCode'] = I('shipping_code');
        // 	$data['LogisticCode'] = I('invoice_no');
        // 	$res = $kdniao->getOrderTracesByJson(json_encode($data));
        // 	$res =  json_decode($res, true);
        // 	if($res['State'] == 3){
        // 		foreach ($res['Traces'] as $val){
        // 			$tmp['context'] = $val['AcceptStation'];
        // 			$tmp['time'] = $val['AcceptTime'];
        // 			$res['data'][] = $tmp;
        // 		}
        // 		$res['status'] = "200";
        // 	}else{
        // 		$res['message'] = $res['Reason'];
        // 	}
        // 	return json($res);
        // }else{
        // 	$shipping_code = input('shipping_code');
        // 	$invoice_no = input('invoice_no');
        // 	if(empty($shipping_code) || empty($invoice_no)){
        // 		return json(['status'=>0,'message'=>'参数有误','result'=>'']);
        // 	}
        // 	return json(queryExpress($shipping_code,$invoice_no));
        // }
    }

    /**
     * 检查订单状态
     */
    public function check_order_pay_status()
    {
        $order_id = I('order_id/d');
        if (empty($order_id)) {
            $res = ['message' => '参数错误', 'status' => -1, 'result' => ''];
            $this->AjaxReturn($res);
        }
        $order = M('order')->field('pay_status')->where(['order_id' => $order_id])->find();
        if (0 != $order['pay_status']) {
            $res = ['message' => '已支付', 'status' => 1, 'result' => $order];
        } else {
            $res = ['message' => '未支付', 'status' => 0, 'result' => $order];
        }
        $this->AjaxReturn($res);
    }

    /**
     * 广告位js.
     */
    public function ad_show()
    {
        $pid = I('pid/d', 1);
        $where = [
            'pid' => $pid,
            'enable' => 1,
            'start_time' => ['lt', strtotime(date('Y-m-d H:00:00'))],
            'end_time' => ['gt', strtotime(date('Y-m-d H:00:00'))],
        ];
        $ad = D('ad')->where($where)->order('orderby desc')->cache(true, TPSHOP_CACHE_TIME)->find();
        $this->assign('ad', $ad);

        return $this->fetch();
    }

    /**
     *  搜索关键字.
     *
     * @return array
     */
    public function searchKey()
    {
        $searchKey = input('key');
        $searchKeyList = Db::name('search_word')
            ->where('keywords', 'like', $searchKey . '%')
            ->whereOr('pinyin_full', 'like', $searchKey . '%')
            ->whereOr('pinyin_simple', 'like', $searchKey . '%')
            ->limit(10)
            ->select();
        if ($searchKeyList) {
            return json(['status' => 1, 'msg' => '搜索成功', 'result' => $searchKeyList]);
        }

        return json(['status' => 0, 'msg' => '没记录', 'result' => $searchKeyList]);
    }

    /**
     * 根据ip设置获取的地区来设置地区缓存.
     */
    public function doCookieArea()
    {
//        $ip = '183.147.30.238';//测试ip
        $address = input('address/a', []);
        if (empty($address) || empty($address['province'])) {
            $this->setCookieArea();

            return;
        }
        $province_id = Db::name('region2')->where(['level' => 1, 'name' => ['like', '%' . $address['province'] . '%']])->limit('1')->value('id');
        if (empty($province_id)) {
            $this->setCookieArea();

            return;
        }
        if (empty($address['city'])) {
            $city_id = Db::name('region2')->where(['level' => 2, 'parent_id' => $province_id])->limit('1')->order('id')->value('id');
        } else {
            $city_id = Db::name('region2')->where(['level' => 2, 'parent_id' => $province_id, 'name' => ['like', '%' . $address['city'] . '%']])->limit('1')->value('id');
        }
        if (empty($address['district'])) {
            $district_id = Db::name('region2')->where(['level' => 3, 'parent_id' => $city_id])->limit('1')->order('id')->value('id');
        } else {
            $district_id = Db::name('region2')->where(['level' => 3, 'parent_id' => $city_id, 'name' => ['like', '%' . $address['district'] . '%']])->limit('1')->value('id');
        }
        $this->setCookieArea($province_id, $city_id, $district_id);
    }

    /**
     * 设置地区缓存.
     *
     * @param $province_id
     * @param $city_id
     * @param $district_id
     */
    private function setCookieArea($province_id = 1, $city_id = 2, $district_id = 3)
    {
        Cookie::set('province_id', $province_id);
        Cookie::set('city_id', $city_id);
        Cookie::set('district_id', $district_id);
    }

    /**
     * 检查身份证信息
     * @param array $request
     * @param string $out
     * @return bool|string|\think\response\Json
     */
    public function checkIdCard($request = [], $out = 'json')
    {
        $idcard = isset($request['id_card']) ? $request['id_card'] : I('id_card', '');
        $realname = isset($request['real_name']) ? $request['real_name'] : I('real_name', '');
        if (!$idcard || !$realname) {
            switch ($out) {
                case 'json':
                    return json(['status' => -1, 'msg' => '参数有误', 'result' => '']);
                default:
                    return ['status' => -1, 'msg' => '参数有误', 'result' => ''];
            }
        }
        $host = 'https://checkid.market.alicloudapi.com';
        $path = '/IDCard';
        $method = 'GET';
        $appcode = '0e19cd48e5b6416c8491677adc8e9ae1';
        $headers = array();
        array_push($headers, 'Authorization:APPCODE ' . $appcode);
        $querys = 'idCard=' . $idcard . '&name=' . $realname;
        $bodys = '';
        $url = $host . $path . '?' . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        //curl_setopt($curl, CURLOPT_HEADER, true); 如不输出json, 请打开这行代码，打印调试头部状态码。
        //状态码: 200 正常；400 URL无效；401 appCode错误； 403 次数用完； 500 API网管错误
        if (1 == strpos('$' . $host, 'https://')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $output = curl_exec($curl);
        $data = json_decode($output, true);

        switch ($out) {
            case 'json':
                return json($data);
            default:
                return $data;
        }
    }

    /**
     * 供应链物流信息
     * @return \think\response\Json
     */
    public function supplierExpress()
    {
        $orderId = I('order_id');
        $supplierGoodsId = I('supplier_goods_id');
        $orderSn = M('order')->where(['order_id' => $orderId])->value('order_sn');
        $express = (new OrderService())->getExpress($orderSn, $supplierGoodsId);
        $returnData = [];
        if ($express['status'] == 0) {
            $returnData[] = [
                'time' => date('Y-m-d H:i:s', time()),
                'status' => '暂无物流信息'
            ];
        } else {
            foreach ($express['data']['express_info'] as $item) {
                $returnData[] = [
                    'time' => $item['time'],
                    'status' => $item['context'],
                ];
            }
        }
        return json(['status' => 1, 'result' => $returnData]);
    }
}
