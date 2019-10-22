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
use app\common\logic\GoodsLogic;
use app\common\logic\MessageLogic;
use app\common\logic\Token as TokenLogic;
use app\common\logic\UsersLogic;
use think\Db;
use think\Hook;
use think\Loader;
use think\Page;
use think\Request;
use think\Url;
use think\Verify;

class User extends Base
{
    public $user_id = 0;
    public $user = [];

    public function __construct()
    {
        parent::__construct();
        // 1. 检查登陆
        $params['user_token'] = isset($this->userToken) ? $this->userToken : null;
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);
        $user = session('user');
        if ($user) {
            $this->user = $user;
            $this->user_id = $user['user_id'];
        }
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

        $count = M('users')->where(array('first_leader' => $this->user_id, 'distribut_level' => array('egt', 2)))->count();
        $apply_check_num = tpCache('basic.apply_check_num');
        if ($Users['distribut_level'] < 2) {
            $error = '您尚未有资格开通金卡会员';
            return json(['status' => 1, 'msg' => 'success', 'result' => ['show_status' => 0, 'show_detail' => $error]]);//已经是金卡
        } else if ($count < $apply_check_num) {
            $error = '您尚未有资格开通金卡会员';
            return json(['status' => 1, 'msg' => 'success', 'result' => ['show_status' => 0, 'show_detail' => $error]]);//已经是金卡
        }
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
            return json(['status' => 0, 'msg' => '您已领取，不能重复领取']);
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
            $is_consummate = $logic->is_consummate($this->user_id, $this->user);
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
        $invite_uid = M('Users')->where('user_id', $id)->getField('invite_uid');

        if ($invite_uid > 0) {
            if ($invite_uid == $this->user_id) {
                return true;
            }
//            return $this->_hasRelationship($invite_uid);
        }

        return false;
    }

    /*
     * 设置推荐人
     */
    public function setInviteId(Request $request)
    {
        if ($request->isPost()) {
            $id = I('post.id/d');

            if ($id < 1) {
                return json(['status' => 0, 'msg' => '缺少传参', 'result' => null]);
            }

            if ($this->_hasRelationship($id)) {
                return json(['status' => 0, 'msg' => '不能绑定和自己有关系的普通会员', 'result' => null]);
            }

            if ($id == $this->user_id) {
                return json(['status' => 0, 'msg' => '不能设置成自己', 'result' => null]);
            }

            if ($this->user['invite_uid'] > 0) {
                return json(['status' => 0, 'msg' => '你已经有推荐人了，不能重复设置', 'result' => null]);
            }

            $data = [];

            $userInfo = M('Users')->find($id);
            $data['invite_uid'] = $data['first_leader'] = $id;
            $data['second_leader'] = $userInfo['first_leader'];
            $data['third_leader'] = $userInfo['second_leader'];

            M('users')->where('first_leader', $this->user_id)->update(['second_leader' => $data['first_leader'], 'third_leader' => $data['second_leader']]);
            M('users')->where('second_leader', $this->user_id)->update(['third_leader' => $data['first_leader']]);

            M('users')->where(['user_id' => $this->user_id])->save($data);

            // 邀请送积分
            $invite_integral = tpCache('basic.invite_integral');
            accountLog($id, 0, $invite_integral, '邀请用户奖励积分', 0, 0, '', 0, 7);

            // 邀请任务
            // $user = M('users')->find($this->user_id);
            // $TaskLogic = new \app\common\logic\TaskLogic(2);
            // $TaskLogic->setUser($user);
            // $TaskLogic->setDistributId($user);
            // $TaskLogic->doInviteAfter();

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

        return json(['status' => 1, 'msg' => '添加地址成功', 'result' => null]);
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

        return json(['status' => 1, 'msg' => '修改地址成功', 'result' => null]);
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
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
        $id = I('get.id/d');

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
        $user_message_count = $messageLogic->getUserMessageCount($this->userToken);
        $data['user_message_count'] = $user_message_count;

        //获取用户活动信息的数量

        $articleLogic = new ArticleLogic();
        $user_article_count = $articleLogic->getUserArticleCount($this->userToken);
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

            if ($post['id_cart'] && !checkIdCard($post['id_cart'])) {
                return json(['status' => 0, 'msg' => '请填写正确的身份证格式']);
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
            $code = I('post.code');
            $scene = I('post.scene', 6);
            $session_id = I('unique_id', $this->userToken);
            $step = I('step', 1);
            $logic = new UsersLogic();
            if (1 == $step) {
                $res = $logic->check_validate_code($code, $old_mobile, 'phone', $session_id, $scene);
                if (!$res && 1 != $res['status']) {
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
                $check = TokenLogic::getCache('validate_code', $this->user['mobile']);
                if (empty($check)) {
                    return json(['status' => 0, 'msg' => '验证码还未验证通过', 'result' => null]);
                }
                if ($confirm_mobile != $mobile) {
                    return json(['status' => 0, 'msg' => '两次输入的手机号码不一样', 'result' => null]);
                }
                //验证有效期
                if (!$logic->update_email_mobile($mobile, $this->user_id, 2)) {
                    return json(['status' => 0, 'msg' => '手机已存在', 'result' => null]);
                }

                return json(['status' => 1, 'msg' => '修改成功', 'result' => null]);
            }

            //验证手机和验证码
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
        $data = $userLogic->get_goods_collect($this->user_id);
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
            return json(['status' => 0, 'msg' => '缺少IDS参数', 'result' => null]);
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

    /*
     * ajax更新一个收藏商品
     */
    public function ajax_goods_collect()
    {
        $goods_id = I('get.goods_id', 0);
        if (!$goods_id) {
            return json(['status' => 0, 'msg' => '缺少goods_id参数', 'result' => null]);
        }
        $status = I('get.status', 0);
        $user_id = $this->user_id;
        if (0 == $status) {
            M('GoodsCollect')->where(['user_id' => $user_id, 'goods_id' => $goods_id])->delete();
        } else {
            $exists = M('GoodsCollect')->where(['user_id' => $user_id, 'goods_id' => $goods_id])->find();
            if (!$exists) {
                $data = [];
                $data['user_id'] = $user_id;
                $data['goods_id'] = $goods_id;
                $data['add_time'] = time();
                M('GoodsCollect')->add($data);
            }
        }

        return json(['status' => 1, 'msg' => '更改收藏商品成功', 'result' => null]);
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
        $code = I('code', '');
        $session_id = I('unique_id', $this->userToken);

        $userLogic = new UsersLogic();
        // 验证验证码
        $res = $userLogic->check_validate_code($code, $this->user['mobile'], 'phone', $session_id, 6);
        if (!$res && 1 != $res['status']) {
            return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
        }
        // 重置密码
        $data = $userLogic->resetPassword($this->user_id, I('post.password'), null, true);
        if (-1 == $data['status']) {
            return json(['status' => 0, 'msg' => $data['msg'], 'result' => $data['result']]);
        }
        return json(['status' => 1, 'msg' => '恭喜！已修改完成', 'result' => null]);
    }

    public function changeType()
    {
        $type = I('post.type', 1);
        $data['type'] = $type;
        $will_invite_uid = M('Users')->where('user_id', $this->user['user_id'])->getField('will_invite_uid');

        $will_invite_uid = $will_invite_uid ?: 0;

        if ($will_invite_uid == $this->user['user_id']) {
            $will_invite_uid = 0;
        }

        $data['invite_uid'] = $will_invite_uid;
        $data['will_invite_uid'] = 0;

        $user_type = M('Users')->where('user_id', $this->user['user_id'])->getField('type');

        if (0 != $user_type) {
            return json(['status' => -1, 'msg' => '该用户已经选择类型，无法继续选定', 'result' => null]);
        }

        if (1 == $type) {
            $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
            if ($pay_points > 0) {
                accountLog($this->user['user_id'], 0, $pay_points, '会员注册赠送积分', 0, 0, '', 0, 6); // 记录日志流水
            }

            $CouponLogic = new \app\common\logic\CouponLogic();
            $CouponLogic->sendNewUser($this->user['user_id']);

            if ($will_invite_uid > 0) {
                $first_leader = Db::name('users')->where("user_id = {$will_invite_uid}")->find();

                $data['first_leader'] = $first_leader['user_id'];
                $data['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
                $data['third_leader'] = $first_leader['second_leader']; // 第二级推荐人

                //他上线分销的下线人数要加1
                Db::name('users')->where(['user_id' => $data['first_leader']])->setInc('underling_number');
                Db::name('users')->where(['user_id' => $data['second_leader']])->setInc('underling_number');
                Db::name('users')->where(['user_id' => $data['third_leader']])->setInc('underling_number');

                // 邀请送积分
                $invite_integral = tpCache('basic.invite_integral');
                accountLog($will_invite_uid, 0, $invite_integral, '邀请用户奖励积分', 0, 0, '', 0, 7);

                M('Users')->where('user_id', $this->user_id)->save($data);

                // 邀请任务
                $user = M('users')->find($this->user_id);
                $TaskLogic = new \app\common\logic\TaskLogic(2);
                $TaskLogic->setUser($user);
                $TaskLogic->setDistributId($user['distribut_level']);
                $TaskLogic->doInviteAfter();
            }
            M('Users')->where('user_id', $this->user_id)->save($data);
        }
        $return = [
            'user_id' => $this->user_id,
            'integral' => $invite_integral,
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

    public function del_visit_log()
    {
        $id = I('id', '');
        $goodsLogic = new Goodslogic();
        $id = explode(',', $id);
        if ($id) {
            foreach ($id as $v) {
                $goodsLogic->del_visit_log($v);
            }
        }

        return json(['status' => 1, 'msg' => '删除用户足迹成功', 'result' => null]);
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
            ->field('a.visit_id, a.visittime, g.goods_name, g.shop_price, g.exchange_integral, g.original_img, g.goods_remark')
            ->where($map)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('a.visittime desc')
            ->select();
        // 处理数据
        $return = [];
        $visitLog = [];
        foreach ($visitList as $item) {
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
            $visitLog[$key]['data'][] = $item;
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

    // 用户余额转电子币
    public function exchangeElectronic()
    {
        $user = session('user');
        $electronic = I('electronic', 0);
        if ($electronic < 1) {
            return json(['status' => 0, 'msg' => '传成电子币的数额不能小于1', 'result' => null]);
        }
        $userMoneyLog = [];
        $electronicLog = [];

        $user_money = M('Users')->where('user_id', $user['user_id'])->getField('user_money');

        if ($user_money < $electronic) {
            return json(['status' => 0, 'msg' => '你的余额不够' . $electronic, 'result' => null]);
        }
        accountLog($user['user_id'], 0, 0, '用户余额转电子币', 0, 0, '', $electronic, 13);
        accountLog($user['user_id'], -$electronic, 0, '电子币充值（余额转）', 0, 0, '', 0, 13);

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
            return json(['status' => 0, 'msg' => '缺少转账用户参数', 'result' => null]);
        }
        $is_exists = M('Users')->find($to_user_id);
        if (!$is_exists) {
            return json(['status' => 0, 'msg' => '转账用户不存在，请重新确认', 'result' => null]);
        }
        $user_electronic = M('Users')->where('user_id', $user['user_id'])->getField('user_electronic');

        if ($user_electronic < $electronic) {
            return json(['status' => 0, 'msg' => '你的电子币不够' . $electronic, 'result' => null]);
        }
        accountLog($user['user_id'], 0, 0, "转出电子币给用户$to_user_id", 0, 0, '', -$electronic, 13);

        accountLog($to_user_id, 0, 0, "来自用户{$user['user_id']}的赠送", 0, 0, '', $electronic, 13);

        return json(['status' => 1, 'msg' => '电子币转帐成功', 'result' => null]);
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
            return json(['status' => 0, 'msg' => '缺少转账用户参数', 'result' => null]);
        }
        $is_exists = M('Users')->find($to_user_id);
        if (!$is_exists) {
            return json(['status' => 0, 'msg' => '转账用户不存在，请重新确认', 'result' => null]);
        }
        $user_pay_points = M('Users')->where('user_id', $user['user_id'])->getField('pay_points');

        if ($user_pay_points < $pay_points) {
            return json(['status' => 0, 'msg' => '你的积分不够' . $pay_points, 'result' => null]);
        }

        accountLog($user['user_id'], 0, -$pay_points, "转出积分给用户$to_user_id", 0, 0, '', 0, 12);

        accountLog($to_user_id, 0, $pay_points, "转入积分From用户{$user['user_id']}", 0, 0, '', 0, 12);

        return json(['status' => 1, 'msg' => '积分转帐成功', 'result' => null]);
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
     * 绑定旧账户.
     * 微信登入的新用户绑定老用户，会把新用户的微信绑定切换到老用户上面，冻结新用户。
     * @return mixed
     */
    public function bindOldUser()
    {
        $session_id = $this->userToken;
        $username = I('post.username', '');
        $password = I('post.password', '');
        $mobile = I('post.mobile', '');
        $code = I('post.code', '');
        $scene = I('post.scene', 6);
        $type = I('post.type', 1);

//        $current_user = M('Users')->find($this->user_id);
        $current_user = $this->user;
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
        // 账号密码绑定方式
        if (1 == $type) {
            if (!$username) {
                return json(['status' => -1, 'msg' => '用户名不能为空', 'result' => null]);
            }
            if (!$password) {
                return json(['status' => -1, 'msg' => '密码不能为空', 'result' => null]);
            }

            //验证成功
            $user = M('Users')
                ->where('user_name', $username)//
                ->where('password', encrypt($password))
                // ->where('is_zhixiao',1)
                // ->where('is_lock',0)
                ->find();
            if (!$user) {
                return json(['status' => -1, 'msg' => '账号密码错误']);
            }
        } else {
            if (!$mobile) {
                return json(['status' => -1, 'msg' => '手机号码不能为空', 'result' => null]);
            }

            if (!$code) {
                return json(['status' => -1, 'msg' => '手机验证码不能为空', 'result' => null]);
            }

            //验证手机号码
            $is_exists = M('Users')->where('mobile', $mobile)->where('user_name', 'neq', '')->find();
            if (!$is_exists) {
                return json(['status' => -1, 'msg' => '手机号码不存在,请输入老用户手机进行绑定', 'result' => null]);
            }

            //验证手机和验证码
            $logic = new UsersLogic();
            $res = $logic->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
            if (1 != $res['status']) {
                return json(['status' => -1, 'msg' => $res['msg']]);
            }

            //验证成功
            $user = M('Users')
                ->where('user_name', $username)
                ->where('mobile', $mobile)
                // ->where('is_zhixiao',1)
                // ->where('is_lock',0)
                ->find();

            if (!$user) {
                return json(['status' => -1, 'msg' => '账号不存在，不能绑定']);
            }
        }

        if ($user['bind_uid'] > 0) {
            return json(['status' => -1, 'msg' => '该旧账号已经绑定！']);
        }

        if ($current_user['user_id'] == $user['user_id']) {
            return json(['status' => -1, 'msg' => '不能绑定自己']);
        }

        DB::startTrans();
        $user_data = [];
        $user_data['oauth'] = $current_user['oauth'];
        $user_data['openid'] = $current_user['openid'];
        $user_data['unionid'] = $current_user['unionid'];
        $user_data['head_pic'] = $current_user['head_pic'];
        $user_data['nickname'] = $current_user['nickname'];
        $user_data['bind_uid'] = $current_user['user_id'];
        // $user_data['mobile'] = $this->user['mobile'];
        $user_data['type'] = 2;
        $user_data['bind_time'] = time();

        //积分变动
        // if($user['distribut_level'] > 2){ // 网点会员
        //      $add_point = $del_point = 0;
        //      $point_data = M('AccountLog')
        //          ->where('user_id',$this->user_id)
        //          ->where('pay_points','neq',0)
        //          ->select();
        //      if($point_data){
        //          foreach ($point_data as $pk => $pv) {
        //              if($pv['pay_points'] > 0){
        //                  $log_data = array();
        //                  $log_data['user_id'] = $user['user_id'];
        //                  $log_data['pay_points'] = -$pv['pay_points'];
        //                  $log_data['desc'] = '扣除新会员赠送积分';
        //                  $log_data['change_time'] = time();
        //                  M('AccountLog')->add($log_data);
        //              }else{
        //                  $del_point += $pv['pay_points'];
        //              }
        //          }
        //      }

        //      if($del_point < 0){
        //          $p = M('Users')->where('user_id',$user['user_id'])->getField('pay_points');

        //          if(($p + $del_point) <= 0 ){

        //              M('Users')->where('user_id',$user['user_id'])
        //              ->update(array('pay_points'=>0));
        //          }else{
        //              M('Users')->where('user_id',$user['user_id'])
        //              ->update(array('pay_points'=>['exp',"pay_points+{$del_point}"]));
        //          }

        //      }
        // }else{
        //      $del_point = 0;
        //      $add_point = M('AccountLog')
        //      ->where('user_id',$this->user_id)
        //      ->where('pay_points','neq',0)
        //      ->getField('SUM(pay_points) as add_point');
        //      if($add_point > 0){
        //           M('Users')->where('user_id',$user['user_id'])
        //           ->update(array('pay_points'=>['exp',"pay_points + {$add_point}"]));
        //      }
        // }

        // 迁移数据
        // M('Order')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('AccountLog')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('Cart')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('UserAddress')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('DeliveryDoc')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('GoodsCollect')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('GoodsVisit')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('OrderAction')->where('action_user',$this->user_id)->update(array('action_user'=>$user['user_id']));
        // M('RebateLog')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('RebateLog')->where('buy_user_id',$this->user_id)->update(array('buy_user_id'=>$user['user_id']));
        // M('Recharge')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('ReturnGoods')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('UserSign')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('UserStore')->where('user_id',$this->user_id)->update(array('user_id'=>$user['user_id']));
        // M('OrderAction')->where('action_user',$this->user_id)->update(array('action_user'=>$user['user_id']));
        // M('Users')->where('first_leader',$this->user_id)->update(array('first_leader'=>$user['user_id']));
        // M('Users')->where('second_leader',$this->user_id)->update(array('second_leader'=>$user['user_id']));
        // M('Users')->where('third_leader',$this->user_id)->update(array('third_leader'=>$user['user_id']));
        // M('Users')->where('invite_uid',$this->user_id)->update(array('invite_uid'=>$user['user_id']));
        M('OauthUsers')->where('user_id', $current_user['user_id'])->update(['user_id' => $user['user_id']]);
        // M('couponList')->where('uid',$this->user_id)->update(array('uid'=>$user['user_id']));

        M('Users')->where('user_id', $user['user_id'])->update($user_data);

        // 冻结新账户
        M('Users')->where('user_id', $current_user['user_id'])->update(['is_lock' => 1]);

        M('bind_log')->add([
            'user_id' => $current_user['user_id'],
            'bind_user_id' => $user['user_id'],
            'add_time' => time(),
            'type' => 1,
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

        $user = M('Users')->where('user_id', $user['user_id'])->find();
        session('user', $user);
        $this->redis->set('user_' . $this->userToken, $user, config('redis_time'));
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

        return json(['status' => 1, 'msg' => '绑定成功']);
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

        $old_password = encrypt($old_password);
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
            if (!$res && 1 != $res['status']) {
                return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
            }

            return json(['status' => 1, 'msg' => '验证成功', 'result' => null]);
        } elseif ($step > 1) {
            $check = TokenLogic::getCache('validate_code', $this->user['mobile']);
            if (empty($check)) {
                return json(['status' => 0, 'msg' => '验证码还未验证通过', 'result' => null]);
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
        if (!$res && 1 != $res['status']) {
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
            if (!$data['bank_card']) {
                return json(['status' => 0, 'msg' => '请填写银行账号']);
            }
            if (!$data['real_name']) {
                return json(['status' => 0, 'msg' => '请填写开户名']);
            }
            if (!$data['id_cart']) {
                return json(['status' => 0, 'msg' => '请填写身份证']);
            }

            if (!checkIdCard($data['id_cart'])) {
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
            // if(encrypt($data['paypwd']) != $this->user['paypwd']){
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
        $user_logic = new UsersLogic();
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
        $return['task_cate'] = C('TASK_CATE');

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function getReward()
    {
        $reward_id = I('reward_id', 0);
        $reward = M('task_log')
            ->where('user_id', $this->user_id)
            ->where('task_reward_id', $reward_id)
            ->where('type', 1)
            ->where('status', 0)
            ->find();

        if ($reward) {
            if ($reward['reward_integral'] > 0) {
                accountLog($this->user_id, 0, $reward['reward_integral'], '用户领取任务奖励', 0, 0, $reward['order_sn'], 0, 16);
            }
            if ($reward['reward_electronic'] > 0) {
                accountLog($this->user_id, 0, 0, '用户领取任务奖励', 0, 0, $reward['order_sn'], $reward['reward_electronic'], 15);
            }
            if ($reward['reward_coupon_id'] > 0) {
                $activityLogic = new \app\common\logic\ActivityLogic();
                $result = $activityLogic->get_coupon($reward['reward_coupon_id'], $this->user_id);
            }

            $return['reward_coupon_money'] = '0.00';
            $return['reward_integral'] = $reward['reward_integral'];
            $return['reward_electronic'] = $reward['reward_electronic'];

            if ($reward['reward_coupon_id']) {
                $coupon_info = M('coupon')->where(['id' => $reward['reward_coupon_id']])->find();
                if ($coupon_info['use_type'] == 4 || $coupon_info['use_type'] == 5) {
                    $return['coupon_name_is_show'] = 1;
                    $return['coupon_name'] = $coupon_info['name'];
                } else {
                    $return['coupon_name_is_show'] = 0;
                }
            } else {
                $return['coupon_name_is_show'] = 0;
            }


            if (isset($result) && 1 == $result['status']) {
                M('task_log')->where('id', $reward['id'])->update(['status' => 1, 'finished_at' => time()]);
                $return['reward_coupon_money'] = $result['coupon']['money'];

                return json(['status' => 1, 'msg' => $result['msg'], 'result' => $return]);
            } elseif (isset($result) && 1 != $result['status']) {
                return json(['status' => 0, 'msg' => $result['msg'], 'result' => $return]);
            }
            M('task_log')->where('id', $reward['id'])->update(['status' => 1, 'finished_at' => time()]);


            return json(['status' => 1, 'msg' => '用户领取奖励成功', 'result' => $return]);
        }

        return json(['status' => 0, 'msg' => '用户还没有领取该奖励资格', 'result' => $return]);
    }

    public function getTask()
    {
        $task_cate = C('TASK_CATE');

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
            $userLogic = new UsersLogic();
            if (!$userLogic->update_info($this->user_id, $post)) {
                return json(['status' => 0, 'msg' => '操作失败', 'result' => null]);
            }
            setcookie('uname', urlencode($this->user['nickname']), null, '/');
            // 完善资料获得积分
            $is_consummate = $userLogic->is_consummate($this->user_id, $this->user);
            if ($is_consummate !== false) {
                return json(['status' => 1, 'msg' => '操作成功', 'result' => ['point' => $is_consummate]]);
            } else {
                return json(['status' => 1, 'msg' => '操作成功', 'result' => null]);
            }
        }
        $data = [];
        // 用户信息
        $data['user'] = [
            'user_id' => $this->user['user_id'],
            'sex' => $this->user['sex'],
            'real_name' => $this->user['real_name'],
            'id_cart' => $this->user['id_cart'],
            'birthday' => $this->user['birthday'],
            'mobile' => $this->user['mobile'],
            'head_pic' => $this->user['head_pic'],
            'type' => $this->user['type'],
            'will_invite_uid' => $this->user['will_invite_uid'],
            'is_not_show_jk' => $this->user['is_not_show_jk'],  // 是否提示加入金卡弹窗
            'has_pay_pwd' => $this->user['paypwd'] ? 1 : 0,
            'is_app' => TokenLogic::getValue('is_app', $this->userToken) ? 1 : 0
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
                'distribut_number' => $this->user['distribut_level'],
                'distribut_level' => M('DistributLevel')->where('level_id', $this->user['distribut_level'])->getField('level_name'),
                'distribut_money' => $this->user['distribut_money'],
                'will_distribut_money' => isset($will_distribut_money['money']) ? $will_distribut_money['money'] : '0.00'
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
        $user_message_count = $messageLogic->getUserMessageCount($this->userToken);
        $data['user_message_count'] = $user_message_count;
        // 获取用户活动信息的数量
        $articleLogic = new ArticleLogic();
        $user_article_count = $articleLogic->getUserArticleCount($this->userToken);
        $data['user_article_count'] = $user_article_count;

        $data['sex'] = C('SEX');

        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }
}