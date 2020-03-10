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

use app\common\logic\SmsLogic;
use app\common\model\SpecialSmsLog;
use think\Controller;
use think\Db;

// 自动任务调度类
class Cron1 extends Controller
{

    function edit_goods()
    {
        $z_goods = M('z_goods')->where('status=0')->select();
        foreach ($z_goods as $k => $v) {
            Db::startTrans();
            $data = array(
                'stax_price' => $v['stax_price'],
                'ctax_price' => $v['ctax_price']
            );

            $agoods = M('goods')->where(array('goods_sn' => $v['order_sn']))->find();
            if ($agoods && bccomp($agoods['stax_price'], $v['stax_price'], 2) == 0 && bccomp($agoods['ctax_price'], $v['ctax_price'], 2) == 0) {
                $bgoods = true;
            } else {
                $bgoods = M('goods')->where(array('goods_sn' => $v['order_sn']))->data($data)->save();
            }
            $bz_goods = M('z_goods')->where(array('id' => $v['id'], 'status' => 0))->data(array('status' => 1))->save();
            if ($bgoods && $bz_goods) {
                Db::commit();
            } else {

                Db::rollback();
            }
        }
    }

    function edit_goods1()
    {
        $z_goods = M('z_goods')->where('status=0')->select();
        foreach ($z_goods as $k => $v) {
            Db::startTrans();
            $data = array(
                'stax_price' => $v['stax_price'],
                'ctax_price' => $v['ctax_price']
            );

            $agoods = M('goods')->where(array('goods_sn' => '0' . $v['order_sn']))->find();
            if ($agoods && bccomp($agoods['stax_price'], $v['stax_price'], 2) == 0 && bccomp($agoods['ctax_price'], $v['ctax_price'], 2) == 0) {
                $bgoods = true;
            } else {
                $bgoods = M('goods')->where(array('goods_sn' => '0' . $v['order_sn']))->data($data)->save();
            }
            $bz_goods = M('z_goods')->where(array('id' => $v['id'], 'status' => 0))->data(array('status' => 1))->save();
            if ($bgoods && $bz_goods) {
                Db::commit();
            } else {

                Db::rollback();
            }
        }
    }

    function edit_goods2()
    {
        $z_goods = M('z_goods')->where('status=0')->select();
        foreach ($z_goods as $k => $v) {
            Db::startTrans();
            $data = array(
                'stax_price' => $v['stax_price'],
                'ctax_price' => $v['ctax_price']
            );

            $agoods = M('goods')->where(array('goods_sn' => '00' . $v['order_sn']))->find();
            if ($agoods && bccomp($agoods['stax_price'], $v['stax_price'], 2) == 0 && bccomp($agoods['ctax_price'], $v['ctax_price'], 2) == 0) {
                $bgoods = true;
            } else {
                $bgoods = M('goods')->where(array('goods_sn' => '00' . $v['order_sn']))->data($data)->save();
            }
            $bz_goods = M('z_goods')->where(array('id' => $v['id'], 'status' => 0))->data(array('status' => 1))->save();
            if ($bgoods && $bz_goods) {
                Db::commit();
            } else {

                Db::rollback();
            }
        }
    }

    function edit_goods3()
    {
        $z_goods = M('z_goods')->where('status=0')->select();
        foreach ($z_goods as $k => $v) {
            Db::startTrans();
            $data = array(
                'stax_price' => $v['stax_price'],
                'ctax_price' => $v['ctax_price']
            );

            $agoods = M('goods')->where(array('goods_sn' => '000' . $v['order_sn']))->find();
            if ($agoods && bccomp($agoods['stax_price'], $v['stax_price'], 2) == 0 && bccomp($agoods['ctax_price'], $v['ctax_price'], 2) == 0) {
                $bgoods = true;
            } else {
                $bgoods = M('goods')->where(array('goods_sn' => '000' . $v['order_sn']))->data($data)->save();
            }

            $bz_goods = M('z_goods')->where(array('id' => $v['id'], 'status' => 0))->data(array('status' => 1))->save();
            if ($bgoods && $bz_goods) {
                Db::commit();
            } else {

                Db::rollback();
            }
        }
    }

    function edit_goods5()
    {
        $z_goods = M('z_goods')->where('status=0')->select();
        foreach ($z_goods as $k => $v) {
            Db::startTrans();
            $data = array(
                'stax_price' => $v['stax_price'],
                'ctax_price' => $v['ctax_price']
            );

            $agoods = M('goods')->where(array('goods_sn' => '00000' . $v['order_sn']))->find();
            if ($agoods && bccomp($agoods['stax_price'], $v['stax_price'], 2) == 0 && bccomp($agoods['ctax_price'], $v['ctax_price'], 2) == 0) {
                $bgoods = true;
            } else {
                $bgoods = M('goods')->where(array('goods_sn' => '00000' . $v['order_sn']))->data($data)->save();
            }

            $bz_goods = M('z_goods')->where(array('id' => $v['id'], 'status' => 0))->data(array('status' => 1))->save();
            if ($bgoods && $bz_goods) {
                Db::commit();
            } else {

                Db::rollback();
            }
        }
    }

    /**
     * 导入会员.
     */
    public function inuser()
    {
        set_time_limit(0);      //执行时间无限
        ini_set('memory_limit', '-1');    //内存无限
        $user_zhixiao = M('users_zhixiao')->where(['send_status' => 0, 'is_activate' => 1])->select();
        foreach ($user_zhixiao as $k => $v) {
            Db::startTrans();
            $data = [
                'user_id' => $v['user_id'],
                'user_name' => $v['user_name'],
                'true_name' => $v['true_name'],
                'mobile' => $v['mobile'],
                'password' => $v['password'],
                'paypwd' => $v['paypwd'],
                'sex' => $v['sex'],
                'birthday' => $v['birthday'],
                'id_cart' => $v['id_card'],
                'reg_time' => $v['reg_time'],
                'first_leader' => $v['referee_user_id'],
                'is_zhixiao' => 1,
                'distribut_level' => 3,
            ];
            $buser = M('users')->add($data);
            $buser_zhixiao = M('users_zhixiao')->where(['user_id' => $v['user_id'], 'send_status' => 0])->data(['send_status' => 1])->save();
            if ($buser && $buser_zhixiao) {
                Db::commit();
            } else {
                Db::rollback();
            }
        }
    }

    /**
     * 导入旧商城.
     */
    public function inuser_old_shop()
    {
        set_time_limit(0);      //执行时间无限
        ini_set('memory_limit', '-1');    //内存无限
        $user_zhixiao = M('users_old_shop')->where('in_status=0')->select();
        foreach ($user_zhixiao as $k => $v) {
            $v['id_card'] = trim($v['id_card']);
            if ($v['id_card'] && check_id_card($v['id_card'])) {
                $id_card = $v['id_card'];

                $birth = 15 == strlen($id_card) ? ('19' . substr($id_card, 6, 6)) : substr($id_card, 6, 8);
                $birthday = substr($birth, 0, 4) . '-' . substr($birth, 4, 2) . '-' . substr($birth, 6, 2);
                $sex = substr($id_card, (15 == strlen($id_card) ? -1 : -2), 1) % 2 ? 1 : 0; //1为男 2为女

                $v['sex'] = $sex;
                $v['birthday'] = $birthday;
            } else {
                $v['sex'] = 0;
                $v['birthday'] = '';
            }

            Db::startTrans();
            $data = [
                'user_name' => $v['user_name'],
                'true_name' => $v['true_name'],
                'mobile' => $v['mobile'],
                'sex' => $v['sex'],
                'id_cart' => $v['id_card'],
                'birthday' => $v['birthday'],
                'reg_time' => strtotime($v['reg_date'] . ' ' . $v['reg_time']),
                'referee_user_name' => $v['referee_user_name'],
                'distribut_level' => 2,
            ];
            $buser = M('users')->add($data);
            $buser_zhixiao = M('users_old_shop')->where(['id' => $v['id'], 'in_status' => 0])->data(['in_status' => 1])->save();
            if ($buser && $buser_zhixiao) {
                Db::commit();
            } else {
                Db::rollback();
            }
        }
    }

    /**
     * 导入旧商城 - 推荐人.
     */
    public function inuser_order_refe()
    {
        set_time_limit(0);      //执行时间无限
        ini_set('memory_limit', '-1');    //内存无限
        $user_zhixiao = M('users')->field('user_id,referee_user_name')->where(['referee_user_name' => ['neq', '']])->select();

        foreach ($user_zhixiao as $k => $v) {
            $users = M('users')->field('user_id')->where(['user_name' => $v['referee_user_name']])->find();
            M('users')->where(['user_id' => $v['user_id']])->data(['first_leader' => $users['user_id']])->save();
        }
    }

    /**
     * 检查旧会员数据   查看推荐人.
     */
    public function no_user()
    {
        $users_old_shop = M('users_old_shop')->where("referee_user_name!=''")->field('referee_user_name,user_name')->select();
        $arr = [];
        $users_id = M('users')->where(['user_name' => 0])->getField('user_id');
        foreach ($users_old_shop as $k => $v) {
            $users = M('users')->where(['user_name' => $v['referee_user_name']])->find();
            if (!$users) {
                $arr[] = $v['user_name'];
                M('users')->where(['user_name' => $v['user_name']])->data(['first_leader' => $users_id])->save();
            }
        }
        var_dump($arr);
    }

    /**
     * 修改报单系统中两个人的推荐人.
     */
    public function update_referee()
    {
        $arr = [
            14803 => 3530,
            18487 => 15947,
        ];
        foreach ($arr as $k => $v) {
            $user_id = M('users')->where(['user_name' => $v])->getField('user_id');
            M('users')->where(['user_name' => $k])->data(['first_leader' => $user_id])->save();
        }
    }

    /**
     * 导入推荐人.
     */
    public function inuser_refe()
    {
        set_time_limit(0);      //执行时间无限
        ini_set('memory_limit', '-1');    //内存无限
        $user_zhixiao = M('users')->field('user_id,first_leader')->where('first_leader!=0')->select();
        foreach ($user_zhixiao as $k => $v) {
            if ($v['first_leader']) {
                $second_leader = M('users')->where(['user_id' => $v['first_leader']])->getField('first_leader');
                if ($second_leader) {
                    $third_leader = M('users')->where(['user_id' => $second_leader])->getField('first_leader');
                } else {
                    $third_leader = 0;
                }
                M('users')->where(['user_id' => $v['user_id']])->data(['second_leader' => $second_leader, 'third_leader' => $third_leader])->save();
            }
        }
    }


    public function test_update_tb()
    {
        $orderActionLog = M('order_action')->where(['log_time' => ['gt', '1583452536']])->field('order_id, log_time')->select();
        foreach ($orderActionLog as $action) {
            M('tb')->add([
                'type' => 6,
                'from_id' => $action['order_id'],
                'system' => 3,
                'status' => 0,
                'add_time' => $action['log_time'],
                'from_system' => 2,
                'tb_sn' => get_rand_str(8, 0, 2)
            ]);
        }
    }

    public function test_log_send_sms()
    {
        $where = [
            'mobile' => ['neq', ''],
            'distribut_level' => ['>=', 3],
            'is_zhixiao' => 1,
            'is_lock' => 0,
            'is_cancel' => 0
        ];
        $userData = M('users')->where($where)->field('user_id, password, mobile, user_name')->select();
        $logData = [];
        foreach ($userData as $k => $data) {
            if ($data['password'] == systemEncrypt(substr($data['mobile'], -4, 4))) {
                $logData[] = [
                    'mobile' => $data['mobile'],
                    'param' => serialize([
                        'user_id' => $data['user_id'],
                        'user_name' => $data['user_name']
                    ])
                ];
            }
        }
        $specialSmsLog = new SpecialSmsLog();
        $res = $specialSmsLog->saveAll($logData);
        var_dump($res != false ? 'ok' : 'bad');
    }

    public function test_send_sms()
    {
        $smsLog = M('special_sms_log')->where(['is_send' => 0])->limit(0, 10)->select();
        $smsLogic = new SmsLogic();
        $logIds = [];
        foreach ($smsLog as $sms) {
            $res = $smsLogic->sendSms(9, $sms['mobile'], unserialize($sms['param']));
            if ($res['status'] == 1) {
                $logIds[] = $sms['id'];
            }
        }
        M('special_sms_log')->where(['id' => ['in', $logIds]])->update(['is_send' => 1]);
    }
}
