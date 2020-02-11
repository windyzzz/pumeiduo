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

use app\admin\logic\TaskLogic;
use think\AjaxPage;
use think\Db;
use think\Exception;
use think\Page;
use think\Request;

class Task extends Base
{
    private $service;

    public function __construct(TaskLogic $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    public function config()
    {
        if ($this->request->isPost()) {
            $banner = $this->request->post()['banner'];
            if (!$banner) {
                $this->error('banner不能为空');
            }
            $config_value = $this->request->post()['config_value'];
            M('task_config')->where(['id' => 1])->update([
                'banner' => $banner,
                'config_value' => serialize($config_value)
            ]);
            $this->success('更新成功');
        }
        $config = M('task_config')->find();
        $config_value = unserialize($config['config_value']);

        return view('', compact('config', 'config_value'));
    }

    public function index()
    {
        $list = $this->service->getList();

        $count = $this->service->getCount();

        $page = new Page($count, 20);

        return view('', compact('list', 'page'));
    }

    public function info(Request $request)
    {
        $id = $request->param('id');

        if ($request->isPost()) {
            $data = input('post.');
            $validate = \think\Loader::validate('Task');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = [
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'result' => $error,
                ];
                $this->ajaxReturn($return_arr);
            }
//            $rewardType = [];
//            foreach ($data['reward'] as $reward) {
//                if (!empty($rewardType) && !in_array($reward['reward_type'], $rewardType)) {
//                    $this->ajaxReturn(['status' => -1, 'msg' => '任务奖励类型要统一']);
//                }
//                $rewardType[] = $reward['reward_type'];
//            }

            try {
                Db::startTrans();
                $this->service->store($data);

                $this->service->afterSave($data['id']);

                $return_arr = [
                    'status' => 1,
                    'msg' => '操作成功',
                ];
                Db::commit();
                $this->ajaxReturn($return_arr);
            } catch (Exception $e) {
                Db::rollback();
                $this->ajaxReturn(['status' => -1, 'msg' => '记录错误']);
            }
        }

        $info = $this->service->getById($id);

//        $task_cate = C('TASK_CATE');
        $task_cate = unserialize(M('task_config')->value('config_value'));
        $distribut_list = getDistributList(); // 分销等级列表

        $template_name = 'info_' . $id;

        return view($template_name, compact('info', 'task_cate', 'distribut_list'));
    }

    public function order_list()
    {
        $reward_id = I('reward_id');

        $order_sn = M('task_log')->where('task_reward_id', $reward_id)->where('type', 2)->getField('order_sn', true);

        if ($order_sn) {
            $list = M('task_log')->where('task_reward_id', $reward_id)->where('type', 1)->where('order_sn', 'not in', $order_sn)->select();
        } else {
            $list = M('task_log')->where('task_reward_id', $reward_id)->where('type', 1)->select();
        }
        $this->assign('reward_id', $reward_id);

        return view('', compact('list'));
    }

    public function export_order_list()
    {
        $reward_id = I('reward_id');

        $order_sn = M('task_log')->where('task_reward_id', $reward_id)->where('type', 2)->getField('order_sn', true);

        if ($order_sn) {
            $list = M('task_log')->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at')->where('task_reward_id', $reward_id)->where('type', 1)->where('order_sn', 'not in', $order_sn)->select();
        } else {
            $list = M('task_log')->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at')->where('task_reward_id', $reward_id)->where('type', 1)->select();
        }

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获得会员id</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获得积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获得电子币</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">获得时间</td>';
        $strTable .= '</tr>';
        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['reward_integral'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['reward_electronic'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['created_at'] . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($list);
        downloadExcel($strTable, 'hongbaolist');
        exit();
    }

    public function userTask()
    {
        return $this->fetch('user_task');
    }

    /*
     *Ajax首页
     */
    public function ajaxUserTask()
    {
        $begin = $this->begin;
        $end = $this->end;

        // 搜索条件
        $condition = [];
        $keyType = I('keytype');
        $keywords = I('keywords', '', 'trim');
        $user_id = I('user_id', '', 'trim');

        $user_id = ($keyType && 'user_id' == $keyType) ? $keywords : I('user_id', 0);
        $user_id ? $condition['user_id'] = $user_id : false;

        $task_reward_id = ($keyType && 'task_reward_id' == $keyType) ? $keywords : I('task_reward_id', 0);
        $task_reward_id ? $condition['task_reward_id'] = $task_reward_id : false;

        $task_reward_desc = ($keyType && 'task_reward_desc' == $keyType) ? $keywords : I('task_reward_desc', '', 'trim');
        $task_reward_desc ? $condition['task_reward_desc'] = trim($task_reward_desc) : false;

        if ($begin && $end) {
            $condition['created_at'] = ['between', "$begin,$end"];
        }

        I('task_id') ? $condition['task_id'] = I('task_id') : false;
        ('' !== I('status')) ? $condition['status'] = I('status') : false;

        $order_sn = ($keyType && 'order_sn' == $keyType) ? $keywords : I('order_sn', '', 'trim');
        // $order_sn ? $condition['order_sn'] = $order_sn : false;
        if ($order_sn) {
            $user_task_id = M('user_task')->where('order_sn_list', 'LIKE', "%{$order_sn}%")->getField('id', true);
        }
        $user_task_id ? $condition['user_task_id'] = ['in', $user_task_id] : false;

        $TaskLog = new \app\common\model\TaskLog();

        $count = $TaskLog->where($condition)->where('task_id', 'in', [2, 3])->count();

        $Page = new AjaxPage($count, 20);
        $show = $Page->show();

        // $userTask = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        $task_log = $TaskLog
            ->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at,FROM_UNIXTIME(finished_at,"%Y-%m-%d %H:%i:%s") as finished_at')
            ->where($condition)
            ->where('task_id', 'in', [2, 3])
            ->with(['user_task'])
            ->limit($Page->firstRow, $Page->listRows)
            ->order('id desc')
            ->select();

        $this->assign('task_log', $task_log);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch('ajax_user_task');
    }

    public function export_user_task()
    {
        $begin = $this->begin;
        $end = $this->end;

        // 搜索条件
        $condition = [];
        $keyType = I('keytype');
        $keywords = I('keywords', '', 'trim');
        $user_id = I('user_id', '', 'trim');

        $user_id = ($keyType && 'user_id' == $keyType) ? $keywords : I('user_id', 0);
        $user_id ? $condition['user_id'] = $user_id : false;

        $task_reward_id = ($keyType && 'task_reward_id' == $keyType) ? $keywords : I('task_reward_id', 0);
        $task_reward_id ? $condition['task_reward_id'] = $task_reward_id : false;

        $task_reward_desc = ($keyType && 'task_reward_desc' == $keyType) ? $keywords : I('task_reward_desc', '', 'trim');
        $task_reward_desc ? $condition['task_reward_desc'] = trim($task_reward_desc) : false;

        if ($begin && $end) {
            $condition['created_at'] = ['between', "$begin,$end"];
        }

        I('task_id') ? $condition['task_id'] = I('task_id') : false;
        ('' !== I('status')) ? $condition['status'] = I('status') : false;

        $ids = I('ids');

        if ($ids) {
            $condition['id'] = ['in', $ids];
        }
        $TaskLog = new \app\common\model\TaskLog();

        $task_log = $TaskLog
            ->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at,FROM_UNIXTIME(finished_at,"%Y-%m-%d %H:%i:%s") as finished_at')
            ->where($condition)
            ->where('task_id', 'in', [2, 3])
            ->with(['user_task'])
            ->order('id desc')
            ->select();

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">任务类型</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">奖励积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">奖励现金券金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">奖励电子币</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">任务描述</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">相关订单</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">领取状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">创建时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">领取时间</td>';
        $strTable .= '</tr>';
        if (is_array($task_log)) {
            foreach ($task_log as $k => $val) {
                $finished_at = null;
                $status = '未领取';
                if (1 == $val['status']) {
                    $status = '已领取';
                    $finished_at = $val['finished_at'];
                }
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . task_type($val['task_id']) . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['reward_integral'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['reward_coupon_money'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['reward_electronic'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['task_reward_desc'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">&nbsp;' . $val['user_task']['order_sn_list'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $status . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['created_at'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $finished_at . '</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($task_log);
        downloadExcel($strTable, 'task_log');
        exit();
    }

    /**
     * 奖励重置
     */
    public function reset_reward()
    {
        $taskId = I('task_id', '');
        Db::startTrans();
        try {
            // 所有未使用的记录
            $taskLog = M('task_log')->where(['task_id' => $taskId, 'status' => 1, 'type' => 1, 'finished_at' => 0])->select();
            // 更新用户记录
            foreach ($taskLog as $log) {
                $payPoints = $log['reward_integral'] != 0 ? -$log['reward_integral'] : 0;
                $userElectronic = $log['reward_electronic'] != 0 ? -$log['reward_electronic'] : 0;
                accountLog($log['user_id'], 0, $payPoints, '登录奖励重置', 0, 0, 0, $userElectronic, 18, false, 4);
            }
            // 更新记录
            M('task_log')->where(['task_id' => $taskId, 'status' => 1, 'type' => 1, 'finished_at' => 0])->update([
                'status' => -1
            ]);
            // 更新任务奖励
            M('task_reward')->where(['task_id' => $taskId])->update([
                'buy_num' => 0,
                'order_num' => 0,
            ]);
            // 关闭任务
            M('task')->where(['id' => $taskId])->update(['is_open' => 0]);
            Db::commit();
            $this->ajaxReturn(['status' => 1, 'msg' => '重置成功']);
        } catch (Exception $e) {
            Db::rollback();
            $this->ajaxReturn(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
