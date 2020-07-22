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

class Distribut extends Base
{
    protected $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new \app\admin\logic\DistributLogic();
    }

    function exportRebateList()
    {

        $begin = I('start_time');
        $end = I('end_time');

        $begin = strtotime($begin);
        $end = strtotime($end) + 24 * 3600;

        $where = ' 1 = 1 '; // 搜索条件

        ('' !== I('type')) && $where = "$where and type = " . I('type');
        ('' !== I('status')) && $where = "$where and status = " . I('status');
        ('' !== I('level')) && $where = "$where and level = " . I('level');

//        $cat_id = I('cat_id');

        $order_sn = I('order_sn') ? trim(I('order_sn')) : '';
        if ($order_sn) {
            $where = "$where and (order_sn = '$order_sn')";
        }
        $condition = [];
        if ($begin && $end) {
            $condition['confirm'] = ['between', "$begin,$end"];
        }

        $user_id = I('user_id') ? trim(I('user_id')) : '';
        if ($user_id) {
            $where = "$where and (user_id = '$user_id')";
        }

        $list = M('rebate_log')->where($where)->where($condition)->order('id desc')->select();
        foreach ($list as $k => $v) {
            $list[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $list[$k]['confirm'] = $v['confirm'] ? date('Y-m-d H:i:s', $v['confirm']) : '';
            $list[$k]['status'] = rebate_status($v['status']);
            $list[$k]['type'] = rebate_type($v['type']);
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">购买人昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:100px;">订单id</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品总价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获佣用户</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获佣金额</td>';

        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获佣积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获佣代数</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">生成时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">确定收货时间</td>';

        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">备注</td>';

        $strTable .= '</tr>';
        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['order_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_price'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['money'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['point'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['level'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['create_time'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['confirm'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['status'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['type'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['remark'] . '</td>';

                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($list);
        downloadExcel($strTable, 'report');
        exit();
    }

    public function rebate_log()
    {
        $begin = I('start_time');
        $end = I('end_time');
        $this->assign('begin', $begin);
        $this->assign('end', $end);

        return $this->fetch('rebateList');
    }

    /**
     *  商品列表.
     */
    public function ajaxRebateList()
    {
        $begin = I('start_time');
        $end = I('end_time');

        $begin = strtotime($begin);
        $end = strtotime($end) + 24 * 3600;

        $where = ' 1 = 1 '; // 搜索条件

        ('' !== I('type')) && $where = "$where and type = " . I('type');
        ('' !== I('status')) && $where = "$where and status = " . I('status');
        ('' !== I('level')) && $where = "$where and level = " . I('level');

//        $cat_id = I('cat_id');

        $order_sn = I('order_sn') ? trim(I('order_sn')) : '';
        if ($order_sn) {
            $where = "$where and (order_sn = '$order_sn')";
        }
        if ($begin && $end) {
            $condition['confirm'] = ['between', "$begin,$end"];
        }

        $user_id = I('user_id') ? trim(I('user_id')) : '';
        if ($user_id) {
            $where = "$where and (user_id = '$user_id')";
        }
        /**  搜索条件下 分页赋值
         * foreach($condition as $key=>$val) {
         * $Page->parameter[$key]   =   urlencode($val);
         * }
         */
        $count = M('rebate_log')->where($where)->where($condition)->count();
        $Page = new AjaxPage($count, 20);
        // $Page  = new AjaxPage($count,20);

        $show = $Page->show();

        $list = M('rebate_log')->where($where)->where($condition)->limit($Page->firstRow . ',' . $Page->listRows)->order('id desc')->select();

        // dump(M('rebate_log')->where($where)->where($condition)->limit($Page->firstRow.','.$Page->listRows)->order('id desc')->fetchSql(1)->select());

        $this->assign('list', $list);
        $this->assign('page', $show); // 赋值分页输出
        return $this->fetch();
    }

    /**
     * 会员VIP升级记录
     * @return mixed
     */
    public function distributeLog()
    {
        $group_list = [
            'daily_log' => '日度记录',
            'monthly_log' => '月度记录',
            'yearly_log' => '年度记录',
        ];
        $this->assign('group_list', $group_list);
        $inc_type = I('get.inc_type', '');
        if (!$inc_type) {
            foreach ($group_list as $key => $item) {
                $inc_type = $key;
                break;
            }
        }
        $this->assign('inc_type', $inc_type);
        return $this->fetch($inc_type);
    }

    public function ajaxDistributeLog()
    {
        $type = I('type', 'daily');
        $startTime = strtotime(I('start_time'));
        $endTime = strtotime(I('end_time'));
        $newLevel = I('new_level', 2);
        $where = [
            'type' => ['IN', [1, 3]],
            'new_level' => $newLevel
        ];
        switch ($type) {
            case 'daily':
                $startTime = strtotime(date('Y-m-d 00:00:00', $startTime));
                $endTime = strtotime(date('Y-m-d 23:59:59', $endTime));
                break;
            case 'monthly':
                $startTime = strtotime(date('Y-m-01 00:00:00', $startTime));
                $endTime = strtotime(date('Y-m-t 23:59:59', $endTime));
                break;
            case 'yearly':
                $startTime = strtotime(date('Y-01-01 00:00:00', $startTime));
                $endTime = strtotime(date('Y-12-31 23:59:59', $endTime));
                break;
        }
        $distributeLog = M('distribut_log')->where($where)->group('user_id')->order('add_time DESC')->field('order_sn, type, add_time')->select();
        $list = [];
        switch ($type) {
            case 'daily':
                $days = diffDate($startTime, $endTime)['a'];
                for ($i = 0; $i <= $days; $i++) {
                    $key = date('Y-m-d', strtotime('-' . $i . 'day', $endTime));
                    $list[$key] = [
                        'date' => $key,
                        'vip_order_num' => 0,
                        'vip_money_num' => 0,
                    ];
                    foreach ($distributeLog as $log) {
                        if ($key == date('Y-m-d', $log['add_time'])) {
                            if ($log['type'] == 1) {
                                // VIP套组升级数
                                // 查询订单状态
                                if (M('order')->where(['order_sn' => $log['order_sn'], 'order_status' => ['NOT IN', [3, 5, 6]], 'pay_status' => 1])->value('order_id')) {
                                    $list[$key]['vip_order_num'] += 1;
                                    $list[$key]['vip_money_num'] += 0;
                                }
                            } else {
                                // VIP累计升级数
                                $list[$key]['vip_order_num'] += 0;
                                $list[$key]['vip_money_num'] += 1;
                            }
                        }
                    }
                }
                break;
            case 'monthly':
                $months = diffDate($startTime, $endTime)['m'];
                for ($i = 0; $i <= $months; $i++) {
                    $key = date('Y-m', strtotime('-' . $i . 'month', strtotime(date('Y-m', $endTime))));
                    $list[$key] = [
                        'date' => $key,
                        'vip_order_num' => 0,
                        'vip_money_num' => 0,
                    ];
                    foreach ($distributeLog as $log) {
                        if ($key == date('Y-m', $log['add_time'])) {
                            if ($log['type'] == 1) {
                                // VIP套组升级数
                                // 查询订单状态
                                if (M('order')->where(['order_sn' => $log['order_sn'], 'order_status' => ['NOT IN', [3, 5, 6]], 'pay_status' => 1])->value('order_id')) {
                                    $list[$key]['vip_order_num'] += 1;
                                    $list[$key]['vip_money_num'] += 0;
                                }
                            } else {
                                // VIP累计升级数
                                $list[$key]['vip_order_num'] += 0;
                                $list[$key]['vip_money_num'] += 1;
                            }
                        }
                    }
                }
                break;
            case 'yearly':
                $years = diffDate($startTime, $endTime)['y'];
                for ($i = 0; $i <= $years; $i++) {
                    $key = date('Y', strtotime('-' . $i . 'year', $endTime));
                    $list[$key] = [
                        'date' => $key,
                        'vip_order_num' => 0,
                        'vip_money_num' => 0,
                    ];
                    foreach ($distributeLog as $log) {
                        if ($key == date('Y', $log['add_time'])) {
                            if ($log['type'] == 1) {
                                // VIP套组升级数
                                // 查询订单状态
                                if (M('order')->where(['order_sn' => $log['order_sn'], 'order_status' => ['NOT IN', [3, 5, 6]], 'pay_status' => 1])->value('order_id')) {
                                    $list[$key]['vip_order_num'] += 1;
                                    $list[$key]['vip_money_num'] += 0;
                                }
                            } else {
                                // VIP累计升级数
                                $list[$key]['vip_order_num'] += 0;
                                $list[$key]['vip_money_num'] += 1;
                            }
                        }
                    }
                }
                break;
        }
        $this->assign('list', $list);
        return $this->fetch();
    }
}
