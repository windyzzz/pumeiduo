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

use app\common\logic\ArticleLogic;
use app\common\logic\CartLogic;
use app\common\logic\Community as CommunityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\MessageLogic;
use app\common\logic\PushLogic;
use app\common\logic\TaskLogic;
use app\common\logic\Token as TokenLogic;
use app\common\logic\UsersLogic;
use app\home\controller\Api as ApiController;
use think\Db;
use think\Hook;
use think\Loader;
use think\Page;
use think\Request;
use think\Url;
use think\Verify;

class User extends Base
{
//    public $user_id = 0;
//    public $user = [];

    public function __construct()
    {
        parent::__construct();
        // 1. 检查登陆
        if (!$this->passAuth) {
            $params['user_token'] = isset($this->userToken) ? $this->userToken : null;
            Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);
        }
//        $user = session('user');
//        if ($user) {
//            $this->user = $user;
//            $this->user_id = $user['user_id'];
//            $this->userToken = session_id();
//        }
    }

    /**
     * 获取注册赠送积分
     * @return \think\response\Json
     */
    function user_reg_point()
    {
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        return json(['status' => 1, 'msg' => 'success', 'result' => ['user_reg_point' => $pay_points]]);
    }

    function check_user_customs()
    {
        $Users = M('Users')->where('user_id', $this->user_id)->field('user_name,distribut_level')->find();
        if ($Users['distribut_level'] >= 3) {//直接显示 用户名
            return json(['status' => 1, 'msg' => 'success', 'result' => ['show_status' => 2, 'show_detail' => $Users['user_name']]]);//已经是金卡
        }
        // 查看用户等级预升级记录
        $userPreLog = M('user_pre_distribute_log')->where(['user_id' => $this->user_id, 'status' => 0])->order('id DESC')->find();
        if (empty($userPreLog)) {
            $tips = "您尚未有资格开通金卡会员\r\n请购买SVIP升级套餐后\r\n按系统提示进行申请\r\n有任何疑问请联系客服" . tpCache('shop_info.mobile');
            return json(['status' => 1, 'msg' => 'success', 'result' => ['show_status' => 0, 'show_detail' => $tips]]);
        } else {
            // 更新预升级记录
            M('user_pre_distribute_log')->where(['user_id' => $this->user_id, 'id' => ['NEQ', $userPreLog['id']]])->update(['status' => -1]);
        }
//        $count = M('users')->where(array('first_leader' => $this->user_id, 'distribut_level' => array('egt', 2)))->count();
//        $apply_check_num = tpCache('basic.apply_check_num');
//        if ($Users['distribut_level'] < 2) {
//            $error = '您尚未有资格开通金卡会员';
//            return json(['status' => 1, 'msg' => 'success', 'result' => ['show_status' => 0, 'show_detail' => $error]]);//已经是金卡
//        } else if ($count < $apply_check_num) {
//            $error = '您尚未有资格开通金卡会员';
//            return json(['status' => 1, 'msg' => 'success', 'result' => ['show_status' => 0, 'show_detail' => $error]]);//已经是金卡
//        }
        return json(['status' => 1, 'msg' => 'success', 'result' => ['show_status' => 1, 'show_detail' => '可以申请']]);//已经是金卡
    }

    /**
     * 申请代理商
     */
    function apply_customs()
    {
        $Users = M('Users')->where('user_id', $this->user_id)->field('user_name,distribut_level')->find();
        if ($Users['distribut_level'] >= 3) {//直接显示 用户名
            $result = array(
                'show' => 2,
                'user_name' => $Users['user_name']
            );
            return json(['status' => 1, 'msg' => 'success', 'result' => $result]);
        } else {

            $apply_customs = M('apply_customs')->where(array('user_id' => $this->user_id))->find();
            $result = array(
                'show' => 1,
                'true_name' => $apply_customs ? $apply_customs['true_name'] : '',
                'id_card' => $apply_customs ? $apply_customs['id_card'] : '',
                'mobile' => $apply_customs ? $apply_customs['mobile'] : '',
                'is_can_cancel' => $apply_customs && $apply_customs['status'] == 0 ? 1 : 0
            );
            $invite_uid = M('Users')->where('user_id', $this->user_id)->getField('invite_uid');

            $logic = new UsersLogic();
            $referee_user_id = $logic->nk($invite_uid, 3);
            if ($referee_user_id) {
                $result['referee_show'] = '推荐金卡会员号：' . $referee_user_id;
            } else {
                $result['referee_show'] = '无';
            }

            $article = M('article')->where(array('article_id' => 104))->field('title,content')->find();
            $result['is_alert_title'] = $article['title'];
            $result['is_alert_content'] = $article['content'];

            $result['apply_content'] = M('article')->where(array('article_id' => 105))->getField('content');

            return json(['status' => 1, 'msg' => 'success', 'result' => $result]);
        }
    }

    /**
     * 撤销申请
     */
    function apply_customs_cancel()
    {
        $logic = new UsersLogic();
        $apply_customs_cancel = $logic->apply_customs_cancel($this->user_id);
        if ($apply_customs_cancel) {
            return json(['status' => 1, 'msg' => $logic->getError()]);
        } else {
            return json(['status' => 0, 'msg' => $logic->getError()]);
        }
    }

    function apply_customs_add()
    {
        $logic = new UsersLogic();
        $apply_customs = $logic->apply_customs($this->user_id, I('request.'));
        if ($apply_customs) {
            return json(['status' => 1, 'msg' => $logic->getError()]);
        } else {
            return json(['status' => 0, 'msg' => $logic->getError()]);
        }
    }

    /*
     * 用户中心首页
     */
    public function index()
    {
        $logic = new UsersLogic();
        $user = $logic->get_info($this->user_id);
        $user = $user['result'];
        $level = Db::name('user_level')->select();
        $level = convert_arr_key($level, 'level_id');
        $coupon = $logic->get_coupon($this->user_id, '', '', '', $p = 2);
        $data = [];
        $data['coupon'] = $coupon['result'];
        $data['level'] = $level;
        $data['user'] = $user;

        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }

    /*
     * 账户资金
     */
    public function account()
    {
        $type = I('type');
        $order_sn = I('order_sn');
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, $type, $order_sn);
        $account_log = $data['result'];
        // foreach ($account_log as $k => $v) {
        //     $account_log[$k]['change_time'] = date('Y.m.d',$v['change_time']);
        //     $account_log[$k]['date'] = date('m',$v['change_time']);
        // }
        $return = [];
        $return['user'] = $this->user;
        $return['account_log'] = $account_log;
        $return['page'] = $data['show'];
        $return['active'] = 'account';

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 用户积分（明细）（新）
     * @return \think\response\Json
     */
    public function accountNew()
    {
        $result = (new UsersLogic())->get_account_log($this->user_id)['result'];
        $return = [
            'user_pay_points' => $this->user['pay_points'],
            'account_log' => []
        ];
        foreach ($result as $month) {
            foreach ($month as $log) {
                $return['account_log'][] = [
                    'log_id' => $log['log_id'],
                    'pay_points' => $log['pay_points'],
                    'title' => $log['desc'],
                    'add_time' => strtotime($log['change_time']) . ''
                ];
            }
        }
        return json(['status' => 1, 'result' => $return]);
    }

    // 获取用户财富
    public function userWealth()
    {
        $user = $this->user;
        $will_distribut_money = M('RebateLog')->field('SUM(money) as money')->where('user_id', $user['user_id'])->where('status', 'in', [1, 2])->find();
        $return = [];
        $return['user_id'] = $user['user_id'];
        $return['user_money'] = $user['user_money'];
        $return['frozen_money'] = $user['frozen_money'];
        $return['will_invite_uid'] = $user['will_invite_uid'];
        $return['type'] = $user['type'];
        $return['pay_points'] = $user['pay_points'];
        $return['user_electronic'] = $user['user_electronic'];
        $return['frozen_electronic'] = $user['frozen_electronic'];
        $return['distribut_number'] = $user['distribut_level'];
        $return['head_pic'] = $user['head_pic'];
        $return['distribut_money'] = $user['distribut_money'];
        $return['will_distribut_money'] = isset($will_distribut_money['money']) ? $will_distribut_money['money'] : '0.00';

        $first_fans = M('users')->where('first_leader', $this->user_id)->count();
        $second_fans = M('users')->where('second_leader', $this->user_id)->count();

        $return['first_fans'] = $first_fans;
        $return['second_fans'] = $second_fans;

        $return['distribut_level'] = M('DistributLevel')->where('level_id', $user['distribut_level'])->getField('level_name');
        $return['has_pay_pwd'] = $user['paypwd'] ? 1 : 0;
        if ($user['distribut_level'] >= 3) $return['type'] = '2'; // 直销商
        $return['is_app'] = TokenLogic::getValue('is_app', $this->userToken) ? 1 : 0;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    function off_show_jk()
    {
        $user = session('user');
        $logic = new UsersLogic();
        $check_apply_customs = $logic->check_apply_customs($user['user_id']);
        if ($check_apply_customs) {
            M('users')->where(array('user_id' => $user['user_id']))->data(array('is_not_show_jk' => 1))->save();
            return json(['status' => 1, 'msg' => '关闭成功']);
        }
        return json(['status' => 0, 'msg' => '关闭失败']);
    }

    /*
     * 优惠券列表  -  会员中心
     */
    public function coupon()
    {
        $type = I('type', 0);
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, I('type'));
        foreach ($data['result'] as $k => $v) {
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if (1 == $user_type || 4 == $user_type || 5 == $user_type) { //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id' => $v['cid']])->getField('goods_id');
                $data['result'][$k]['goods_count'] = M('goods_coupon')->field('goods_id')->where(['coupon_id' => $v['cid']])->count();
            }
            if (2 == $user_type) { //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id' => $v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        foreach ($coupon_list as $key => $value) {
            $coupon_list[$key]['changStatus'] = false;
            if ($value['category_id'] > 0) {
                $coupon_list[$key]['category_name'] = M('goods_category')->where('id', $value['category_id'])->getField('name');
            }
            if ($value['goods_id'] > 0) {
                $coupon_list[$key]['goods_image'] = M('goods')->where('goods_id', $value['goods_id'])->getField('original_img');
            }

            $coupon_list[$key]['use_start_time'] = date('Y-m-d', $coupon_list[$key]['use_start_time']);
            $coupon_list[$key]['use_end_time'] = date('Y-m-d', $coupon_list[$key]['use_end_time']);

            if ($value['use_type'] == 4) {
                $coupon_money = $value['money'];
                $coupon_money = bcadd($coupon_money, 0, 1);
                $coupon_money = str_replace('.0', '', $coupon_money);
                $coupon_list[$key]['money'] = $coupon_money . '折';
            }


            $coupon_list[$key]['content'] = htmlspecialchars_decode($value['content']);
        }
        $return = [];
        $return['coupon_list'] = $coupon_list;
        $return['page'] = $data['show'];
        $return['active'] = 'coupon';
        $return['type'] = $type;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 领用券列表
     */
    function couponReceiveList()
    {
        $where = array(
            'send_start_time' => array('elt', NOW_TIME),
            'send_end_time' => array('egt', NOW_TIME),
            'use_type' => 5
        );
        $field = 'id,name,condition,content,createnum,send_num,use_end_time';
        $coupon = M('coupon')->field($field)->where($where)->order('id desc')->select();
        if ($coupon) {
            foreach ($coupon as $k => $v) {
                $coupon[$k]['use_end_time'] = date('Y-m-d', $v['use_end_time']);
                //检查是否已经领取
                $is_has_coupon = M('coupon_list')->where(array('cid' => $v['id'], 'uid' => $this->user_id))->field('id')->find();
                if ($is_has_coupon) {
                    $coupon[$k]['is_has'] = 1;
                } else {
                    $coupon[$k]['is_has'] = 0;
                }
                $coupon[$k]['content'] = htmlspecialchars_decode($coupon[$k]['content']);
                if ($v['createnum'] > 0) {
                    if ($v['send_num'] >= $v['createnum']) {
                        $coupon[$k]['is_has_num'] = 0;
                    } else {
                        $coupon[$k]['is_has_num'] = 1;
                    }
                } else {
                    $coupon[$k]['is_has_num'] = 1;
                }

            }
        }
        return json(['status' => 1, 'msg' => 'success', 'result' => $coupon]);
    }

    function couponReceive()
    {
        $re_id = I('re_id', 0);
        if (!$re_id) {
            return json(['status' => 0, 'msg' => '操作有误']);
        }

        Db::startTrans();
        $where = array(
            'send_start_time' => array('elt', NOW_TIME),
            'send_end_time' => array('egt', NOW_TIME),
            'use_type' => 5,
            'id' => $re_id
        );

        $coupon = M('coupon')->where($where)->find();

        if (!$coupon) {
            Db::rollback();
            return json(['status' => 0, 'msg' => '该券不可领取']);
        }
        if ($coupon['createnum'] > 0) {
            if ($coupon['send_num'] >= $coupon['createnum']) {
                Db::rollback();
                return json(['status' => 0, 'msg' => '该券已领完']);
            }
        }

        //检查是否已经领取

        $is_has_coupon = M('coupon_list')->where(array('cid' => $re_id, 'uid' => $this->user_id))->field('id')->find();

        if ($is_has_coupon) {
            Db::rollback();
            return json(['status' => 0, 'msg' => '已经领取过了']);
        }

        $bcoupon_list = true;
        $add = array();
        $add['cid'] = $coupon['id'];
        $add['type'] = $coupon['type'];
        $add['send_time'] = time();
        $add['uid'] = $this->user_id;
        do {
            $code = get_rand_str(8, 0, 1); //获取随机8位字符串

            $check_exist = M('coupon_list')->where(['code' => $code])->find();
        } while ($check_exist);

        $add['code'] = $code;

        $bcoupon_list = M('coupon_list')->add($add);

        $bcoupon = M('coupon')->where(array('id' => $re_id))->setInc('send_num', 1);
        if ($bcoupon_list && $bcoupon) {
            Db::commit();
            return json(['status' => 1, 'msg' => '领取成功']);
        } else {
            Db::rollback();
            return json(['status' => 0, 'msg' => '领取失败']);
        }
    }

    /*
     * 优惠券列表  -  下单页
     */
    public function couponAllList()
    {
        return json(['status' => 1, 'msg' => 'success', 'result' => array()]);

        $type = I('type', 0);
        $logic = new UsersLogic();
        $data = $logic->get_coupons($this->user_id, I('type'), null, 0, 100, true);
        foreach ($data['result'] as $k => $v) {

            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if (1 == $user_type || $user_type == 4) { //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id' => $v['cid']])->getField('goods_id');
            }
            if (2 == $user_type) { //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id' => $v['cid']])->getField('goods_category_id');
            }

            //折扣券
            if ($user_type == 4) {
                $coupon_money = $v['money'];
                $coupon_money = bcadd($coupon_money, 0, 1);
                $coupon_money = str_replace('.0', '', $coupon_money);
                $data['result'][$k]['money'] = $coupon_money . '折';
            }


        }
        $coupon_list = $data['result'];
        $return = [];
        $return['coupon_list'] = $coupon_list;
        $return['page'] = $data['show'];
        $return['active'] = 'coupon';
        $return['type'] = $type;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function couponAllListRe()
    {
        $type = I('type', 0);
        $logic = new UsersLogic();
        $data = $logic->get_coupons($this->user_id, I('type'), null, 0, 100, false);
        foreach ($data['result'] as $k => $v) {

            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if (1 == $user_type || $user_type == 4) { //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id' => $v['cid']])->getField('goods_id');
            }
            if (2 == $user_type) { //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id' => $v['cid']])->getField('goods_category_id');
            }
        }

        $coupon_list = $data['result'];
        $return = [];
        $return['coupon_list'] = $coupon_list;
        $return['page'] = $data['show'];
        $return['active'] = 'coupon';
        $return['type'] = $type;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 用户地址列表
     * @return \think\response\Json
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_ids = [];
        foreach ($address_lists as $k => $v) {
            $region_ids[$v['province']] = $v['province'];
            $region_ids[$v['city']] = $v['city'];
            $region_ids[$v['district']] = $v['district'];
            $region_ids[$v['twon']] = $v['twon'];
        }

        $region_list = M('region2')->where('id', 'IN', $region_ids)->getField('id,name');
        $data = [];
        $data['region_list'] = $region_list;
        $data['lists'] = $address_lists;
        $data['active'] = 'address_list';

        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }

    /**
     * 用户地址列表（新）
     * @return \think\response\Json
     */
    public function address_list_new()
    {
        $goodsId = I('goods_id', '');           // 商品ID
        $cartIds = I('cart_ids', '');           // 购物车ID组合
        if (!empty($goodsId) && empty(trim($cartIds))) {
            $goodsIds = [['goods_id' => $goodsId]];
            $GoodsLogic = new GoodsLogic();
        } elseif (empty($goodsId) && !empty(trim($cartIds))) {
            $goodsIds = (new CartLogic())->getCartGoods($cartIds, 'c.goods_id');
            $GoodsLogic = new GoodsLogic();
        }
        $userLogic = new UsersLogic();
        // 用户地址
        $addressList = get_user_address_list_new($this->user_id);
        // 地址标签
        $addressTab = $userLogic->getAddressTab($this->user_id);
        // 超出范围的地址
        $outRange = [];
        foreach ($addressList as $k1 => $value) {
            $addressList[$k1]['town_name'] = $value['town_name'] ?? '';
            $addressList[$k1]['is_illegal'] = 0;     // 非法地址
            $addressList[$k1]['out_range'] = 0;      // 超出配送范围
            $addressList[$k1]['limit_tips'] = '';    // 限制的提示
            $tabs = explode(',', $value['tabs']);
            unset($addressList[$k1]['tabs']);
            foreach ($addressTab as $k2 => $item) {
                $addressList[$k1]['tabs'][$k2] = [
                    'tab_id' => $item['tab_id'],
                    'name' => $item['name'],
                    'is_selected' => 0
                ];
                if (in_array($item['tab_id'], $tabs)) {
                    $addressList[$k1]['tabs'][$k2]['is_selected'] = 1;
                }
            }
            if ($value['is_default'] == 1) {
                $addressList[$k1]['tabs'][] = [
                    'tab_id' => 0,
                    'name' => '默认',
                    'is_selected' => 1
                ];
            }
            // 判断用户地址是否合法
            $userAddress = $userLogic->checkAddressIllegal($value);
            if ($userAddress['is_illegal'] == 1) {
                $addressList[$k1]['is_illegal'] = 1;
            }
            // 判断传入商品是否能在该地区配送
            if (!empty($goodsIds)) {
                $checkGoodsShipping = $GoodsLogic->checkGoodsListShipping($goodsIds, $value['district'], $value);
                foreach ($checkGoodsShipping as $shippingKey => $shippingVal) {
                    if (true != $shippingVal['shipping_able']) {
                        // 订单中部分商品不支持对当前地址的配送
                        $addressList[$k1]['out_range'] = 1;
                        if ($addressList[$k1]['is_illegal'] == 0) {
                            $outRange[] = $addressList[$k1];
                            unset($addressList[$k1]);
                        }
                        break;
                    }
                }
            }
            if ($userAddress['is_illegal'] == 1) {
                $addressList[$k1]['limit_tips'] = '当前地址信息不完整，请添加街道后补充完整地址信息再提交订单';
            } elseif ($userAddress['out_range'] == 1) {
                $addressList[$k1]['limit_tips'] = '当前地址不在配送范围内，请重新选择';
            }
        }
        $returnData = [
            'list' => array_values($addressList),
            'out_range_list' => $outRange
        ];
        return json(['status' => 1, 'msg' => 'success', 'result' => $returnData]);
    }

    /**
     * 用户地址信息
     * @return \think\response\Json
     */
    public function address_info()
    {
        $addressId = I('address_id', '');
        if (!$addressId) {
            return json(['status' => 0, 'msg' => '地址ID错误']);
        }
        // 用户地址
        $addressList = get_user_address_list_new($this->user_id, false, $addressId);
        // 地址标签
        $addressTab = (new UsersLogic())->getAddressTab($this->user_id);
        foreach ($addressList as $k1 => $value) {
            $addressList[$k1]['town_name'] = $value['town_name'] ?? '';
            $tabs = explode(',', $value['tabs']);
            unset($addressList[$k1]['tabs']);
            foreach ($addressTab as $k2 => $item) {
                $addressList[$k1]['tabs'][$k2] = [
                    'tab_id' => $item['tab_id'],
                    'name' => $item['name'],
                    'is_selected' => 0
                ];
                if (in_array($item['tab_id'], $tabs)) {
                    $addressList[$k1]['tabs'][$k2]['is_selected'] = 1;
                }
            }
        }
        return json(['status' => 1, 'msg' => 'success', 'result' => $addressList[0]]);
    }

    /**
     * 地址标签
     * @param Request $request
     * @return \think\response\Json
     */
    public function address_tab(Request $request)
    {
        if ($request->isPost()) {
            // 添加标签
            $action = $request->post('action', '');
            switch ($action) {
                case 'add':
                    $tabName = $request->post('tab_name', '');
                    if (!$tabName) {
                        return json(['status' => 0, 'msg' => '标签名不能为空']);
                    }
                    $res = (new UsersLogic())->addAddressTab($this->user_id, $tabName);
                    break;
                case 'del':
                    $tabId = I('tab_id', '');
                    if (!$tabId) {
                        return json(['status' => 0, 'msg' => '标签ID不能为空']);
                    }
                    $res = (new UsersLogic())->delAddressTab($this->user_id, $tabId);
                    break;
                default:
                    return json(['status' => 0, 'msg' => '行为错误']);
            }
            if ($res['status'] == 0) return json(['status' => 0, 'msg' => $res['msg']]);
            return json(['status' => 1, 'msg' => $res['msg']]);
        }
        $addressTab = (new UsersLogic())->getAddressTab($this->user_id);
        return json(['status' => 1, 'result' => $addressTab]);
    }

    /**
     * 设置新手机.
     *
     * @return mixed
     */
    public function set_mobile()
    {
        $mobile = I('post.mobile');
        $code = I('post.code');
        $scene = I('post.scene', 6);
        $session_id = I('unique_id', $this->userToken);
        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
        $res['status'] = 1;
        //验证手机和验证码
        if (1 == $res['status']) {
            // 验证手机格式
            if (!check_mobile($mobile)) {
                return json(['status' => -1, 'msg' => '手机号填写错误']);
            }
            // 验证有效性
            $userLogic = new UsersLogic();
            if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2)) {
                return json(['status' => -1, 'msg' => '手机号码已存在！不能绑定该手机号码']);
            }
            $is_consummate = $logic->is_consummate($this->user_id);
            if ($is_consummate) {
                return json(['status' => 1, 'msg' => '设置成功', 'result' => ['point' => $is_consummate]]);
            } else {
                // setcookie('uname',urlencode($post['nickname']),null,'/');
                return json(['status' => 1, 'msg' => '设置成功', 'result' => null]);
            }
        }
        return json(['status' => -1, 'msg' => $res['msg']]);
    }

    private function _hasRelationship($id)
    {
        $invite_uid = M('Users')->where('user_id', $id)->getField('first_leader');

        if ($invite_uid > 0) {
            if ($invite_uid == $this->user_id) {
                return true;
            }
            return $this->_hasRelationship($invite_uid);
        }

        return false;
    }

    /*
     * 设置推荐人
     */
    public function setInviteId(Request $request)
    {
        if ($request->isPost()) {

            if ($this->user['distribut_level'] >= 3) {
                return json(['status' => 0, 'msg' => '直销商不需要绑定推荐人', 'result' => null]);
            }

            if ($this->user['invite_uid'] > 0) {
                return json(['status' => 0, 'msg' => '你已经有推荐人了，不能重复设置', 'result' => null]);
            }

            $id = I('post.id/d');

            if ($id < 1) {
                return json(['status' => 0, 'msg' => '缺少传参', 'result' => null]);
            }

            if ($id == $this->user_id) {
                return json(['status' => 0, 'msg' => '不能设置成自己', 'result' => null]);
            }

            $userInfo = M('Users')->find($id);
            if (empty($userInfo)) {
                return json(['status' => 0, 'msg' => '推荐人ID有误', 'result' => null]);
            }
            if ($userInfo['is_lock'] == 1) {
                return json(['status' => 0, 'msg' => '推荐人账号已经冻结了', 'result' => null]);
            }
            if ($userInfo['is_cancel'] == 1) {
                return json(['status' => 0, 'msg' => '推荐人账号已经注销了', 'result' => null]);
            }
            if ($this->_hasRelationship($id)) {
                return json(['status' => 0, 'msg' => '不能绑定和自己有关系的普通会员', 'result' => null]);
            }

            $data = [];
            $data['invite_uid'] = $data['first_leader'] = $id;
            $data['invite_time'] = time();
            $data['second_leader'] = $userInfo['first_leader'];
            $data['third_leader'] = $userInfo['second_leader'];

            M('users')->where('first_leader', $this->user_id)->update(['second_leader' => $data['first_leader'], 'third_leader' => $data['second_leader']]);
            M('users')->where('second_leader', $this->user_id)->update(['third_leader' => $data['first_leader']]);
            M('users')->where(['user_id' => $this->user_id])->save($data);
            // 更新缓存
            $user = Db::name('users')->where('user_id', $this->user_id)->find();
            TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);

            // 邀请送积分
            $invite_integral = tpCache('basic.invite_integral');
            accountLog($id, 0, $invite_integral, '邀请用户奖励积分', 0, 0, '', 0, 7, false);

            // 邀请任务
//            $TaskLogic = new \app\common\logic\TaskLogic(2);
//            $TaskLogic->setUser($user);
//            $TaskLogic->setDistributId($user);
//            $TaskLogic->doInviteAfter();

            return json(['status' => 1, 'msg' => 'success', 'result' => null]);
        }
        $return['invite_uid'] = $this->user['invite_uid'];

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 获取地址
     */
    public function get_address()
    {
        header('Content-type:text/html;charset=utf-8');

        $id = I('get.id/d');
        $address = Db::name('user_address')->where(['address_id' => $id, 'user_id' => $this->user_id])->find();
        $region_ids = [];
        $region_ids[$address['province']] = $address['province'];
        $region_ids[$address['city']] = $address['city'];
        $region_ids[$address['district']] = $address['district'];
        $region_ids[$address['twon']] = $address['twon'];
        if (empty($address)) {
            return json(['status' => 0, 'msg' => '地址不存在', 'result' => null]);
        }

        $return = [];
        $region_list = M('region2')->where('id', 'IN', $region_ids)->getField('id,name');
        $return['region_list'] = $region_list;
        $return['address'] = $address;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 添加地址
     */
    public function add_address(Request $request)
    {
        header('Content-type:text/html;charset=utf-8');

        if (!$request->isPost()) {
            $p = Db::name('region2')->where(['parent_id' => 0, 'level' => 1])->select();
            $return = [];
            $return['province'] = $p;

            return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
        }

        $logic = new UsersLogic();
        $data = $logic->add_address($this->user_id, 0, I('post.'));
        if (1 != $data['status']) {
            return json(['status' => 0, 'msg' => '添加地址失败,' . $data['msg'], 'result' => null]);
        }
        $addressList = get_user_address_list_new($this->user_id, false, $data['result']);
        $address = $addressList[0];
        unset($address['zipcode']);
        unset($address['is_pickup']);
        unset($address['tabs']);
        return json(['status' => 1, 'msg' => '添加地址成功', 'result' => ['user_address' => $address]]);
    }

    /*
     * 地址编辑
     */
    public function edit_address(Request $request)
    {
        header('Content-type:text/html;charset=utf-8');

        if (!$request->isPost()) {
            return $this->get_address();
        }

        $id = I('get.id/d');

        $logic = new UsersLogic();
        $data = $logic->add_address($this->user_id, $id, I('post.'));
        if (1 != $data['status']) {
            return json(['status' => 0, 'msg' => '修改地址失败,' . $data['msg'], 'result' => null]);
        }
        $addressList = get_user_address_list_new($this->user_id, false, $data['result']);
        $address = $addressList[0];
        $address['town_name'] = $address['town_name'] ?? '';
        unset($address['zipcode']);
        unset($address['is_pickup']);
        unset($address['tabs']);
        return json(['status' => 1, 'msg' => '修改地址成功', 'result' => ['user_address' => $address]]);
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('id/d');
        Db::name('user_address')->where(['user_id' => $this->user_id])->save(['is_default' => 0]);
        $row = Db::name('user_address')->where(['user_id' => $this->user_id, 'address_id' => $id])->save(['is_default' => 1]);
        if (!$row) {
            return json(['status' => 0, 'msg' => '操作失败', 'result' => null]);
        }
        return json(['status' => 1, 'msg' => '操作成功', 'result' => null]);
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('id/d');

        $address = Db::name('user_address')->where('address_id', $id)->find();
        $row = Db::name('user_address')->where(['user_id' => $this->user_id, 'address_id' => $id])->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if (1 == $address['is_default']) {
            $address2 = Db::name('user_address')->where('user_id', $this->user_id)->find();
            $address2 && Db::name('user_address')->where('address_id', $address2['address_id'])->save(['is_default' => 1]);
        }
        if (!$row) {
            return json(['status' => 0, 'msg' => '操作失败', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '操作成功', 'result' => null]);
    }

    /**
     * 检测地址
     * @return \think\response\Json
     */
    public function checkAddress()
    {
        $id = I('get.id/d');
        $goodsId = I('goods_id');
        $cartIds = I('cart_ids');
        if (!empty($goodsId) && empty(trim($cartIds))) {
            $goodsIds = [['goods_id' => $goodsId]];
            $GoodsLogic = new GoodsLogic();
        } elseif (empty($goodsId) && !empty(trim($cartIds))) {
            $goodsIds = (new CartLogic())->getCartGoods($cartIds, 'c.goods_id');
            $GoodsLogic = new GoodsLogic();
        }
        $userAddress = Db::name('user_address')->where(['user_id' => $this->user_id, 'address_id' => $id])->find();
        if (empty($userAddress)) {
            // 默认地址
            $addressList = get_user_address_list_new($this->user_id, true);
            $userAddress = $addressList[0];
        }
        $userAddress['town_name'] = $userAddress['town_name'] ?? '';
        $userAddress['is_illegal'] = 0;     // 非法地址
        $userAddress['out_range'] = 0;      // 超出配送范围
        $userAddress['limit_tips'] = '';    // 限制的提示
        unset($userAddress['zipcode']);
        unset($userAddress['is_pickup']);
        unset($userAddress['tabs']);
        // 判断用户地址是否合法
        $userLogic = new UsersLogic();
        $userAddress = $userLogic->checkAddressIllegal($userAddress);
        // 判断传入商品是否能在该地区配送
        if (!empty($goodsIds)) {
            $checkGoodsShipping = $GoodsLogic->checkGoodsListShipping($goodsIds, $userAddress['district'], $userAddress);
            foreach ($checkGoodsShipping as $shippingKey => $shippingVal) {
                if (true != $shippingVal['shipping_able']) {
                    // 订单中部分商品不支持对当前地址的配送
                    $userAddress['out_range'] = 1;
                    break;
                } else {
                    $userAddress['out_range'] = 0;
                }
            }
        }
        if ($userAddress['is_illegal'] == 1) {
            $userAddress['limit_tips'] = '当前地址信息不完整，请添加街道后补充完整地址信息再提交订单';
        } elseif ($userAddress['out_range'] == 1) {
            $userAddress['limit_tips'] = '当前地址不在配送范围内，请重新选择';
        }
        $return = ['need_update' => 1, 'user_address' => $userAddress];

        return json(['status' => 1, 'result' => $return]);
    }

    // 个人信息
    public function info(Request $request)
    {
        // 获取用户信息
//        $userLogic = new UsersLogic();
//        $user_info = $userLogic->get_info($this->user_id);
//        $user_info = $user_info['result'];
        $user_info = [
            'user_id' => $this->user['user_id'],
            'sex' => $this->user['sex'],
            'real_name' => $this->user['real_name'],
            'id_cart' => $this->user['id_cart'],
            'birthday' => $this->user['birthday'],
            'mobile' => $this->user['mobile'],
            'head_pic' => $this->user['head_pic'],
            'is_not_show_jk' => $this->user['is_not_show_jk'],
            'type' => $this->user['type']
        ];

        $data = [];

        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount($this->user_id);
        $data['user_message_count'] = $user_message_count;

        //获取用户活动信息的数量

        $articleLogic = new ArticleLogic();
        $user_article_count = $articleLogic->getUserArticleCount($this->user_id);
        $data['user_article_count'] = $user_article_count;

        //用户中心面包屑导航
        // $navigate_user = navigate_user();
        // $data['navigate_user'] = $navigate_user;

        if ($request->isPost()) {
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = I('post.birthday') : false;  // 生日
            I('post.id_cart') ? $post['id_cart'] = I('post.id_cart') : false;  // 身份证
            I('post.real_name') ? $post['real_name'] = I('post.real_name') : false;  // 真实姓名

            $userLogic = new UsersLogic();
            if (!$userLogic->update_info($this->user_id, $post)) {
                return json(['status' => 0, 'msg' => '操作失败', 'result' => null]);
            }
            setcookie('uname', urlencode($post['nickname']), null, '/');

            $is_consummate = $userLogic->is_consummate($this->user_id);
            if ($is_consummate !== false) {
                return json(['status' => 1, 'msg' => '操作成功', 'result' => array('point' => $is_consummate, 'is_get_point' => 1)]);
            } else {
                return json(['status' => 1, 'msg' => '操作成功', 'result' => null]);
            }

            exit;
        }
        /*        //  获取省份
                $province = Db::name('region2')->where(array('parent_id'=>0,'level'=>1))->select();
                //  获取订单城市
                $city =  Db::name('region2')->where(array('parent_id'=>$user_info['province'],'level'=>2))->select();
                //获取订单地区
                $area =  Db::name('region2')->where(array('parent_id'=>$user_info['city'],'level'=>3))->select();

                $data['province'] = $province;
                $data['city'] = $city;
                $data['area'] = $area;*/
        $data['user'] = $user_info;
        $data['sex'] = C('SEX');
        $data['active'] = 'info';


        //是否弹出金卡提示框
        $data['is_show_apply_jk'] = 0;
        if ($user_info['is_not_show_jk'] == 0) {
            $logic = new UsersLogic();
            $check_apply_customs = $logic->check_apply_customs($user_info['user_id']);
            if ($check_apply_customs) {
                $data['is_show_apply_jk'] = 1;
            }
        }


        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }

    // 修改银行卡信息
    public function updateCart(Request $request)
    {
        if ($request->isPost()) {
            $userLogic = new UsersLogic();
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'update_cart')) {
                $res = ['status' => 0, 'msg' => '验证码错误'];

                return json($res);
            }

            I('post.id_cart') ? $post['id_cart'] = I('post.id_cart') : false;  // 身份证
            I('post.real_name') ? $post['real_name'] = I('post.real_name') : false;  // 真实姓名
            I('post.bank_name') ? $post['bank_name'] = I('post.bank_name') : false;  // 收款银行 (银行名称)
            I('post.bank_card') ? $post['bank_card'] = I('post.bank_card') : false;  // 收款账户 (银行账号)

            if ($post['id_cart'] && !check_id_card($post['id_cart'])) {
                return json(['status' => 0, 'msg' => '请填写正确的身份证格式']);
            }

            if ($post['bank_card'] && !checkBankCard($post['bank_card'])) {
                return json(['status' => 0, 'msg' => '请填写正确的银行卡卡号']);
            }

            if (!$userLogic->update_info($this->user_id, $post)) {
                return json(['status' => 0, 'msg' => '操作失败', 'result' => null]);
            }

            // setcookie('uname',urlencode($post['nickname']),null,'/');
            $is_consummate = $userLogic->is_consummate($this->user_id);
            if ($is_consummate !== false) {
                return json(['status' => 1, 'msg' => '操作成功', 'result' => array('point' => $is_consummate, 'is_get_point' => 1)]);
            } else {
                // setcookie('uname',urlencode($post['nickname']),null,'/');
                return json(['status' => 1, 'msg' => '操作成功', 'result' => null]);
            }
        }

        return json(['status' => -1, 'msg' => '请求方式出错', 'result' => null]);
    }

    // 获取银行卡信息
    public function getCartInfo()
    {
        $info = M('Users')->field('id_cart,real_name,bank_name,bank_card')->where('user_id', $this->user_id)->find();

        return json(['status' => 1, 'msg' => '操作成功', 'result' => $info]);
    }

    //添加自提点
    public function save_pickup()
    {
        $post = I('post.');
        if (empty($post['consignee'])) {
            return json(['status' => -1, 'msg' => '收货人不能为空', 'result' => '']);
        }
        if (!$post['province'] || !$post['city'] || !$post['district']) {
            return json(['status' => -1, 'msg' => '所在地区不能为空', 'result' => '']);
        }
        if (!check_mobile($post['mobile'])) {
            return json(['status' => -1, 'msg' => '手机号码格式有误', 'result' => '']);
        }
        if (!$post['pickup_id']) {
            return json(['status' => -1, 'msg' => '请选择自提点', 'result' => '']);
        }

        $user_logic = new UsersLogic();
        $res = $user_logic->add_pick_up($this->user_id, $post);
        if (1 != $res['status']) {
            return json($res);
        }
        $call_back = $_REQUEST['call_back'];
        $return = [];
        $return['call_back'] = $call_back;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 邮箱验证
     */
    public function email_validate(Request $request)
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        if ($request->isPost()) {
            $email = I('post.email');
            $old_email = I('post.old_email', ''); //旧邮箱
            $code = I('post.code');
            $info = session('validate_code');
            if (!$info) {
                return json(['status' => 0, 'msg' => '非法操作', 'result' => null]);
            }
            if ($info['time'] < time()) {
                session('validate_code', null);

                return json(['status' => 0, 'msg' => '验证超时，请重新验证', 'result' => null]);
            }
            //检查原邮箱是否正确
            if (1 == $user_info['email_validated'] && $old_email != $user_info['email']) {
                return json(['status' => 0, 'msg' => '原邮箱匹配错误', 'result' => null]);
            }
            //验证邮箱和验证码
            if ($info['sender'] == $email && $info['code'] == $code) {
                session('validate_code', null);
                if (!$userLogic->update_email_mobile($email, $this->user_id)) {
                    return json(['status' => 0, 'msg' => '邮箱已存在', 'result' => null]);
                }

                return json(['status' => 1, 'msg' => '绑定成功', 'result' => null]);
            }

            return json(['status' => 0, 'msg' => '邮箱验证码不匹配', 'result' => null]);
        }
        $return = [];

        $return['user_info'] = $user_info;
        $return['step'] = $step;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 手机验证
     *
     * @return mixed
     */
    public function mobile_validate(Request $request)
    {
        if ($request->isPost()) {
            $user_info = $this->user;
            $config = tpCache('sms');
            $sms_time_out = $config['sms_time_out'];

            $return = [];

            $return['time'] = $sms_time_out;

            $old_mobile = I('post.old_mobile');
            $mobile = I('post.mobile');
            $confirm_mobile = I('post.confirm_mobile');
            if (!$mobile) $mobile = $confirm_mobile;
            $code = I('post.code');
            $scene = I('post.scene', 6);
            $session_id = I('unique_id', $this->userToken);
            $step = I('step', 1);
            $logic = new UsersLogic();
            if (1 == $step) {
                $res = $logic->check_validate_code($code, $old_mobile, 'phone', $session_id, $scene);
                if (!$res || 1 != $res['status']) {
                    return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
                }

                //检查原手机是否正确
                if (1 == $user_info['mobile_validated'] && $old_mobile != $user_info['mobile']) {
                    return json(['status' => 0, 'msg' => '原手机号码错误', 'result' => null]);
                }
                if (1 == $res['status']) {
                    return json(['status' => 1, 'msg' => 'success', 'result' => null]);
                }

                return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
            } elseif (2 == $step) {
                if ($this->isApp || $this->isApplet) {
                    $old_mobile = $mobile;
                    $res = $logic->check_validate_code($code, $old_mobile, 'phone', $session_id, $scene);
                    if (!$res || 1 != $res['status']) {
                        return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
                    }
                }

                //验证有效期
                if (!$logic->update_email_mobile($mobile, $this->user_id, 2)) {
                    return json(['status' => 0, 'msg' => '手机已存在', 'result' => null]);
                }

                return json(['status' => 1, 'msg' => '修改成功', 'result' => null]);
            }
        }

        return json(['status' => -1, 'msg' => '请求方式出错', 'result' => null]);
    }

    /**
     * 检查用户有没有设置手机.
     */
    public function isSetMobile()
    {
        $user_info = $this->user;
        if (empty($user_info['mobile'])) {
            return json(['status' => 0, 'msg' => 'fail', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => null]);
    }

    /*
     *商品收藏
     */
    public function goods_collect()
    {
        $userLogic = new UsersLogic();
        $source = $this->isApp ? 3 : ($this->isApplet ? 4 : 1);
        $data = $userLogic->get_goods_collect($this->user_id, $source);
        $return = [];
        $return['page'] = $data['show']; // 赋值分页输出
        $return['lists'] = $data['result'];
        $return['active'] = 'goods_collect';

        return json(['status' => 1, 'msg' => '获取成功', 'result' => $return]);
    }

    /*
     * 删除一个收藏商品
     */
    public function del_goods_collect()
    {
        $ids = I('get.ids', '');
        if (!$ids) {
            return json(['status' => 0, 'msg' => '请至少选择一个商品', 'result' => null]);
        }
        $collect_ids = explode(',', $ids);
        if (!$collect_ids) {
            return json(['status' => 0, 'msg' => 'IDS参数非法', 'result' => null]);
        }
        $row = Db::name('goods_collect')->where(['collect_id' => ['IN', $collect_ids], 'user_id' => $this->user_id])->delete();
        if (!$row) {
            return json(['status' => 0, 'msg' => '删除失败', 'result' => null]);
        }
        return json(['status' => 1, 'msg' => '删除成功', 'result' => null]);
    }

    /**
     * （移除）收藏商品
     * @return \think\response\Json
     */
    public function ajax_goods_collect()
    {
        $goods_id = I('get.goods_id', 0);
        if (!$goods_id) {
            return json(['status' => 0, 'msg' => '缺少goods_id参数', 'result' => null]);
        }
        $status = I('get.status', 0);
        if (0 == $status) {
            // 移除收藏
            M('GoodsCollect')->where(['user_id' => $this->user_id, 'goods_id' => $goods_id])->delete();
            $msg = '移除收藏商品成功';
        } else {
            // 添加收藏
            $goodsLogic = new GoodsLogic();
            $res = $goodsLogic->collect_goods($this->user_id, $goods_id);
            if ($res['status'] !== 1) {
                return json(['status' => $res['status'], 'msg' => $res['msg'], 'result' => null]);
            }
            $msg = '添加收藏商品成功';
        }

        return json(['status' => 1, 'msg' => $msg, 'result' => null]);
    }

    /**
     * 收藏商品添加到购物车
     * @return \think\response\Json
     */
    public function collect_goods_cart()
    {
        $id = I('post.id', null);
        if (!$id) {
            return json(['status' => 0, 'msg' => '缺少id参数', 'result' => null]);
        }
        // 收藏信息
        $collect = Db::name('goods_collect')->where(['collect_id' => $id, 'user_id' => $this->user_id])->find();
        if (!$collect) {
            return json(['status' => 0, 'msg' => '收藏信息已失效', 'result' => null]);
        }
        Db::startTrans();
        try {
            // 删除收藏
            Db::name('goods_collect')->where(['collect_id' => $id, 'user_id' => $this->user_id])->delete();
            // 添加到购物车
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($this->user_id);
            $cartLogic->setUserToken($this->userToken);
            $cartLogic->setGoodsModel($collect['goods_id']);
            $cartLogic->setGoodsBuyNum(1);
            $cartLogic->addGoodsToCart($this->isApp);
            Db::commit();
            return json(['status' => 1, 'msg' => '添加成功', 'result' => null]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['status' => 0, 'msg' => $e->getMessage(), 'result' => null]);
        }
    }

    /*
     * 密码修改
     */
    public function password(Request $request)
    {
        //检查是否第三方登录用户
//        $logic = new UsersLogic();
//        $data = $logic->get_info($this->user_id);
//        $user = $data['result'];
        if ('' == $this->user['mobile'] && '' == $this->user['email']) {
            return json(['status' => 0, 'msg' => '请先绑定手机或邮箱', 'result' => null]);
        }
        if ($request->isPost()) {
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password')); // 获取用户信息
            if (-1 == $data['status']) {
                return json(['status' => 0, 'msg' => $data['msg'], 'result' => null]);
            }

            return json(['status' => 1, 'msg' => $data['msg'], 'result' => null]);
        }

        return json(['status' => 0, 'msg' => '请求方式出错', 'result' => null]);
    }

    /**
     * 找回密码
     * @return \think\response\Json
     */
    public function findPassword()
    {
        $mobile = I('mobile', '');
        $code = I('code', '');
        $session_id = S('mobile_token_' . $mobile);
        if ($mobile) {
            $user = M('users')->where(['mobile' => $mobile])->find();
        }
        if (empty($user)) {
            return json(['status' => 0, 'msg' => '账号不存在']);
        }
        $userLogic = new UsersLogic();
        // 验证验证码
        $res = $userLogic->check_validate_code($code, $user['mobile'], 'phone', $session_id, 6);
        if (1 != $res['status']) {
            return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
        }
        // 重置密码
        $passAuth = $this->isApp ? true : ($this->isApplet ? true : false);
        $data = $userLogic->resetPassword($user['user_id'], I('post.password'), null, $passAuth);
        if (-1 == $data['status']) {
            return json(['status' => 0, 'msg' => $data['msg'], 'result' => $data['result']]);
        }
        return json(['status' => 1, 'msg' => '恭喜！已修改完成', 'result' => null]);
    }

    public function changeType()
    {
        $type = I('post.type', 1);
        $data['type'] = $type;

        $will_invite_uid = $this->user['will_invite_uid'];
        $will_invite_uid = $will_invite_uid ?: 0;

        if ($will_invite_uid == $this->user['user_id']) {
            $will_invite_uid = 0;
        }

        $data['invite_uid'] = $will_invite_uid;
        $data['will_invite_uid'] = 0;

        $user_type = $this->user['type'];
        if (0 != $user_type) {
            return json(['status' => -1, 'msg' => '该用户已经选择类型，无法继续选定', 'result' => null]);
        }
        if (1 == $type) {
            // 频繁请求
            $res = cache($this->user_id . 'changeType');
            if ($res) {
                $return = [
                    'user_id' => $this->user_id,
                    'integral' => '0',
                    'point' => '0',
                ];
                return json(['status' => 1, 'msg' => '新账号认证成功', 'result' => $return]);
            }
            cache($this->user_id . 'changeType', 1, 5);

            // 会员注册赠送积分
            $pay_points = tpCache('basic.reg_integral');
            if ($pay_points > 0) {
                accountLog($this->user['user_id'], 0, $pay_points, '会员注册赠送积分', 0, 0, '', 0, 6);
            }
            // 新会员赠送优惠券
            $CouponLogic = new CouponLogic();
            $CouponLogic->sendNewUser($this->user['user_id']);

            if ($will_invite_uid > 0) {
                $first_leader = Db::name('users')->where("user_id = {$will_invite_uid}")->find();
                if ($first_leader['is_lock'] == 1) {
                    return json(['status' => -1, 'msg' => '推荐人账号已经冻结了', 'result' => null]);
                }
                if ($first_leader['is_cancel'] == 1) {
                    return json(['status' => -1, 'msg' => '推荐人账号已经注销了', 'result' => null]);
                }
                if ($this->_hasRelationship($will_invite_uid)) {
                    return json(['status' => -1, 'msg' => '不能绑定和自己有关系的普通会员', 'result' => null]);
                }

                $data['first_leader'] = $first_leader['user_id'];
                $data['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
                $data['third_leader'] = $first_leader['second_leader']; // 第二级推荐人

                //他上线分销的下线人数要加1
                Db::name('users')->where(['user_id' => $data['first_leader']])->setInc('underling_number');
                Db::name('users')->where(['user_id' => $data['second_leader']])->setInc('underling_number');
                Db::name('users')->where(['user_id' => $data['third_leader']])->setInc('underling_number');

                // 邀请送积分
                $invite_integral = tpCache('basic.invite_integral');
                accountLog($will_invite_uid, 0, $invite_integral, '邀请用户奖励积分', 0, 0, '', 0, 7, false);

                $data['invite_time'] = time();

                M('Users')->where('user_id', $this->user_id)->save($data);

                // 邀请人记录
                inviteLog($will_invite_uid, $this->user_id, 1, $data['invite_time']);

                // 邀请任务
//                $user = M('users')->find($this->user_id);
//                $TaskLogic = new \app\common\logic\TaskLogic(2);
//                $TaskLogic->setUser($user);
//                $TaskLogic->setDistributId($user['distribut_level']);
//                $TaskLogic->doInviteAfter();
            } else {
                M('Users')->where('user_id', $this->user_id)->save($data);
            }
        }
        // 更新缓存
        $user = Db::name('users')->where('user_id', $this->user_id)->find();
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
        $return = [
            'user_id' => $this->user_id,
            'integral' => $invite_integral ?? '0',
            'point' => $pay_points ?? '0',
        ];

        return json(['status' => 1, 'msg' => '新账号认证成功', 'result' => $return]);
    }

    // 获取我的邀请码
    public function invite()
    {
        $params['user_token'] = isset($this->userToken) ? $this->userToken : null;
        Hook::exec('app\\home\\behavior\\CheckValid', 'run', $params);
        Url::root('/');
        $baseUrl = url('/', '', '', true);

        $filename = 'public/images/qrcode/user/user_' . $this->user_id . '.png';

        $return['qr_img'] = $filename;
        $return['user_id'] = $this->user_id;
        $return['basic_url'] = $baseUrl;
        if (file_exists($filename)) {
            return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
        }
        $this->scerweima($this->user_id);

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    private function scerweima($user_id)
    {
        Loader::import('phpqrcode', EXTEND_PATH);

        Url::root('/');
        $baseUrl = url('/', '', '', true);

        $url = $baseUrl . '/#/register?invite=' . $user_id;

        $value = $url;                  //二维码内容

        $errorCorrectionLevel = 'L';    //容错级别
        $matrixPointSize = 10;           //生成图片大小

        //生成二维码图片
        $filename = 'public/images/qrcode/user/user_' . $user_id . '.png';

        \QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 2);

        // $QR = $filename;                //已经生成的原始二维码图片文件

        // $QR = imagecreatefromstring(file_get_contents($QR));

        // //输出图片
        // imagepng($QR, 'qrcode.png');
        // imagedestroy($QR);
        // return '<img src="qrcode.png" alt="使用微信扫描支付">';
    }

    /**
     * 删除我的足迹
     * @return \think\response\Json
     */
    public function del_visit_log()
    {
        $id = I('id', '');
        $goodsLogic = new Goodslogic();
        $goodsLogic->del_visit_log($id);

        return json(['status' => 1, 'msg' => '删除用户足迹成功']);
    }

    /**
     * 我的足迹.
     *
     * @author lxl
     * @time  17-4-20
     * 拷多商家User控制器
     * */
    public function visit_log()
    {
        $cat_id = I('cat_id', 0);
        $map['user_id'] = $this->user_id;
        if ($cat_id > 0) {
            $map['a.cat_id'] = $cat_id;
        }
        $count = Db::name('goods_visit a')->where($map)->count();
        $Page = new Page($count, 10);
        $visit_list = Db::name('goods_visit a')->field('a.*,g.goods_name,g.shop_price,g.exchange_integral,g.original_img, g.shop_price - g.exchange_integral as point_price')
            ->join('__GOODS__ g', 'a.goods_id = g.goods_id', 'LEFT')
            ->where($map)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('a.visittime desc')
            ->select();
        $visit_log = $cates = [];
        $visit_total = 0;
        if ($visit_list) {
            $now = time();
            $endLastweek = mktime(23, 59, 59, date('m'), date('d') - date('w') + 7 - 7, date('Y'));
            $weekarray = ['日', '一', '二', '三', '四', '五', '六'];
            foreach ($visit_list as $k => $val) {
                $visit_list[$k]['original_img_new'] = getFullPath($val['original_img']);
                if ($now - $val['visittime'] < 3600 * 24 * 7) {
                    if (date('Y-m-d') == date('Y-m-d', $val['visittime'])) {
                        $val['date'] = '今天';
                    } else {
                        if ($val['visittime'] < $endLastweek) {
                            $val['date'] = '上周' . $weekarray[date('w', $val['visittime'])];
                        } else {
                            $val['date'] = '周' . $weekarray[date('w', $val['visittime'])];
                        }
                    }
                } else {
                    $val['date'] = '更早以前';
                }
                $visit_log[$val['date']][] = $val;
            }
            $cates = Db::name('goods_visit a')->field('cat_id,COUNT(cat_id) as csum')->where($map)->group('cat_id')->select();
            $cat_ids = get_arr_column($cates, 'cat_id');
            $cateArr = Db::name('goods_category')->whereIN('id', array_unique($cat_ids))->getField('id,name'); //收藏商品对应分类名称
            foreach ($cates as $k => $v) {
                if (isset($cateArr[$v['cat_id']])) {
                    $cates[$k]['name'] = $cateArr[$v['cat_id']];
                }
                $visit_total += $v['csum'];
            }
        }
        $return['visit_total'] = $visit_total;
        $return['catids'] = $cates;
        $return['page'] = $Page->show();
        $return['visit_log'] = $visit_log; //浏览录
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 获取我的足迹（新）
     * @return \think\response\Json
     */
    public function visit_log_new()
    {
        $map = ['user_id' => $this->user_id];
        $count = Db::name('goods_visit a')->where($map)->count();
        $Page = new Page($count, 10);
        $visitList = Db::name('goods_visit a')
            ->join('__GOODS__ g', 'a.goods_id = g.goods_id', 'LEFT')
            ->field('a.visit_id, a.visittime, g.goods_id, g.goods_name, g.shop_price, g.exchange_integral, g.original_img, g.goods_remark')
            ->where($map)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('a.visittime desc')
            ->select();
        // 处理数据
        $return = [];
        $visitLog = [];
        foreach ($visitList as $k => $item) {
            $visitList[$k]['original_img_new'] = getFullPath($item['original_img']);
            $visitTime = date('Y-m-d', $item['visittime']);
            // 判断访问时间
            if ($visitTime == date('Y-m-d', time())) {
                $key = '今天';
            } elseif ($visitTime == date('Y-m-d', time() - (86400))) {
                $key = '昨天';
            } elseif ($visitTime == date('Y-m-d', time() - (86400 * 2))) {
                $key = '前天';
            } else {
                $key = $visitTime;
            }
            if (!isset($visitLog[$key]['date'])) {
                $visitLog[$key]['date'] = $key;
            }
            // 处理显示金额
            if ($item['exchange_integral'] != 0) {
                $visitList[$k]['exchange_price'] = bcdiv(bcsub(bcmul($item['shop_price'], 100), bcmul($item['exchange_integral'], 100)), 100, 2);
            } else {
                $visitList[$k]['exchange_price'] = $item['shop_price'];
            }
            $visitLog[$key]['data'][] = $visitList[$k];
        }
        $return['visit_log'] = array_values($visitLog);
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    // 用户电子币明细
    public function electronic()
    {
        $user = session('user');
        $type = I('type');
        $order_sn = I('order_sn');
        $logic = new UsersLogic();
        $data = $logic->get_electronic_log($this->user_id, $type, $order_sn);

        $account_log = $data['result'];
        // foreach ($account_log as $k => $v) {
        //     $account_log[$k]['change_time'] = date('Y.m.d',$v['change_time']);
        //     $account_log[$k]['date'] = date('m',$v['change_time']);
        // }
        $return = [];
        $return['account_log'] = $account_log;
        $return['page'] = $data['show'];
        $return['active'] = 'account';

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }


    public function electronicNew()
    {
        $type = I('type');

        $userLogic = new UsersLogic();
        $result = $userLogic->get_electronic_log($this->user_id, $type);
        print_r($result);
    }

    // 用户余额转电子币
    public function exchangeElectronic()
    {
        $user = session('user');
        $electronic = I('electronic', 0);
        if ($electronic < 1) {
            return json(['status' => 0, 'msg' => '传成电子币的数额不能小于1', 'result' => null]);
        }
        $withdrawal = M('withdrawals')->where('user_id', $this->user_id)->where('status', 0)->value('money');
        $user_money = M('Users')->where('user_id', $this->user_id)->getField('user_money');
        if ($withdrawal && $user_money - $withdrawal < $electronic) {
            return json(['status' => 0, 'msg' => '你有一笔余额正在申请提现，因此你的余额不够' . $electronic]);
        } elseif ($user_money < $electronic) {
            return json(['status' => 0, 'msg' => '你的余额不够' . $electronic]);
        }
        $user_money = M('Users')->where('user_id', $user['user_id'])->getField('user_money');
        if ($user_money < $electronic) {
            return json(['status' => 0, 'msg' => '你的余额不够' . $electronic, 'result' => null]);
        }
        Db::startTrans();
        accountLog($user['user_id'], 0, 0, '用户余额转电子币', 0, 0, '', $electronic, 13);
        $res = accountLog($user['user_id'], -$electronic, 0, '电子币充值（余额转）', 0, 0, '', 0, 13);
        if (!$res) {
            Db::rollback();
            return json(['status' => 0, 'msg' => '电子币充值（余额转）失败']);
        }
        Db::commit();
        return json(['status' => 1, 'msg' => '电子币充值成功', 'result' => null]);
    }

    // 用户电子币转向其他会员
    public function transfer()
    {
        $user = session('user');
        $electronic = I('electronic', 0);
        if ($electronic < 1) {
            return json(['status' => 0, 'msg' => '传成电子币的数额不能小于1', 'result' => null]);
        }
        $to_user_id = I('to_user_id', 0);
        if ($to_user_id < 1) {
            return json(['status' => 0, 'msg' => '缺少转让用户参数', 'result' => null]);
        }
        $is_exists = M('Users')->find($to_user_id);
        if (!$is_exists) {
            return json(['status' => 0, 'msg' => '转账用户不存在，请重新确认', 'result' => null]);
        }
        $user_electronic = M('Users')->where('user_id', $user['user_id'])->getField('user_electronic');

        if ($user_electronic < $electronic) {
            return json(['status' => 0, 'msg' => '你的电子币不够' . $electronic, 'result' => null]);
        }
        Db::startTrans();
        $res = accountLog($user['user_id'], 0, 0, "转出电子币给用户$to_user_id", 0, 0, '', -$electronic, 13);
        if (!$res) {
            Db::rollback();
            return json(['status' => 0, 'msg' => "转出电子币给用户$to_user_id" . '失败', 'result' => null]);
        }
        accountLog($to_user_id, 0, 0, "来自用户{$user['user_id']}的赠送", 0, 0, '', $electronic, 13, false);
        Db::commit();
        return json(['status' => 1, 'msg' => '电子币转帐成功', 'result' => null]);
    }

    /**
     * 电子币转让（新）
     * @return \think\response\Json
     */
    public function transferElectronicNew()
    {
        if ($this->request->isPost()) {
            $electronic = I('electronic', 0);
            $toUser = I('user_name', '');
            $payPwd = I('pay_pwd', '');
            if (empty($this->user['paypwd'])) {
                return json(['status' => 0, 'msg' => '未设置支付密码']);
            }
            if ($this->user['paypwd'] !== systemEncrypt($payPwd)) {
                return json(['status' => 0, 'msg' => '密码错误']);
            }
            if ($electronic < 1) {
                return json(['status' => 0, 'msg' => '转让电子币的数额不能小于1']);
            }
            if ($electronic % 100 != 0) {
                return json(['status' => 0, 'msg' => '转让电子币的数额必须是100的倍数']);
            }
            if (!$toUser) {
                return json(['status' => 0, 'msg' => '缺少转让用户参数']);
            }
            if (check_mobile($toUser)) {
                $toUser = Db::name('users')->where(['mobile' => $toUser])->find();
            } else {
                $toUser = Db::name('users')->where(['user_id' => $toUser])->find();
            }
            if (!$toUser) {
                return json(['status' => 0, 'msg' => '无此用户']);
            }
            $userElectronic = M('Users')->where('user_id', $this->user_id)->getField('user_electronic');
            if ($userElectronic < $electronic) {
                return json(['status' => 0, 'msg' => '你的电子币不够' . $electronic]);
            }
            Db::startTrans();
            $res = accountLog($this->user_id, 0, 0, '转出电子币给用户' . $toUser['user_id'], 0, 0, '', -$electronic, 12);
            if (!$res) {
                Db::rollback();
                return json(['status' => 0, 'msg' => '转出电子币给用户' . $toUser['user_id'] . '失败']);
            }
            accountLog($toUser['user_id'], 0, 0, '转入电子币From用户' . $this->user_id, 0, 0, '', $electronic, 12, false);
            Db::commit();
            $userElectronic = M('Users')->where('user_id', $this->user_id)->getField('user_electronic');
            return json(['status' => 1, 'msg' => '电子币转增成功', 'result' => ['user_electronic' => $userElectronic]]);
        } else {
            return json(['status' => 1, 'result' => ['user_electronic' => $this->user['user_electronic']]]);
        }
    }

    // 用户积分转向其他会员
    public function transferPayPoints()
    {
        $user = session('user');
        $pay_points = I('pay_points', 0);
        if ($pay_points < 1) {
            return json(['status' => 0, 'msg' => '传成积分的数额不能小于1', 'result' => null]);
        }
        $to_user_id = I('to_user_id', 0);
        if ($to_user_id < 1) {
            return json(['status' => 0, 'msg' => '缺少转让用户参数', 'result' => null]);
        }
        $is_exists = M('Users')->find($to_user_id);
        if (!$is_exists) {
            return json(['status' => 0, 'msg' => '转账用户不存在，请重新确认', 'result' => null]);
        }
        $user_pay_points = M('Users')->where('user_id', $user['user_id'])->getField('pay_points');
        if ($user_pay_points < $pay_points) {
            return json(['status' => 0, 'msg' => '你的积分不够' . $pay_points, 'result' => null]);
        }
        Db::startTrans();
        $res = accountLog($user['user_id'], 0, -$pay_points, "转出积分给用户$to_user_id", 0, 0, '', 0, 12);
        if (!$res) {
            Db::rollback();
            return json(['status' => 0, 'msg' => "转出积分给用户$to_user_id" . '失败', 'result' => null]);
        }
        accountLog($to_user_id, 0, $pay_points, "转入积分From用户{$user['user_id']}", 0, 0, '', 0, 12, false);
        Db::commit();
        return json(['status' => 1, 'msg' => '积分转帐成功', 'result' => null]);
    }

    /**
     * 积分转让（新）
     * @return \think\response\Json
     */
    public function transferPayPointsNew()
    {
        if ($this->request->isPost()) {
            $payPoints = I('pay_points', 0);
            $toUser = I('user_name', '');
            $payPwd = I('pay_pwd', '');
            if (empty($this->user['paypwd'])) {
                return json(['status' => 0, 'msg' => '未设置支付密码']);
            }
            if ($this->user['paypwd'] !== systemEncrypt($payPwd)) {
                return json(['status' => 0, 'msg' => '密码错误']);
            }
            if ($payPoints < 1) {
                return json(['status' => 0, 'msg' => '转让积分的数额不能小于1']);
            }
            if ($payPoints % 100 != 0) {
                return json(['status' => 0, 'msg' => '转让积分的数额必须是100的倍数']);
            }
            if (!$toUser) {
                return json(['status' => 0, 'msg' => '缺少转让用户参数']);
            }
            if (check_mobile($toUser)) {
                $toUser = Db::name('users')->where(['mobile' => $toUser])->find();
            } else {
                $toUser = Db::name('users')->where(['user_id' => $toUser])->find();
            }
            if (!$toUser) {
                return json(['status' => 0, 'msg' => '无此用户']);
            }
            $userPayPoints = M('Users')->where('user_id', $this->user_id)->getField('pay_points');
            if ($userPayPoints < $payPoints) {
                return json(['status' => 0, 'msg' => '你的积分不够' . $payPoints]);
            }
            Db::startTrans();
            $res = accountLog($this->user_id, 0, -$payPoints, '积分转赠用户' . $toUser['user_id'], 0, 0, '', 0, 12);
            if (!$res) {
                Db::rollback();
                return json(['status' => 0, 'msg' => '积分转赠用户' . $toUser['user_id'] . '失败']);
            }
            accountLog($toUser['user_id'], 0, $payPoints, '从用户' . $this->user_id . '获赠', 0, 0, '', 0, 12, false);
            Db::commit();
            $userPayPoints = M('Users')->where('user_id', $this->user_id)->getField('pay_points');
            return json(['status' => 1, 'msg' => '积分转增成功', 'result' => ['user_pay_points' => $userPayPoints]]);
        }
        return json(['status' => 1, 'result' => ['user_pay_points' => $this->user['pay_points']]]);
    }

    public function recharge(Request $request)
    {
        if ($request->isPost()) {
            $data['user_id'] = $this->user_id;
            $data['nickname'] = $this->user['nickname'];
            $data['account'] = I('account');
            $data['order_sn'] = 'recharge' . get_rand_str(10, 0, 1);
            $data['ctime'] = time();
            $order_id = M('recharge')->add($data);
            if ($order_id) {
                $url = U('Payment/getPay', ['pay_radio' => $_REQUEST['pay_radio'], 'order_id' => $order_id]);
                $return['url'] = $url;

                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            }

            return json(['status' => 0, 'msg' => '提交失败,参数有误!', 'result' => null]);
        }

        $paymentList = Db::name('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,2)")->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if (2 == $val['config_value']['is_bank']) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $return['paymentList'] = $paymentList;
        $return['bank_img'] = $bank_img;
        $return['bankCodeList'] = $bankCodeList;

        $type = I('type');
        $Userlogic = new UsersLogic();
        $result = $Userlogic->get_money_log($this->user_id, $type);  //用户资金变动记录
        // if($type == 1){
        //     $result = $Userlogic->get_money_log($this->user_id);  //用户资金变动记录
        // }else if($type == 2){
        //     $return['status'] = C('WITHDRAW_STATUS');
        //     $result=$Userlogic->get_withdrawals_log($this->user_id);  //提现记录
        // }else{
        //     $return['status'] = C('RECHARGE_STATUS');
        //     $result=$Userlogic->get_recharge_log($this->user_id);  //充值记录
        // }
        $return['page'] = $result['show'];
        $return['lists'] = $result['result'];

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 余额充值到电子币
     * @return \think\response\Json
     */
    public function exchangeElectronicNew()
    {
        if ($this->request->isPost()) {
            $amount = I('amount', 0);
            $payPwd = I('pay_pwd', '');
            if (empty($this->user['paypwd'])) {
                return json(['status' => 0, 'msg' => '未设置支付密码']);
            }
            if ($this->user['paypwd'] !== systemEncrypt($payPwd)) {
                return json(['status' => 0, 'msg' => '密码错误']);
            }
            if ($amount < 1) {
                return json(['status' => 0, 'msg' => '数额不能小于1']);
            }
            $withdrawal = M('withdrawals')->where('user_id', $this->user_id)->where('status', 0)->value('money');
            $user_money = M('Users')->where('user_id', $this->user_id)->getField('user_money');
            if ($withdrawal && $user_money - $withdrawal < $amount) {
                return json(['status' => 0, 'msg' => '你有一笔余额正在申请提现，因此你的余额不够' . $amount]);
            } elseif ($user_money < $amount) {
                return json(['status' => 0, 'msg' => '你的余额不够' . $amount]);
            }
            Db::startTrans();
            accountLog($this->user_id, 0, 0, '用户余额转电子币', 0, 0, '', $amount, 13);
            $res = accountLog($this->user_id, -$amount, 0, '电子币充值（余额转）', 0, 0, '', 0, 13);
            if (!$res) {
                Db::rollback();
                return json(['status' => 0, 'msg' => '电子币充值（余额转）失败']);
            }
            Db::commit();
            $user = M('users')->where(['user_id' => $this->user_id])->field('user_electronic, user_money')->find();
            return json(['status' => 1, 'msg' => '电子币充值成功', 'result' => [
                'user_electronic' => $user['user_electronic'],
                'user_money' => $user['user_money'],
            ]]);
        }
        return json(['status' => 1, 'result' => [
            'user_electronic' => $this->user['user_electronic'],
            'user_money' => $this->user['user_money'],
        ]]);
    }

    /**
     * 绑定旧账户.
     * 微信登入的新用户绑定老用户，会把新用户的微信绑定切换到老用户上面，冻结新用户。
     * @return mixed
     */
    public function bindOldUser()
    {
        $username = I('post.username', '');
        $password = I('post.password', '');
        $mobile = I('post.mobile', '');
        $code = I('post.code', '');
        $scene = I('post.scene', 6);
        $type = I('post.type', 1);

        $current_user = M('Users')->where(['user_id' => $this->user_id])->find();
        if (2 == $current_user['type']) {
            return json(['status' => -1, 'msg' => '老用户无法继续绑定', 'result' => null]);
        }
        if ($current_user['bind_uid'] > 0) {
            return json(['status' => -1, 'msg' => '不能再绑定其他旧账号了']);
        }
        $apply_customs = M('apply_customs')->where(['user_id' => $this->user_id, 'status' => 0])->find();
        if ($apply_customs) {
            return json(['status' => -1, 'msg' => '您正在申请金卡，不能进行合并操作']);
        }
        if (1 == $type) {
            // 代理商账号密码绑定方式
            if (!$username) {
                return json(['status' => -1, 'msg' => '用户名不能为空', 'result' => null]);
            }
            if (!$password) {
                return json(['status' => -1, 'msg' => '密码不能为空', 'result' => null]);
            }
            $whereOr = ['user_id' => $username, 'user_name' => $username];
            $bind_user = M('Users')
                ->where(function ($query) use ($whereOr) {
                    $query->whereOr($whereOr);
                })
                ->where('password', systemEncrypt($password))
                // ->where('is_zhixiao',1)
                ->find();
            if (empty($bind_user)) {
                return json(['status' => -1, 'msg' => '账号不存在，不能绑定']);
            }
            if ($bind_user['is_lock'] == 1) {
                return json(['status' => -1, 'msg' => '账号已被冻结，不能绑定']);
            }
            if ($bind_user['is_cancel'] == 1) {
                return json(['status' => -1, 'msg' => '账号已被注销，不能绑定']);
            }
        } else {
            // 手机验证码绑定方式
            if (!$mobile) {
                return json(['status' => -1, 'msg' => '手机号码不能为空', 'result' => null]);
            }
            if (!$code) {
                return json(['status' => -1, 'msg' => '手机验证码不能为空', 'result' => null]);
            }
            // 验证手机号码
            $is_exists = M('Users')->where('mobile', $mobile)->where('user_name', 'neq', '')->find();
            if (!$is_exists) {
                return json(['status' => -1, 'msg' => '手机号码不存在,请输入老用户手机进行绑定', 'result' => null]);
            }
            // 验证手机和验证码
            $session_id = S('mobile_token_' . $mobile);
            if (!$session_id) {
                return json(['status' => 0, 'msg' => '验证码已过期']);
            }
            $logic = new UsersLogic();
            $res = $logic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
            if (1 != $res['status']) {
                return json(['status' => -1, 'msg' => $res['msg']]);
            }
            $bind_user = M('Users')
                ->where('mobile', $mobile)
                // ->where('is_zhixiao',1)
                ->find();
            if (empty($bind_user)) {
                return json(['status' => -1, 'msg' => '账号不存在，不能绑定']);
            }
            if ($bind_user['is_lock'] == 1) {
                return json(['status' => -1, 'msg' => '账号已被冻结，不能绑定']);
            }
            if ($bind_user['is_cancel'] == 1) {
                return json(['status' => -1, 'msg' => '账号已被注销，不能绑定']);
            }
        }
        if ($bind_user['bind_uid'] > 0) {
            return json(['status' => -1, 'msg' => '该旧账号已经绑定！']);
        }
        if ($current_user['user_id'] == $bind_user['user_id']) {
            return json(['status' => -1, 'msg' => '不能绑定自己']);
        }
        if ($current_user['first_leader'] == $bind_user['user_id']) {
            return json(['status' => 0, 'msg' => '不能绑定和自己有关系的普通会员']);
        }
        if ($this->_hasRelationship($bind_user['user_id'])) {
            return json(['status' => 0, 'msg' => '不能绑定和自己有关系的普通会员']);
        }

        DB::startTrans();
        // 更新用户信息
        $user_data = [];
        $user_data['distribut_level'] = $current_user['distribut_level'] > $bind_user['distribut_level'] ? $current_user['distribut_level'] : $bind_user['distribut_level'];
        if ($user_data['distribut_level'] > 1) {
            $user_data['is_distribut'] = 1;
        }
        if ($bind_user['distribut_level'] != 3) {
            // 合并账号不是SVIP才变更父级
            $user_data['invite_uid'] = $current_user['will_invite_uid'] != 0 ? $current_user['will_invite_uid'] : $current_user['invite_uid'];
            $user_data['invite_time'] = $current_user['will_invite_uid'] != 0 ? time() : $current_user['invite_time'];
            $user_data['first_leader'] = $current_user['first_leader'];
            $user_data['second_leader'] = $current_user['second_leader'];
            $user_data['third_leader'] = $current_user['third_leader'];
        }
        $user_data['mobile'] = $current_user['mobile'];
        $user_data['head_pic'] = $current_user['head_pic'];
        $user_data['nickname'] = $current_user['nickname'];
        $user_data['oauth'] = $current_user['oauth'];
        $user_data['openid'] = $current_user['openid'];
        $user_data['unionid'] = $current_user['unionid'];
        $user_data['bind_uid'] = $current_user['user_id'];
        $user_data['bind_time'] = time();
        $user_data['type'] = 2;
        $user_data['time_out'] = strtotime('+' . config('REDIS_DAY') . ' days');
        M('Users')->where('user_id', $bind_user['user_id'])->update($user_data);
        // 授权登录
        M('OauthUsers')->where('user_id', $bind_user['user_id'])->delete();
        M('OauthUsers')->where('user_id', $current_user['user_id'])->update(['user_id' => $bind_user['user_id']]);
        // 下级推荐人
        M('Users')->where('first_leader', $current_user['user_id'])->update(array('first_leader' => $bind_user['user_id'], 'invite_uid' => $bind_user['user_id']));
        M('Users')->where('second_leader', $current_user['user_id'])->update(array('second_leader' => $bind_user['user_id']));
        M('Users')->where('third_leader', $current_user['user_id'])->update(array('third_leader' => $bind_user['user_id']));
        // 积分变动
        $payPoints = M('AccountLog')
            ->where('user_id', $current_user['user_id'])
            ->where('pay_points', 'gt', 0)
            ->where('type', 'neq', 6)// 不要注册积分
            ->sum('pay_points');
        if ($payPoints > 0) {
            accountLog($bind_user['user_id'], 0, $payPoints, '账号合并积分', 0, 0, '', 0, 11, false);
        }
        // 电子币变动
        $electronic = M('AccountLog')
            ->where('user_id', $current_user['user_id'])
            ->where('user_electronic', 'gt', 0)
            ->sum('user_electronic');
        if ($electronic > 0) {
            accountLog($bind_user['user_id'], 0, 0, '账号合并电子币', 0, 0, '', $electronic, 11, false);
        }
        // 余额变动
        $userMoney = M('AccountLog')
            ->where('user_id', $current_user['user_id'])
            ->where('user_money', 'gt', 0)
            ->sum('user_money');
        if ($userMoney > 0) {
            accountLog($bind_user['user_id'], $userMoney, 0, '账号合并余额', 0, 0, '', 0, 11, false);
        }
        // 订单
        M('Order')->where('user_id', $current_user['user_id'])->update(array('user_id' => $bind_user['user_id']));
        M('OrderAction')->where('action_user', $current_user['user_id'])->update(array('action_user' => $bind_user['user_id']));
        // 快递
        M('DeliveryDoc')->where('user_id', $current_user['user_id'])->update(array('user_id' => $bind_user['user_id']));
        // 退换货
        M('ReturnGoods')->where('user_id', $current_user['user_id'])->update(array('user_id' => $bind_user['user_id']));
        // 提成记录
        M('RebateLog')->where('user_id', $current_user['user_id'])->update(array('user_id' => $bind_user['user_id']));
        M('RebateLog')->where('buy_user_id', $current_user['user_id'])->update(array('buy_user_id' => $bind_user['user_id']));

        // 迁移数据
        // M('AccountLog')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('Cart')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('UserAddress')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('GoodsCollect')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('GoodsVisit')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('Recharge')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('UserSign')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('UserStore')->where('user_id',$this->user_id)->update(array('user_id'=>$bind_user['user_id']));
        // M('couponList')->where('uid',$this->user_id)->update(array('uid'=>$bind_user['user_id']));

        // 冻结新账户
        M('Users')->where('user_id', $current_user['user_id'])->update(['is_lock' => 1]);
        // 绑定记录
        M('bind_log')->add([
            'user_id' => $current_user['user_id'],
            'bind_user_id' => $bind_user['user_id'],
            'add_time' => time(),
            'type' => 1,
            'way' => 1
        ]);

        DB::commit();

        setcookie('uname', '', time() - 3600, '/');
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('user', '', time() - 3600, '/');
        setcookie('PHPSESSID', '', time() - 3600, '/');
        session_unset();
        session_destroy();
        $this->redis->rm('user_' . $this->userToken);

        $user = M('Users')->where('user_id', $bind_user['user_id'])->find();
        // 更新用户推送tags
        $res = (new PushLogic())->bindPushTag($user);
        if ($res['status'] == 2) {
            $user = Db::name('users')->where('user_id', $user['user_id'])->find();
        }
        if (empty($user['token'])) {
            $userToken = TokenLogic::setToken();
            $updateData = [
                'last_login' => time(),
                'token' => $userToken,
                'time_out' => strtotime('+' . config('REDIS_DAY') . ' days')
            ];
            M('Users')->where('user_id', $bind_user['user_id'])->update($updateData);
            $user['token'] = $userToken;
        }
        session('user', $user);
        $this->redis->set('user_' . $user['token'], $user, config('REDIS_TIME'));
        setcookie('user_id', $user['user_id'], null, '/');
        setcookie('is_distribut', $user['is_distribut'], null, '/');
        $nickname = empty($user['nickname']) ? '第三方用户' : $user['nickname'];
        setcookie('uname', urlencode($nickname), null, '/');
        setcookie('cn', 0, time() - 3600, '/');
        // 登录后将购物车的商品的 user_id 改为当前登录的id
        M('cart')->where('session_id', $session_id)->save(['user_id' => $user['user_id']]);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user['user_id']);
        $cartLogic->setUserToken($user['token']);
        $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作

        $returnUser = [
            'user_id' => $user['user_id'],
            'sex' => $user['sex'],
            'nickname' => $user['nickname'] ?? $user['user_name'],
            'user_name' => $user['user_name'],
            'real_name' => $user['real_name'],
            'id_cart' => $user['id_cart'],
            'birthday' => $user['birthday'],
            'mobile' => $user['mobile'],
            'head_pic' => $user['head_pic'],
            'type' => $user['distribut_level'] >= 3 ? '2' : $user['type'],
            'invite_uid' => $user['invite_uid'],
            'is_distribut' => $user['is_distribut'],
            'is_lock' => $user['is_lock'],
            'level' => $user['distribut_level'],
            'level_name' => M('DistributLevel')->where('level_id', $user['distribut_level'])->getField('level_name') ?? '普通会员',
            'is_not_show_jk' => $user['is_not_show_jk'],  // 是否提示加入金卡弹窗
            'is_not_show_invite' => $user['distribut_level'] >= 3 ? 1 : 0,  // 是否隐藏推荐人绑定
            'has_pay_pwd' => $user['paypwd'] ? 1 : 0,
            'is_app' => TokenLogic::getValue('is_app', $user['token']) ? 1 : 0,
            'token' => $user['token'],
            'jpush_tags' => [$user['push_tag']]
        ];
        return json(['status' => 1, 'msg' => '绑定成功', 'result' => ['user' => $returnUser]]);
    }

    /**
     * 绑定旧账户.
     * 微信登入的新用户绑定老用户，会把新用户的微信绑定切换到老用户上面，冻结新用户。
     * @return mixed
     */
    public function bindOldUser2()
    {
        $type = I('post.type', 1);
        $data = [
            'username' => I('post.username', ''),
            'password' => I('post.password', ''),
            'mobile' => I('post.mobile', ''),
            'code' => I('post.code', ''),
            'scene' => I('post.scene', 6),
        ];

        $usersLogic = new UsersLogic();
        $res = $usersLogic->bindUser($this->user_id, $type, $data);
        if ($res['status'] !== 1) {
            return json($res);
        }
        $bindUserId = $res['result']['bind_user_id'];

        setcookie('uname', '', time() - 3600, '/');
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('user', '', time() - 3600, '/');
        setcookie('PHPSESSID', '', time() - 3600, '/');
        session_unset();
        session_destroy();
        $this->redis->rm('user_' . $this->userToken);

        $user = M('Users')->where('user_id', $bindUserId)->find();
        // 更新用户推送tags
        $res = (new PushLogic())->bindPushTag($user);
        if ($res['status'] == 2) {
            $user = Db::name('users')->where('user_id', $user['user_id'])->find();
        }
        if (empty($user['token'])) {
            $userToken = TokenLogic::setToken();
            $updateData = [
                'last_login' => time(),
                'token' => $userToken,
                'time_out' => strtotime('+' . config('REDIS_DAY') . ' days')
            ];
            M('Users')->where('user_id', $bindUserId)->update($updateData);
            $user['token'] = $userToken;
        }
        session('user', $user);
        $this->redis->set('user_' . $user['token'], $user, config('REDIS_TIME'));
        setcookie('user_id', $user['user_id'], null, '/');
        setcookie('is_distribut', $user['is_distribut'], null, '/');
        $nickname = empty($user['nickname']) ? '第三方用户' : $user['nickname'];
        setcookie('uname', urlencode($nickname), null, '/');
        setcookie('cn', 0, time() - 3600, '/');
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user['user_id']);
        $cartLogic->setUserToken($user['token']);
        $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作

        $returnUser = [
            'user_id' => $user['user_id'],
            'sex' => $user['sex'],
            'nickname' => $user['nickname'] ?? $user['user_name'],
            'user_name' => $user['user_name'],
            'real_name' => $user['real_name'],
            'id_cart' => $user['id_cart'],
            'birthday' => $user['birthday'],
            'mobile' => $user['mobile'],
            'head_pic' => $user['head_pic'],
            'type' => $user['distribut_level'] >= 3 ? '2' : $user['type'],
            'invite_uid' => $user['invite_uid'],
            'is_distribut' => $user['is_distribut'],
            'is_lock' => $user['is_lock'],
            'level' => $user['distribut_level'],
            'level_name' => M('DistributLevel')->where('level_id', $user['distribut_level'])->getField('level_name') ?? '普通会员',
            'is_not_show_jk' => $user['is_not_show_jk'],  // 是否提示加入金卡弹窗
            'is_not_show_invite' => $user['distribut_level'] >= 3 ? 1 : 0,  // 是否隐藏推荐人绑定
            'has_pay_pwd' => $user['paypwd'] ? 1 : 0,
            'is_app' => TokenLogic::getValue('is_app', $user['token']) ? 1 : 0,
            'token' => $user['token'],
            'jpush_tags' => [$user['push_tag']]
        ];
        return json(['status' => 1, 'msg' => '绑定成功', 'result' => ['user' => $returnUser]]);
    }

    function bindOldUserInfo()
    {
        $data['is_alert'] = 1;
        $article = M('article')->where(array('article_id' => 104))->field('title,content')->find();
        $data['is_alert_title'] = $article['title'];
        $data['is_alert_content'] = $article['content'];
        return json(['status' => 1, 'msg' => '设置成功', 'result' => $data]);
    }

    // 设置支付密码
    public function setPayPwd()
    {
        $new_password = I('post.new_password');
        $confirm_password = I('post.confirm_password');

        $old_password_u = M('Users')->where('user_id', $this->user_id)->getField('paypwd');
        if ($old_password_u) {
            return json(['status' => 0, 'msg' => '已经有支付密码，不能重新设置', 'result' => null]);
        }

        $logic = new UsersLogic();
        $data = $logic->paypwd($this->user_id, $new_password, $confirm_password, $this->userToken);
        if (-1 == $data['status']) {
            return json(['status' => 0, 'msg' => $data['msg'], 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '设置成功', 'result' => null]);
    }

    // 修改支付密码
    public function updatePayPwd()
    {
        $old_password = I('post.old_password');
        $new_password = I('post.new_password');
        $confirm_password = I('post.confirm_password');

        $old_password = systemEncrypt($old_password);
        $old_password_u = M('Users')->where('user_id', $this->user_id)->getField('paypwd');
        if ($old_password_u !== $old_password) {
            return json(['status' => 0, 'msg' => '输入原来的支付密码不正确', 'result' => null]);
        }

        $logic = new UsersLogic();
        $data = $logic->paypwd($this->user_id, $new_password, $confirm_password);
        if (-1 == $data['status']) {
            return json(['status' => 0, 'msg' => $data['msg'], 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '设置成功', 'result' => null]);
    }

    // 忘记支付密码，重新找回
    public function forgetPayPwd()
    {
        $step = I('post.step', 1);
        $code = I('post.code');
        $scene = I('post.scene', 6);
        $session_id = I('unique_id', $this->userToken);

        $logic = new UsersLogic();
        if (1 == $step) {
            $res = $logic->check_validate_code($code, $this->user['mobile'], 'phone', $session_id, $scene);
            if (!$res || 1 != $res['status']) {
                return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
            }
            return json(['status' => 1, 'msg' => '验证成功', 'result' => null]);
        } elseif ($step > 1) {
            if ($this->isApp || $this->isApplet) {
                $res = $logic->check_validate_code($code, $this->user['mobile'], 'phone', $session_id, $scene);
                if (!$res || 1 != $res['status']) {
                    return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
                }
            }
            $data = $logic->paypwd($this->user_id, I('post.new_password'), I('post.confirm_password'), $this->userToken);
            if (-1 == $data['status']) {
                return json(['status' => 0, 'msg' => $data['msg'], 'result' => null]);
            }
            return json(['status' => 1, 'msg' => '重新设置支付密码成功', 'result' => null]);
        }
    }

    /**
     * 支付密码
     *
     * @return mixed
     */
    public function paypwd(Request $request)
    {
//        //检查是否第三方登录用户
//        $logic = new UsersLogic();
//        $data = $logic->get_info($this->user_id);
//        $user = $data['result'];
        if ('/cart2.html' == strrchr($_SERVER['HTTP_REFERER'], '/')) {  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl', U('Mobile/Cart/cart2'));
        } else {
            S('payPriorUrl_' . $this->userToken, U('Mobile/Cart/cart2'), 86400);
        }
        if ('' == $this->user['mobile']) {
            return json(['status' => 0, 'msg' => '请先绑定手机', 'result' => null]);
        }
        $step = I('step', 1);
        $code = I('post.code');
        $scene = I('post.scene', 6);
        $session_id = I('unique_id', $this->userToken);

        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $this->user['mobile'], 'phone', $session_id, $scene);
        if (!$res || 1 != $res['status']) {
            return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
        }

        if ($step > 1) {
            $check = TokenLogic::getCache('validate_code', $this->user['mobile']);
            if (empty($check)) {
                return json(['status' => 0, 'msg' => '验证码还未验证通过', 'result' => null]);
            }
        }
        if ($request->isPost() && 3 == $step) {
            $userLogic = new UsersLogic();
//            $data = I('post.');
            $data = $userLogic->paypwd($this->user_id, I('new_password'), I('confirm_password'), $this->userToken);
            if (-1 == $data['status']) {
                return json(['status' => 0, 'msg' => $data['msg'], 'result' => null]);
            }
        }
        $return['step'] = $step;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*// 用户提现申请
    public function takeCash()
    {
         $user_id = $this->user_id;
         $price = I('post.price/f',0);
         $cart_code = I('post.cart_code','');
         $bank = I('post.bank','');
         $time = time();

         $bank = trim($bank);

         $user_money = M('Users')->where('user_id',$user_id)->getField('user_money');

         if($user_money < $price)
         {
             return json(['status'=>0,'msg'=>'你的用余额不够'.$price,'result'=>null]);
         }

         // 插入account表
         $userMoneyLog = [
             'user_id'      => $user_id,
             'frozen_money' => -$price,
             'change_time'  => $time,
             'desc'         => '用户提现申请',
         ];

         Db::name('account_log')->insert($userMoneyLog);

         // 插入users表
         M('users')->where('user_id',$user['user_id'])->setInc('frozen_money',$price);
         M('users')->where('user_id',$user['user_id'])->setDec('user_money',$price);

         // 插入take_cash表
         $data = array(
             'user_id' => $user_id,
             'bank' => $bank,
             'cart_code' => $cart_code,
             'price' => $price,
             'status' => 0,
             'remrak' => '',
             'create_time' => $time,
         );

         $result = M('TakeCash')->add($data);

         return json(['status'=>1,'msg'=>'申请提现成功','result'=>$result]);
    }*/

    /**
     * 申请提现记录.
     */
    public function withdrawals(Request $request)
    {
        if ($request->isPost()) {
            // if(!$this->verifyHandle('withdrawals')){
            //      return json(['status'=>0,'msg'=>'图像验证码错误']);
            // };
            $data = I('post.');
            if (!$data['bank_name']) {
                return json(['status' => 0, 'msg' => '请填写银行名称']);
            }
            if (!$data['bank_card'] || !checkBankCard($data['bank_card'])) {
                return json(['status' => 0, 'msg' => '请填写正确的银行账号']);
            }
            if (!$data['real_name']) {
                return json(['status' => 0, 'msg' => '请填写开户名']);
            }
            if (!$data['id_cart']) {
                return json(['status' => 0, 'msg' => '请填写身份证']);
            }

            if (!check_id_card($data['id_cart'])) {
                return json(['status' => 0, 'msg' => '请填写正确的身份证格式']);
            }

            $data['realname'] = $data['real_name'];
            unset($data['real_name']);

            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min = tpCache('basic.min'); // 最少提现额度
            $distribut_need = tpCache('basic.need'); // 满多少才能提现
            if ($this->user['user_money'] < $distribut_need) {
                return json(['status' => 0, 'msg' => '账户余额最少达到' . $distribut_need . '多少才能提现']);
            }
            if ($data['money'] < $distribut_min) {
                return json(['status' => 0, 'msg' => '每次最少提现额度' . $distribut_min]);
            }
            if ($data['money'] > $this->user['user_money']) {
                return json(['status' => 0, 'msg' => "你最多可提现{$this->user['user_money']}账户余额."]);
            }
            // if(systemEncrypt($data['paypwd']) != $this->user['paypwd']){
            //     return json(['status'=>0,'msg'=>"支付密码错误"]);
            // }
            if (M('withdrawals')->where('user_id', $this->user_id)->where('status', 0)->find()) {
                return json(['status' => 0, 'msg' => '你还有一个提现在审核中，请勿重复提交申请']);
            }
            if (M('withdrawals')->add($data)) {
                return json(['status' => 1, 'msg' => '已提交申请']);
            }

            return json(['status' => 1, 'msg' => '提交失败,联系客服!']);
        }
    }

    /**
     * 获取用户提现记录.
     */
    public function getWithdrawals()
    {
        $list = M('Withdrawals')
            ->field('*,
            CASE create_time  WHEN  0 THEN "0" ELSE FROM_UNIXTIME(create_time,"%Y-%m-%d %H:%i:%s") END as create_time,
            CASE check_time  WHEN  0 THEN "0" ELSE FROM_UNIXTIME(check_time,"%Y-%m-%d %H:%i:%s") END as check_time,
            CASE pay_time  WHEN  0 THEN "0" ELSE FROM_UNIXTIME(pay_time,"%Y-%m-%d %H:%i:%s") END as pay_time,
            CASE refuse_time  WHEN  0 THEN "0" ELSE FROM_UNIXTIME(refuse_time,"%Y-%m-%d %H:%i:%s") END as refuse_time
        ')
            ->where('user_id', $this->user_id)->select();
        $WITHDRAW_STATUS = C('WITHDRAW_STATUS');
        $return['list'] = $list;
        $return['WITHDRAW_STATUS'] = $WITHDRAW_STATUS;

        return json(['status' => 1, 'msg' => '已提交申请', 'result' => $return]);
    }

    /**
     * 提现申请（记录）
     * @return \think\response\Json
     */
    public function withdrawal()
    {
        if ($this->request->isPost()) {
            $amount = I('amount', 0);
            $payPwd = I('pay_pwd', '');
            $moneyMin = tpCache('basic.min');
            if ($amount % $moneyMin != 0) return json(['status' => 0, 'msg' => '提现金额必须是' . $moneyMin . '的倍数']);
            // 用户信息
            $user = M('users')->where(['user_id' => $this->user_id])
                ->field('paypwd, user_money, bank_name, bank_code, bank_region, bank_branch, bank_card, real_name, id_cart')->find();
            if (empty($user['bank_card']) || empty($user['bank_region']) || empty($user['bank_branch'])) {
                return json(['status' => 0, 'msg' => '用户银行信息不完善']);
            }
            if (systemEncrypt($payPwd) != $user['paypwd']) {
                return json(['status' => 0, 'msg' => '支付密码错误']);
            }
            if ($amount < $moneyMin) {
                return json(['status' => 0, 'msg' => '每次最少提现额度' . $moneyMin]);
            }
            $moneyNeed = tpCache('basic.need'); // 满多少才能提现
            if ($user['user_money'] < $moneyNeed) {
                return json(['status' => 0, 'msg' => '账户余额最少达到' . $moneyNeed . '多少才能提现']);
            }
            if ($amount > $user['user_money']) {
                return json(['status' => 0, 'msg' => '用户余额不足']);
            }
            if (M('withdrawals')->where('user_id', $this->user_id)->where('status', 'IN', [0, 1])->find()) {
                return json(['status' => 0, 'msg' => '你还有一个提现在审核中，请勿重复提交申请']);
            }
            $data = [
                'user_id' => $this->user_id,
                'money' => $amount,
                'create_time' => time(),
                'bank_name' => $user['bank_name'],
                'bank_code' => $user['bank_code'],
                'bank_region' => $user['bank_region'],
                'bank_branch' => $user['bank_branch'],
                'bank_card' => $user['bank_card'],
                'realname' => $user['real_name'],
                'id_cart' => $user['id_cart'],
                'taxfee' => tpCache('basic.hand_fee')
            ];
            M('withdrawals')->add($data);
            return json(['status' => 1, 'msg' => '提现申请已提交，请留意到账信息']);
        }
        // 获取提现记录
        $count = M('withdrawals')->where(['user_id' => $this->user_id])->count('id');
        $page = new Page($count, 10);
        $withdrawal = M('withdrawals')->where(['user_id' => $this->user_id, 'status' => ['neq', -2]])
            ->field('id, money, create_time add_time, bank_name, taxfee, status')
            ->limit($page->firstRow . ',' . $page->listRows)->order('add_time desc')->select();
        foreach ($withdrawal as $key => $item) {
            $withdrawal[$key]['amount'] = bcsub($item['money'], $item['taxfee'], 2);
            unset($withdrawal[$key]['money']);
            unset($withdrawal[$key]['taxfee']);
        }
        return json(['status' => 1, 'result' => ['list' => $withdrawal]]);
    }

    /**
     * 会员签到积分奖励
     * 2017/9/28.
     */
    public function sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $info = $userLogic->idenUserSign($user_id); //标识签到
        $return = $info;

        return json(['status' => 1, 'msg' => '获取签到信息成功！', 'result' => $return]);
    }

    /**
     * Ajax会员签到
     * 2017/11/19.
     */
    public function user_sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $config = tpCache('sign');
        $date = date('Y-n-j', time()); //2017-9-29
        //是否正确请求
        if (date('Y-n-j', time()) != $date) {
            return json(['status' => false, 'msg' => '签到失败！', 'result' => '']);
        }
        //签到开关
        if ($config['sign_on_off'] > 0) {
            $map['sign_last'] = $date;
            $map['user_id'] = $user_id;
            $userSingInfo = Db::name('user_sign')->where($map)->find();
            //今天是否已签
            if ($userSingInfo) {
                return json(['status' => false, 'msg' => '您今天已经签过啦！', 'result' => '']);
            }
            //是否有过签到记录
            $checkSign = Db::name('user_sign')->where(['user_id' => $user_id])->find();
            if (!$checkSign) {
                $result = $userLogic->addUserSign($user_id, $date);            //第一次签到
            } else {
                $result = $userLogic->updateUserSign($checkSign, $date);       //累计签到
            }
            $return = ['status' => $result['status'], 'msg' => $result['msg'], 'result' => ''];
        } else {
            $return = ['status' => false, 'msg' => '该功能未开启！', 'result' => ''];
        }

        return json($return);
    }

    /**
     * ajax用户消息通知请求
     *
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type');
        $user_logic = new UsersLogic();
        $message_model = new MessageLogic();
        if (0 == $type) {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
        } elseif (1 == $type) {
            //活动消息：后续开发
            $user_sys_message = [];
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $message_log = [];
        if ($user_sys_message) {
            $now = time();
            $endLastweek = mktime(23, 59, 59, date('m'), date('d') - date('w') + 7 - 7, date('Y'));
            $weekarray = ['日', '一', '二', '三', '四', '五', '六'];
            foreach ($user_sys_message as $k => $val) {
                if ($now - $val['send_time'] < 3600 * 24 * 7) {
                    if (date('Y-m-d') == date('Y-m-d', $val['send_time'])) {
                        $val['date'] = '今天';
                    } else {
                        if ($val['send_time'] < $endLastweek) {
                            $val['date'] = '上周' . $weekarray[date('w', $val['send_time'])];
                        } else {
                            $val['date'] = '周' . $weekarray[date('w', $val['send_time'])];
                        }
                    }
                } else {
                    $val['date'] = '更早以前';
                }

                if ('今天' == $val['date']) {
                    $date_time = date('H:i', $val['send_time']);
                    $message_log[$val['date'] . $date_time][] = $val;
                } else {
                    $message_log[$val['date']][] = $val;
                }
            }
        }
        $return['messages'] = $message_log;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 将用户系统消息修改成已读.
     */
    public function set_message_notice()
    {
        $type = I('type');
        $msg_ids = I('msg_id');
        $msg_ids = explode(',', $msg_ids);
        $user_logic = new UsersLogic();
        foreach ($msg_ids as $msg_id) {
            $res = $user_logic->setMessageForRead($type, $msg_id);
        }

        return json($res);
    }

    /**
     * 将用户系统消息修改成删除.
     */
    public function set_message_delete()
    {
        $type = I('type');
        $msg_ids = I('msg_id');
        $msg_ids = explode(',', $msg_ids);
        $user_logic = new UsersLogic();
        foreach ($msg_ids as $msg_id) {
            $res = $user_logic->setMessageForDelete($type, $msg_id);
        }

        return json($res);
    }

    /**
     * ajax用户消息通知请求
     *
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_article_notice()
    {
        $type = I('type');
//        $user_logic = new UsersLogic();
        $article_model = new ArticleLogic();
        if (0 == $type) {
            //系统消息
            $user_sys_article = $article_model->getUserArticleNotice();
        } elseif (1 == $type) {
            //活动消息：后续开发
            $user_sys_article = [];
        } else {
            //全部消息：后续完善
            $user_sys_article = $article_model->getUserArticleNotice();
        }
        $article_log = [];
        if ($user_sys_article) {
            $now = time();
            $endLastweek = mktime(23, 59, 59, date('m'), date('d') - date('w') + 7 - 7, date('Y'));
            $weekarray = ['日', '一', '二', '三', '四', '五', '六'];
            foreach ($user_sys_article as $k => $val) {
                if ($now - $val['publish_time'] < 3600 * 24 * 7) {
                    if (date('Y-m-d') == date('Y-m-d', $val['publish_time'])) {
                        $val['date'] = '今天';
                    } else {
                        if ($val['publish_time'] < $endLastweek) {
                            $val['date'] = '上周' . $weekarray[date('w', $val['publish_time'])];
                        } else {
                            $val['date'] = '周' . $weekarray[date('w', $val['publish_time'])];
                        }
                    }
                } else {
                    $val['date'] = '更早以前';
                }

                if ('今天' == $val['date']) {
                    $date_time = date('H:i', $val['publish_time']);
                    $val['publish_time'] = date('Y-m-d H:i:s', $val['publish_time']);
                    $article_log[$val['date'] . $date_time][] = $val;
                } else {
                    $val['publish_time'] = date('Y-m-d H:i:s', $val['publish_time']);
                    $article_log[$val['date']][] = $val;
                }
            }
        }
        $return['articles'] = $article_log;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * ajax用户消息通知请求
     */
    public function set_article_notice()
    {
        $msg_ids = I('art_id');
        $msg_ids = explode(',', $msg_ids);

        $user_logic = new UsersLogic();
        foreach ($msg_ids as $msg_id) {
            $res = $user_logic->setArticleForRead($msg_id);
        }

        return json($res);
    }

    /**
     * ajax用户消息通知请求
     */
    public function set_article_delete()
    {
        $msg_ids = I('art_id');
        $msg_ids = explode(',', $msg_ids);
        $user_logic = new UsersLogic();
        foreach ($msg_ids as $msg_id) {
            $res = $user_logic->setArticleForDelete($msg_id);
        }

        return json($res);
    }

    public function getTaskList()
    {
        $cate_id = I('cate_id', 1);

        $taskRward = new \app\common\model\TaskReward();

        $list = $taskRward->with(['task' => function ($query) {
            $query->field('*,FROM_UNIXTIME(start_time,"%Y-%m-%d %H:%i:%s") as start,FROM_UNIXTIME(end_time,"%Y-%m-%d %H:%i:%s") as end');
        }])->where('task_cate', $cate_id)->where('task_id', 'in', [2, 3])->select();

        foreach ($list as $k => $v) {
            $data = [];
            $list[$k]['is_work'] = 1;
            if (1 != $v['task']['is_open'] || $v['task']['start_time'] > time() || $v['task']['end_time'] < time()) {
                $list[$k]['is_work'] = 0;
                unset($list[$k]);
                continue;
            }
            $data = M('user_task')
                ->field('*,FORMAT(finish_num/target_num,2)*100 AS precent')
                ->where('user_id', $this->user_id)
                ->where('task_reward_id', $v['reward_id'])
                ->where('created_at', 'gt', $v['task']['start_time'])
                ->where('created_at', 'lt', $v['task']['end_time'])
                ->order('id desc')
                ->find();

            if ($data['precent'] > 100) {
                $data['precent'] = 100;
            }

            $list[$k]['user_can_get'] = M('task_log')
                ->where('user_id', $this->user_id)
                ->where('task_reward_id', $v['reward_id'])
                ->where('type', 1)
                ->where('status', 0)
                ->find() ? 1 : 0;

            $list[$k]['have_get'] = 0;
            if (1 == $data['status'] && 0 == $v['cycle']) {
                $list[$k]['have_get'] = 1;
            }
            // dump($list[$k]['have_get']);
            // dump($data['precent']);
            // dump($list[$k]['have_get'] == 0);
            // dump($data['precent'] == 100);
            // dump($data['precent']);

            if (0 == $list[$k]['have_get'] && 0 == $list[$k]['user_can_get'] && 100 == $data['precent']) {
                $data['precent'] = 0;
            }
            $list[$k]['user_task'] = $data;
            // dump($list[$k]['user_task']['precents']);

            // if($list[$k]['user_can_get'] == 0)
            // {
            //     $have_get = M('task_log')
            //     ->where('user_id', $this->user_id)
            //     ->where('task_reward_id',$v['reward_id'])
            //     ->where('type', 1)
            //     ->where('status', 1)
            //     ->find() ? 1 : 0;
            //     $list[$k]['have_get'] = $have_get;
            // }


        }
        // exit;
        $return['list'] = $list;
//        $return['task_cate'] = C('TASK_CATE');
        $return['task_cate'] = unserialize(M('task_config')->value('config_value'));

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function getReward()
    {
        $reward_id = I('reward_id', 0);
        $rewardLog = M('task_log')
            ->where('user_id', $this->user_id)
            ->where('task_reward_id', $reward_id)
            ->where('type', 1)
            ->where('status', 0)
            ->find();

        if ($rewardLog) {
            $taskReward = M('task_reward')->where(['reward_id' => $rewardLog['task_reward_id']])->find();
            Db::startTrans();
            if ($taskReward['reward_num'] > 0) {
                accountLog($this->user_id, 0, $taskReward['reward_num'], '用户领取任务奖励', 0, 0, $rewardLog['order_sn'], 0, 16);
            }
            if ($taskReward['reward_price'] > 0) {
                accountLog($this->user_id, 0, 0, '用户领取任务奖励', 0, 0, $rewardLog['order_sn'], $taskReward['reward_price'], 15);
            }
            if ($taskReward['reward_coupon_id'] != 0) {
                $activityLogic = new \app\common\logic\ActivityLogic();
                $rewardCoupon = explode('-', $taskReward['reward_coupon_id']);
                foreach ($rewardCoupon as $coupon) {
                    $result = $activityLogic->get_coupon($coupon, $this->user_id);
                    if ($result['status'] != 1) {
                        Db::rollback();
                        return json(['status' => 0, 'msg' => $result['msg']]);
                    }
                }
            }

            M('task_log')->where('id', $rewardLog['id'])->update(['status' => 1, 'finished_at' => time()]);

            Db::commit();
            return json(['status' => 1, 'msg' => '用户领取奖励成功']);

//            $return['reward_coupon_money'] = '0.00';
//            $return['reward_integral'] = $reward['reward_integral'];
//            $return['reward_electronic'] = $reward['reward_electronic'];
//
//            if ($reward['reward_coupon_id']) {
//                $coupon_info = M('coupon')->where(['id' => $reward['reward_coupon_id']])->find();
//                if ($coupon_info['use_type'] == 4 || $coupon_info['use_type'] == 5) {
//                    $return['coupon_name_is_show'] = 1;
//                    $return['coupon_name'] = $coupon_info['name'];
//                } else {
//                    $return['coupon_name_is_show'] = 0;
//                }
//            } else {
//                $return['coupon_name_is_show'] = 0;
//            }
//
//            if (isset($result) && 1 == $result['status']) {
//                M('task_log')->where('id', $reward['id'])->update(['status' => 1, 'finished_at' => time()]);
//                $return['reward_coupon_money'] = $result['coupon']['money'];
//
//                return json(['status' => 1, 'msg' => $result['msg'], 'result' => $return]);
//            } elseif (isset($result) && 1 != $result['status']) {
//                return json(['status' => 0, 'msg' => $result['msg'], 'result' => $return]);
//            }
//            M('task_log')->where('id', $reward['id'])->update(['status' => 1, 'finished_at' => time()]);
//
//            return json(['status' => 1, 'msg' => '用户领取奖励成功', 'result' => $return]);
        }

        return json(['status' => 0, 'msg' => '用户还没有领取该奖励资格', 'result' => '']);
    }

    public function getTask()
    {
//        $task_cate = C('TASK_CATE');
        $task_cate = unserialize(M('task_config')->value('config_value'));

        $list = [];
        foreach ($task_cate as $k => $v) {
            $task_list = M('task_reward')
                ->alias('tr')
                ->join('__TASK__ t', 't.id=tr.task_id')
                ->where('tr.task_cate', $k)
                ->where('t.is_open', 1)
                ->where('t.start_time', 'lt', time())
                ->where('t.end_time', 'gt', time())
                ->select();

            $data = [];
            $data['name'] = $v;
            $data['cate_id'] = $k;
            $data['task_count'] = 0;
            $data['finish_count'] = 0;
            $data['precent'] = 0;
            $finish_count = 0;
            if ($task_list) {
                foreach ($task_list as $key => $value) {
                    $have_get = M('task_log')
                        ->where('user_id', $this->user_id)
                        ->where('task_reward_id', $value['reward_id'])
                        ->where('type', 1)
                        ->where('status', 1)
                        ->find() ? 1 : 0;
                    if (1 == $have_get) {
                        ++$finish_count;
                    }
                }

                $data['task_count'] = count($task_list);
                $data['finish_count'] = $finish_count;
                $data['precent'] = round($finish_count / $data['task_count'], 2) * 100;
            }
            $list[] = $data;
        }

        $return['list'] = $list;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 用户任务列表
     * @return \think\response\Json
     */
    public function userTask()
    {
        if ($this->passAuth) die(json_encode(['status' => -999, 'msg' => '请先登录']));

        $userDistributeLevel = M('users')->where(['user_id' => $this->user_id])->value('distribut_level');
        $taskLogic = new TaskLogic(0);
        // 任务列表
        $taskList = $taskLogic->taskList();
        // 已失效的任务
        $invalidTaskIds = [];
        // 任务奖励
        $taskData = [];
        foreach ($taskList as $task) {
            if ($task['is_open'] == 0 || $task['start_time'] > time() || $task['end_time'] < time()) {
                $invalidTaskIds[] = $task['id'];
            }
            $taskReward = $taskLogic->taskReward($task['id']);
            $taskRewardData = [];
            foreach ($taskReward as $k => $reward) {
                if ($reward['distribut_id'] != 0 && $reward['distribut_id'] != $userDistributeLevel) {
                    continue;
                }
                switch ($reward['reward_type']) {
                    case 1:
                        // 积分
                        $rewardThing = $reward['reward_num'];
                        break;
                    case 2:
                        // 电子币
                        $rewardThing = $reward['reward_price'] . '元';
                        break;
                    case 3:
                        // 优惠券
                        $couponIds = explode('-', $reward['reward_coupon_id']);
                        $couponInfo = M('coupon')->where(['id' => ['IN', $couponIds], 'status' => 1])->select();
                        if (empty($couponInfo)) {
                            // 优惠券无法被领取
                            continue 2;
                        }
                        foreach ($couponInfo as $coupon) {
                            if ($coupon['send_end_time'] < time()) {
                                // 领取时间已过
                                continue 3;
                            } elseif ($coupon['send_num'] >= $coupon['createnum'] && 0 != $coupon['createnum']) {
                                // 优惠券被抢完
                                continue 3;
                            }
                        }
                        $rewardThing = '优惠券x' . count($couponIds);
                        break;
                    default:
                        continue;
                }
                $taskRewardData[$k] = [
                    'task_cate' => $reward['task_cate'],
                    'task_id' => $task['id'],
                    'task_title' => $task['title'],
                    'reward_id' => $reward['reward_id'],
                    'task_reward' => '+' . $rewardThing,
                    'task_reward_type' => $reward['reward_type'],
                    'reward_desc' => $reward['description'],
                    'reward_cycle' => $reward['cycle'],
                    'reward_set' => $reward['invite_num'] != 0 ? $reward['invite_num'] : ($reward['order_num'] != 0 ? $reward['order_num'] : 0), // 任务规定需要完成的次数（才能获得奖励）
                    'user_reward_set' => 0,         // 用户完成任务次数
                    'reward_times' => 0,            // 任务可领取奖励次数
                    'user_reward_times' => 0        // 用户可领取奖励次数（机会）
                ];
                // 查看用户任务记录
                $userTask = M('user_task')
                    ->where(['user_id' => $this->user_id, 'task_id' => $task['id'], 'task_reward_id' => $reward['reward_id'], 'status' => ['NEQ', -2]])
                    ->field('id, target_num, finish_num')->order('created_at desc')->find();
                if (!empty($userTask)) {
                    // 查看用户任务记录领取情况
                    $userTaskLog = M('task_log')->where(['user_task_id' => $userTask['id'], 'type' => 1])->find();
                    if (!empty($userTaskLog) && $userTaskLog['status'] == 1) {
                        // 已领取
                        $taskRewardData[$k]['user_reward_set'] = 0;
                        $taskRewardData[$k]['reward_times'] = $userTask['target_num'];
                    } else {
                        // 未完成 || 未领取
                        $taskRewardData[$k]['user_reward_set'] = $userTask['finish_num'];
                        $taskRewardData[$k]['reward_times'] = bcdiv($userTask['target_num'], $userTask['finish_num']);
                    }
                }
                // 用户可领取奖励次数（机会）
                $userTaskLog = M('task_log')->where(['user_id' => $this->user_id, 'task_id' => $task['id'], 'task_reward_id' => $reward['reward_id'],
                    'type' => 1, 'status' => 0])->count('id');
                $taskRewardData[$k]['user_reward_times'] = $userTaskLog == 0 ? $userTaskLog : bcdiv($userTaskLog, $taskRewardData[$k]['reward_set']);
            }
            if (empty($taskData)) {
                $taskData = $taskRewardData;
            } else {
                $taskData = array_merge($taskData, $taskRewardData);
            }
        }
        // 任务配置
        $taskConfig = M('task_config')->find();
        // 任务类型
        $taskCate = unserialize($taskConfig['config_value']);
        $cateData = [];
        foreach ($taskCate as $key => $cate) {
            $cateData[$key] = [
                'id' => $key,
                'title' => $cate,
                'is_all_finished' => 1,     // 分类下的任务是否全部完成
                'list' => []
            ];
            foreach ($taskData as $k2 => $data) {
                // 查看该类型的任务是否完成
                switch ($data['reward_cycle']) {
                    case 0:
                        // 一次性任务
                        if ($data['user_reward_set'] >= $data['reward_set']) {
                            $taskData[$k2]['reward_times'] = 0;
                            $data['is_finished'] = 1;
                        } else {
                            $data['is_finished'] = 0;
                        }
                        // 是否已领取奖励
                        if (M('task_log')->where(['task_id' => $data['task_id'], 'task_reward_id' => $data['reward_id'], 'user_id' => $this->user_id, 'status' => 1])->value('status')) {
                            $data['is_got'] = 1;
                        } else {
                            $data['is_got'] = 0;
                        }
                        break;
                    case 1:
                        // 每次（循环）
//                        $logCount = M('task_log')->where(['task_id' => $data['task_id'], 'task_reward_id' => $data['reward_id'], 'user_id' => $this->user_id, 'finished_at' => 0])->count('id');
//                        $times = $logCount >= $data['reward_set'] ? bcdiv($logCount, $data['reward_set']) : 0;
//                        $taskData[$k2]['user_reward_set'] = $times;
//                        $taskData[$k2]['reward_times'] = $times;
//                        $taskData[$k2]['user_reward_times'] = $times;
                        $logStatus = M('task_log')->where(['task_id' => $data['task_id'], 'task_reward_id' => $data['reward_id'], 'user_id' => $this->user_id])->order('id DESC')->value('status');
                        if (is_null($logStatus)) {
                            // 未完成任务
                            $data['is_finished'] = 0;
                            $data['is_got'] = 0;
                        } elseif ($logStatus == 0) {
                            // 已完成任务，但未领取奖励
                            $data['is_finished'] = 1;
                            $data['is_got'] = 0;
                        } elseif ($logStatus == 1) {
                            // 已完成任务，已领取奖励，可以继续完成任务
                            $data['is_finished'] = 0;
                            $data['is_got'] = 0;
                        }
                        break;
                }
                if ($key == $data['task_cate']) {
                    unset($data['task_cate']);
                    $cateData[$key]['list'][] = $data;
                }
            }
        }
        foreach ($cateData as $key => $cate) {
            if (empty($cate['list'])) {
                unset($cateData[$key]);
                continue;
            }
            // 查看分类下的任务是否：1、有可领取奖励 2、已全都完成
            $canGet = false;
            foreach ($cate['list'] as $list) {
                if ($list['is_finished'] == 0) {
                    $cateData[$key]['is_all_finished'] = 0;
                } elseif ($list['is_got'] == 0) {
                    $canGet = true;
                }
            }
            if (in_array($cate['list'][0]['task_id'], $invalidTaskIds) && !$canGet) {
                // 任务已失效，且没有未领取的奖励
                unset($cateData[$key]);
            }
        }
        $task = [
            'banner' => $taskConfig['banner'],
            'cate' => array_values($cateData)
        ];
        return json(['status' => 1, 'result' => $task]);
    }

    /**
     * 奖励记录
     * @return \think\response\Json
     */
    public function userTaskReward()
    {
        $taskLogic = new TaskLogic(0);
        $taskLogic->setUser($this->user);
        // 奖励记录
        $taskRewardLog = $taskLogic->taskLog(1, 1);
        // 任务配置
        $taskConfig = unserialize(M('task_config')->value('config_value'));

        $rewardLogList = [];
        $integral = 0.00;
        $electronic = 0.00;
        $coupon = 0;
        foreach ($taskRewardLog as $log) {
            switch ($log['reward_type']) {
                case 1:
                    // 积分
                    $reward = '+' . $log['reward_integral'] . '积分';
                    $integral = bcadd($integral, $log['reward_integral'], 2);
                    break;
                case 2:
                    // 电子币
                    $reward = '+' . $log['reward_electronic'] . '电子币';
                    $electronic = bcadd($electronic, $log['reward_electronic'], 2);
                    break;
                case 3:
                    // 优惠券
                    $couponIds = explode('-', $log['reward_coupon_id']);
                    $reward = '+优惠券' . count($couponIds) . '张';
                    $coupon += count($couponIds);
                    break;
                default:
                    continue;
            }
            $rewardLogList[] = [
                'log_id' => $log['id'],
                'cate_name' => $taskConfig[$log['task_cate']],
                'title' => $log['task_title'],
                'reward' => $reward,
                'create_time' => $log['finished_at'] != 0 ? $log['finished_at'] : $log['created_at']
            ];
        }
        $rewardLog = [
            'integral' => $integral,
            'electronic' => $electronic,
            'coupon' => $coupon,
            'reward_list' => $rewardLogList
        ];
        return json(['status' => 1, 'result' => $rewardLog]);
    }

    /**
     * 验证用户信息（转账的时候使用）By J.
     *
     * @return \think\response\Json
     */
    public function checkUser()
    {
        $check_user_id = I('id/d', 0);

        if (!$check_user_id) {
            return json(['status' => -1, 'msg' => '请输入会员号进行查询', 'result' => null]);
        }

        $check_user_info = M('Users')->field('user_id,user_name,mobile,nickname')->where('user_id', $check_user_id)->find();

        if (!$check_user_info) {
            return json(['status' => -1, 'msg' => '你输入的会员号不存在，请重新确认再输入！', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => 'ok', 'result' => $check_user_info]);
    }

    /**
     * 查看用户是否存在
     * @return \think\response\Json
     */
    public function checkUserNew()
    {
        $username = I('user_name', '');
        if (check_mobile($username)) {
            $user = Db::name('users')->where(['mobile' => $username])->find();
        } else {
            $user = Db::name('users')->where(['user_id' => $username])->find();
        }
        if (!$user) {
            return json(['status' => 0, 'msg' => '无此用户']);
        }
        $result = [
            'user_id' => $user['user_id'],
            'user_name' => $user['user_name']
        ];
        return json(['status' => 1, 'result' => $result]);
    }

    /**
     * （新）获取用户信息
     * @param Request $request
     * @return \think\response\Json
     */
    public function userInfo(Request $request)
    {
        if ($request->isPost()) {
            // 修改用户信息
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.real_name') ? $post['real_name'] = I('post.real_name') : false;  // 真实姓名
            I('post.id_cart') ? $post['id_cart'] = I('post.id_cart') : false;  // 身份证
            I('post.birthday') ? $post['birthday'] = I('post.birthday') : false;  // 生日
            if (isset($post['id_cart']) && !check_id_card($post['id_cart'])) {
                return json(['status' => 0, 'msg' => '身份证填写错误']);
            }
            $userLogic = new UsersLogic();
            if (!$userLogic->update_info($this->user_id, $post)) {
                return json(['status' => 0, 'msg' => '操作失败']);
            }
            // 完善资料获得积分
            $is_consummate = $userLogic->is_consummate($this->user_id);
            if ($is_consummate !== false) {
                return json(['status' => 1, 'msg' => '操作成功', 'result' => ['point' => $is_consummate]]);
            } else {
                return json(['status' => 1, 'msg' => '操作成功', 'result' => ['point' => '0']]);
            }
        }
        // 更新用户推送tags
        $updateJpushTags = 0;
        $res = (new PushLogic())->bindPushTag($this->user);
        if ($res['status'] == 2) {
            $updateJpushTags = 1;
            $this->user = Db::name('users')->where('user_id', $this->user['user_id'])->find();
            // 更新缓存
            $this->redis->set('user_' . $this->user['token'], $this->user, config('REDIS_TIME'));
        }
        $data = [];
        // 用户信息
        $data['user'] = [
            'user_id' => $this->user['user_id'],
            'sex' => $this->user['sex'],
            'nickname' => $this->user['nickname'] == '' ? $this->user['user_name'] : $this->user['nickname'],
            'user_name' => $this->user['user_name'],
            'real_name' => $this->user['real_name'],
            'id_cart' => $this->user['id_cart'],
            'birthday' => $this->user['birthday'],
            'mobile' => $this->user['mobile'],
            'head_pic' => $this->user['head_pic'],
            'type' => $this->user['distribut_level'] >= 3 ? '2' : $this->user['type'],
            'invite_uid' => $this->user['invite_uid'],
            'is_distribut' => $this->user['is_distribut'],
            'is_lock' => $this->user['is_lock'],
            'level' => $this->user['distribut_level'],
            'level_name' => M('DistributLevel')->where('level_id', $this->user['distribut_level'])->getField('level_name') ?? '普通会员',
            'is_not_show_jk' => $this->user['is_not_show_jk'],  // 是否提示加入金卡弹窗
            'is_not_show_invite' => $this->user['distribut_level'] >= 3 ? 1 : 0,  // 是否隐藏推荐人绑定
            'has_pay_pwd' => $this->user['paypwd'] ? 1 : 0,
            'is_app' => TokenLogic::getValue('is_app', $this->user['token']) ? 1 : 0,
            'token' => $this->user['token'],
            'jpush_tags' => explode(',', $this->user['push_tag']),
            'action_after_update' => [
                'update_jpush_tags' => $updateJpushTags
            ]
        ];
        if (I('get.is_wealth', null)) {
            // 输出资金信息
            $will_distribut_money = M('RebateLog')->field('SUM(money) as money')->where('user_id', $this->user_id)->where('status', 'in', [1, 2])->find();
            $data['user']['wealth'] = [
                'user_money' => $this->user['user_money'],
                'frozen_money' => $this->user['frozen_money'],
                'user_electronic' => $this->user['user_electronic'],
                'frozen_electronic' => $this->user['frozen_electronic'],
                'pay_points' => $this->user['pay_points'],
                'level' => $this->user['distribut_level'],
                'level_name' => M('DistributLevel')->where('level_id', $this->user['distribut_level'])->getField('level_name'),
                'distribute_money' => $this->user['distribut_money'],
                'will_distribute_money' => isset($will_distribut_money['money']) ? $will_distribut_money['money'] : '0.00'
            ];
        }
        if (I('get.is_invite', null)) {
            // 输出邀请码
            $baseUrl = url('/', '', '', true);
            $filename = 'public/images/qrcode/user/user_' . $this->user_id . '.png';
            $return['qr_img'] = $filename;
            $return['user_id'] = $this->user_id;
            $return['basic_url'] = $baseUrl;
            if (!file_exists($filename)) {
                // 生成二维码
                $this->scerweima($this->user_id);
            }
            $data['user']['invite'] = $return;
        }
        // 获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount($this->user_id);
        $data['user_message_count'] = $user_message_count;
        // 获取用户活动信息的数量
        $articleLogic = new ArticleLogic();
        $user_article_count = $articleLogic->getUserArticleCount($this->user_id);
        $data['user_article_count'] = $user_article_count;

        $data['sex'] = C('SEX');

        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }

    /**
     * 用户资金信息
     * @return \think\response\Json
     */
    public function wealth()
    {
        $type = I('type', 1);
        $usersLogic = new UsersLogic();
        switch ($type) {
            case 1:
                // 余额
                $return['amount'] = M('users')->where(['user_id' => $this->user_id])->value('user_money');
                $return['will_get_amount'] = M('RebateLog')->where('user_id', $this->user_id)->where('status', 'in', [1, 2])->value('SUM(money) as money') ?? '0.00';
                $return['log_list'] = [];
                $result = $usersLogic->get_money_log($this->user_id, 0, null, true)['result'];
                foreach ($result as $res) {
                    foreach ($res as $log) {
                        $changeTime = strtotime($log['change_time']);
                        $return['log_list'][] = [
                            'id' => $log['log_id'],
                            'title' => $log['desc'],
                            'amount' => $log['user_money'],
                            'add_time' => $changeTime,
                            'add_month' => date('Y-m', $changeTime)
                        ];
                    }
                }
                break;
            case 2:
                // 电子币
                $return['amount'] = M('users')->where(['user_id' => $this->user_id])->value('user_electronic');
                $return['will_get_amount'] = '0';
                $return['log_list'] = [];
                $result = $usersLogic->get_electronic_log($this->user_id, 0, null, true)['result'];
                foreach ($result as $res) {
                    foreach ($res as $log) {
                        $changeTime = strtotime($log['change_time']);
                        $return['log_list'][] = [
                            'id' => $log['log_id'],
                            'title' => $log['desc'],
                            'amount' => $log['user_electronic'],
                            'add_time' => $changeTime,
                            'add_month' => date('Y-m', $changeTime)
                        ];
                    }
                }
                break;
            default:
                return json(['status' => 0, 'msg' => '类型错误']);
        }
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 获取推荐人信息
     * @return \think\response\Json
     */
    public function getInviteUser()
    {
        $inviteUid = M('users')->where(['user_id' => $this->user_id])->value('invite_uid');
        if (!$inviteUid || $inviteUid == 0) {
            return json(['status' => -11, 'msg' => '暂无推荐人']);
        }
        $inviteUser = M('users')->where(['user_id' => $inviteUid])->field('user_id, nickname, user_name')->find();
        if (!empty($inviteUser['user_name'])) {
            $inviteUser['nickname'] = $inviteUser['user_name'];
        }
        unset($inviteUser['user_name']);
        return json(['status' => 1, 'result' => $inviteUser]);
    }

    /**
     * 查看用户购买VIP套餐资格
     * @return \think\response\Json
     */
    public function checkUserLevelGoods()
    {
        $goodsId = I('goods_id', '');
        if (!$goodsId || $goodsId === 0) {
            return json(['status' => 0, 'msg' => '请传入正确的商品ID']);
        }
        // 商品分销等级
        $goodsDistribute = M('goods')->where(['goods_id' => $goodsId])->value('distribut_id');
        if (in_array($goodsDistribute, [0, 1])) {
            return json(['status' => 1]);
        }
        // 用户分销等级
        $userDistribute = M('users')->where(['user_id' => $this->user_id])->value('distribut_level');
        if ($userDistribute == 1 && $goodsDistribute == 3) {
            return json(['status' => -11, 'msg' => '普通会员无法购买SVIP产品']);
        }
        if ($goodsDistribute <= $userDistribute) {
            switch ($userDistribute) {
                case 2:
                    return json(['status' => -11, 'msg' => '你已经是VIP会员了，无法再次购买']);
                case 3:
                    return json(['status' => -11, 'msg' => '你已经是SVIP会员了，无法再次购买']);
            }
        }
        return json(['status' => 1]);
    }

    /**
     * 银行列表
     * @return \think\response\Json
     */
    public function bankList()
    {
        $bankList = M('bank')->where(['status' => 1])->field('id, name_cn name, name_en code, icon')->order('sort desc')->select();
        return json(['status' => 1, 'result' => ['list' => $bankList]]);
    }

    /**
     * 用户银行卡信息
     * @return \think\response\Json
     */
    public function bankCard()
    {
        $return = [
            'bank_id' => '',
            'bank_name' => '',
            'bank_icon' => '',
            'account' => '',
            'hide_account' => '',
            'money_need' => tpCache('basic.need') ?? '0',
            'money_min' => tpCache('basic.min') ?? '0',
            'hand_fee' => '￥' . tpCache('basic.hand_fee') ?? '0'
        ];
        $userBankInfo = M('users')->where(['user_id' => $this->user_id])->field('id_cart id_card, bank_name, bank_code, bank_region, bank_branch, bank_card')->find();
        if ($userBankInfo['bank_code'] == 'Alipay') {
            // 支付宝
            $bankInfo = M('bank')->where(['name_en' => $userBankInfo['bank_code']])->field('id, name_cn, icon')->find();
            $return = [
                'bank_id' => $bankInfo['id'],
                'bank_name' => $bankInfo['name_cn'],
                'bank_icon' => $bankInfo['icon'],
                'account' => $userBankInfo['bank_card'],
                'hide_account' => strlen($userBankInfo['bank_card']) > 4 ? hideStr($userBankInfo['bank_card'], 0, 4, 4) : $userBankInfo['bank_card'],
                'money_need' => tpCache('basic.need') ?? '0',
                'money_min' => tpCache('basic.min') ?? '0',
                'hand_fee' => '￥' . tpCache('basic.hand_fee')
            ];
        } elseif (!empty($userBankInfo['bank_card'])) {
            // 银行
            if (empty($userBankInfo['bank_code']) || empty($userBankInfo['bank_region']) || empty($userBankInfo['bank_branch'])) {
                // 之前设置不完善的数据
                $return['account'] = $userBankInfo['bank_card'];
                $return['hide_account'] = strlen($userBankInfo['bank_card']) > 4 ? hideStr($userBankInfo['bank_card'], 0, 4, 4) : $userBankInfo['bank_card'];
            } else {
                // 后来补充完善的数据
                $bankInfo = M('bank')->where(['name_cn' => $userBankInfo['bank_name']])->field('id, name_cn, icon')->find();
                $return = [
                    'bank_id' => $bankInfo['id'],
                    'bank_name' => $bankInfo['name_cn'],
                    'bank_icon' => $bankInfo['icon'],
                    'account' => $userBankInfo['bank_card'],
                    'hide_account' => strlen($userBankInfo['bank_card']) > 4 ? hideStr($userBankInfo['bank_card'], 0, 4, 4) : $userBankInfo['bank_card'],
                    'money_need' => tpCache('basic.need') ?? '0',
                    'money_min' => tpCache('basic.min') ?? '0',
                    'hand_fee' => '￥' . tpCache('basic.hand_fee')
                ];
            }
        }
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 用户银行详情
     * @return \think\response\Json
     */
    public function bankCardInfo()
    {
        if ($this->request->isPost()) {
            $post['bank_code'] = I('bank_code', '');
            $bankId = I('bank_id', '');
            $post['bank_region'] = I('bank_region', '');
            $post['bank_branch'] = I('bank_branch', '');
            $post['bank_card'] = I('account', '');
            $post['real_name'] = I('real_name', '');
            $post['id_cart'] = I('id_card', '');

            if (empty($post['bank_code']) || empty($bankId)) return json(['status' => 0, 'msg' => '请选择银行']);
            if ($post['bank_code'] != 'Alipay' && (empty($post['bank_region']) || empty($post['bank_branch']))) return json(['status' => 0, 'msg' => '请填写银行信息']);
            if (empty($post['bank_card']) || empty($post['real_name'])) return json(['status' => 0, 'msg' => '请填写银行信息']);
            if (!check_id_card($post['id_cart'])) return json(['status' => 0, 'msg' => '请填写正确的身份证格式']);

            Db::startTrans();
            // 清除旧信息
            $res = M('users')->where(['user_id' => $this->user_id])->update([
                'id_cart' => '',
                'real_name' => '',
                'bank_name' => '',
                'bank_code' => '',
                'bank_region' => '',
                'bank_branch' => '',
                'bank_card' => ''
            ]);
            if (!$res) {
                Db::rollback();
                return json(['status' => 0, 'msg' => '记录错误']);
            }
            // 更新信息
            $post['bank_name'] = M('bank')->where(['id' => $bankId])->value('name_cn');
            $usersLogic = new UsersLogic();
            if (!$usersLogic->update_info($this->user_id, $post)) {
                Db::rollback();
                return json(['status' => 0, 'msg' => '记录失败']);
            }
            Db::commit();
            return json(['status' => 1, 'msg' => '银行卡信息已保存成功']);
        }
        // 用户银行卡信息
        $userBankInfo = M('users')->where(['user_id' => $this->user_id])->field('id_cart id_card, real_name, bank_name, bank_code, bank_region, bank_branch, bank_card account')->find();
        $userBankInfo['hide_account'] = strlen($userBankInfo['account']) > 4 ? hideStr($userBankInfo['account'], 0, 4, 4, '*') : $userBankInfo['account'];
        $userBankInfo['hide_id_card'] = strlen($userBankInfo['id_card']) > 4 ? hideStr($userBankInfo['id_card'], 0, 4, 4, '*') : $userBankInfo['id_card'];
        if ($userBankInfo['bank_code'] == 'Alipay') {
            // 支付宝
            $bankInfo = M('bank')->where(['name_en' => $userBankInfo['bank_code']])->field('id, name_cn, name_en')->find();
            $userBankInfo['bank_id'] = $bankInfo['id'];
            $userBankInfo['bank_name'] = $bankInfo['name_cn'];
            $userBankInfo['bank_code'] = $bankInfo['name_en'];
            $userBankInfo['bank_region'] = '';
            $userBankInfo['bank_branch'] = '';
        } elseif (!empty($userBankInfo['account'])) {
            // 银行
            if (empty($userBankInfo['bank_region']) || empty($userBankInfo['bank_branch'])) {
                // 之前设置不完善的数据
                $userBankInfo['bank_id'] = '0';
                $userBankInfo['bank_name'] = '';
                $userBankInfo['bank_code'] = '';
                $userBankInfo['bank_region'] = '';
                $userBankInfo['bank_branch'] = $userBankInfo['bank_name'];
            } else {
                // 后来补充完善的数据
                $bankInfo = M('bank')->where(['name_cn' => $userBankInfo['bank_name']])->field('id, name_cn, name_en')->find();
                $userBankInfo['bank_id'] = $bankInfo['id'];
                $userBankInfo['bank_name'] = $bankInfo['name_cn'];
                $userBankInfo['bank_code'] = $bankInfo['name_en'];
            }
        } else {
            $userBankInfo = [
                'id_card' => '',
                'hide_id_card' => '',
                'real_name' => '',
                'bank_name' => '',
                'bank_region' => '',
                'bank_branch' => '',
                'bank_id' => '',
                'bank_code' => '',
                'account' => '',
                'hide_account' => '',
            ];
        }
        return json(['status' => 1, 'result' => $userBankInfo]);
    }

    /**
     * 获取代理商系统地址
     * @return string
     */
    public function getAgentUrl()
    {
        if (!$this->isApp) {
            return json(['status' => 0, 'msg' => '抱歉，该区域目前只在APP开放，请下载最新版APP体验']);
        }
        if (!$this->user) {
            return json(['status' => 0, 'msg' => '请先登录']);
        }
        if ($this->user['distribut_level'] != 3) {
            return json(['status' => 0, 'msg' => '抱歉，SVIP才能进入']);
        }
        $sign = shopEncrypt(time(), $this->user['user_name']);
        $url = C('SERVER_URL') . '/Index/index/login_sign/shop/user_name/' . $this->user['user_name'] . '/time/' . time() . '/sign/' . $sign;
        return json(['status' => 1, 'result' => ['url' => $url]]);
    }

    /**
     * 检测是否显示登陆奖励
     * @return \think\response\Json
     */
    public function checkLoginProfit()
    {
        if ($this->user_id == 36410) {
            // APP审核账号
            return json(['status' => 1, 'result' => ['state' => 0, 'url' => '']]);
        }
        if ($this->passAuth) {
            $result = ['status' => 1, 'result' => ['state' => 0, 'url' => '']];
        } else {
            // 登录奖励
            $taskLogic = new TaskLogic(4);
            if ($taskLogic->checkTaskEnable(true)) {
                if (!empty($this->user['bind_uid']) || $this->user['bind_uid'] != 0) {
                    // 是绑定的旧账号
                    $userId = $this->user['bind_uid'];
                    $taskLogic->setUserId($userId);
                    if ($taskLogic->checkLoginProfit()) {
                        //--- 新账号未领取过奖励
                        $taskLogic->setUserId($this->user_id);
                        if ($taskLogic->checkLoginProfit()) {
                            $url = SITE_URL . '/#/app_redRain?red_token=' . $this->user['token'];
                            $result = ['status' => 1, 'result' => ['state' => 1, 'url' => $url]];
                        } else {
                            $result = ['status' => 1, 'result' => ['state' => 0, 'url' => '']];
                        }
                    } else {
                        //--- 新账号已领取过奖励，因此旧账号不能获取奖励
                        $result = ['status' => 1, 'result' => ['state' => 0, 'url' => '']];
                    }
                } else {
                    // 普通账号
                    $taskLogic->setUserId($this->user_id);
                    if ($taskLogic->checkLoginProfit()) {
                        $url = SITE_URL . '/#/app_redRain?red_token=' . $this->user['token'];
                        $result = ['status' => 1, 'result' => ['state' => 1, 'url' => $url]];
                    } else {
                        $result = ['status' => 1, 'result' => ['state' => 0, 'url' => '']];
                    }
                }
            } else {
                $result = ['status' => 1, 'result' => ['state' => 0, 'url' => '']];
            }
        }
        return json($result);
    }

    /**
     * 领取登录奖励
     * @return \think\response\Json
     */
    public function loginProfit()
    {
        $taskLogic = new TaskLogic(4);
        $taskLogic->setUser($this->user);
        $taskLogic->setUserId($this->user_id);
        $res = $taskLogic->checkLoginProfit();
        if (!$res) {
            return json(['status' => 0, 'msg' => '您已领取过奖励']);
        }
        $res = $taskLogic->loginProfit();
        return json($res);
    }

    /**
     * 查看用户通知（我的页面）
     * @return \think\response\Json
     */
    public function checkNote()
    {
        $noteList = [];
        /*
         * 用户是否有完成未领取的任务奖励
         */
        $userTaskLog = M('task_log tl')
            ->join('task t', 't.id = tl.task_id')
            ->join('task_reward tr', 'tr.reward_id = tl.task_reward_id')
//            ->where(['t.is_open' => 1, 't.start_time' => ['<=', time()], 't.end_time' => ['>=', time()]])
            ->where(['t.id' => ['not in', [1, 4]], 'tl.user_id' => $this->user_id, 'tl.type' => 1, 'tl.status' => 0])
            ->order('created_at desc')
            ->field('t.id, t.title, tr.reward_type, tr.reward_coupon_id')->select();
        if (!empty($userTaskLog)) {
            $taskData = [];
            foreach ($userTaskLog as $taskLog) {
                if ($taskLog['reward_type'] == 3) {
                    // 查看优惠券是否能领取
                    $couponIds = explode('-', $taskLog['reward_coupon_id']);
                    $couponInfo = M('coupon')->where(['id' => ['IN', $couponIds], 'status' => 1])->select();
                    if (empty($couponInfo)) {
                        // 优惠券无法被领取
                        continue;
                    }
                    foreach ($couponInfo as $coupon) {
                        if ($coupon['send_end_time'] < time()) {
                            // 领取时间已过
                            continue 2;
                        } elseif ($coupon['send_num'] >= $coupon['createnum'] && 0 != $coupon['createnum']) {
                            // 优惠券被抢完
                            continue 2;
                        }
                    }
                    $taskData = [
                        'id' => $taskLog['id'],
                        'title' => $taskLog['title']
                    ];
                    break;
                } else {
                    $taskData = [
                        'id' => $taskLog['id'],
                        'title' => $taskLog['title']
                    ];
                    break;
                }
            }
            if (!empty($taskData)) {
                $noteList[] = [
                    'type' => 1,
                    'is_note' => 1,
                    'note_data' => [
                        'id' => $taskData['id'],
                        'title' => $taskData['title']
                    ]
                ];
            } else {
                $noteList[] = [
                    'type' => 1,
                    'is_note' => 0,
                    'note_data' => [
                        'id' => '0',
                        'title' => ''
                    ]
                ];
            }
        } else {
            $noteList[] = [
                'type' => 1,
                'is_note' => 0,
                'note_data' => [
                    'id' => '0',
                    'title' => ''
                ]
            ];
        }
        /*
         * 用户是否已经升级成为VIP
         */
        $vip_buy_tips = trim(tpCache('distribut.vip_buy_tips'));
        $referee_vip_tips = trim(tpCache('distribut.referee_vip_tips'));
        $referee_svip_tips = trim(tpCache('distribut.referee_svip_tips'));
        if ($vip_buy_tips || $referee_vip_tips || $referee_svip_tips) {
            $distributeLog = M('distribut_log')->where(['user_id' => $this->user_id, 'type' => ['IN', [1, 3]], 'note_status' => 0])->select();
            if (!empty($distributeLog)) {
                foreach ($distributeLog as $log) {
                    switch ($log['type']) {
                        case 1:
                            // 购买VIP/SVIP套组升级
                            if (M('distribut_log')->where(['user_id' => $this->user_id, 'order_sn' => $log['order_sn'], 'type' => 2])->find() || (!$referee_vip_tips && !$referee_svip_tips)) {
                                continue;
                            }
                            switch ($log['new_level']) {
                                case 2:
                                    $noteList[] = [
                                        'type' => 2,
                                        'is_note' => 1,
                                        'note_data' => [
                                            'id' => $log['id'],
                                            'title' => $referee_vip_tips
                                        ]
                                    ];
                                    break;
                                case 3:
                                    $noteList[] = [
                                        'type' => 4,
                                        'is_note' => 1,
                                        'note_data' => [
                                            'id' => $log['id'],
                                            'title' => $referee_svip_tips
                                        ]
                                    ];
                                    break;
                            }
                            break 2;
                        case 3:
                            // 累积消费升级
                            if (!$vip_buy_tips) {
                                continue;
                            }
                            $noteList[] = [
                                'type' => 3,
                                'is_note' => 1,
                                'note_data' => [
                                    'id' => $log['id'],
                                    'title' => $vip_buy_tips
                                ]
                            ];
                            break 2;
                    }
                }
            } else {
                $noteList[] = [
                    'type' => 2,
                    'is_note' => 0,
                    'note_data' => [
                        'id' => '0',
                        'title' => ''
                    ]
                ];
            }
        }
        return json(['status' => 1, 'result' => ['list' => $noteList]]);
    }

    /**
     * 关闭用户通知（我的页面）
     * @return \think\response\Json
     */
    public function closeNote()
    {
        $type = I('type', 2);
        switch ($type) {
            case 2:
            case 4:
                /*
                 * 用户升级成为VIP弹窗
                 */
                M('distribut_log')->where(['user_id' => $this->user_id, 'type' => 1, 'note_status' => 0])->update(['note_status' => 1]);
                $return = ['status' => 1];
                break;
            case 3:
                /*
                 * 用户升级成为VIP弹窗
                 */
                M('distribut_log')->where(['user_id' => $this->user_id, 'type' => 3, 'note_status' => 0])->update(['note_status' => 1]);
                $return = ['status' => 1];
                break;
            default:
                $return = ['status' => 0, 'msg' => '通知类型错误'];
        }
        return json($return);
    }

    /**
     * 检查真实姓名与身份证是否匹配
     * @return \think\response\Json
     */
    public function checkIdCard()
    {
        $realName = I('real_name', '');
        $idCard = I('id_card', '');
        $setAge = I('age', 0);
        if (empty($realName)) return json(['status' => 0, 'msg' => '请传入真实姓名']);
        if (empty($idCard)) return json(['status' => 0, 'msg' => '请传入身份证号码']);
        if (!check_id_card($idCard)) return json(['status' => 0, 'msg' => '请填写正确的身份证号码']);
        // 第三方验证姓名与身份证
        $apiController = new ApiController();
        $query = [
            'id_card' => $idCard,
            'real_name' => $realName
        ];
        $res = $apiController->checkIdCard($query, 'array');
        if ($res['status'] != '01') {
            return json(['status' => 0, 'msg' => "请填写正确的身份信息\r\n（身份证号以及姓名）"]);
        }
        if ($setAge > 0) {
            // 验证是否已满XX周岁
            $birthday = $res['birthday'];
            $age = date('Y', time()) - date('Y', strtotime($birthday)) - 1;
            if (date('m', time()) == date('m', strtotime($birthday))) {
                if (date('d', time()) > date('d', strtotime($birthday))) {
                    $age++;
                }
            } elseif (date('m', time()) > date('m', strtotime($birthday))) {
                $age++;
            }
            if ($age < $setAge) {
                return json(['status' => 0, 'msg' => "您未满{$setAge}周岁，不能购买烟酒类商品"]);
            }
        }
        // 记录身份证信息
        if (!M('user_id_card_info')->where(['real_name' => $realName, 'id_card' => $idCard])->find()) {
            M('user_id_card_info')->add([
                'user_id' => $this->user_id ?? 0,
                'id_card' => $idCard,
                'real_name' => $realName
            ]);
        }
        return json(['status' => 1]);
    }

    /**
     * 我的页面中的提示红点标识
     * @return \think\response\Json
     */
    public function tipsCheck()
    {
        $return = [
            'wealth' => 0,              // 我的收益
            'distribute' => 0,          // 我的推荐
            'task' => 0,                // 我的任务
            'community' => 0,           // 社区
            'account' => 0,             // 积分
            'coupon' => 0,              // 礼券
            'visit' => 0,               // 足迹
            'collect' => 0              // 收藏
        ];
        /*
         * 社区
         */
        $param['is_browse'] = 0;
        $param['status'] = '';
        $param['user_id'] = $this->user_id;
        $communityLogic = new CommunityLogic();
        // 获取用户文章数据
        $list = $communityLogic->getArticleList($param)['list'];
        foreach ($list as $value) {
            switch ($value['status']) {
                case -1:
                    $return['community'] += 1;
                    break;
            }
        }
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 显示新用户奖励弹窗
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getNewProfit()
    {
        $return = [
            'state' => 0,
            'coupon_list' => []
        ];
        if ($this->user['is_new'] == 1) {
            if (M('order')->where(['user_id' => $this->user_id])->value('order_id')) {
                // 老用户
                M('users')->where(['user_id' => $this->user_id])->update(['is_new' => 0]);
                $user = Db::name('users')->where('user_id', $this->user_id)->find();
                TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
            } else {
                // 新用户
                $res = (new UsersLogic())->checkNewProfit($this->user_id);
                if ($res['status'] != 0) {
                    if ($res['status'] == -1) {
                        // 新会员赠送优惠券
                        (new CouponLogic())->sendNewUser($this->user_id);
                    }
                    // 新用户奖励记录
                    $userNewLog = M('user_new_log')->where(['user_id' => $this->user_id, 'status' => ['NEQ', -1]])->order('add_time DESC')->find();
                    if (!empty($userNewLog)) {
                        // 优惠券信息
                        $couponIds = explode(',', $userNewLog['coupon_id']);
                        $couponData = M('coupon')->where(['id' => ['IN', $couponIds]])->order('id desc')->select();
                        // 优惠券商品
                        $couponGoods = Db::name('goods_coupon gc')->join('goods g', 'g.goods_id = gc.goods_id')->where(['gc.coupon_id' => ['in', $couponIds]])->field('gc.coupon_id, g.goods_id, g.goods_name, g.original_img')->select();
                        // 优惠券分类
                        $couponCate = Db::name('goods_coupon gc1')->join('goods_category gc2', 'gc1.goods_category_id = gc2.id')->where(['gc1.coupon_id' => ['in', $couponIds]])->getField('gc1.coupon_id, gc2.id cate_id, gc2.name cate_name', true);
                        $couponLogic = new CouponLogic();
                        $couponList = [];
                        foreach ($couponData as $k => $coupon) {
                            if ($coupon['use_type'] == 1) {
                                foreach ($couponGoods as $goods) {
                                    if ($coupon['id'] == $goods['coupon_id']) {
                                        $couponList[] = [
                                            'coupon_id' => $coupon['id'],
                                            'use_type' => $coupon['use_type'],
                                            'money' => floatval($coupon['money']) . '',
                                            'use_start_time' => date('Y.m.d', $coupon['use_start_time']),
                                            'use_end_time' => date('Y.m.d', $coupon['use_end_time']),
                                            'cate_id' => '',
                                            'cate_name' => '',
                                            'goods_id' => $goods['goods_id'],
                                            'goods_name' => $goods['goods_name'],
                                            'original_img_new' => getFullPath($goods['original_img']),
                                            'title' => $coupon['name'],
                                            'desc' => '￥' . floatval($coupon['money']) . '仅限' . $goods['goods_name'] . '可用',
                                            'content' => $coupon['content'] ?? ''
                                        ];
                                    }
                                }
                            } else {
                                $couponList[$k] = [
                                    'coupon_id' => $coupon['id'],
                                    'use_type' => $coupon['use_type'],
                                    'money' => floatval($coupon['money']) . '',
                                    'use_start_time' => date('Y.m.d', $coupon['use_start_time']),
                                    'use_end_time' => date('Y.m.d', $coupon['use_end_time']),
                                    'cate_id' => isset($couponCate[$coupon['id']]) ? $couponCate[$coupon['id']]['cate_id'] : '',
                                    'cate_name' => isset($couponCate[$coupon['id']]) ? $couponCate[$coupon['id']]['cate_name'] : '',
                                    'goods_id' => '',
                                    'goods_name' => '',
                                    'original_img_new' => '',
                                    'title' => '',
                                    'desc' => '',
                                    'content' => $coupon['content'] ?? ''
                                ];
                                // 优惠券展示描述
                                $res = $couponLogic->couponTitleDesc($coupon, $couponList[$k]['goods_name'], $couponList[$k]['cate_name']);
                                if (empty($res)) {
                                    continue;
                                }
                                $couponList[$k]['title'] = $res['title'];
                                $couponList[$k]['desc'] = $res['desc'];
                            }
                            unset($couponList[$k]['cate_id']);
                            unset($couponList[$k]['cate_name']);
                            unset($couponList[$k]['goods_id']);
                            unset($couponList[$k]['goods_name']);
                        }
                        $return = [
                            'state' => 1,
                            'coupon_list' => $couponList
                        ];
                        // 更新奖励记录
                        M('user_new_log')->where(['id' => $userNewLog['id']])->update(['status' => 1]);
                        // 更新用户信息
                        M('users')->where(['user_id' => $this->user_id])->update(['is_new' => 0]);
                        $user = Db::name('users')->where('user_id', $this->user_id)->find();
                        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
                    }
                }
            }
        }
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 会员升级提示信息
     * @return \think\response\Json
     */
    public function levelTipsInfo()
    {
        $type = I('type', 2); // 2为普卡会员 3为网点会员
        $where = [];
        $levelTipsInfo = [
            'svip_benefit' => []
        ];
        switch ($type) {
            case 3:
                $where['type'] = 'svip_benefit';
                break;
        }
        $distributeConfig = M('distribute_config')->where($where)->select();
        foreach ($distributeConfig as $config) {
            $levelTipsInfo['svip_benefit'][] = [
                'name' => $config['name'],
                'icon' => getFullPath($config['url'])
            ];
        }
        return json(['status' => 1, 'result' => $levelTipsInfo]);
    }

    /**
     * 根据手机查找用户信息
     * @return \think\response\Json
     */
    public function checkByPhone()
    {
        $mobile = I('mobile');
        if (!check_mobile($mobile)) return json(['status' => 0, 'msg' => '请输入正确的手机号']);
        $code = I('code', '');
        if ($code) {
            $session_id = S('mobile_token_' . $mobile);
            if (!$session_id) {
                return json(['status' => 0, 'msg' => '验证码已过期']);
            }
            $check_code = (new UsersLogic())->check_validate_code($code, $mobile, 'phone', $session_id, 6);
            if (1 != $check_code['status']) {
                return json($check_code);
            }
        }
        $infoList = M('users')->where(['mobile' => $mobile, 'is_lock' => 0, 'is_cancel' => 0])->field('user_id, user_name')->select();
        $list = [];
        foreach ($infoList as $info) {
            if (!isset($list[$info['user_name']])) {
                $list[$info['user_name']] = [
                    'user_name' => $info['user_name'] ?? '',
                    'user_ids' => [$info['user_id']]
                ];
            } else {
                $list[$info['user_name']]['user_ids'][] = $info['user_id'];
            }
        }
        $return = [
            'list' => array_values($list)
        ];
        return json(['status' => 1, 'result' => $return]);
    }
}
