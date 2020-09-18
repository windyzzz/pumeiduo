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

use app\admin\logic\GoodsLogic;
use app\admin\model\Order;
use think\Db;
use think\Page;

class Report extends Base
{
    public $begin;
    public $end;

    public function _initialize()
    {
        parent::_initialize();
        $start_time = I('start_time');
        if (I('start_time')) {
            $begin = urldecode($start_time);
            $end_time = I('end_time');
            $end = urldecode($end_time);
        } else {
            // $begin = date('Y-m-d', strtotime("-1 month"));//30天前
            $begin = '2018-08-15';
            $end = date('Y-m-d', strtotime('+1 days'));
        }
        $this->assign('start_time', $begin);
        $this->assign('end_time', $end);
        $this->begin = strtotime($begin);
        $this->end = strtotime($end) + 86399;
    }

    public function index()
    {
        $now = strtotime(date('Y-m-d'));
        $today['today_amount'] = M('order')->where('parent_id = 0')->where("add_time>$now AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4)")->sum('total_amount'); //今日销售总额
        $today['today_order'] = M('order')->where('parent_id = 0')->where("add_time>$now and (pay_status=1 or pay_code='cod')")->count(); //今日订单数
        $today['cancel_order'] = M('order')->where('parent_id = 0')->where("add_time>$now AND order_status=3")->count(); //今日取消订单
        if (0 == $today['today_order']) {
            $today['sign'] = round(0, 2);
        } else {
            $today['sign'] = round($today['today_amount'] / $today['today_order'], 2);
        }
        $this->assign('today', $today);

        $res1 = Db::name('order')
            ->field(" COUNT(*) as tnum,sum(order_amount + user_electronic) as amount, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
            ->where(" order_type in(1,3) AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res1 as $val) {
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
        }
        $res2 = Db::name('order')
            ->field(" COUNT(*) as tnum, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res2 as $val) {
            if (isset($arr[$val['gap']])) {
                $arr[$val['gap']] += $val['tnum'];
            } else {
                $arr[$val['gap']] = $val['tnum'];
            }
        }
        $res3 = Db::name('order o')
            ->join('order_goods og', 'og.order_id = o.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->field(" sum(g.cost_price * og.goods_num) as amount, FROM_UNIXTIME(o.add_time,'%Y-%m-%d') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res3 as $val) {
            if (isset($brr[$val['gap']])) {
                $brr[$val['gap']] += $val['amount'];
            } else {
                $brr[$val['gap']] = $val['amount'];
            }
            $crr[$val['gap']] = $val['amount'];
        }
        for ($i = $this->begin; $i <= $this->end; $i = $i + 24 * 3600) {
            $tmp_num = empty($arr[date('Y-m-d', $i)]) ? 0 : $arr[date('Y-m-d', $i)];
            $tmp_amount = empty($brr[date('Y-m-d', $i)]) ? 0 : $brr[date('Y-m-d', $i)];
            $tmp_abroad_amount = empty($crr[date('Y-m-d', $i)]) ? 0 : $crr[date('Y-m-d', $i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount / $tmp_num, 2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $date = date('Y-m-d', $i);
            $j = $i + 24 * 3600;
            //销售不含税价
            $tmp_c_amout = 0;
            $result = Db::name('order')
                ->alias('oi')
                ->join('order_goods og', 'og.order_id = oi.order_id', 'left')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where("oi.add_time >$i AND oi.add_time < $j AND oi.pay_status=1 AND oi.order_status in(1,2,4) ")
                ->field('oi.order_id, og.rec_id, og.goods_price, og.member_goods_price, og.use_integral, og.re_id, og.goods_num, g.goods_id, g.goods_name, g.ctax_price, g.stax_price, g.zone')
                ->select();
            $vip_order_num = 0;
            if ($result) {
                foreach ($result as $k => $v) {
                    if ($v['re_id'] == 0) {
                        if ($v['member_goods_price'] > 0 || $v['use_integral'] > 0) {
                            if ($v['use_integral'] > 0) {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['ctax_price'], (bcadd($v['member_goods_price'], $v['use_integral'], 2) / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            } else {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['stax_price'], ($v['member_goods_price'] / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            }
                        }
                    }
                    if ($v['zone'] == 3) {
                        $vip_order_num++;
                    }
                }
            }
            $list[] = ['day' => $date, 'order_num' => $tmp_num, 'amount' => $tmp_amount, 'abroad_amount' => $tmp_abroad_amount, 'sign' => $tmp_sign, 'end' => date('Y-m-d', $i + 24 * 60 * 60), 'c_amount' => $tmp_c_amout, 'vip_order_num' => $vip_order_num];
            $day[] = $date;
        }
        rsort($list);
        $this->assign('list', $list);
        $result = ['order' => $order_arr, 'amount' => $amount_arr, 'sign' => $sign_arr, 'time' => $day];
        $this->assign('result', json_encode($result));

        return $this->fetch();
    }

    public function exportIndex()
    {
        $res1 = Db::name('order')
            ->field(" COUNT(*) as tnum,sum(order_amount + user_electronic) as amount, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
            ->where(" order_type in(1,3) AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res1 as $val) {
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
        }
        $res2 = Db::name('order')
            ->field(" COUNT(*) as tnum, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res2 as $val) {
            if (isset($arr[$val['gap']])) {
                $arr[$val['gap']] += $val['tnum'];
            } else {
                $arr[$val['gap']] = $val['tnum'];
            }
        }
        $res3 = Db::name('order o')
            ->join('order_goods og', 'og.order_id = o.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->field(" sum(g.cost_price * og.goods_num) as amount, FROM_UNIXTIME(o.add_time,'%Y-%m-%d') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res3 as $val) {
            if (isset($brr[$val['gap']])) {
                $brr[$val['gap']] += $val['amount'];
            } else {
                $brr[$val['gap']] = $val['amount'];
            }
            $crr[$val['gap']] = $val['amount'];
        }
        for ($i = $this->begin; $i <= $this->end; $i = $i + 24 * 3600) {
            $tmp_num = empty($arr[date('Y-m-d', $i)]) ? 0 : $arr[date('Y-m-d', $i)];
            $tmp_amount = empty($brr[date('Y-m-d', $i)]) ? 0 : $brr[date('Y-m-d', $i)];
            $tmp_abroad_amount = empty($crr[date('Y-m-d', $i)]) ? 0 : $crr[date('Y-m-d', $i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount / $tmp_num, 2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $date = date('Y-m-d', $i);
            $j = $i + 24 * 3600;
            //销售不含税价
            $tmp_c_amout = 0;
            $result = Db::name('order')
                ->alias('oi')
                ->join('order_goods og', 'og.order_id = oi.order_id', 'left')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where("oi.add_time >$i AND oi.add_time < $j AND oi.pay_status=1 AND oi.order_status in(1,2,4) ")
                ->field('oi.order_id, og.rec_id, og.goods_price, og.member_goods_price, og.use_integral, og.re_id, og.goods_num, g.goods_id, g.goods_name, g.ctax_price, g.stax_price, g.zone')
                ->select();
            $vip_order_num = 0;
            if ($result) {
                foreach ($result as $k => $v) {
                    if ($v['re_id'] == 0) {
                        if ($v['member_goods_price'] > 0 || $v['use_integral'] > 0) {
                            if ($v['use_integral'] > 0) {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['ctax_price'], (bcadd($v['member_goods_price'], $v['use_integral'], 2) / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            } else {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['stax_price'], ($v['member_goods_price'] / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            }
                        }
                    }
                    if ($v['zone'] == 3) {
                        $vip_order_num++;
                    }
                }
            }
            $list[] = ['day' => $date, 'order_num' => $tmp_num, 'amount' => $tmp_amount, 'abroad_amount' => $tmp_abroad_amount, 'sign' => $tmp_sign, 'end' => date('Y-m-d', $i + 24 * 60 * 60), 'c_amount' => $tmp_c_amout, 'vip_order_num' => $vip_order_num];
            $day[] = $date;
        }
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:left;font-size:12px;width:120px;">时间</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="100">VIP订单数</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="100">订单数</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">销售总额</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">韩国购销售总额（成本价）</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">销售不含税价</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">客单价</td>';
        $strTable .= '</tr>';
        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['day'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['vip_order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['abroad_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['c_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['sign'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($list);
        downloadExcel($strTable, 'report');
        exit();
    }

    public function indexMonth()
    {
        if (I('start_time')) {
            $this->begin = strtotime(date('Y-m', $this->begin));
            $year = date('Y', $this->end);
            $m = date('m', $this->end);
            $day_num = date('t', strtotime("$year-$m"));
            $day_num = $day_num - 1;
            $this->end = $this->end + $day_num * 24 * 3600;
            $end = strtotime(I('end_time'));
        } else {
            $this->begin = strtotime(date('Y-m', $this->begin));
            $this->end = strtotime('+1 month');
            $end = $this->end;
        }

        $now = strtotime(date('Y-m-d'));
        $today['today_amount'] = M('order')->where('parent_id = 0')->where("add_time>$now AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4)")->sum('total_amount'); //今日销售总额
        $today['today_order'] = M('order')->where('parent_id = 0')->where("add_time>$now and (pay_status=1 or pay_code='cod')")->count(); //今日订单数
        $today['cancel_order'] = M('order')->where('parent_id = 0')->where("add_time>$now AND order_status=3")->count(); //今日取消订单
        if (0 == $today['today_order']) {
            $today['sign'] = round(0, 2);
        } else {
            $today['sign'] = round($today['today_amount'] / $today['today_order'], 2);
        }
        $this->assign('today', $today);

        $res1 = Db::name('order')
            ->field(" COUNT(*) as tnum,sum(order_amount + user_electronic) as amount, FROM_UNIXTIME(add_time,'%Y-%m') as gap ")
            ->where(" order_type in(1,3) AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res1 as $val) {
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
        }
        $res2 = Db::name('order')
            ->field(" COUNT(*) as tnum, FROM_UNIXTIME(add_time,'%Y-%m') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res2 as $val) {
            if (isset($arr[$val['gap']])) {
                $arr[$val['gap']] += $val['tnum'];
            } else {
                $arr[$val['gap']] = $val['tnum'];
            }
        }
        $res3 = Db::name('order o')
            ->join('order_goods og', 'og.order_id = o.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->field(" sum(g.cost_price * og.goods_num) as amount, FROM_UNIXTIME(o.add_time,'%Y-%m') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res3 as $val) {
            if (isset($brr[$val['gap']])) {
                $brr[$val['gap']] += $val['amount'];
            } else {
                $brr[$val['gap']] = $val['amount'];
            }
            $crr[$val['gap']] = $val['amount'];
        }
        for ($i = $this->begin; $i <= $this->end;) {
            $year = date('Y', $i);
            $m = date('m', $i);
            $day_num = date('t', strtotime("$year-$m"));
            $tmp_num = empty($arr[date('Y-m', $i)]) ? 0 : $arr[date('Y-m', $i)];
            $tmp_amount = empty($brr[date('Y-m', $i)]) ? 0 : $brr[date('Y-m', $i)];
            $tmp_abroad_amount = empty($crr[date('Y-m', $i)]) ? 0 : $crr[date('Y-m', $i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount / $tmp_num, 2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $date = date('Y-m', $i);
            $j = $i + $day_num * 24 * 3600;
            //销售不含税价
            $tmp_c_amout = 0;
            $result = Db::name('order')
                ->alias('oi')
                ->join('order_goods og', 'og.order_id = oi.order_id', 'left')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where("oi.add_time >$i AND oi.add_time < $j AND oi.pay_status=1 AND oi.order_status in(1,2,4) ")
                ->field('oi.order_id, og.rec_id, og.goods_price, og.member_goods_price, og.use_integral, og.re_id, og.goods_num, g.goods_id, g.goods_name, g.ctax_price, g.stax_price, g.zone')
                ->select();
            $vip_order_num = 0;
            if ($result) {
                foreach ($result as $k => $v) {
                    if ($v['re_id'] == 0) {
                        if ($v['member_goods_price'] > 0 || $v['use_integral'] > 0) {
                            if ($v['use_integral'] > 0) {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['ctax_price'], (bcadd($v['member_goods_price'], $v['use_integral'], 2) / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            } else {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['stax_price'], ($v['member_goods_price'] / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            }
                        }
                    }
                    if ($v['zone'] == 3) {
                        $vip_order_num++;
                    }
                }
            }
            $list[] = ['day' => $date . '-01', 'order_num' => $tmp_num, 'amount' => $tmp_amount, 'abroad_amount' => $tmp_abroad_amount, 'sign' => $tmp_sign, 'end' => date('Y-m-d', $i + $day_num * 24 * 60 * 60), 'c_amount' => $tmp_c_amout, 'vip_order_num' => $vip_order_num];
            $day[] = $date;
            $i = $i + $day_num * 24 * 3600;
        }

        rsort($list);

        $begin = date('Y-m', $this->begin);
        $end = date('Y-m', $end);

        $this->assign('start_time', $begin);
        $this->assign('end_time', $end);
        $this->assign('list', $list);
        $result = ['order' => $order_arr, 'amount' => $amount_arr, 'sign' => $sign_arr, 'time' => $day];
        $this->assign('result', json_encode($result));

        return $this->fetch();
    }

    public function exportIndexMonth()
    {
        if (I('start_time')) {
            $this->begin = strtotime(date('Y-m', $this->begin));
            $year = date('Y', $this->end);
            $m = date('m', $this->end);
            $day_num = date('t', strtotime("$year-$m"));
            $day_num = $day_num - 1;
            $this->end = $this->end + $day_num * 24 * 3600;
        } else {
            $this->begin = strtotime(date('Y-m', $this->begin));
            $this->end = strtotime('+1 month');
        }

        $res1 = Db::name('order')
            ->field(" COUNT(*) as tnum,sum(order_amount + user_electronic) as amount, FROM_UNIXTIME(add_time,'%Y-%m') as gap ")
            ->where(" order_type in(1,3) AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res1 as $val) {
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
        }
        $res2 = Db::name('order')
            ->field(" COUNT(*) as tnum, FROM_UNIXTIME(add_time,'%Y-%m') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res2 as $val) {
            if (isset($arr[$val['gap']])) {
                $arr[$val['gap']] += $val['tnum'];
            } else {
                $arr[$val['gap']] = $val['tnum'];
            }
        }
        $res3 = Db::name('order o')
            ->join('order_goods og', 'og.order_id = o.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->field(" sum(g.cost_price * og.goods_num) as amount, FROM_UNIXTIME(o.add_time,'%Y-%m') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res3 as $val) {
            if (isset($brr[$val['gap']])) {
                $brr[$val['gap']] += $val['amount'];
            } else {
                $brr[$val['gap']] = $val['amount'];
            }
            $crr[$val['gap']] = $val['amount'];
        }
        for ($i = $this->begin; $i <= $this->end;) {
            $year = date('Y', $i);
            $m = date('m', $i);
            $day_num = date('t', strtotime("$year-$m"));
            $tmp_num = empty($arr[date('Y-m', $i)]) ? 0 : $arr[date('Y-m', $i)];
            $tmp_amount = empty($brr[date('Y-m', $i)]) ? 0 : $brr[date('Y-m', $i)];
            $tmp_abroad_amount = empty($crr[date('Y-m', $i)]) ? 0 : $crr[date('Y-m', $i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount / $tmp_num, 2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $date = date('Y-m', $i);
            $j = $i + $day_num * 24 * 3600;
            //销售不含税价
            $tmp_c_amout = 0;
            $result = Db::name('order')
                ->alias('oi')
                ->join('order_goods og', 'og.order_id = oi.order_id', 'left')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where("oi.add_time >$i AND oi.add_time < $j AND oi.pay_status=1 AND oi.order_status in(1,2,4) ")
                ->field('oi.order_id, og.rec_id, og.goods_price, og.member_goods_price, og.use_integral, og.re_id, og.goods_num, g.goods_id, g.goods_name, g.ctax_price, g.stax_price, g.zone')
                ->select();
            $vip_order_num = 0;
            if ($result) {
                foreach ($result as $k => $v) {
                    if ($v['re_id'] == 0) {
                        if ($v['member_goods_price'] > 0 || $v['use_integral'] > 0) {
                            if ($v['use_integral'] > 0) {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['ctax_price'], (bcadd($v['member_goods_price'], $v['use_integral'], 2) / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            } else {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['stax_price'], ($v['member_goods_price'] / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            }
                        }
                    }
                    if ($v['zone'] == 3) {
                        $vip_order_num++;
                    }
                }
            }
            $list[] = ['day' => $date, 'order_num' => $tmp_num, 'amount' => $tmp_amount, 'abroad_amount' => $tmp_abroad_amount, 'sign' => $tmp_sign, 'end' => date('Y-m-d', $i + $day_num * 24 * 60 * 60), 'c_amount' => $tmp_c_amout, 'vip_order_num' => $vip_order_num];
            $day[] = $date;
            $i = $i + $day_num * 24 * 3600;
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:left;font-size:12px;width:120px;">时间</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="100">VIP订单数</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="100">订单数</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">销售总额</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">韩国购销售总额（成本价）</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">销售不含税价</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">客单价</td>';
        $strTable .= '</tr>';
        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['day'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['vip_order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['abroad_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['c_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['sign'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($list);
        downloadExcel($strTable, 'report');
        exit();
    }

    public function indexYear()
    {
        if (I('start_time')) {
            $this->begin = strtotime(date('Y-01-01', $this->begin));
            $this->end = $this->begin;
            $end = strtotime(I('end_time'));
            $year = date('Y', $this->end);
            $m = 12;
            $day_num = date('t', strtotime("$year-$m"));
            $this->end = strtotime("{$year}-12-{$day_num} 23:59:59");
        } else {
            $this->begin = strtotime(date('Y-01-01', $this->begin));
            $this->end = strtotime('+1 year');
            $this->end = strtotime(date('Y-01-01', $this->end));
            $end = $this->end;
        }

        $now = strtotime(date('Y-m-d'));
        $today['today_amount'] = M('order')->where('parent_id = 0')->where("add_time>$now AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4)")->sum('total_amount'); //今日销售总额
        $today['today_order'] = M('order')->where('parent_id = 0')->where("add_time>$now and (pay_status=1 or pay_code='cod')")->count(); //今日订单数
        $today['cancel_order'] = M('order')->where('parent_id = 0')->where("add_time>$now AND order_status=3")->count(); //今日取消订单
        if (0 == $today['today_order']) {
            $today['sign'] = round(0, 2);
        } else {
            $today['sign'] = round($today['today_amount'] / $today['today_order'], 2);
        }
        $this->assign('today', $today);

        $res1 = Db::name('order')
            ->field(" COUNT(*) as tnum,sum(order_amount + user_electronic) as amount, FROM_UNIXTIME(add_time,'%Y-01-01') as gap ")
            ->where(" order_type in(1,3) AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res1 as $val) {
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
        }
        $res2 = Db::name('order')
            ->field(" COUNT(*) as tnum, FROM_UNIXTIME(add_time,'%Y-01-01') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res2 as $val) {
            if (isset($arr[$val['gap']])) {
                $arr[$val['gap']] += $val['tnum'];
            } else {
                $arr[$val['gap']] = $val['tnum'];
            }
        }
        $res3 = Db::name('order o')
            ->join('order_goods og', 'og.order_id = o.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->field(" sum(g.cost_price * og.goods_num) as amount, FROM_UNIXTIME(o.add_time,'%Y-01-01') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res3 as $val) {
            if (isset($brr[$val['gap']])) {
                $brr[$val['gap']] += $val['amount'];
            } else {
                $brr[$val['gap']] = $val['amount'];
            }
            $crr[$val['gap']] = $val['amount'];
        }
        for ($i = $this->begin; $i <= $this->end;) {
            $tmp_num = empty($arr[date('Y-01-01', $i)]) ? 0 : $arr[date('Y-01-01', $i)];
            $tmp_amount = empty($brr[date('Y-01-01', $i)]) ? 0 : $brr[date('Y-01-01', $i)];
            $tmp_abroad_amount = empty($crr[date('Y-01-01', $i)]) ? 0 : $crr[date('Y-01-01', $i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount / $tmp_num, 2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $date = date('Y', $i);
            $j = $i + 365 * 24 * 3600;
            //销售不含税价
            $tmp_c_amout = 0;
            $result = Db::name('order')
                ->alias('oi')
                ->join('order_goods og', 'og.order_id = oi.order_id', 'left')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where("oi.add_time >$i AND oi.add_time < $j AND oi.pay_status=1 AND oi.order_status in(1,2,4) ")
                ->field('oi.order_id, og.rec_id, og.goods_price, og.member_goods_price, og.use_integral, og.re_id, og.goods_num, g.goods_id, g.goods_name, g.ctax_price, g.stax_price, g.zone')
                ->select();
            $vip_order_num = 0;
            if ($result) {
                foreach ($result as $k => $v) {
                    if ($v['re_id'] == 0) {
                        if ($v['member_goods_price'] > 0 || $v['use_integral'] > 0) {
                            if ($v['use_integral'] > 0) {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['ctax_price'], (bcadd($v['member_goods_price'], $v['use_integral'], 2) / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            } else {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['stax_price'], ($v['member_goods_price'] / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            }
                        }
                    }
                    if ($v['zone'] == 3) {
                        $vip_order_num++;
                    }
                }
            }
            $list[] = ['day' => $date . '-01-01', 'order_num' => $tmp_num, 'amount' => $tmp_amount, 'abroad_amount' => $tmp_abroad_amount, 'sign' => $tmp_sign, 'end' => date('Y-m-d', $i + 365 * 24 * 60 * 60), 'c_amount' => $tmp_c_amout, 'vip_order_num' => $vip_order_num];
            $day[] = $date;

            $i = $i + 365 * 24 * 3600;
        }
        rsort($list);
        $begin = date('Y', $this->begin);
        $end = date('Y', $end);

        $this->assign('start_time', $begin);
        $this->assign('end_time', $end);
        $this->assign('list', $list);
        $result = ['order' => $order_arr, 'amount' => $amount_arr, 'sign' => $sign_arr, 'time' => $day];
        $this->assign('result', json_encode($result));

        return $this->fetch();
    }

    public function exportIndexYear()
    {
        if (I('start_time')) {
            $this->begin = strtotime(date('Y-01-01', $this->begin));
            $this->end = $this->begin;
            $year = date('Y', $this->end);
            $m = 12;
            $day_num = date('t', strtotime("$year-$m"));
            $this->end = strtotime("{$year}-12-{$day_num} 23:59:59");
        } else {
            $this->begin = strtotime(date('Y-01-01', $this->begin));
            $this->end = strtotime('+1 year');
            $this->end = strtotime(date('Y-01-01', $this->end));
        }

        $res1 = Db::name('order')
            ->field(" COUNT(*) as tnum,sum(order_amount + user_electronic) as amount, FROM_UNIXTIME(add_time,'%Y-01-01') as gap ")
            ->where(" order_type in(1,3) AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res1 as $val) {
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
        }
        $res2 = Db::name('order')
            ->field(" COUNT(*) as tnum, FROM_UNIXTIME(add_time,'%Y-01-01') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res2 as $val) {
            if (isset($arr[$val['gap']])) {
                $arr[$val['gap']] += $val['tnum'];
            } else {
                $arr[$val['gap']] = $val['tnum'];
            }
        }
        $res3 = Db::name('order o')
            ->join('order_goods og', 'og.order_id = o.order_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->field(" sum(g.cost_price * og.goods_num) as amount, FROM_UNIXTIME(o.add_time,'%Y-01-01') as gap ")
            ->where(" order_type = 2 AND add_time >$this->begin AND add_time < $this->end AND (pay_status=1 OR pay_code='cod') AND order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        foreach ($res3 as $val) {
            if (isset($brr[$val['gap']])) {
                $brr[$val['gap']] += $val['amount'];
            } else {
                $brr[$val['gap']] = $val['amount'];
            }
            $crr[$val['gap']] = $val['amount'];
        }
        for ($i = $this->begin; $i <= $this->end;) {
            $tmp_num = empty($arr[date('Y-01-01', $i)]) ? 0 : $arr[date('Y-01-01', $i)];
            $tmp_amount = empty($brr[date('Y-01-01', $i)]) ? 0 : $brr[date('Y-01-01', $i)];
            $tmp_abroad_amount = empty($crr[date('Y-01-01', $i)]) ? 0 : $crr[date('Y-01-01', $i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount / $tmp_num, 2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $date = date('Y', $i);
            $j = $i + 365 * 24 * 3600;
            //销售不含税价
            $tmp_c_amout = 0;
            $result = Db::name('order')
                ->alias('oi')
                ->join('order_goods og', 'og.order_id = oi.order_id', 'left')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where("oi.add_time >$i AND oi.add_time < $j AND oi.pay_status=1 AND oi.order_status in(1,2,4) ")
                ->field('oi.order_id, og.rec_id, og.goods_price, og.member_goods_price, og.use_integral, og.re_id, og.goods_num, g.goods_id, g.goods_name, g.ctax_price, g.stax_price, g.zone')
                ->select();
            $vip_order_num = 0;
            if ($result) {
                foreach ($result as $k => $v) {
                    if ($v['re_id'] == 0) {
                        if ($v['member_goods_price'] > 0 || $v['use_integral'] > 0) {
                            if ($v['use_integral'] > 0) {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['ctax_price'], (bcadd($v['member_goods_price'], $v['use_integral'], 2) / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            } else {
                                $tmp_c_amout = bcadd($tmp_c_amout, bcmul(bcmul($v['stax_price'], ($v['member_goods_price'] / $v['goods_price']), 2), $v['goods_num'], 2), 2);
                            }
                        }
                    }
                    if ($v['zone'] == 3) {
                        $vip_order_num++;
                    }
                }
            }
            $list[] = ['day' => $date, 'order_num' => $tmp_num, 'amount' => $tmp_amount, 'abroad_amount' => $tmp_abroad_amount, 'sign' => $tmp_sign, 'end' => date('Y', $i + 365 * 24 * 60 * 60), 'c_amount' => $tmp_c_amout, 'vip_order_num' => $vip_order_num];
            $day[] = $date;

            $i = $i + 365 * 24 * 3600;
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:left;font-size:12px;width:120px;">时间</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="100">VIP订单数</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="100">订单数</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">销售总额</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">韩国购销售总额（成本价）</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">销售不含税价</td>';
        $strTable .= '<td style="text-align:left;font-size:12px;" width="*">客单价</td>';
        $strTable .= '</tr>';
        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['day'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['vip_order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['abroad_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['c_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['sign'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($list);
        downloadExcel($strTable, 'report');
        exit();
    }

    /**
     * 销量排行.
     *
     * @return mixed
     */
    public function saleTop()
    {
        $cat_id = I('cat_id', 0);
        $goods_name = I('goods_name');
        $where = 'od.pay_time BETWEEN ' . $this->begin . ' AND ' . $this->end;
        $where .= ' AND og.is_send = 1';
        if (!empty($goods_name)) {
            $where .= " AND og.goods_name LIKE '%" . $goods_name . "%'";
        }
        if ($cat_id > 0) {
            $where .= " AND (g.cat_id=$cat_id OR g.extend_cat_id=$cat_id)";
            $this->assign('cat_id', $cat_id);
        }
        $count = Db::name('order_goods')->alias('og')
            ->field('sum(og.goods_num) as sale_num,sum(og.goods_num*og.goods_price) as sale_amount ')
            ->join('order od', 'og.order_id=od.order_id', 'LEFT')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->where($where)->group('og.goods_id')->count();
        $Page = new Page($count, $this->page_size);
        $res = Db::name('order_goods')->alias('og')
            ->join('order od', 'og.order_id=od.order_id', 'LEFT')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->field('og.goods_name,og.goods_id,og.goods_sn,sum(og.goods_num) as sale_num,sum(og.goods_num*og.goods_price) as sale_amount, g.is_on_sale ')
            ->where($where)->group('og.goods_id')->order('sale_num DESC')
            ->limit($Page->firstRow, $Page->listRows)->cache(true, 3600)->select();

        $is_export = I('is_export');
        if (1 == $is_export) {
            $res = Db::name('order_goods')->alias('og')
                ->join('order od', 'og.order_id=od.order_id', 'LEFT')
                ->join('goods g', 'g.goods_id = og.goods_id')
                ->field('og.goods_name,og.goods_id,og.goods_sn,sum(og.goods_num) as sale_num,sum(og.goods_num*og.goods_price) as sale_amount, g.is_on_sale, g.cat_id ')
                ->where($where)->group('og.goods_id')->order('sale_num DESC')
                ->cache(true, 3600)->select();
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">排行</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品分类1</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品分类2</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品分类3</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">货号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">销售量</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">销售额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">均价</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">上架状态</td>';
            $strTable .= '</tr>';
            if (is_array($res)) {
                // 分类数据
                $GoodsLogic = new GoodsLogic();
                $cateInfo = $GoodsLogic->get_parent_cate();
                foreach ($res as $k => $val) {
                    if (isset($cateInfo[$val['cat_id']])) {
                        switch ($cateInfo[$val['cat_id']]['level']) {
                            case 3:
                                $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                                $val['second_cat'] = $cateInfo[$val['cat_id']]['level_2']['name'];
                                $val['third_cat'] = $cateInfo[$val['cat_id']]['name'];
                                break;
                            case 2:
                                $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                                $val['second_cat'] = $cateInfo[$val['cat_id']]['name'];
                                $val['third_cat'] = '';
                                break;
                            case 1:
                                $val['first_cat'] = $cateInfo[$val['cat_id']]['name'];
                                $val['second_cat'] = '';
                                $val['third_cat'] = '';
                                break;
                        }
                    } else {
                        $val['first_cat'] = '';
                        $val['second_cat'] = '';
                        $val['third_cat'] = '';
                    }
                    $isOnSale = '上架';
                    if ($val['is_on_sale'] == 0) {
                        $isOnSale = '下架';
                    }
                    $pai = $k + 1 + ((I('p/d', 1) - 1) * $this->page_size);
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px">&nbsp;' . $pai . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['first_cat'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['second_cat'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['third_cat'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_sn'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['sale_num'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['sale_amount'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . round($val['sale_amount'] / $val['sale_num'], 2) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $isOnSale . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($res);
            downloadExcel($strTable, 'saleTopRes');
            exit();
        }

        $GoodsLogic = new GoodsLogic();
        $categoryList = $GoodsLogic->getSortCategory(); //获取排好序的分类列表
        $this->assign('categoryList', $categoryList);
        $this->assign('list', $res);
        $this->assign('page', $Page);
        $this->assign('p', I('p/d', 1));
        $this->assign('page_size', $this->page_size);

        return $this->fetch();
    }


    public function clickTop()
    {
        $goods_name = I('goods_name');
        $sort = I('sort', 'DESC');
        $where = [];
        if (!empty($goods_name)) {
            $where['g.goods_name'] = ['like', "%{$goods_name}%"];
        }
        $count = Db::name('goods')->alias('g')->where($where)->count();
        $Page = new Page($count, $this->page_size);
        $res = Db::name('goods')->alias('g')
            ->field('g.*')
            ->where($where)->order('g.click_count ' . $sort)
            ->limit($Page->firstRow, $Page->listRows)->cache(true, 3600)->select();

        $is_export = I('is_export');
        if (1 == $is_export) {
            $res = Db::name('goods')->alias('g')
                ->field('g.*')
                ->where($where)->order('g.click_count ' . $sort)
                ->cache(true, 3600)->select();
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">排行</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">货号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">上架状态</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">点击数</td>';
            $strTable .= '</tr>';
            if (is_array($res)) {
                foreach ($res as $k => $val) {
                    $isOnSale = '上架';
                    if ($val['is_on_sale'] == 0) {
                        $isOnSale = '下架';
                    }
                    $pai = $k + 1 + ((I('p/d', 1) - 1) * $this->page_size);
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px">&nbsp;' . $pai . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_name'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_sn'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $isOnSale . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['click_count'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($res);
            downloadExcel($strTable, 'saleTopRes');
            exit();
        }

        $this->assign('list', $res);
        $this->assign('page', $Page);
        $this->assign('p', I('p/d', 1));
        $this->assign('page_size', $this->page_size);
        $this->assign('sort', $sort);

        return $this->fetch();
    }

    public function export_user_top()
    {
        $mobile = I('mobile');
        $email = I('email');
        $order_where = [
            'o.add_time' => ['Between', "$this->begin,$this->end"],
            'o.pay_status' => 1,
        ];
        if ($mobile) {
            $user_where['mobile'] = $mobile;
        }
        if ($email) {
            $user_where['email'] = $email;
        }
        if ($user_where) {   //有查询单个用户的条件就去找出user_id
            $user_id = Db::name('users')->where($user_where)->getField('user_id');
            $order_where['o.user_id'] = $user_id;
        }
        $order_where['o.order_status'] = ['in', [1, 2, 4]];

        $ids = I('ids');

        if ($ids) {
            $order_where['o.user_id'] = ['in', $ids];
        }

        $list = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount)+sum(o.user_electronic) as amount,sum(o.order_amount) as order_amount,sum(o.user_electronic) as total_electronic,sum(o.coupon_price) as coupon_price,o.user_id,u.mobile,u.email,u.nickname')
            ->join('users u', 'o.user_id=u.user_id', 'LEFT')
            ->where($order_where)
            ->group('o.user_id')
            ->order('amount DESC')
            ->select();   //以用户ID分组查询
        unset($order_where['o.user_id']);
        $lists = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount)+sum(o.user_electronic) as amount,sum(o.order_amount) as order_amount,sum(o.user_electronic) as total_electronic,sum(o.coupon_price) as coupon_price,o.user_id,u.mobile,u.email,u.nickname')
            ->join('users u', 'o.user_id=u.user_id', 'LEFT')
            ->where($order_where)
            ->group('o.user_id')
            ->order('amount DESC')
            ->select();   //以用户ID分组查询

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">排行</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员手机</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单数</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">购物金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">现金累计</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">电子币累计</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">优惠券抵扣</td>';
        $strTable .= '</tr>';

        if (is_array($list)) {
            foreach ($list as $k => $val) {
                foreach ($lists as $kv => $va) {
                    if ($va['user_id'] == $val['user_id']) {
                        $level = $kv + 1;
                    }
                }
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $level . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['order_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['order_amount'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_electronic'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['coupon_price'] . '</td>';
                $strTable .= '</tr>';
            }
            unset($list);
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'exportUserTop' . $i);
        exit();
    }

    /**
     * 统计报表 - 会员排行.
     *
     * @return mixed
     */
    public function userTop()
    {
        $mobile = I('mobile');
        $email = I('email');
        $order_where = [
            'o.add_time' => ['Between', "$this->begin,$this->end"],
            'o.pay_status' => 1,
        ];
        if ($mobile) {
            $user_where['mobile'] = $mobile;
        }
        if ($email) {
            $user_where['email'] = $email;
        }
        if ($user_where) {   //有查询单个用户的条件就去找出user_id
            $user_id = Db::name('users')->where($user_where)->getField('user_id');
            $order_where['o.user_id'] = $user_id;
        }
        $order_where['o.order_status'] = ['in', [1, 2, 4]];
        $count = Db::name('order')->alias('o')->where($order_where)->group('o.user_id')->count();  //统计数量
        $Page = new Page($count, $this->page_size);
        $list = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount)+sum(o.user_electronic) as amount,sum(o.order_amount) as order_amount,sum(o.user_electronic) as total_electronic,sum(o.coupon_price) as coupon_price,o.user_id,u.mobile,u.email,u.nickname')
            ->join('users u', 'o.user_id=u.user_id', 'LEFT')
            ->where($order_where)
            ->group('o.user_id')
            ->order('amount DESC')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();   //以用户ID分组查询

        $this->assign('page', $Page);
        $this->assign('p', I('p/d', 1));
        $this->assign('page_size', $this->page_size);
        $this->assign('list', $list);

        return $this->fetch();
    }

    /**
     * 用户订单.
     *
     * @return mixed
     */
    public function userOrder()
    {
        $orderModel = new Order();
        $user_id = trim(I('user_id'));
        // 搜索条件
        $condition = [
            'add_time' => ['Between', "$this->begin,$this->end"],
            'pay_status' => 1,
            'user_id' => $user_id,
        ];
        $keyType = I('keytype');
        $keywords = I('keywords', '', 'trim');

        $pay_code = input('pay_code');
        $order_sn = ($keyType && 'order_sn' == $keyType) ? $keywords : I('order_sn');
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        '' != $pay_code ? $condition['pay_code'] = $pay_code : false;   //支付方式

        $count = $orderModel->where($condition)->count();
        $Page = new Page($count, $this->page_size);
        $orderList = $orderModel->where($condition)
            ->limit("{$Page->firstRow},{$Page->listRows}")->order('add_time desc')->select();

        $this->assign('orderList', $orderList);
        $this->assign('user_id', $user_id);
        $this->assign('keywords', $keywords);
        $this->assign('page', $Page); // 赋值分页输出
        return $this->fetch();
    }

    public function saleOrder()
    {
        $end_time = strtotime(I('end_time'));
        $order_where = "o.add_time>$this->begin and o.add_time<$end_time AND (pay_status=1 or pay_code='cod')";  //交易成功的有效订单
        $order_count = Db::name('order')->alias('o')->where($order_where)->whereIn('order_status', '1,2,4')->count();
        $Page = new Page($order_count, 20);
        $order_list = Db::name('order')->alias('o')
            ->field('o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.add_time,u.user_id,u.nickname')
            ->join('users u', 'u.user_id = o.user_id', 'left')
            ->where($order_where)->whereIn('order_status', '1,2,4')
            ->limit($Page->firstRow, $Page->listRows)->select();
        $this->assign('order_list', $order_list);

        $this->assign('page', $Page);

        return $this->fetch();
    }

    /**
     * 销售明细列表.
     */
    public function saleList()
    {
        $cat_id = I('cat_id', 0);
        $brand_id = I('brand_id', 0);
        $goods_id = I('goods_id', 0);
        $goods_name = I('goods_name', '');
        $where = "o.add_time>$this->begin and o.add_time<$this->end and order_status in(1,2,4,6) and og.is_send in (1,2)";  //交易成功的有效订单
        if ($cat_id > 0) {
            $where .= " and (g.cat_id=$cat_id or g.extend_cat_id=$cat_id)";
            $this->assign('cat_id', $cat_id);
        }
        if ($brand_id > 0) {
            $where .= " and g.brand_id=$brand_id";
            $this->assign('brand_id', $brand_id);
        }
        if ($goods_name) {
            $where .= "and og.goods_name='$goods_name' ";
            $this->assign('goods_name', $goods_name);
        }
        if ($goods_id > 0) {
            $where .= " and og.goods_id=$goods_id";
        }
        $count = Db::name('order_goods')->alias('og')
            ->join('order o', 'og.order_id=o.order_id ', 'left')
            ->join('goods g', 'og.goods_id = g.goods_id', 'left')
            ->where($where)->count();  //统计数量
        $Page = new Page($count, 20);
        $show = $Page->show();
        //$where .= ' and og.member_goods_price > 0 ';
        $res = Db::name('order_goods')->alias('og')->field('og.*,o.user_id,o.order_sn,o.shipping_name,o.pay_name,o.add_time,og.spec_key_name')
            ->join('order o', 'og.order_id=o.order_id ', 'left')
            ->join('goods g', 'og.goods_id = g.goods_id', 'left')
            ->where($where)->limit($Page->firstRow, $Page->listRows)
            ->order('o.add_time desc')->select();
        $this->assign('list', $res);
        $this->assign('pager', $Page);
        $this->assign('page', $show);

        $GoodsLogic = new GoodsLogic();
//        $brandList = $GoodsLogic->getSortBrands();  //获取排好序的品牌列表
        $categoryList = $GoodsLogic->getSortCategory(); //获取排好序的分类列表
        $this->assign('categoryList', $categoryList);
//        $this->assign('brandList', $brandList);

        return $this->fetch();
    }

    /**
     * 导出销售明细列表.
     */
    public function exportSaleList()
    {
        $cat_id = I('cat_id', 0);
        $brand_id = I('brand_id', 0);
        $goods_id = I('goods_id', 0);
        $where = "o.add_time>$this->begin and o.add_time<$this->end and order_status in(1,2,4) AND (pay_status=1 or pay_code='cod')";  //交易成功的有效订单
        if ($cat_id > 0) {
            $where .= " and (g.cat_id=$cat_id or g.extend_cat_id=$cat_id)";
            $this->assign('cat_id', $cat_id);
        }
        if ($brand_id > 0) {
            $where .= " and g.brand_id=$brand_id";
            $this->assign('brand_id', $brand_id);
        }
        if ($goods_id > 0) {
            $where .= " and og.goods_id=$goods_id";
        }
        $res = Db::name('order_goods')->alias('og')
            ->field('og.goods_id,og.goods_sn,og.goods_name,g.ctax_price,g.stax_price,og.spec_key,og.final_price,og.goods_price,og.use_integral')
            ->join('order o', 'og.order_id=o.order_id', 'right')
            ->join('goods g', 'og.goods_id = g.goods_id', 'left')
            ->where($where)
            ->group('og.goods_id,og.spec_key')
            ->order('o.add_time desc')
            ->select();

        foreach ($res as $k => $v) {
            $res[$k]['inte_num'] = Db::name('order_goods')->alias('og')
                ->join('order o', 'og.order_id=o.order_id', 'right')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where($where . ' and og.member_goods_price > 0')->where('use_integral', 'gt', 0)->where('og.goods_id', $v['goods_id'])->where('og.spec_key', $v['spec_key'])->where('og.goods_price', $v['goods_price'])
                ->group('og.goods_id,og.spec_key')
                ->sum('og.goods_num');
            $res[$k]['cash_num'] = Db::name('order_goods')->alias('og')
                ->join('order o', 'og.order_id=o.order_id', 'right')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where($where . ' and og.member_goods_price > 0')->where('use_integral', 0)->where('og.goods_id', $v['goods_id'])->where('og.spec_key', $v['spec_key'])->where('og.goods_price', $v['goods_price'])
                ->group('og.goods_id,og.spec_key')
                ->sum('og.goods_num');
            $res[$k]['song_num'] = Db::name('order_goods')->alias('og')
                ->join('order o', 'og.order_id=o.order_id', 'right')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where($where . ' and og.member_goods_price = 0')->where('use_integral', 0)->where('og.goods_id', $v['goods_id'])->where('og.spec_key', $v['spec_key'])->where('og.goods_price', $v['goods_price'])
                ->group('og.goods_id,og.spec_key')
                ->sum('og.goods_num');
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;" rowspan="2">商品货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="180" rowspan="2">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*"  colspan="2">积分价（现金部分）</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" rowspan="2">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*"  colspan="2">零售价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" rowspan="2">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" rowspan="2">赠送</td>';
        $strTable .= '</tr>';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >积分价含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >积分价不含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >零售价含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >零售价不含税价</td>';
        $strTable .= '</tr>';
        if (is_array($res)) {
            foreach ($res as $k => $val) {
                $a = $val['goods_price'] - $val['use_integral'];
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_name'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $a . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['ctax_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['inte_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['stax_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['cash_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['song_num'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($orderList);
        downloadExcel($strTable, 'sale_list');
        exit();
    }

    /**
     * 导出销售明细列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function exportSaleList_v2()
    {
        $cat_id = I('cat_id', 0);
        $brand_id = I('brand_id', 0);
        $goods_id = I('goods_id', 0);
        $where = "o.add_time>$this->begin and o.add_time<$this->end and order_status in(1,2,4) AND (pay_status=1 or pay_code='cod')";  //交易成功的有效订单
        if ($cat_id > 0) {
            $where .= " and (g.cat_id=$cat_id or g.extend_cat_id=$cat_id)";
            $this->assign('cat_id', $cat_id);
        }
        if ($brand_id > 0) {
            $where .= " and g.brand_id=$brand_id";
            $this->assign('brand_id', $brand_id);
        }
        if ($goods_id > 0) {
            $where .= " and og.goods_id=$goods_id";
        }
        $orderGoods = Db::name('order_goods')->alias('og')
            ->field('g.cat_id, g.ctax_price, g.stax_price, og.goods_id, og.goods_sn, og.goods_name, og.spec_key, og.final_price, og.goods_price, og.use_integral')
            ->join('order o', 'og.order_id = o.order_id', 'right')
            ->join('goods g', 'og.goods_id = g.goods_id', 'left')
            ->where($where)
            ->group('og.goods_id,og.spec_key')
            ->order('o.add_time desc')
            ->select();
        $goodsPriceData = [];
        foreach ($orderGoods as $k => $v1) {
            $order_goods = M('order_goods og')
                ->join('order o', 'og.order_id = o.order_id', 'right')
                ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                ->where($where)->where(['og.goods_id' => $v1['goods_id'], 'og.spec_key' => $v1['spec_key']])->group('og.member_goods_price')->field('og.member_goods_price, og.use_integral')->select();
            foreach ($order_goods as $v2) {
                $goodsPriceData[] = [
                    'cat_id' => $v1['cat_id'],
                    'goods_sn' => $v1['goods_sn'],
                    'goods_name' => $v1['goods_name'],
                    'member_goods_price1' => $v2['member_goods_price'],
                    'ctax_price' => $v1['ctax_price'],
                    'inte_num' => M('order_goods og')
                        ->join('order o', 'og.order_id = o.order_id', 'right')
                        ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                        ->where($where)
                        ->where(['og.goods_id' => $v1['goods_id'], 'og.spec_key' => $v1['spec_key'], 'og.member_goods_price' => $v2['member_goods_price'], 'og.use_integral' => ['GT', 0]])
                        ->group('og.goods_id, og.spec_key')
                        ->sum('og.goods_num'),
                    'member_goods_price2' => bcadd($v2['member_goods_price'], $v2['use_integral'], 2),
                    'stax_price' => $v1['stax_price'],
                    'cash_num' => M('order_goods og')
                        ->join('order o', 'og.order_id = o.order_id', 'right')
                        ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                        ->where($where)
                        ->where(['og.goods_id' => $v1['goods_id'], 'og.spec_key' => $v1['spec_key'], 'og.member_goods_price' => $v2['member_goods_price'], 'og.use_integral' => 0])
                        ->group('og.goods_id, og.spec_key')
                        ->sum('og.goods_num'),
                    'gift_num' => M('order_goods og')
                        ->join('order o', 'og.order_id = o.order_id', 'right')
                        ->join('goods g', 'og.goods_id = g.goods_id', 'left')
                        ->where($where)
                        ->where(['og.goods_id' => $v1['goods_id'], 'og.spec_key' => $v1['spec_key'], 'og.member_goods_price' => 0, 'og.use_integral' => 0])
                        ->group('og.goods_id, og.spec_key')
                        ->sum('og.goods_num'),
                ];
            }
        }
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;" rowspan="2">商品货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="180" rowspan="2">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="180" rowspan="2">商品分类1</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="180" rowspan="2">商品分类2</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="180" rowspan="2">商品分类3</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*"  colspan="2">积分价（现金部分）</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" rowspan="2">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*"  colspan="2">零售价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" rowspan="2">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" rowspan="2">赠送</td>';
        $strTable .= '</tr>';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >积分价含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >积分价不含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >零售价含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*" >零售价不含税价</td>';
        $strTable .= '</tr>';
        if (!empty($goodsPriceData)) {
            // 分类数据
            $GoodsLogic = new GoodsLogic();
            $cateInfo = $GoodsLogic->get_parent_cate();
            foreach ($goodsPriceData as $k => $val) {
                if (isset($cateInfo[$val['cat_id']])) {
                    switch ($cateInfo[$val['cat_id']]['level']) {
                        case 3:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                            $val['second_cat'] = $cateInfo[$val['cat_id']]['level_2']['name'];
                            $val['third_cat'] = $cateInfo[$val['cat_id']]['name'];
                            break;
                        case 2:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                            $val['second_cat'] = $cateInfo[$val['cat_id']]['name'];
                            $val['third_cat'] = '';
                            break;
                        case 1:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['name'];
                            $val['second_cat'] = '';
                            $val['third_cat'] = '';
                            break;
                    }
                } else {
                    $val['first_cat'] = '';
                    $val['second_cat'] = '';
                    $val['third_cat'] = '';
                }
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_name'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['first_cat'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['second_cat'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['third_cat'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['member_goods_price1'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['ctax_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['inte_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['member_goods_price2'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['stax_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['cash_num'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['gift_num'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($orderList);
        downloadExcel($strTable, 'sale_list');
        exit();
    }

    public function user()
    {
        $today = strtotime(date('Y-m-d'));
        $month = strtotime(date('Y-m-01'));
        $user['today'] = D('users')->where("reg_time>$today")->count(); //今日新增会员
        $user['month'] = D('users')->where("reg_time>$month")->count(); //本月新增会员
        $user['total'] = D('users')->count(); //会员总数
        $user['user_money'] = D('users')->sum('user_money'); //会员余额总额
        $res = M('order')->where('parent_id = 0')->cache(true)->distinct(true)->field('user_id')->select();
        $user['hasorder'] = count($res);
        $this->assign('user', $user);
        $sql = "SELECT COUNT(*) as num,FROM_UNIXTIME(reg_time,'%Y-%m-%d') as gap from __PREFIX__users where reg_time>$this->begin and reg_time<$this->end group by gap";
        $new = DB::query($sql); //新增会员趋势
        foreach ($new as $val) {
            $arr[$val['gap']] = $val['num'];
        }

        for ($i = $this->begin; $i <= $this->end; $i = $i + 24 * 3600) {
            $brr[] = empty($arr[date('Y-m-d', $i)]) ? 0 : $arr[date('Y-m-d', $i)];
            $day[] = date('Y-m-d', $i);
        }
        $result = ['data' => $brr, 'time' => $day];

        $is_export = I('is_export');
        if (1 == $is_export) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">日期</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">新增会员数</td>';
            $strTable .= '</tr>';
            if (is_array($result)) {
                foreach ($brr as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px">&nbsp;' . $day[$k] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val . ' </td>';

                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($result);
            downloadExcel($strTable, 'userInsert');
            exit();
        }
        $this->assign('result', json_encode($result));

        return $this->fetch();
    }

    public function expense_log()
    {
        $map = [];
        $add_time_begin = I('add_time_begin');
        $add_time_end = I('add_time_end');
        $begin = strtotime($add_time_begin);
        $end = strtotime($add_time_end);
        $admin_id = I('admin_id');
        if ($begin && $end) {
            $map['addtime'] = ['between', "$begin,$end"];
        }
        if ($admin_id) {
            $map['admin_id'] = $admin_id;
        }
        $count = M('expense_log')->where($map)->count();
        $page = new Page($count);
        $lists = M('expense_log')->where($map)->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('total_count', $count);
        $this->assign('add_time_begin', $add_time_begin);
        $this->assign('add_time_end', $add_time_end);
        $this->assign('list', $lists);
        $admin = M('admin')->getField('admin_id,user_name');
        $this->assign('admin', $admin);
        $typeArr = ['', '会员提现', '订单退款', '其他']; //数据库设计问题
        $this->assign('typeArr', $typeArr);

        return $this->fetch();
    }

    //财务统计
    public function finance()
    {
        $begin = $this->begin;
        $end_time = $this->end;
        $order = Db::name('order')->alias('o')
            ->where(['o.pay_status' => 1, 'o.shipping_status' => 1])->whereTime('o.add_time', 'between', [$begin, $end_time])
            ->order('o.add_time asc')->getField('order_id,o.*');  //以时间升序
        $order_id_arr = get_arr_column($order, 'order_id');
        $order_ids = implode(',', $order_id_arr);            //订单ID组
        $order_goods = Db::name('order_goods')->where(['is_send' => ['in', '1,2'], 'order_id' => ['in', $order_ids]])->group('order_id')
            ->order('order_id asc')->getField('order_id,sum(goods_num*cost_price) as cost_price,sum(goods_num*member_goods_price) as goods_amount');  //订单商品退货的不算
        $frist_key = key($order);  //第一个key
        $sratus_date = strtotime(date('Y-m-d', $order["$frist_key"]['add_time']));  //有数据那天为循环初始时间，大范围查询可以避免前面输出一堆没用的数据
        $key = array_keys($order);
        $lastkey = end($key); //最后一个key
        $end_date = strtotime(date('Y-m-d', $order["$lastkey"]['add_time'])) + 24 * 3600;  //数据最后时间为循环结束点，大范围查询可以避免前面输出一堆没用的数据
        for ($i = $sratus_date; $i <= $end_date; $i = $i + 24 * 3600) {   //循环时间
            $date = $day[] = date('Y-m-d', $i);
            $everyday_end_time = $i + 24 * 3600;
            $goods_amount = $cost_price = $shipping_amount = $coupon_amount = $order_prom_amount = $total_amount = 0.00; //初始化变量
            foreach ($order as $okey => $oval) {   //循环订单
                $for_order_id = $oval['order_id'];
                if (!isset($order_goods["$for_order_id"])) {
                    unset($order[$for_order_id]);           //去掉整个订单都了退货后的
                }
                if ($oval['add_time'] >= $i && $oval['add_time'] < $everyday_end_time) {      //统计同一天内的数据
                    $goods_amount += $oval['goods_price'];
                    $total_amount += $oval['total_amount'];
                    $cost_price += $order_goods["$for_order_id"]['cost_price']; //订单成本价
                    $shipping_amount += $oval['shipping_price'];
                    $coupon_amount += $oval['coupon_price'];
                    $order_prom_amount += $oval['order_prom_amount'];
                    unset($order[$okey]);  //省的来回循环
                }
            }
            //拼装输出到图表的数据
            $goods_arr[] = $goods_amount;
            $total_arr[] = $total_amount;
            $cost_arr[] = $cost_price;
            $shipping_arr[] = $shipping_amount;
            $coupon_arr[] = $coupon_amount;

            $list[] = [
                'day' => $date,
                'goods_amount' => $goods_amount,
                'total_amount' => $total_amount,
                'cost_amount' => $cost_price,
                'shipping_amount' => $shipping_amount,
                'coupon_amount' => $coupon_amount,
                'order_prom_amount' => $order_prom_amount,
                'end' => $everyday_end_time,
            ];  //拼装列表
        }
        rsort($list);
        $this->assign('list', $list);
        $result = ['goods_arr' => $goods_arr, 'cost_arr' => $cost_arr, 'shipping_arr' => $shipping_arr, 'coupon_arr' => $coupon_arr, 'time' => $day];
        $this->assign('result', json_encode($result));

        return $this->fetch();
    }

    /**
     * 运营概况详情.
     *
     * @return mixed
     */
    public function financeDetail()
    {
        $begin = $this->begin;
        $end_time = $this->begin + 24 * 60 * 60;
        $order_where = [
            'o.pay_status' => 1,
            'o.shipping_status' => 1,
            'og.is_send' => ['in', '1,2'],];  //交易成功的有效订单
        $order_count = Db::name('order')->alias('o')
            ->join('order_goods og', 'o.order_id = og.order_id', 'left')->join('users u', 'u.user_id = o.user_id', 'left')
            ->whereTime('o.add_time', 'between', [$begin, $end_time])->where($order_where)
            ->group('o.order_id')->count();
        $Page = new Page($order_count, 50);

        $order_list = Db::name('order')->alias('o')
            ->field('o.*,u.user_id,u.nickname,SUM(og.cost_price) as coupon_amount')
            ->join('order_goods og', 'o.order_id = og.order_id', 'left')->join('users u', 'u.user_id = o.user_id', 'left')
            ->where($order_where)->whereTime('o.add_time', 'between', [$begin, $end_time])
            ->group('o.order_id')->limit($Page->firstRow, $Page->listRows)->select();
        $this->assign('order_list', $order_list);
        $this->assign('page', $Page);

        return $this->fetch();
    }

    /**
     * 点击记录列表
     * @return mixed
     */
    public function clickList()
    {
        $positionIds = M('click_log')->group('position')->getField('position', true);
        $clickList = [];
        foreach ($positionIds as $positionId) {
            $count = M('click_log')->where(['position' => $positionId])->count('id');
            switch ($positionId) {
                case 1:
                    $position = 'H5下载头';
                    break;
                default:
                    continue 2;
            }
            $clickList[] = [
                'position_id' => $positionId,
                'position' => $position,
                'count' => $count
            ];
        }

        $this->assign('list', $clickList);
        return $this->fetch('click_list');
    }

    /**
     * 点击记录详情
     * @return mixed
     */
    public function clickLog()
    {
        $position = I('position', '');
        $count = M('click_log')->where(['position' => $position])->count('id');
        $page = new Page($count, 10);
        $clickList = M('click_log')->where(['position' => $position])->order('time desc')->limit($page->firstRow, $page->listRows)->select();
        switch ($position) {
            case 1:
                $position = 'H5下载头';
                break;
            default:
                $position = '';
        }
        $this->assign('page', $page);
        $this->assign('position', $position);
        $this->assign('list', $clickList);
        return $this->fetch('click_log');
    }

    /**
     * 下载记录列表
     * @return mixed
     */
    public function downloadList()
    {
        $typeIds = M('download_log')->group('type')->getField('type', true);
        $downloadList = [];
        foreach ($typeIds as $typeId) {
            $count = M('download_log')->where(['type' => $typeId])->count('id');
            switch ($typeId) {
                case 1:
                    $type = 'IOS';
                    break;
                case 2:
                    $type = 'Android';
                    break;
                default:
                    continue 2;
            }
            $downloadList[] = [
                'type_id' => $typeId,
                'type' => $type,
                'count' => $count
            ];
        }

        $this->assign('list', $downloadList);
        return $this->fetch('download_list');
    }

    /**
     * 下载记录详情
     * @return mixed
     */
    public function downloadLog()
    {
        $type = I('type', '');
        $count = M('download_log')->where(['type' => $type])->count('id');
        $page = new Page($count, 10);
        $downloadList = M('download_log')->where(['type' => $type])->order('down_time desc')->limit($page->firstRow, $page->listRows)->select();
        switch ($type) {
            case 1:
                $type = 'IOS';
                break;
            case 2:
                $type = 'Android';
                break;
            default:
                $type = '';
        }
        $this->assign('page', $page);
        $this->assign('type', $type);
        $this->assign('list', $downloadList);
        return $this->fetch('download_log');
    }
}
