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

use app\common\model\Coupon as CouponModel;
use think\AjaxPage;
use think\Db;
use think\Loader;
use think\Page;

class Coupon extends Base
{
    /**----------------------------------------------*/
    /*                优惠券控制器                  */
    /**----------------------------------------------*/
    /*
     * 优惠券类型列表
     */
    public function index()
    {
        //获取优惠券列表
        $count = M('coupon')->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $coupon = new CouponModel();
        $lists = $coupon->order('add_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($lists as &$list) {
            $list['use_num'] = M('coupon_list')->where(['cid' => $list['id'], 'status' => 1])->count('id');
        }
        $this->assign('lists', $lists);
        $this->assign('pager', $Page); // 赋值分页输出
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('coupons', C('COUPON_TYPE'));

        return $this->fetch();
    }

    /*
     * 添加编辑一个优惠券类型
     */
    public function coupon_info()
    {
        $cid = I('get.id/d');
        if ($cid) {
            $coupon = M('coupon')->where(['id' => $cid])->find();
            $coupon['type_value_arr'] = explode(',', $coupon['type_value']);
            if (empty($coupon)) {
                $this->error('代金券不存在');
            } else {
                if (2 == $coupon['use_type']) {
                    $goods_coupon = Db::name('goods_coupon')->where('coupon_id', $cid)->find();
                    $cat_info = M('goods_category')->where(['id' => $goods_coupon['goods_category_id']])->find();
                    $cat_path = explode('_', $cat_info['parent_id_path']);
                    $coupon['cat_id1'] = $cat_path[1];
                    $coupon['cat_id2'] = $cat_path[2];
                    $coupon['cat_id3'] = $goods_coupon['goods_category_id'];
                }
                if (in_array($coupon['use_type'], array(1, 4, 5))) {
                    $coupon_goods_list = Db::name('goods_coupon')->where('coupon_id', $cid)->getField('goods_id,number,coupon_id', true);
                    $this->assign('coupon_goods_list', $coupon_goods_list);
                    $coupon_goods_ids = get_arr_column($coupon_goods_list, 'goods_id');
                    $enable_goods = M('goods')->where('goods_id', 'in', $coupon_goods_ids)->select();

                    $this->assign('enable_goods', $enable_goods);
                }
            }

            $this->assign('coupon', $coupon);
        } else {
            $def['send_start_time'] = strtotime('+1 day');
            $def['send_end_time'] = strtotime('+1 month');
            $def['use_start_time'] = strtotime('+1 day');
            $def['use_end_time'] = strtotime('+2 month');
            $def['type_value_arr'] = [];
            $def['status'] = 1;

            $this->assign('coupon', $def);
        }

        $cat_list = M('goods_category')->where(['parent_id' => 0])->select(); //自营店已绑定所有分类
        $this->assign('cat_list', $cat_list);

        return $this->fetch();
    }

    /**
     * 添加编辑优惠券.
     */
    public function addEditCoupon()
    {
        $data = I('post.');
        $data['type'] = 1;
        if ($data['type_value'] && isset($data['type'])) {
            if (in_array('0', $data['type_value'])) {
                $data['type_value'] = 0;
            } else {
                $data['type_value'] = implode(',', $data['type_value']);
            }
        } else {
            $data['type_value'] = 0;
        }
        if (in_array($data['use_type'], [0, 1, 2])) {
            if ($data['money'] >= $data['condition']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '优惠券面值不能大于消费金额']);
            }
        } elseif ($data['use_type'] == 4) {
            if ($data['money'] >= 10) {
                $this->ajaxReturn(['status' => 0, 'msg' => '折扣数值不能超过10']);
            }
        }
        $data['send_start_time'] = strtotime($data['send_start_time']);
        $data['send_end_time'] = strtotime($data['send_end_time']);
        $data['use_end_time'] = strtotime($data['use_end_time']);
        $data['use_start_time'] = strtotime($data['use_start_time']);
        $couponValidate = Loader::validate('Coupon');
        if (!$couponValidate->batch()->check($data)) {
            $msg = '';
            foreach ($couponValidate->getError() as $value) {
                $msg .= $value . ',';
            }
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败，' . rtrim($msg, ','), 'result' => '']);
        }

        if (empty($data['id'])) {
            $data['add_time'] = time();
            $row = Db::name('coupon')->insertGetId($data);
            //指定商品
            if (in_array($data['use_type'], [1, 3, 4])) {
                foreach ($data['goods_id'] as $value) {
                    Db::name('goods_coupon')->add(['coupon_id' => $row, 'goods_id' => $value['goods_id'], 'goods_category_id' => 0]);
                }
            }
            //兑换券
            if (5 == $data['use_type']) {
                foreach ($data['goods_id'] as $value) {
                    Db::name('goods_coupon')->add(['coupon_id' => $row, 'goods_id' => $value['goods_id'], 'goods_category_id' => 0, 'number' => $value['number']]);
                }
            }
            //指定商品分类id
            if (2 == $data['use_type']) {
                Db::name('goods_coupon')->add(['coupon_id' => $row, 'goods_category_id' => $data['cat_id3']]);
            }
        } else {
            $row = M('coupon')->where(['id' => $data['id']])->save($data);
            Db::name('goods_coupon')->where(['coupon_id' => $data['id']])->delete(); //先删除后添加
            //指定商品
            if (in_array($data['use_type'], [1, 3, 4])) {
                foreach ($data['goods_id'] as $value) {
                    Db::name('goods_coupon')->add(['coupon_id' => $data['id'], 'goods_id' => $value['goods_id']]);
                }
            }
            //兑换券
            if (5 == $data['use_type']) {
                foreach ($data['goods_id'] as $value) {
                    Db::name('goods_coupon')->add(['coupon_id' => $data['id'], 'goods_id' => $value['goods_id'], 'number' => $value['number']]);
                }
            }
            //指定商品分类id
            if (2 == $data['use_type']) {
                Db::name('goods_coupon')->add(['coupon_id' => $data['id'], 'goods_category_id' => $data['cat_id3']]);
            }
        }
        if (false !== $row) {
            $this->ajaxReturn(['status' => 1, 'msg' => '编辑代金券成功', 'result' => '']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '编辑代金券失败', 'result' => '']);
        }
    }

    /*
    * 优惠券发放
    */
    public function make_coupon()
    {
        //获取优惠券ID
        $cid = I('get.id/d');
        $type = I('get.type');
        //查询是否存在优惠券
        $data = M('coupon')->where(['id' => $cid])->find();
        $remain = $data['createnum'] - $data['send_num']; //剩余派发量
        if ($remain <= 0 && $data['createnum'] > 0) {
            $this->error($data['name'] . '已经发放完了');
        }
        if (!$data) {
            $this->error('优惠券类型不存在');
        }
        if (3 != $type) {
            $this->error('该优惠券类型不支持发放');
        }
        if (IS_POST) {
            $num = I('post.num/d');
            if ($num > $remain && $data['createnum'] > 0) {
                $this->error($data['name'] . '发放量不够了');
            }
            if (!$num > 0) {
                $this->error('发放数量不能小于0');
            }
            if (2 == $data['status']) {
                $this->error('优惠券已设置为失效');
            }
            $add['cid'] = $cid;
            $add['type'] = $type;
            $add['send_time'] = time();
            for ($i = 0; $i < $num; ++$i) {
                do {
                    $code = get_rand_str(8, 0, 1); //获取随机8位字符串
                    $check_exist = M('coupon_list')->where(['code' => $code])->find();
                } while ($check_exist);
                $add['code'] = $code;
                M('coupon_list')->add($add);
            }
            M('coupon')->where('id', $cid)->setInc('send_num', $num);
            adminLog('发放' . $num . '张' . $data['name']);
            $this->success('发放成功', U('Admin/Coupon/index'));
            exit;
        }
        $this->assign('coupon', $data);

        return $this->fetch();
    }

    public function ajax_get_user()
    {
        //搜索条件
        $condition = [];
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;
        I('level_id') ? $condition['level'] = I('level_id') : false;
        $cid = I('cid');
        $nickname = I('nickname');
        if (!empty($nickname)) {
            $condition['nickname'] = ['like', "%$nickname%"];
        }
        $issued_uids = Db::name('coupon_list')->where(['cid' => $cid])->getField('uid', true); //已经发放的用户ID
        $count = Db::name('users')->whereNotIn('user_id', $issued_uids)->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        /*foreach($condition as $key=>$val) {
    		$Page->parameter[$key] = urlencode($val);
    	}*/
        $show = $Page->show();
        $userList = Db::name('users')->whereNotIn('user_id', $issued_uids)->where($condition)->order('user_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $user_level = M('user_level')->getField('level_id,level_name', true);
        $this->assign('user_level', $user_level);
        $this->assign('userList', $userList);
        $this->assign('page', $show);
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    public function send_coupon()
    {
        $cid = I('cid/d');
        if (IS_POST) {
            $coupon = M('coupon')->where('id', $cid)->find();
            if ($coupon['nature'] != 1) {
                return json(['status' => -1, 'msg' => '该优惠券不能发放', 'result' => null]);
            }
            $coupon['type_value'] = explode(',', $coupon['type_value']);
            if (in_array(4, $coupon['type_value']) || in_array(5, $coupon['type_value']) || in_array($coupon['use_type'], array(5))) {
                return json(['status' => -1, 'msg' => '该优惠券不能发放', 'result' => null]);
            }
            if ($coupon['createnum'] > 0) {
                $remain = $coupon['createnum'] - $coupon['send_num']; //剩余派发量
                if ($remain <= 0) {
                    return json(['status' => -1, 'msg' => $coupon['name'] . '已经发放完了', 'result' => null]);
                }
            }
            if ($coupon['send_num'] > 0) {
                return json(['status' => -1, 'msg' => $coupon['name'] . '已经发放过了', 'result' => null]);
            }
            if ($coupon['type_value']) {
                if (in_array(0, $coupon['type_value'])) {
                    $user_id = M('users')->field('user_id')->where('is_lock', 'neq', 1)->where('is_cancel', 'neq', 1)->select();
                } else {
                    $user_id = M('users')->field('user_id')
                        ->where('distribut_level', 'IN', $coupon['type_value'])// 会员等级
                        ->where('is_lock', 'neq', 1)
                        ->select();
                }
            }
            if (!empty($user_id)) {
                $able = count($user_id); //本次发送量
                if ($coupon['createnum'] > 0 && $remain < $able) {
                    return json(['status' => -1, 'msg' => $coupon['name'] . '派发量只剩' . $remain . '张', 'result' => null]);
                }
                foreach ($user_id as $k => $v) {
                    $time = time();
                    $insert[] = ['cid' => $cid, 'type' => 4, 'uid' => $v['user_id'], 'send_time' => $time];
                }
                DB::name('coupon_list')->insertAll($insert);
                M('coupon')->where('id', $cid)->setInc('send_num', $able);
                adminLog('发放' . $able . '张' . $coupon['name']);

                return json(['status' => 1, 'msg' => '发放成功', 'result' => null]);
            } else {
                return json(['status' => -1, 'msg' => '选择的对象用户不存在', 'result' => null]);
            }
        }
    }

    public function send_cancel()
    {
    }

    /*
     * 删除优惠券类型
     */
    public function del_coupon()
    {
        //获取优惠券ID
        $cid = I('get.id/d');
        //查询是否存在优惠券
        $row = M('coupon')->where(['id' => $cid])->delete();
        if (!$row) {
            $this->ajaxReturn(['status' => 0, 'msg' => '优惠券不存在，删除失败']);
        }

        //删除此类型下的优惠券
        M('coupon_list')->where(['cid' => $cid])->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    /*
     * 优惠券详细查看
     */
    public function coupon_list()
    {
        //获取优惠券ID
        $cid = I('get.id/d');
        //查询是否存在优惠券
        $check_coupon = M('coupon')->field('id,type')->where(['id' => $cid])->find();
        if (!$check_coupon['id'] > 0) {
            $this->error('不存在该类型优惠券');
        }

        //查询该优惠券的列表的数量
        $sql = 'SELECT count(1) as c FROM __PREFIX__coupon_list  l ' .
            'LEFT JOIN __PREFIX__coupon c ON c.id = l.cid ' . //联合优惠券表查询名称
            'LEFT JOIN __PREFIX__order o ON o.order_id = l.order_id ' .     //联合订单表查询订单编号
            'LEFT JOIN __PREFIX__users u ON u.user_id = l.uid WHERE l.cid = :cid';    //联合用户表去查询用户名

        $count = DB::query($sql, ['cid' => $cid]);
        $count = $count[0]['c'];
        $Page = new Page($count, 10);
        $show = $Page->show();

        //查询该优惠券的列表
        $sql = 'SELECT l.*,c.name,o.order_sn,u.nickname FROM __PREFIX__coupon_list  l ' .
            'LEFT JOIN __PREFIX__coupon c ON c.id = l.cid ' . //联合优惠券表查询名称
            'LEFT JOIN __PREFIX__order o ON o.order_id = l.order_id ' .     //联合订单表查询订单编号
            'LEFT JOIN __PREFIX__users u ON u.user_id = l.uid WHERE l.cid = :cid' .    //联合用户表去查询用户名
            ' ORDER BY l.send_time DESC' .
            " limit {$Page->firstRow} , {$Page->listRows}";
        $coupon_list = DB::query($sql, ['cid' => $cid]);
        $this->assign('cid', $cid);
        $this->assign('coupon_type', C('COUPON_TYPE'));
        $this->assign('type', $check_coupon['type']);
        $this->assign('lists', $coupon_list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    /**
     * 导出优惠券使用详情
     */
    public function export_coupon_list()
    {
        $cid = I('get.id/d');
        //查询该优惠券的列表
        $sql = 'SELECT l.*,c.name,o.order_sn,u.nickname FROM __PREFIX__coupon_list  l ' .
            'LEFT JOIN __PREFIX__coupon c ON c.id = l.cid ' . //联合优惠券表查询名称
            'LEFT JOIN __PREFIX__order o ON o.order_id = l.order_id ' .     //联合订单表查询订单编号
            'LEFT JOIN __PREFIX__users u ON u.user_id = l.uid WHERE l.cid = :cid' .    //联合用户表去查询用户名
            ' ORDER BY l.send_time DESC';
        $coupon_list = DB::query($sql, ['cid' => $cid]);

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">优惠券名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">发放类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属用户ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">所属用户名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">领取（发放）时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">使用时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">优惠券码</td>';
        if (!empty($coupon_list)) {
            foreach ($coupon_list as $coupon) {
                $sendTime = date('Y-m-d H:i', $coupon['send_time']);
                $useTime = $coupon['use_time'] > 0 ? date('Y-m-d H:i', $coupon['send_time']) : '未使用';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $coupon['name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . C('COUPON_TYPE')[$coupon['type']] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px; vnd.ms-excel.numberformat:@;">' . $coupon['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $coupon['uid'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $coupon['nickname'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $sendTime . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $useTime . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $coupon['code'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'coupon_list');
        exit();
    }

    /*
     * 删除一张优惠券
     */
    public function coupon_list_del()
    {
        //获取优惠券ID
        $cid = I('get.id');
        if (!$cid) {
            $this->error('缺少参数值');
        }
        //查询是否存在优惠券
        $row = M('coupon_list')->where(['id' => $cid])->delete();
        if (!$row) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }
}
