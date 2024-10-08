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

use app\common\logic\OssLogic;
use app\common\model\DistributeConfig;
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
    public function distributeLogVip()
    {
        $group_list = [
            'vip_daily_log' => '日度记录',
            'vip_monthly_log' => '月度记录',
            'vip_yearly_log' => '年度记录',
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

    /**
     * 会员VIP升级记录
     * @return mixed
     */
    public function distributeLogSvip()
    {
        $group_list = [
            'svip_daily_log' => '日度记录',
            'svip_monthly_log' => '月度记录',
            'svip_yearly_log' => '年度记录',
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
        $distributeLog = M('distribut_log')->where($where)->group('user_id')->order('add_time DESC')->field('order_sn, type, add_time')->select();
        $list = [];
        switch ($type) {
            case 'daily':
                $startTime = strtotime(date('Y-m-d 00:00:00', $startTime));
                $endTime = strtotime(date('Y-m-d 23:59:59', $endTime));
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
                $startTime = strtotime(date('Y-m-01 00:00:00', $startTime));
                $endTime = strtotime(date('Y-m-t 23:59:59', $endTime));
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
                $startTime = strtotime(date('Y-01-01 00:00:00', $startTime));
                $endTime = strtotime(date('Y-12-31 23:59:59', $endTime));
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
        $this->assign('new_level', $newLevel);
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 会员VIP升级记录导出
     * @throws \Exception
     */
    public function exportDistributeLog()
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
                    $key = date('Y-m-01', strtotime('-' . $i . 'month', strtotime(date('Y-m', $endTime))));
                    $list[$key] = [
                        'date' => $key,
                        'vip_order_num' => 0,
                        'vip_money_num' => 0,
                    ];
                    foreach ($distributeLog as $log) {
                        if ($key == date('Y-m-01', $log['add_time'])) {
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
        // 表头
        $headList = [
            '时间', 'VIP套组升级数', 'VIP累计升级数'
        ];
        toCsvExcel(array_values($list), $headList, 'distribute_log');
    }

    /**
     * 升级设置
     * @return mixed
     * @throws \Exception
     */
    public function upgradeConfig()
    {
        if (IS_POST) {
            $param = I('post.');
            M('distribute_config')->where(['type' => 'svip_benefit'])->delete();
            foreach ($param as $key => $value) {
                if ($key == 'svip_benefit') {
                    $data = [];
                    if (count($value['name']) > 4) {
                        $this->error('SVIP专属权益配置数量不能超过4个', U('Admin/Distribut/config'));
                    }
                    foreach ($value['name'] as $k => $v) {
                        if (empty($v)) {
                            continue;
                        }
                        $data[] = [
                            'type' => $key,
                            'name' => $v,
                            'url' => $value['url'][$k]
                        ];
                    }
                    $distributeConfig = new DistributeConfig();
                    $distributeConfig->saveAll($data);
                }
            }
            $this->success('操作成功', U('Admin/Distribut/config'));
        }
        $distributeConfig = M('distribute_config')->select();
        $config = [];
        foreach ($distributeConfig as $val) {
            $config[$val['type']][] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }
        if (empty($config['svip_benefit'])) {
            $svipKey = 0;
        } else {
            $svipKey = count($config['svip_benefit']);
        }
        $this->assign('svip_key', $svipKey);
        $this->assign('config', $config);
        return $this->fetch('upgrade_config');
    }

    /**
     * 代理商等级设置
     * @return mixed
     */
    public function levelConfig()
    {
        $ossLogic = new OssLogic();
        if (IS_POST) {
            $param = I('post.');
            // 配置
            foreach ($param as $k => $v) {
                if (strstr($v['url'], 'aliyuncs.com')) {
                    // 原图
                    $v['url'] = M('distribute_config')->where(['type' => $k])->value('url');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($v['url'], strrpos($v['url'], '/public/') + 8);
                    $fileName = substr($v['url'], strrpos($v['url'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $ossLogic->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $v['url'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
                $data = [
                    'type' => $k,
                    'name' => isset($v['name']) ? $v['name'] : '',
                    'url' => isset($v['url']) ? $v['url'] : '',
                    'content' => isset($v['content']) ? $v['content'] : '',
                ];
                $config = M('distribute_config')->where(['type' => $k])->find();
                if (!empty($config)) {
                    M('distribute_config')->where(['id' => $config['id']])->update($data);
                } else {
                    M('distribute_config')->add($data);
                }
            }
            $this->success('操作成功', U('Distribut/levelConfig'));
        }
        // 配置
        $distributeConfig = M('distribute_config')->select();
        $config = [];
        foreach ($distributeConfig as $val) {
            if (!empty($val['url'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $ossLogic::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            }
            $config[$val['type']] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }

        $this->assign('config', $config);
        return $this->fetch('level_config');
    }
}
