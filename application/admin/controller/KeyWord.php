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

use app\admin\logic\KeyWordLogic;
use app\admin\validate\KeyWord as KeyWordValidate;
use think\AjaxPage;
use think\Page;

class KeyWord extends Base
{
    private $service;

    public function __construct(KeyWordLogic $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->getList();

        $count = $this->service->getCount();

        $page = new Page($count, 20);

        return view('keyword/index', compact('list', 'page'));
    }

    public function add()
    {
        return view('keyword/add');
    }

    public function store(KeyWordValidate $validate)
    {
        $data = I('post.');
        if (!$validate->check($data)) {
            return json(['status' => 0, 'msg' => $validate->getError(), 'result' => null]);
        }

        $result = $this->service->store($data);

        if (!$result) {
            return json(['status' => 0, 'msg' => '新增热门关键词失败.', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '新增热门关键词成功.', 'result' => null]);
    }

    public function update(KeyWordValidate $validate)
    {
        $data = I('post.');
        if (!$validate->check($data)) {
            return json(['status' => 0, 'msg' => $validate->getError(), 'result' => null]);
        }

        $result = $this->service->update($data);

        if (!$result) {
            return json(['status' => 0, 'msg' => '编辑赠品活动失败.错误信息:'.$this->service->error, 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '编辑赠品活动成功.', 'result' => null]);
    }

    /**
     * 删除赠品活动.
     *
     * @return \think\response\Json
     */
    public function delete()
    {
        $id = I('id');

        $result = $this->service->delete($id);

        if (!$result) {
            return json(['status' => 0, 'msg' => '删除热门关键词失败.', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '删除热门关键词成功.', 'result' => null]);
    }

    public function info()
    {
        $id = I('id');

        $info = $this->service->getById($id);

        return view('keyword/info', compact('info'));
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
                $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['user_id'].'</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['reward_integral'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['reward_electronic'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['created_at'].'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($list);
        downloadExcel($strTable, 'hongbaolist');
        exit();
    }

    public function userKeyWord()
    {
        return $this->fetch('user_task');
    }

    /*
     *Ajax首页
     */
    public function ajaxUserKeyWord()
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

        $KeyWordLog = new \app\common\model\KeyWordLog();

        $count = $KeyWordLog->where($condition)->where('task_id', 'in', [2, 3])->count();

        $Page = new AjaxPage($count, 20);
        $show = $Page->show();

        // $userKeyWord = $orderLogic->getOrderList($condition,$sort_order,$Page->firstRow,$Page->listRows);
        $task_log = $KeyWordLog
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
        $KeyWordLog = new \app\common\model\KeyWordLog();

        $task_log = $KeyWordLog
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
                $strTable .= '<td style="text-align:center;font-size:12px">'.$val['user_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.task_type($val['task_id']).' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['reward_integral'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['reward_coupon_money'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['reward_electronic'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['task_reward_desc'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">&nbsp;'.$val['user_task']['order_sn_list'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$status.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['created_at'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$finished_at.'</td>';
                $strTable .= '</tr>';
            }
        }
        $strTable .= '</table>';
        unset($task_log);
        downloadExcel($strTable, 'task_log');
        exit();
    }
}
