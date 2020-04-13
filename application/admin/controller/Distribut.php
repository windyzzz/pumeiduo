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
}
