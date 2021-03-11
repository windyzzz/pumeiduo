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

use app\common\logic\supplier\AccountService;
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

        $list = M('CommissionLog')->where($where)->order('id desc')->select();
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

            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px">' . exchangeDate($v['create_time']) . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . bcadd($v['total_amount'], $vipProfit, 2) . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . bcadd($v['real_amount'], $vipProfit, 2) . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . commission_type($v['status']) . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . $v['order_num'] . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . bcadd($v['sale_free'], $vipProfit, 2) . '</td>';
            $strTable .= '<td style="text-align:left;font-size:12px;">' . $v['shop_free'] . '</td>';
            $strTable .= '</tr>';
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

    /**
     * 供应链账户信息
     * @return mixed
     */
    public function supplierAccount()
    {
        $group_list = [
            'supplier_recharge_log' => '充值记录',
            'supplier_consume_log' => '消费记录',
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
        // 供应链账户余额
        $res = (new AccountService())->storeMoney();
        if ($res['status'] == 0) {
            $balance = 0;
        } else {
            $balance = $res['data']['store_money'];
        }
        $this->assign('balance', $balance);
        return $this->fetch($inc_type);
    }

    /**
     * 供应链账户记录
     * @return mixed
     */
    public function supplierAccountLog()
    {
        $type = I('type', 1);
        $page = I('p', 1);
        // 供应链账户记录
        $res = (new AccountService())->rechargeLog($type, $page);
//        $res = '{"status":1,"data":{"count":1,"list":[{"payment_no":"202007231557298218","pay_type":"\u786e\u8ba4\u8ba2\u5355\u6263\u6b3e","freight_price":"0.00","consume_price":"41.73","creat_time":"2020-07-23 15:57:29","order":[{"order_id":"10119","order_sn":"221510119082185","pt_order_sn":"C202007231556082185","consignee":"\u738b\u5cf0","pay_goods":[{"order_id":"10119","goods_id":"233591","goods_name":"\u683c\u6717(GL)\u5a74\u513f\u65e5\u5e38\u62a4\u74067\u4ef6\u5957\u793c\u76d2\u88c5","goods_num":"1","s_price":"41.73","service_price":"0.00","is_pay":"1"}]},{"order_id":"10119","order_sn":"221510119082185","pt_order_sn":"C202007231556082185","consignee":"\u738b\u5cf0","pay_goods":[{"order_id":"10119","goods_id":"233591","goods_name":"\u683c\u6717(GL)\u5a74\u513f\u65e5\u5e38\u62a4\u74067\u4ef6\u5957\u793c\u76d2\u88c5","goods_num":"1","s_price":"41.73","service_price":"0.00","is_pay":"1"},{"order_id":"10119","goods_id":"233591","goods_name":"\u683c\u6717(GL)\u5a74\u513f\u65e5\u5e38\u62a4\u74067\u4ef6\u5957\u793c\u76d2\u88c5","goods_num":"1","s_price":"41.73","service_price":"0.00","is_pay":"1"}]}]}]}}';
//        $res = json_decode($res, true);
        if ($res['status'] == 0) {
            $count = 0;
            $logList = [];
        } else {
            $count = $res['data']['count'];
            $logList = [];
            foreach ($res['data']['list'] as $k1 => $list) {
                $logList[$k1] = [
                    'payment_no' => $list['payment_no'],
                    'pay_type' => $list['pay_type'],
                    'price' => isset($list['price']) ? $list['price'] : 0.00,
                    'freight_price' => isset($list['freight_price']) ? $list['freight_price'] : 0.00,
                    'cost_price' => isset($list['cost_price']) ? $list['cost_price'] : 0.00,
                    'total_price' => isset($list['total_price']) ? $list['total_price'] : 0.00,
                    'consume_price' => isset($list['consume_price']) ? $list['consume_price'] : 0.00,
                    'creat_time' => $type == 1 ? date('Y-m-d H:i:s', $list['creat_time']) : $list['creat_time'],
                    'pay_time' => isset($list['pay_time']) && $list['pay_time'] != 0 ? date('Y-m-d H:i:s', $list['pay_time']) : '',
                    'order' => '',
                ];
                if (!empty($list['order'])) {
                    foreach ($list['order'] as $k2 => $order) {
                        $orderSn = M('order o1')->join('order o2', 'o1.parent_id = o2.order_id')->where(['o1.order_sn' => $order['pt_order_sn']])->value('o2.order_sn');
                        $logList[$k1]['order'] .= '主订单号：' . $orderSn . '；供应链订单号：' . $order['pt_order_sn'] . '；收件人：' . $order['consignee'] . "；\n";
                        foreach ($order['pay_goods'] as $goods) {
                            $logList[$k1]['order'] .= '——商品：' . $goods['goods_name'] . '；数量：' . $goods['goods_num'] . '；成本价：' . $goods['s_price'] . '；服务费：' . $goods['service_price'] . "；\n";
                        }
                        $logList[$k1]['order'] .= "\n";
                    }
                }
            }
        }
        $page = new AjaxPage($count, 20);
        $show = $page->show();
        $this->assign('page', $show);
        $this->assign('type', $type);
        $this->assign('log_list', $logList);
        return $this->fetch('supplier_account_log');
    }

    /**
     * 导出供应链账户记录(xls)
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function exportSupplierAccountLog_xls()
    {
        $type = I('type', 1);
        // 供应链账户记录
        $res = (new AccountService())->rechargeLog($type, 1, 1000);
        if ($res['status'] == 0) {
            $this->error('导出失败', U('Admin/Finance/supplierAccount', ['inc_type' => 'supplier_consume_log']));
        } else {
            // 表头
            $expCellName = [
                ['payment_no', '流水号', 20, 1],
                ['pay_type', '扣除方式', 20, 1],
                ['freight_price', '总运费', 20, 1],
                ['consume_price', '扣除预存金额', 20, 1],
                ['creat_time', '创建时间', 20, 1],
                ['order_info', '订单信息', 500, 1],
            ];
            // 表数据
            $dataList = [];
            foreach ($res['data']['list'] as $k1 => $list) {
                $dataList[] = [
                    'payment_no' => "\t" . $list['payment_no'],
                    'pay_type' => $list['pay_type'],
                    'freight_price' => $list['freight_price'],
                    'consume_price' => $list['consume_price'],
                    'creat_time' => $list['creat_time'],
                ];
                if (empty($list['order'])) {
                    $dataList[$k1]['order_info'] = '';
                } else {
                    $orderInfo = '';
                    foreach ($list['order'] as $k2 => $order) {
                        $orderSn = M('order o1')->join('order o2', 'o1.parent_id = o2.order_id')->where(['o1.order_sn' => $list['order'][0]['pt_order_sn']])->value('o2.order_sn');
                        $orderInfo .= '主订单号：' . $orderSn . '；供应链订单号：' . $list['order'][0]['pt_order_sn'] . '；收件人：' . $list['order'][0]['consignee'] . "\n";
                        foreach ($list['order'][0]['pay_goods'] as $goods) {
                            $orderInfo .= '——商品：' . $goods['goods_name'] . '；数量：' . $goods['goods_num'] . '；成本价：' . $goods['s_price'] . '；服务费：' . $goods['service_price'] . "\n";
                        }
                        $orderInfo .= "\n";
                    }
                    $dataList[$k1]['order_info'] = $orderInfo;
                }
            }
            exportExcel('供应链账户记录', $expCellName, $dataList, 'supplier_account_log');
        }
    }

    /**
     * 导出供应链账户记录(csv)
     */
    public function exportSupplierAccountLog_csv()
    {
        $type = I('type', 1);
        // 供应链账户记录
        $res = (new AccountService())->rechargeLog($type, 1, 1000);
        if ($res['status'] == 0) {
            $this->error('导出失败', U('Admin/Finance/supplierAccount', ['inc_type' => 'supplier_consume_log']));
        } else {
            // 表头
            $headList = [
                '流水号', '扣除方式', '总运费', '扣除预存金额', '创建时间', '订单信息'
            ];
            // 表数据
            $dataList = [];
            foreach ($res['data']['list'] as $k1 => $list) {
                $dataList[$k1] = [
                    "\t" . $list['payment_no'],
                    $list['pay_type'],
                    $list['freight_price'],
                    $list['consume_price'],
                    $list['creat_time'],
                ];
                if (empty($list['order'])) {
                    $dataList[$k1][] = '';
                } else {
                    $orderInfo = [];
                    foreach ($list['order'] as $k2 => $order) {
                        $orderSn = M('order o1')->join('order o2', 'o1.parent_id = o2.order_id')->where(['o1.order_sn' => $list['order'][0]['pt_order_sn']])->value('o2.order_sn');
                        $orderInfo[] = '主订单号：' . $orderSn . '；供应链订单号：' . $list['order'][0]['pt_order_sn'] . '；收件人：' . $list['order'][0]['consignee'];
                        foreach ($list['order'][0]['pay_goods'] as $goods) {
                            $orderInfo[] = '——商品：' . $goods['goods_name'] . '；数量：' . $goods['goods_num'] . '；成本价：' . $goods['s_price'] . '；服务费：' . $goods['service_price'];
                        }
                        $orderInfo[] = '';
                    }
                    $dataList[$k1][] = $orderInfo;
                }
            }
            toCsvExcel($dataList, $headList, 'supplier_account_log');
        }
    }
}
