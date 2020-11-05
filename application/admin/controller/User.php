<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

use app\admin\logic\UsersLogic;
use app\common\logic\Token as TokenLogic;
use app\common\logic\UsersLogic as CommonUsersLogic;
use app\common\model\UserLoginLog as UserLoginLogModel;
use app\common\model\Users as UsersModel;
use think\AjaxPage;
use think\Db;
use think\Loader;
use think\Page;
use think\Request;

class User extends Base
{
    function jinka()
    {
        $where = array('n.distribut_level' => array('in', array(2, 3)), 'n.first_leader' => array('gt', 0));
        $user_id = I('user_id');
        if ($user_id) {
            $where['n.first_leader'] = $user_id;
        }

        $distribut_level = I('distribut_level');
        if ($distribut_level) {
            $where['u.distribut_level'] = $distribut_level;
        }

        $apply_check_num = tpCache('basic.apply_check_num');

        $count = M('users')
            ->alias('n')
            ->field('n.first_leader,count(*) as count')
            ->join('users u', 'u.user_id = n.first_leader')
            ->where($where)
            ->group('n.first_leader')
            ->having("count(*) >= " . $apply_check_num)
            ->count();


        $page_num = I('page_num', 20, 'int');
        $Page = new Page($count, $page_num);

        $list = M('users')
            ->field('n.first_leader,count(*) as count')
            ->alias('n')
            ->join('users u', 'u.user_id = n.first_leader')
            ->where($where)
            ->group('n.first_leader')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->having("count(*) >= " . $apply_check_num)
            ->select();

        if ($list) {
            $user_ids = get_arr_column($list, 'first_leader');
            $users = M('users')->where(array('user_id' => array('in', $user_ids)))->getField('user_id,nickname,user_name,distribut_level', true);

            $apply_customs = M('apply_customs')->where(array('user_id' => array('in', $user_ids)))->getField('user_id,status,add_time', true);

            foreach ($list as $k => $v) {
                $list[$k]['user_name'] = $users[$v['first_leader']]['user_name'];
                $list[$k]['nickname'] = $users[$v['first_leader']]['nickname'];
                if ($users[$v['first_leader']]['distribut_level'] == 3) {
                    $list[$k]['status_show'] = '金卡';
                } else if (isset($apply_customs[$v['user_id']]['status'])) {
                    $list[$k]['status_show'] = '申请中';
                } else {
                    $list[$k]['status_show'] = 'VIP会员';
                }
            }
        }

        if (I('is_export')) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;"> 商城用户id</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="120">用户名</td>';

            $strTable .= '<td style="text-align:center;font-size:12px;" width="120">昵称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">数量</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">资格</td>';

            $strTable .= '</tr>';
            if (is_array($list)) {
                foreach ($list as $k => $val) {
                    $orderGoodsNum = 1;
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;" rowspan="' . $orderGoodsNum . '">&nbsp;' . $val['first_leader'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['user_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['nickname'] . ' </td>';

                    $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['count'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;"   rowspan="' . $orderGoodsNum . '">' . $val['status_show'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($orderList);
            downloadExcel($strTable, 'jinka');
            exit();
        }


        $this->assign('distribut_level', $distribut_level);
        $this->assign('page_num', $page_num);
        $this->assign('count', $count);
        $this->assign('user_id', $user_id);
        $this->assign('show', $Page->show());
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        return $this->fetch();
    }


    function apply_customs()
    {
        $where = array();
        $status = I('status', -1, 'int');
        if ($status != -1) {
            $where['status'] = $status;
        }

        $user_id = I('user_id');
        if ($user_id) {
            $where['user_id'] = $user_id;
        }

        //时间
        $field2 = I('field2', '');
        $from_time = I('from_time', '');
        $to_time = I('to_time', '');

        if ($from_time || $to_time) {
            $where[$field2] = whereTime($from_time, $to_time, 86399);
        }
        $this->assign('field2', $field2);
        //姓名
        $true_name = I('true_name', '');
        if ($true_name) {
            $where['true_name'] = $true_name;
        }

        $mobile = I('mobile', '');
        if ($mobile) {
            $where['mobile'] = $mobile;
        }


        //账号
        $id_card = I('id_card', '');
        if ($id_card) {
            $where['id_card'] = $id_card;
        }

        //会员号
        $referee_user_name = I('referee_user_name', '');
        if ($referee_user_name) {
            $user = get_user_info($referee_user_name, 5, '', 'user_id');
            $where['referee_user_id'] = $user ? $user['user_id'] : 0;
        }

        $count = Db::name('apply_customs')->where($where)->count();

        $page_num = I('page_num', 20, 'int');
        $Page = new Page($count, $page_num);
        $list = Db::name('apply_customs')->where($where)->order("id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $status_arr = array(
            0 => '待审核',
            1 => '已完成',
            2 => '已撤销'
        );
        foreach ($list as $k => $v) {
            $list[$k]['status_show'] = $status_arr[$v['status']];
            $list[$k]['add_time_show'] = $v['add_time'] ? date('Y-m-d H:i:s', $v['add_time']) : '';
            $list[$k]['success_time_show'] = $v['success_time'] ? date('Y-m-d H:i:s', $v['success_time']) : '';
            $list[$k]['cancel_time_show'] = $v['cancel_time'] ? date('Y-m-d H:i:s', $v['cancel_time']) : '';
        }

        $this->assign('page_num', $page_num);

        $this->assign('true_name', $true_name);
        $this->assign('user_id', $user_id);
        $this->assign('id_card', $id_card);
        $this->assign('referee_user_name', $referee_user_name);
        $this->assign('to_time', $to_time);
        $this->assign('from_time', $from_time);
        $this->assign('show', $Page->show());
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        $this->assign('status', $status);
        $this->assign('mobile', $mobile);
        $this->assign('status_arr', $status_arr);
        return $this->fetch();
    }

    public function index()
    {
        $this->assign('level', M('distribut_level')->getField('level_id,level_name'));

        return $this->fetch();
    }

    public function relation()
    {
        $user_id = I('user_id');
        if (IS_POST) {
            if (!$user_id) {
                exit($this->error('参数非法!缺少传递user_id参数。'));
            }

            $list = $this->getRelation($user_id);
            $list = array_reverse($list);
            $user_id_arr = get_arr_column($list, 'user_id');
            if (!empty($user_id_arr)) {
                $first_leader = DB::query('select first_leader,count(1) as count  from __PREFIX__users where first_leader in(' . implode(',', $user_id_arr) . ')  group by first_leader');
                $first_leader = convert_arr_key($first_leader, 'first_leader');

                $second_leader = DB::query('select second_leader,count(1) as count  from __PREFIX__users where second_leader in(' . implode(',', $user_id_arr) . ')  group by second_leader');
                $second_leader = convert_arr_key($second_leader, 'second_leader');

                $third_leader = DB::query('select third_leader,count(1) as count  from __PREFIX__users where third_leader in(' . implode(',', $user_id_arr) . ')  group by third_leader');
                $third_leader = convert_arr_key($third_leader, 'third_leader');
            }
            $this->assign('first_leader', $first_leader);
            $this->assign('second_leader', $second_leader);
            $this->assign('third_leader', $third_leader);
            $this->assign('level', M('distribut_level')->getField('level_id,level_name'));
            $this->assign('list', $list);
        }

        $this->assign('user_id', $user_id);

        return $this->fetch();
    }

    public function getRelation($user_id)
    {
        $list = [];
        $user = M('Users')->where('user_id', $user_id)->find();
        if ($user) {
            $list[] = $user;
            if ($user['first_leader'] > 0) {
                $list = array_merge($list, $this->getRelation($user['first_leader']));
            }
        }

        return $list;
    }

    /**
     * 会员列表.
     */
    public function ajaxindex()
    {
        $condition = [];
        //级别
        $distribut_level = I('distribut_level', 0, 'int');
        if ($distribut_level) {
            $condition['distribut_level'] = $distribut_level;
        }
        //关系
        $field1 = I('field1', '');
        $value1 = I('value1', '');
        if ($value1) {
            $value1Info = get_user_info($value1, 0, '', 'user_id');
            $condition[$field1] = $value1Info ? $value1Info['user_id'] : -1;
        }
        //时间
        $field2 = I('field2', '');
        $from_time = I('from_time', '');
        $to_time = I('to_time', '');
        if ($from_time || $to_time) {
            $condition[$field2] = whereTime($from_time, $to_time);
        }
        //个人信息
        $field3 = I('field3', '');
        $value3 = I('value3', '');
        if ($value3) {
            $ausers = M('users')->where([$field3 => $value3])->field('user_id')->select();
            $condition['user_id'] = $ausers ? ['in', get_arr_column($ausers, 'user_id')] : -1;
        }
        //用户状态
        $userStatus = I('user_status', 0);
        switch ($userStatus) {
            case 1:
                $condition['is_lock'] = 1;
                break;
            case 2:
                $condition['is_cancel'] = 1;
                break;
        }
        //排序
        if (I('order_by') == 'user_status') {
            $sort_order = 'is_lock ' . I('sort') . ', is_cancel ' . I('sort');
        } else {
            $sort_order = I('order_by') . ' ' . I('sort');
        }

//        $model = M('users');
        $model = new UsersModel();
        $count = $model->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        /*if($condition){
            foreach($condition as $key=>$val) {
                $Page->parameter[$key]   =   urlencode($val);
            }
        }*/

        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($userList as $key => $user) {
            $appFirstLogin = M('user_login_log')->where(['user_id' => $user['user_id'], 'is_app_first' => 1])->value('login_time');
            $userList[$key]['first_login'] = $appFirstLogin ?? '';
        }

        $user_id_arr = get_arr_column($userList, 'user_id');
        if (!empty($user_id_arr)) {
            $first_leader = DB::query('select first_leader,count(1) as count  from __PREFIX__users where first_leader in(' . implode(',', $user_id_arr) . ')  group by first_leader');
            $first_leader = convert_arr_key($first_leader, 'first_leader');

            $second_leader = DB::query('select second_leader,count(1) as count  from __PREFIX__users where second_leader in(' . implode(',', $user_id_arr) . ')  group by second_leader');
            $second_leader = convert_arr_key($second_leader, 'second_leader');

            $third_leader = DB::query('select third_leader,count(1) as count  from __PREFIX__users where third_leader in(' . implode(',', $user_id_arr) . ')  group by third_leader');
            $third_leader = convert_arr_key($third_leader, 'third_leader');
        }
        $this->assign('first_leader', $first_leader);
        $this->assign('second_leader', $second_leader);
        $this->assign('third_leader', $third_leader);
        $show = $Page->show();
        $this->assign('userList', $userList);
        $this->assign('level', M('distribut_level')->getField('level_id,level_name'));
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    private function _hasRelationship($id, $uid)
    {
        $invite_uid = M('Users')->where('user_id', $id)->getField('first_leader');

        if ($invite_uid > 0) {
            if ($invite_uid == $uid) {
                return true;
            }

            return $this->_hasRelationship($invite_uid, $uid);
        }

        return false;
    }

    /**
     * 会员合并.
     */
    public function merge()
    {
        if (IS_POST) {
            $uid = I('post.id');
            $user = D('users')->where(['user_id' => $uid])->find();
            if (!$user) {
                exit($this->error('会员不存在'));
            }
            if ($user['distribut_level'] >= 3) {
                exit($this->error('直销商账号不能合并其他账号'));
            }
            if ($user['bind_uid'] > 0) {
                exit($this->error('要合并的用户ID此前已经合并过报单系统用户，不能多次合并'));
            }
            $apply_customs = M('apply_customs')->where(['user_id' => $uid, 'status' => 0])->find();
            if ($apply_customs) {
                exit($this->error('该会员在申请金卡，不能进行合并操作'));
            }
            $merge_uid = I('post.merge_uid');
            $user_name = I('post.user_name');
            // 合并报单系统用户
            if (!empty($merge_uid)) {
                $merge_uid = trim($merge_uid);
                $user_name = trim($user_name);
                if ($uid == $merge_uid) {
                    exit($this->error('合并报单系统用户ID不能与当前用户ID一致'));
                }
                if ($user_name) {
                    $c = M('users')->where("user_id = $merge_uid and user_name = '$user_name' and is_lock = 0")->count();
                    !$c && exit($this->error('合并报单系统用户不存在,或者被冻结，不能合并！'));

                    $c = M('users')->where("user_id = $merge_uid and user_name = '$user_name' and is_lock = 0")->find();
                } else {
                    $c = M('users')->where("user_id = $merge_uid and is_lock = 0")->count();
                    !$c && exit($this->error('合并报单系统用户不存在,或者被冻结，不能合并！'));

                    $c = M('users')->where("user_id = $merge_uid and is_lock = 0")->find();
                }

                // 开始合并用户信息
                // 1.粉丝数据
                if ($this->_hasRelationship($c['first_leader'], $uid)) {
                    $this->error('合并的报单系统用户和要合并的用户id存在普通会员关系，不能合并！');
                }
                if ($c['distribut_level'] < 3) {
                    $this->error('合并的报单系统用户低于店长级别，不能合并！');
                }
                if ($c['distribut_level'] > 2 && $c['bind_uid'] > 0) {
                    $this->error('合并的报单系统用户已经新用户被绑定过老用户，不能合并！');
                }
                if ($c['user_name']) {
                    $cusers = M('users')->where(array("user_name" => $c['user_name']))->count();
                    if ($cusers >= 2) {
                        $this->error($c['user_name'] . '已合并多次，禁止多次合并');
                    }
                }
                // 更新用户信息
                $user_data = [];
                $user_data['distribut_level'] = $c['distribut_level'] > $user['distribut_level'] ? $c['distribut_level'] : $user['distribut_level'];
                if ($user_data['distribut_level'] > 1) {
                    $user_data['is_distribut'] = 1;
                }
                if ($user['distribut_level'] != 3) {
                    // 合并账号不是SVIP才变更父级
                    $user_data['invite_uid'] = $c['will_invite_uid'] != 0 ? $c['will_invite_uid'] : $c['invite_uid'];
                    $user_data['invite_time'] = $c['will_invite_uid'] != 0 ? time() : $c['invite_time'];
                    $user_data['first_leader'] = $c['first_leader'];
                    $user_data['second_leader'] = $c['second_leader'];
                    $user_data['third_leader'] = $c['third_leader'];
                }
                $user_data['user_name'] = $c['user_name'];
                $user_data['type'] = 2;
                $user_data['bind_uid'] = $merge_uid;
                $user_data['bind_time'] = time();
                $user_data['time_out'] = strtotime('+' . config('REDIS_DAY') . ' days');
                M('Users')->where('user_id', $uid)->update($user_data);
                // 授权登录
                M('OauthUsers')->where('user_id', $merge_uid)->delete();
                // 下级推荐人
                if ($user['invite_uid'] != $c['invite_uid']) {
                    M('users')->where('first_leader', $uid)->update(['second_leader' => $user_data['first_leader'], 'third_leader' => $user_data['second_leader']]);
                    M('users')->where('second_leader', $uid)->update(['third_leader' => $user_data['first_leader']]);
                }
                M('users')->where('first_leader', $merge_uid)->update(['first_leader' => $uid, 'invite_uid' => $uid]);
                M('users')->where('second_leader', $merge_uid)->update(['second_leader' => $uid]);
                M('users')->where('third_leader', $merge_uid)->update(['third_leader' => $uid]);
                // 积分变动
                $payPoints = M('AccountLog')
                    ->where('user_id', $merge_uid)
                    ->where('pay_points', 'gt', 0)
                    ->where('type', 'neq', 6)// 不要注册积分
                    ->sum('pay_points');
                if ($payPoints > 0) {
                    accountLog($uid, 0, $payPoints, '账号合并积分', 0, 0, '', 0, 11, false);
                }
                // 电子币变动
                $electronic = M('AccountLog')
                    ->where('user_id', $merge_uid)
                    ->where('user_electronic', 'gt', 0)
                    ->sum('user_electronic');
                if ($payPoints > 0) {
                    accountLog($uid, 0, 0, '账号合并电子币', 0, 0, '', $electronic, 11, false);
                }
                // 余额变动
                $userMoney = M('AccountLog')
                    ->where('user_id', $merge_uid)
                    ->where('user_money', 'gt', 0)
                    ->sum('user_money');
                if ($payPoints > 0) {
                    accountLog($uid, $userMoney, 0, '账号合并余额', 0, 0, '', 0, 11, false);
                }
                // 订单
                M('Order')->where('user_id', $merge_uid)->update(array('user_id' => $uid));
                M('OrderAction')->where('action_user', $merge_uid)->update(array('action_user' => $uid));
                // 快递
                M('DeliveryDoc')->where('user_id', $merge_uid)->update(array('user_id' => $uid));
                // 退换货
                M('ReturnGoods')->where('user_id', $merge_uid)->update(array('user_id' => $uid));
                // 提成记录
                M('RebateLog')->where('user_id', $merge_uid)->update(array('user_id' => $uid));
                M('RebateLog')->where('buy_user_id', $merge_uid)->update(array('buy_user_id' => $uid));
                // 冻结报单系统用户
                M('users')->where(['user_id' => $merge_uid])->save(['is_lock' => 1]);
                // 绑定记录
                M('bind_log')->add([
                    'user_id' => $merge_uid,
                    'bind_user_id' => $uid,
                    'add_time' => time(),
                    'type' => 1,
                    'way' => 2
                ]);
                $user = Db::name('users')->where('user_id', $uid)->find();
                TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
                exit($this->success('合并成功'));
            }
        }

        return $this->fetch();
    }

    /**
     * 会员合并.
     */
    public function merge2()
    {
        if (IS_POST) {
            $uid = I('post.id');
            $data = [
                'bindUserId' => I('post.merge_uid'),
                'username' => I('post.merge_uid'),
            ];
            $usersLogic = new CommonUsersLogic();
            $res = $usersLogic->bindUser($uid, 4, $data);
            if ($res['status'] !== 1) {
                exit($this->error($res['msg']));
            }
            exit($this->success('合并成功'));
        }
        return $this->fetch();
    }

    /**
     * 会员详细信息查看.
     */
    public function detail()
    {
        $uid = I('get.id');
        $usersModel = new UsersModel();
        $user = $usersModel->where(['user_id' => $uid])->find();
        if (!$user) {
            exit($this->error('会员不存在'));
        }
        if (IS_POST) {
            $oldData = $user;

            //  会员信息编辑
            $password = I('post.password');
            $password2 = I('post.password2');
            if ('' != $password && $password != $password2) {
                exit($this->error('两次输入密码不同'));
            }
            if ('' == $password && '' == $password2) {
                unset($_POST['password']);
            } else {
                $_POST['password'] = systemEncrypt($_POST['password']);
            }
            $id = $_POST['invite_uid'];
            if ($user['invite_uid'] != $_POST['invite_uid']) {
                $_POST['invite_time'] = time();
            }
            $_POST['is_distribut'] = 0;
            if ($_POST['distribut_level'] > 1) {
                $_POST['is_distribut'] = 1;
            }

            if ($user['user_name'] == '') {
                if ($user['distribut_level'] != 3 && $_POST['distribut_level'] == 3) {
                    $this->error('不可以设置为金卡级别');
                }

                if ($user['distribut_level'] == 3 && $_POST['distribut_level'] != 3) {
                    $this->error('不可以将金卡级别降低');
                }
            }

            if ($id > 0) {
                if ($this->_hasRelationship($id, $uid)) {
                    $this->error('不能绑定和自己有关系的普通会员');
                }

                if ($id == $uid) {
                    $this->error('不能设置成自己');
                }
            }

            $userInfo = M('Users')->find($_POST['invite_uid']);
            $_POST['first_leader'] = $_POST['invite_uid'];
            $_POST['second_leader'] = $userInfo['first_leader'];
            $_POST['third_leader'] = $userInfo['second_leader'];

            M('users')->where('first_leader', $uid)->update(['second_leader' => $_POST['first_leader'], 'third_leader' => $_POST['second_leader']]);
            M('users')->where('second_leader', $uid)->update(['third_leader' => $_POST['first_leader']]);

            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if (!empty($_POST['mobile'])) {
                $mobile = trim($_POST['mobile']);
                $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            if ($_POST['user_money'] != $user['user_money'] || $_POST['user_electronic'] != $user['user_electronic'] || $_POST['pay_points'] != $user['pay_points']) {
                $money = $_POST['user_money'] - $user['user_money'];
                $electronic = $_POST['user_electronic'] - $user['user_electronic'];
                $point = $_POST['pay_points'] - $user['pay_points'];
                accountLog($uid, $money, $point, '客服调整', 0, 0, '', $electronic, 0);
                unset($_POST['user_money']);
                unset($_POST['user_electronic']);
                unset($_POST['pay_points']);
            }

            // 更新用户信息
            M('users')->where(['user_id' => $uid])->save($_POST);

            if ($_POST['distribut_level'] == 1) {
                //将未发放的提成  改为0
                M('RebateLog')->where(array('user_id' => $uid, 'status' => array('in', array(0, 1, 2))))->update(array('point' => 0, 'money' => 0, 'remark' => '降级，追回佣金'));
            }
            // 更新缓存
            $user = M('users')->where('user_id', $uid)->find();
            TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);

            $newData = $user;
            // 记录日志
            M('users_edit_log')->add([
                'admin_id' => session('admin_id'),
                'user_id' => $uid,
                'old_data' => json_encode($oldData),
                'new_data' => json_encode($newData),
                'create_time' => time()
            ]);

            exit($this->success('修改成功'));
        }

        $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = M('users')->where("third_leader = {$user['user_id']}")->count();

        $this->assign('user', $user);

        return $this->fetch();
    }

    public function add_user()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if (1 == $res['status']) {
                $this->success('添加成功', U('User/index'));
                exit;
            }
            $this->error('添加失败,' . $res['msg'], U('User/index'));
        }

        return $this->fetch();
    }

    public function export_user()
    {
        $condition = [];
        //级别
        $distribut_level = I('distribut_level', 0, 'int');
        if ($distribut_level) {
            $condition['distribut_level'] = $distribut_level;
        }

        //关系
        $field1 = I('field1', '');
        $value1 = I('value1', '');
        if ($value1) {
            $value1Info = get_user_info($value1, 5, '', 'user_id');
            $condition[$field1] = $value1Info ? $value1Info['user_id'] : -1;
        }

        //时间
        $field2 = I('field2', '');
        $from_time = I('from_time', '');
        $to_time = I('to_time', '');
        if ($from_time || $to_time) {
            $condition[$field2] = whereTime($from_time, $to_time);
        }
        //个人信息
        $field3 = I('field3', '');
        $value3 = I('value3', '');
        if ($value3) {
            $ausers = M('users')->where([$field3 => $value3])->field('user_id')->select();
            $condition['user_id'] = $ausers ? ['in', get_arr_column($ausers, 'user_id')] : -1;
        }

        $ids = I('ids');
        if ($ids) {
            $condition['user_id'] = ['in', $ids];
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">性别</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">生日</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">父级ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">电子币</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册来源</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">首次登陆APP时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆来源</td>';
        $strTable .= '</tr>';

        // 会员订单数据
        $user_total_amount = M('order')->where('order_status', 'in', [1, 2, 4])->group('user_id')->getField('user_id, sum(order_amount) + sum(user_electronic)', true);
        // 等级列表
        $level_list = M('distribut_level')->getField('level_id, level_name');
        // 来源
        $source = ['1' => '微信', '2' => 'PC', '3' => 'APP'];
        // 用户第一次APP登陆
        $firstAppLogin = M('user_login_log')->where(['is_app_first' => 1])->group('user_id')->getField('user_id, login_time', true);
        // 用户数据
        $count = M('users')->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; ++$i) {
            $start = $i * 5000;
            $userList = M('users')->where($condition)
                ->field('user_id, sex, birthday, first_leader, user_name, nickname, distribut_level, mobile, reg_time, reg_source, last_login, last_login_source, user_money, pay_points, user_electronic')
                ->order('user_id')->limit($start, 5000)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_name'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                    switch ($val['sex']) {
                        case 1:
                            $sex = '男';
                            break;
                        case 2:
                            $sex = '女';
                            break;
                        default:
                            $sex = '保密';
                    }
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $sex. ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['birthday']. ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $level_list[$val['distribut_level']] . '</td>';
                    $totalAmount = isset($user_total_amount[$val['user_id']]) ? $user_total_amount[$val['user_id']] : "0.00";
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $totalAmount . ' </td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['first_leader'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_electronic'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['reg_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $source[$val['reg_source']] . ' </td>';
                    $firstLogin = isset($firstAppLogin[$val['user_id']]) ? date('Y-m-d H:i', $firstAppLogin[$val['user_id']]) : '';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $firstLogin . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['last_login']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $source[$val['last_login_source']] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }

    /**
     * 用户收货地址查看.
     */
    public function address()
    {
        $uid = I('get.id');
        $lists = D('user_address')->where(['user_id' => $uid])->select();
        $regionList = get_region_list();
        $this->assign('regionList', $regionList);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    /**
     * 删除会员.
     */
    public function delete()
    {
        $uid = I('get.id');
        $row = M('users')->where(['user_id' => $uid])->delete();
        $row1 = M('oauth_users')->where(['user_id' => $uid])->delete();
        if ($row) {
            return json(['status' => 1, 'msg' => '成功删除会员']);
        }

        return json(['status' => -1, 'msg' => '操作失败']);
        // $this->error('操作失败');
    }

    /**
     * 注销会员
     * @return \think\response\Json
     */
    public function cancel()
    {
        $uid = I('get.id');
        $oldData = M('users')->where('user_id', $uid)->find();
        // 注销会员
        M('users')->where(['user_id' => $uid])->update(['is_cancel' => 1]);
        M('oauth_users')->where(['user_id' => $uid])->delete();
        // 更新缓存
        $newData = M('users')->where('user_id', $uid)->find();
        TokenLogic::updateValue('user', $newData['token'], $newData, $newData['time_out']);
        // 记录日志
        M('users_edit_log')->add([
            'admin_id' => session('admin_id'),
            'user_id' => $uid,
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
            'create_time' => time()
        ]);
        return json(['status' => 1, 'msg' => '成功注销用户']);
    }

    /**
     * 删除会员.
     */
    public function ajax_delete()
    {
        $uid = I('id');
        if ($uid) {
            $row = M('users')->where(['user_id' => $uid])->delete();
            if (false !== $row) {
                //把关联的第三方账号删除
                M('OauthUsers')->where(['user_id' => $uid])->delete();
                $this->ajaxReturn(['status' => 1, 'msg' => '删除成功', 'data' => '']);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '删除失败', 'data' => '']);
            }
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'data' => '']);
        }
    }

    /**
     * 账户资金记录.
     */
    public function account_log()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->where(['user_id' => $user_id])->count();
        $page = new Page($count);
        $lists = M('account_log')->where(['user_id' => $user_id])->order('change_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数有误']);
        }
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id', $user_id)->find();
        if (IS_POST) {
            $desc = I('post.desc');
            if (!$desc) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请填写操作说明']);
            }
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money = $m_op_type ? $user_money : 0 - $user_money;

            //加减用户电子币
            $e_op_type = I('post.electronic_act_type');
            $user_electronic = I('post.user_electronic/f');
            $user_electronic = $e_op_type ? $user_electronic : 0 - $user_electronic;

            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points = $p_op_type ? $pay_points : 0 - $pay_points;
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if (0 != $revision_frozen_money) {    //有加减冻结资金的时候
                $frozen_money = $f_op_type ? $revision_frozen_money : 0 - $revision_frozen_money;
                $frozen_money = $user['frozen_money'] + $frozen_money;    //计算用户被冻结的资金
                if (1 == $f_op_type and $revision_frozen_money > $user['user_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '用户剩余资金不足！！']);
                }
                if (0 == $f_op_type and $revision_frozen_money > $user['frozen_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '冻结的资金不足！！']);
                }
                $user_money = $f_op_type ? 0 - $revision_frozen_money : $revision_frozen_money;    //计算用户剩余资金
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $frozen_money]);
            }
            if (accountLog($user_id, $user_money, $pay_points, $desc, 0, 0, '', $user_electronic, 0)) {
                $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/User/account_log', ['id' => $user_id])]);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => '操作失败']);
            }
            exit;
        }
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);

        return $this->fetch();
    }

    public function recharge()
    {
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $map = [];
        if ($timegap) {
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['ctime'] = ['between', [strtotime($begin), strtotime($end)]];
        }
        if ($nickname) {
            $map['nickname'] = ['like', "%$nickname%"];
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        return $this->fetch();
    }

    public function level()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $level_id = I('get.level_id');
        if ($level_id) {
            $level_info = D('user_level')->where('level_id=' . $level_id)->find();
            $this->assign('info', $level_info);
        }

        return $this->fetch();
    }

    public function levelList()
    {
        $Ad = M('user_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level_id')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);

        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除.
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => '']; //初始化返回信息
        if ('add' == $data['act']) {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if (false !== $r) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ('edit' == $data['act']) {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if (false !== $r) {
                    $discount = $data['discount'] / 100;
                    D('users')->where(['level' => $data['level_id']])->save(['discount' => $discount]);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ('del' == $data['act']) {
            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();
            if (false !== $r) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名.
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if ('' == $search_key) {
            $this->ajaxReturn(['status' => -1, 'msg' => '请按要求输入！！']);
        }
        $list = M('users')->where(['nickname' => ['like', "%$search_key%"]])->select();
        if ($list) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'result' => $list]);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '未查询到相应数据！！']);
    }

    /**
     * 分销树状关系.
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where('first_leader = 1')->select();

        return $this->fetch();
    }

    /**
     * @time 2016/08/31
     *
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = [];
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(['user_id' => ['IN', $user_id_array]])->select();
        }
        $this->assign('distribut_level', M('distribut_level')->getField('level_id,level_name'));
        $this->assign('users', $users);

        return $this->fetch();
    }

    /**
     * 发送系统消息.
     *
     * @author dyr
     * @time  2016/09/01
     */
    public function doSendMessage()
    {
        $call_back = I('call_back'); //回调方法
        $text = I('post.text'); //内容
        $type = I('post.type', 0); //个体or全体
        $admin_id = session('admin_id');
        $users = I('post.user/a'); //个体id
        $distribut_level = I('post.distribut_level/d'); //个体id
        if (1 != $type && $distribut_level > 0) {
            $this->error('设置错误，级别设置必须是选择发给所有用户');
        }
        $message = [
            'admin_id' => $admin_id,
            'message' => $text,
            'category' => 0,
            'distribut_level' => $distribut_level,
            'send_time' => time(),
        ];

        if (1 == $type) {
            //全体用户系统消息
            $message['type'] = 1;
            M('Message')->add($message);
        } else {
            //个体消息
            $message['type'] = 0;
            if (!empty($users)) {
                $create_message_id = M('Message')->add($message);
                foreach ($users as $key) {
                    M('user_message')->add(['user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0]);
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     * @time 2016/09/03
     *
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = [];
        if (!empty($user_id_array)) {
            $user_where = [
                'user_id' => ['IN', $user_id_array],
                'email' => ['neq', ''],
            ];
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);

        return $this->fetch();
    }

    /**
     * 发送邮箱.
     *
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back'); //回调方法
        $message = I('post.text'); //内容
        $title = I('post.title'); //标题
        $users = I('post.user/a');
        $email = I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(['user_id' => ['IN', $user_id_array]])->select();
            $to = [];
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if ($email) {
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     * 提现申请记录.
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        $this->assign('withdraw_status', C('WITHDRAW_STATUS'));

        return $this->fetch();
    }

    public function get_withdrawals_list($status = '')
    {
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = I('create_time');
        $ids = I('ids');
        $create_time = str_replace('+', ' ', $create_time);
        $create_time2 = $create_time ? $create_time : date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));
        $create_time3 = explode(' - ', $create_time2);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        $where['w.create_time'] = ['between', [strtotime($create_time3[0]), strtotime($create_time3[1])]];

        $status = empty($status) ? I('status') : $status;
        if ('' !== $status) {
            $where['w.status'] = $status;
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = ['like', '%' . $realname . '%'];
        $bank_card && $where['w.bank_card'] = ['like', '%' . $bank_card . '%'];
        $export = I('export');
        if (1 == $export) {
            $logStatus = [
                '-2' => '删除作废',
                '-1' => '审核失败',
                '0' => '申请中',
                '1' => '审核通过',
                '2' => '付款成功',
                '3' => '付款失败',
            ];
            if ($ids) {
                $ids = explode(',', $ids);
                $where['w.id'] = ['in', $ids];
            }
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">用户ID</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">审核时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">记录生成时间</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order('w.id desc')->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $logStatus[$val['status']] . '</td>';
                    $checkTime = '';
                    if ($val['check_time'] > 0) {
                        $checkTime = date('Y-m-d H:i:s', $val['check_time']);
                    }
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $checkTime . '</td>';
                    $payTime = '';
                    if ($val['pay_time'] > 0) {
                        $payTime = date('Y-m-d H:i:s', $val['pay_time']);
                    }
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $payTime . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, '提现申请记录');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('withdrawals')
            ->alias('w')
            ->field('w.*,u.nickname')
            ->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')
            ->where($where)->order('w.id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $this->assign('create_time', $create_time2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
    }

    /**
     * 删除申请记录.
     */
    public function delWithdrawals()
    {
        $id = I('del_id/d');
        $res = Db::name('withdrawals')->where(['id' => $id])->delete();
        if (false !== $res) {
            $return_arr = ['status' => 1, 'msg' => '操作成功', 'data' => ''];
        } else {
            $return_arr = ['status' => -1, 'msg' => '删除失败', 'data' => ''];
        }
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现.
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $withdrawals = Db::name('withdrawals')->find($id);
        $user = M('users')->where(['user_id' => $withdrawals['user_id']])->find();
        if ($user['nickname']) {
            $withdrawals['user_name'] = $user['nickname'];
        } elseif ($user['email']) {
            $withdrawals['user_name'] = $user['email'];
        } elseif ($user['mobile']) {
            $withdrawals['user_name'] = $user['mobile'];
        }
        $status = $withdrawals['status'];
        $withdrawals['status_code'] = C('WITHDRAW_STATUS')["$status"];
        $this->assign('user', $user);
        $this->assign('data', $withdrawals);

        return $this->fetch();
    }

    /**
     *  处理会员提现申请.
     */
    public function withdrawals_update()
    {
        $id_arr = I('id/a');
        $ids = implode(',', $id_arr);
        if (empty($ids)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败,参数为空'], 'JSON');
        }
        $data['status'] = I('status');
        $data['remark'] = I('remark');
        switch ($data['status']) {
            case -1:
                $data['refuse_time'] = time();
                break;
            case 1:
                $data['check_time'] = time();
                break;
            case 2:
                $data['pay_time'] = time();
                break;
        }
        Db::startTrans();
        if ($data['status'] == 2) {
            // 扣除用户账户记录
            foreach ($id_arr as $k => $v) {
                $val = Db::name('withdrawals')->find($v);
                // 查看用户现在余额
                $userMoney = M('users')->where(['user_id' => $val['user_id']])->value('user_money');
                if ($userMoney < $val['money']) {
                    Db::rollback();
                    $this->ajaxReturn(['status' => 0, 'msg' => '用户ID：' . $val['user_id'] . ' 现在余额不足，请联系客户'], 'JSON');
                }
                $res = accountLog($val['user_id'], ($val['money'] * -1), 0, '提现已完成', 0, 0, '', 0, 20); //手动转账，默认视为已通过线下转方式处理了该笔提现申请
                if (!$res) {
                    Db::rollback();
                    $this->ajaxReturn(['status' => 0, 'msg' => '用户ID：' . $val['user_id'] . ' 现在余额不足，请联系客户'], 'JSON');
                }
            }
        }
        $r = Db::name('withdrawals')->whereIn('id', $ids)->update($data);
        if (false !== $r) {
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功'], 'JSON');
        } else {
            Db::rollback();
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败'], 'JSON');
        }
    }

    // 用户申请提现
    public function transfer()
    {
        $id = I('selected/a');
        if (empty($id)) {
            $this->error('请至少选择一条记录');
        }
        $atype = I('atype');
        if (is_array($id)) {
            $withdrawals = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->select();
        } else {
            $withdrawals = M('withdrawals')->where(['id' => $id])->select();
        }
        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach ($withdrawals as $val) {
            $user = M('users')->where(['user_id' => $val['user_id']])->find();
            if ($user['user_money'] < $val['money']) {
                $data = ['status' => -2, 'remark' => '账户余额不足'];
                M('withdrawals')->where(['id' => $val['id']])->save($data);
                $this->error('账户余额不足');
            } else {
                $rdata = ['type' => 1, 'money' => $val['money'], 'log_type_id' => $val['id'], 'user_id' => $val['user_id']];
                if ('online' == $atype) {
                    header('Content-type: text/html; charset=utf-8');
                    exit('该功能暂未开放');
                }

                accountLog($val['user_id'], ($val['money'] * -1), 0, '管理员处理用户提现申请', 0, 0, '', 0, 20); //手动转账，默认视为已通过线下转方式处理了该笔提现申请
                $r = M('withdrawals')->where(['id' => $val['id']])->save(['status' => 2, 'pay_time' => time()]);
                expenseLog($rdata); //支出记录日志
            }
        }
        if ($alipay['batch_num'] > 0) {
            //支付宝在线批量付款
            include_once PLUGIN_PATH . 'payment/alipay/alipay.class.php';
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success('操作成功!', U('remittance'), 3);
    }

    /**
     *  转账汇款记录.
     */
    public function remittance()
    {
        $status = I('status', 1);
        $this->assign('status', $status);
        $this->get_withdrawals_list($status);

        return $this->fetch();
    }

    /**
     * 签到列表.
     *
     * @date 2017/09/28
     */
    public function signList()
    {
        return $this->fetch();
    }

    /**
     * 会员签到 ajax.
     *
     * @date 2017/09/28
     */
    public function ajaxsignList()
    {
        $where = ' 1 = 1 '; // 搜索条件

        ('' !== I('mobile')) && $where = "$where and u.mobile = " . I('mobile');

        $count = M('user_sign')->where($where)->count();
        $Page = new AjaxPage($count, 10);

        $show = $Page->show();

        $list = M('user_sign')
            ->field('us.*,u.mobile,u.nickname')
            ->alias('us')
            ->join('__USERS__ u', 'us.user_id = u.user_id')
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('id desc')
            ->select();

        $this->assign('list', $list);
        $this->assign('page', $show); // 赋值分页输出
        return $this->fetch();
    }

    /**
     * 签到规则设置.
     *
     * @date 2017/09/28
     */
    public function signRule()
    {
        if (IS_POST) {
            $param = I('post.');
            $inc_type = 'sign';
            //unset($param['__hash__']);
            // unset($param['inc_type']);
            tpCache($inc_type, $param);
            $this->success('操作成功', U('User/signList'));
        }
        $config = tpCache('sign');
        $this->assign('config', $config);

        return $this->fetch();
    }

    /**
     * 用户微信解绑 By J.
     */
    public function unbind()
    {
        if (Request::instance()->isPost()) {
            $user_id = I('id', '', 'trim');
            $info = get_user_info($user_id);

            if (!$info) {
                $this->error('输入会员id有误，请检查清楚重新输入');
            }

            if ($info['bind_uid'] == $info['user_id']) {
                $this->error('该会员是通过申请金卡成为金卡的，不能解绑');
            }
            Db::startTrans();
            M('users')->where('user_id', $user_id)->update([
                'oauth' => '',
                'openid' => '',
                'unionid' => '',
                'head_pic' => '',
                'bind_time' => 0,
                'bind_uid' => 0,
                'nickname' => '',
            ]);
            $o = M('oauth_users')->where('user_id', $user_id)->find();
            M('oauth_users')->where('user_id', $user_id)->delete();
            if ($o) {
                M('bind_log')->add([
                    'user_id' => $user_id,
                    'bind_user_id' => 0,
                    'add_time' => time(),
                    'type' => 2,    // 1绑定 2解绑
                    'way' => 2,
                    'openid' => $o['openid'],
                    'unionid' => $o['unionid'],
                    'oauth' => $o['oauth'],
                ]);
                Db::commit();
                return $this->success('解绑成功');
            } else {
                Db::rollback();
                $this->error('解绑失败');
            }
        }
        return $this->fetch();
    }

    public function getUserInfo()
    {
        $user_id = I('user_id', '', 'trim');
        $info = get_user_info($user_id);
        if ($info) {
            return json(['status' => 1, 'msg' => 'ok', 'result' => $info]);
        }

        return json(['status' => 0, 'msg' => '输入会员id有误，请检查清楚重新输入', 'result' => null]);
    }

    /**
     * 会员信息修改记录
     * @return mixed
     */
    public function usersEditLog()
    {
        $count = M('users_edit_log')->count('id');
        $page = new Page($count, 20);
        $editLog = M('users_edit_log')->alias('uel')
            ->join('admin a', 'a.admin_id = uel.admin_id')
            ->field('uel.*, a.user_name admin_name')
            ->limit($page->firstRow . ',' . $page->listRows)->order('create_time desc')->select();

        $this->assign('page', $page);
        $this->assign('edit_log', $editLog);
        return $this->fetch('users_edit_log');
    }

    /**
     * 会员信息修改记录详情
     * @return mixed
     */
    public function usersEditLogInfo()
    {
        $logId = I('log_id', '');
        $type = I('type', 'old_data');
        $logInfo = M('users_edit_log')->where(['id' => $logId])->value($type);
        $user = json_decode($logInfo, true);

        $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = M('users')->where("third_leader = {$user['user_id']}")->count();

        $this->assign('type', $type);
        $this->assign('user', $user);
        return $this->fetch('users_edit_log_info');
    }

    /**
     * 用户登录记录
     * @return mixed
     */
    public function userLoginLog()
    {
        $userId = I('user_id', 0);
        $where = ['user_id' => $userId];
        $userLoginLog = new UserLoginLogModel();
        $count = $userLoginLog->where($where)->count();
        $page = new Page($count, 10);
        $loginLog = $userLoginLog->where($where)->order('login_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page);
        $this->assign('user_id', $userId);
        $this->assign('login_log', $loginLog);
        return $this->fetch('login_log');
    }

    /**
     * APP首次登陆统计
     * @return mixed
     */
    public function appLoginStatistics()
    {
        $count = M('user_login_log')->group('login_date')->count();
        $page = new Page($count, 10);
        $loginDate = M('user_login_log')->order('login_date desc')->group('login_date')->limit($page->firstRow . ',' . $page->listRows)->getField('login_date', true);
        $logList = [];
        foreach ($loginDate as $date) {
            $logList[] = [
                'date' => $date,
                'count' => M('user_login_log')->where(['login_date' => $date, 'is_app_first' => 1])->group('user_id')->count('id')
            ];
        }
        $this->assign('page', $page);
        $this->assign('list', $logList);
        return $this->fetch('app_login_statistics');
    }

    /**
     * APP首次登陆统计导出
     */
    public function exportAppLoginStatistics()
    {
        $loginDate = M('user_login_log')->order('login_date desc')->group('login_date')->getField('login_date', true);
        $logList = [];
        foreach ($loginDate as $date) {
            $logList[] = [
                'date' => $date,
                'count' => M('user_login_log')->where(['login_date' => $date, 'is_app_first' => 1])->group('user_id')->count('id')
            ];
        }
        // 表头
        $headList = [
            '日期', '数量'
        ];
        toCsvExcel($logList, $headList, 'app_login_statistics');
    }

    /**
     * APP首次登陆详情
     * @return mixed
     */
    public function appLoginLog()
    {
        $date = I('date');
        $where = [
            'login_date' => $date,
            'is_app_first' => 1
        ];
        $count = M('user_login_log')->where($where)->count();
        $page = new Page($count, 10);
        $loginList = M('user_login_log ull')
            ->join('users u', 'u.user_id = ull.user_id')
            ->where($where)->field('ull.*, u.user_name, u.nickname')
            ->order('login_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('date', $date);
        $this->assign('page', $page);
        $this->assign('list', $loginList);
        return $this->fetch('app_login_log');
    }

    /**
     * APP首次登陆详情导出
     */
    public function exportAppLoginLog()
    {
        $date = I('date');
        $where = [
            'login_date' => $date,
            'is_app_first' => 1
        ];
        $loginList = M('user_login_log ull')
            ->join('users u', 'u.user_id = ull.user_id')
            ->where($where)->field('ull.*, u.user_name, u.nickname')
            ->order('login_time desc')->select();
        // 表头
        $headList = [
            '会员号', '用户名', '会员昵称', 'ip', '登陆时间'
        ];
        // 表数据
        $dataList = [];
        foreach ($loginList as $log) {
            $dataList[] = [
                $log['user_id'],
                $log['user_name'],
                $log['nickname'],
                $log['login_ip'],
                date('Y-m-d H:i', $log['login_time']),
            ];
        }
        toCsvExcel($dataList, $headList, 'app_login_log');
    }
}
