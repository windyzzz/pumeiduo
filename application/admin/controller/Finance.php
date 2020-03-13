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

use think\AjaxPage;
use think\Page;

class Finance extends Base
{
    // 系统年度结算列表
    public function commissionLogYear()
    {
        return $this->fetch();
    }

    // 系统月度结算列表
    public function commissionLogMonth()
    {
        $begin = date('Y-m', strtotime('-12 month')); //30天前
        $end = date('Y-m', strtotime('+1 days'));
        $this->assign('start_time', $begin);
        $this->assign('end_time', $end);

        $this->begin = strtotime($begin);
        $this->end = strtotime($end . '+1 month');

        return $this->fetch();
    }

    // 系统日度结算列表
    public function commissionLog()
    {
        return $this->fetch();
    }

    /**
     *  商品列表.
     */
    public function ajaxCommissionList()
    {
        $where = ' 1 = 1 '; // 搜索条件

        $begin = $this->begin;
        $end = $this->end;

        if ($begin && $end) {
            $where = "$where and create_time between $begin AND $end";
            // $condition['created_at'] = array('between',"$begin,$end");
        }

        ('' !== I('status')) && $where = "$where and status = " . I('status');
        ('' !== I('level')) && $where = "$where and level = " . I('level');

//        $cat_id = I('cat_id');

        $type = I('type');
        if ($type) {
            $where = "$where and type = '$type'";
        } else {
            $type = 'd';
            $where = "$where and type = 'd'";
        }

        $order_sn = I('order_sn') ? trim(I('order_sn')) : '';
        if ($order_sn) {
            $where = "$where and (order_sn = '$order_sn')";
        }

        $user_id = I('user_id') ? trim(I('user_id')) : '';
        if ($user_id) {
            $where = "$where and (user_id = '$user_id')";
        }
//        // 搜索条件下 分页赋值
//        foreach ($condition as $key => $val) {
//            $Page->parameter[$key] = urlencode($val);
//        }
        $count = M('CommissionLog')->where($where)->count();
        $Page = new AjaxPage($count, 20);

        $show = $Page->show();

        $list = M('CommissionLog')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('id desc')->select();
        foreach ($list as $k => $v) {
            switch ($type) {
                case 'd':
                    $startDate = $v['create_time'];
                    $endDate = strtotime(date('Y-m-d 23:59:59', $v['create_time']));
                    break;
                case 'm':
                    $endDate = $v['create_time'];
                    $startDate = strtotime(date('Y-m-t 23:59:59', strtotime(date('Y-m-01 H:i:s', $endDate) . ' -1 month')));
                    break;
                case 'y':
                    $endDate = $v['create_time'];
                    $startDate = strtotime(date('Y-12-31 23:59:59', strtotime(date('Y-m-d H:i:s', $endDate) . ' -1 year')));
                    break;
            }
            // VIP套组分享奖励金额
            $vipProfit = M('account_log')->where([
                'user_money' => ['>', 0],
                'change_time' => ['BETWEEN', [$startDate, $endDate]],
                'type' => 14
            ])->sum('user_money');

            $list[$k]['total_amount'] = bcadd($list[$k]['total_amount'], $vipProfit, 2);
            $list[$k]['real_amount'] = bcadd($list[$k]['real_amount'], $vipProfit, 2);
            $list[$k]['sale_free'] = bcadd($list[$k]['sale_free'], $vipProfit, 2);
        }

        $this->assign('list', $list);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    public function export_commission_log()
    {
        $where = ' 1 = 1 '; // 搜索条件

        $begin = $this->begin;
        $end = $this->end;

        if ($begin && $end) {
            $where = "$where and create_time between $begin AND $end";
            // $condition['created_at'] = array('between',"$begin,$end");
        }

        ('' !== I('status')) && $where = "$where and status = " . I('status');
        ('' !== I('level')) && $where = "$where and level = " . I('level');

//        $cat_id = I('cat_id');

        $type = I('type');
        if ($type) {
            $where = "$where and type = '$type'";
        } else {
            $where = "$where and type = 'd'";
        }

        $order_sn = I('order_sn') ? trim(I('order_sn')) : '';
        if ($order_sn) {
            $where = "$where and (order_sn = '$order_sn')";
        }

        $user_id = I('user_id') ? trim(I('user_id')) : '';
        if ($user_id) {
            $where = "$where and (user_id = '$user_id')";
        }

        $ids = I('ids');

        if ($ids) {
            $where = "$where and id IN ($ids)";
        }
        // dump($where);
        // exit;
        $commission_list = M('CommissionLog')->where($where)->order('id desc')->select();

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">创建日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">应发总金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">实发总金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">发放状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单总数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获佣金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">店铺金额</td>';
        $strTable .= '</tr>';
        if (is_array($commission_list)) {
            foreach ($commission_list as $k => $val) {
                $finished_at = null;
                $status = '未领取';
                if (1 == $val['status']) {
                    $status = '已领取';
                    $finished_at = $val['finished_at'];
                }
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px">' . exchangeDate($val['create_time']) . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['real_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . commission_type($val['status']) . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['sale_free'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['shop_free'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($commission_list);
        downloadExcel($strTable, 'commission_list');
        exit();
    }

    public function account_list()
    {
        $model = M('account_log');

        $map = [];
        $having = '';

        $begin = $this->begin;
        $end = $this->end;

        $status = I('status');
        $type = I('type', 'sss');
        // dump($type);
        // exit;
        if ('sss' !== $type) {
            $type_relation = C('ACCOUNT_TYPE_RELATION');
            if (isset($type_relation[$type])) {
                $map['a.type'] = ['in', $type_relation[$type]];
            } else {
                $map['a.type'] = $type;
            }
        }

        $user_id = I('user_id');
        if (1 == $status) {
            $having = 'total_count > 0';
        }
        if (-1 == $status) {
            $having = 'total_count < 0';
        }
        $goods_name = I('goods_name');
        if ($goods_name) {
            $map['goods_name'] = ['like', "%$goods_name%"];
        }

        if ($begin && $end) {
            $map['change_time'] = ['between', "$begin,$end"];
        }

        if ($user_id) {
            $map['a.user_id'] = $user_id;
        }

        $ids = I('ids');

        if ($ids) {
            $map['log_id'] = ['in', $ids];
        }
        // $ctime = urldecode(I('ctime'));
        // if($ctime){
        //     $gap = explode(' - ', $ctime);
        //     $this->assign('start_time',$gap[0]);
        //     $this->assign('end_time',$gap[1]);
        //     $this->assign('ctime',$gap[0].' - '.$gap[1]);
        //     $map['ctime'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
        // }

        $count = $model->alias('a')->where($map)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('pager', $Page);
        $this->assign('page', $show); // 赋值分页输出
        $account_list = $model
            ->field('a.*,u.user_name,a.pay_points+a.user_electronic+a.user_money as total_count')
            ->alias('a')
            ->join('__USERS__ u', 'a.user_id = u.user_id', 'left')
            ->where($map)->order('log_id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->having($having)
            ->select();

        $is_export = I('is_export');
        if (1 == $is_export) {
            $account_list = $model
                ->field('a.*,u.user_name,a.pay_points+a.user_electronic+a.user_money as total_count')
                ->alias('a')
                ->join('__USERS__ u', 'a.user_id = u.user_id', 'left')
                ->where($map)->order('log_id desc')
                ->having($having)
                ->select();
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员ID</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">描述</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">奖金</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">电子币</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单编号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">变动时间</td>';
            $strTable .= '</tr>';
            if (is_array($account_list)) {
                foreach ($account_list as $k => $val) {
                    $finished_at = date('Y-m-d H:i:s', $val['change_time']);

                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px">&nbsp;' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['desc'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_electronic'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $finished_at . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($account_list);
            downloadExcel($strTable, 'account_list');
            exit();
        }

        //转入
        $where0 = [
            'user_money|user_electronic|pay_points' => ['gt', 0],
        ];
        $where1 = [
            'user_money|user_electronic|pay_points' => ['lt', 0],
        ];
        $up_data = $model
            ->alias('a')
            ->field('SUM(user_money) as user_money,SUM(user_electronic) as user_electronic,SUM(pay_points) as pay_points, SUM(user_money)+SUM(user_electronic)+SUM(pay_points) as total')
            ->where($where0)
            ->where($map)
            ->find();

        $down_data = $model
            ->alias('a')
            ->field('SUM(user_money) as user_money,SUM(user_electronic) as user_electronic,SUM(pay_points) as pay_points, SUM(user_money)+SUM(user_electronic)+SUM(pay_points) as total')
            ->where($where1)
            ->where($map)
            ->find();

        // dump($type);
        // exit;
        $this->assign('status', $status);
        $this->assign('type', $type);
        $this->assign('account_list', $account_list);
        $this->assign('account_type', C('ACCOUNT_TYPE'));
        $this->assign('up_data', $up_data);
        $this->assign('down_data', $down_data);

        return $this->fetch();
    }
}
