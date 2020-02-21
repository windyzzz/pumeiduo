<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

use app\admin\logic\TaskLogic as TaskService;
use app\common\model\UserTask;
use think\Db;

class TaskLogic
{
    private $task;
    private $order;
    private $user;
    private $distribut_id;

    public function __construct($id = 1)
    {
        if ($id && $id !== 0) {
            $taskService = new TaskService();
            $this->task = $taskService->getById($id);
        }
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function setDistributId($distribut_id)
    {
        $this->distribut_id = $distribut_id;
    }

    /**
     * 获取任务列表
     * @return mixed
     */
    public function taskList()
    {
        $taskList = M('task t')->join('task_reward tr', 'tr.task_id = t.id')
            ->where(['t.id' => ['not in', [1, 4]]])
            ->where(['t.is_open' => 1, 't.start_time' => ['<=', time()], 't.end_time' => ['>=', time()]])
            ->group('tr.task_id')->field('t.*, tr.reward_type')->select();
        return $taskList;
    }

    /**
     * 获取任务奖励
     * @param $taskId
     * @return mixed
     */
    public function taskReward($taskId)
    {
        $taskReward = M('task_reward')->where(['task_id' => $taskId])->select();
        return $taskReward;
    }

    /**
     * 获取任务记录
     * @param $status
     * @param $type
     * @return mixed
     */
    public function taskLog($status, $type)
    {
        $where = ['tl.user_id' => $this->user['user_id']];
        if (is_int($status)) {
            $where['tl.status'] = $status;
        }
        if ($type) {
            $where['tl.type'] = $type;
        }
        $taskLog = M('task_log tl')->join('task_reward tr', 'tr.reward_id = tl.task_reward_id')
            ->where($where)->field('tl.*, tr.*')->order('tl.created_at DESC')->select();
        return $taskLog;
    }

    // 销售任务
    public function doOrderPayAfterSell()
    {
        if ($this->checkTaskEnable()) {
            $distribut_log = M('distribut_log')->where('order_sn', $this->order['order_sn'])->where('type', 1)->find();
            $user = M('Users')->where('user_id', $this->order['user_id'])->find();
            if ($distribut_log) {
                $user['distribut_level'] = $distribut_log['old_level'];
            }
            $this->setUser($user);

            $first_leader = $this->user['first_leader'];
            $second_leader = $this->user['second_leader'];
            $third_leader = $this->user['third_leader'];

            $this->getRewardSell($first_leader);
            $this->getRewardSell($second_leader);
            $this->getRewardSell($third_leader);
        }
    }

    private function getRewardSell($uid)
    {
        if ($uid < 1) {
            return false;
        }

        $order_sn = '';
        if ($this->order) {
            $order_sn = $this->order['order_sn'];
        }

        foreach ($this->task['task_reward'] as $tk => $tv) {
            if ($tv['distribut_id'] == $this->user['distribut_level'] || 0 == $tv['distribut_id']) {
                //1.查看任务存不存在，不存在则创建
                $user_task = M('user_task')
                    ->where('user_id', $uid)
                    ->where('task_reward_id', $tv['reward_id'])
                    ->where('status', 0)
                    ->where('created_at', 'gt', $this->task['start_time'])
                    ->where('created_at', 'lt', $this->task['end_time'])
                    ->find();

                $status = 0;
                if (!$user_task) {
                    // 如果奖励周期是空的话（只奖励一次），则跳过新增
                    if (0 == $tv['cycle']) {
                        $has_task = M('user_task')
                            ->where('user_id', $uid)
                            ->where('task_reward_id', $tv['reward_id'])
                            ->where('created_at', 'gt', $this->task['start_time'])
                            ->where('created_at', 'lt', $this->task['end_time'])
                            ->find();
                        if ($has_task) {
                            continue;
                        }
                    }

                    if (1 == $tv['order_num']) {
                        $status = 1;
                    }
                    $data = [
                        'user_id' => $uid,
                        'task_id' => $this->task['id'],
                        'task_reward_id' => $tv['reward_id'],
                        // 'task_reward_desc' => $tv['description'],
                        'finish_num' => 1,
                        'target_num' => $tv['order_num'],
                        'status' => $status,
                        'invite_uid_list' => $this->user['user_id'],
                        'order_sn_list' => $order_sn,
                        'created_at' => time(),
                        'finished_at' => $status ? time() : 0,
                    ];
                    $user_task_id = M('user_task')->insertGetId($data);
                } else {
                    // 必须是不同的粉丝
                    $invite_uid_list = explode(',', $user_task['invite_uid_list']);
                    if (in_array($this->order['user_id'], $invite_uid_list)) {
                        continue;
                    }
                    $finish_num = $user_task['finish_num'] + 1;
                    if ($finish_num == $user_task['target_num']) {
                        $status = 1;
                    }
                    if ($user_task['order_sn_list']) {
                        $order_sn_list = $user_task['order_sn_list'] . ',' . $order_sn;
                    } else {
                        $order_sn_list = '';
                    }
                    $update = [
                        'finish_num' => $finish_num,
                        'status' => $status,
                        'invite_uid_list' => $user_task['invite_uid_list'] . ',' . $this->user['user_id'],
                        'order_sn_list' => $order_sn_list,
                        'finished_at' => $status ? time() : 0,
                    ];
                    M('user_task')->where('id', $user_task['id'])->update($update);
                    $user_task_id = $user_task['id'];
                }
                if ($status > 0) {
                    $reward_price = '0.00';
                    $reward_num = 0;
                    $reward_coupon_id = 0;
                    if (1 == $tv['reward_type']) {
                        $reward_num = $tv['reward_num'];
                    } elseif (2 == $tv['reward_type']) {
                        $reward_price = $tv['reward_price'];
                    } else {
                        $reward_coupon_id = $tv['reward_coupon_id'];
                    }
                    taskLog($uid, $this->task, $tv, $order_sn, $reward_price, $reward_num, 1, 0, $reward_coupon_id, $user_task_id);
                }
            }
        }
    }

    // 双11任务 随机红包
    public function doOrderPayAfter()
    {
        if ($this->checkTaskEnable()) {
            $goods_id_list = explode(',', $this->task['goods_id_list']);
            $order_goods = M('OrderGoods')->where('order_id', $this->order['order_id'])->select();
            foreach ($order_goods as $k => $v) {
                if (in_array($v['goods_id'], $goods_id_list)) {
                    // 开始抽奖

                    $all_store_count = M('task_reward')->where('task_id', $this->task['id'])->sum('store_count');
                    if ($all_store_count > 0) {
                        $reward = [];
                        $count = rand(1, $all_store_count);
                        foreach ($this->task['task_reward'] as $tk => $tv) {
                            $count -= $tv['store_count'];
                            if ($count <= 0) {
                                $reward = $tv;
                                break;
                            }
                        }

                        if ($reward) {
                            $pay_point = $user_electronic = 0;
                            list($min, $max) = explode('-', $reward['reward_interval']);
                            if (1 == $reward['reward_type']) {
                                $pay_point = rand($min, $max);
                            } elseif (2 == $reward['reward_type']) {
                                $min = $min * 100;
                                $max = $max * 100;
                                $user_electronic = rand($min, $max);
                                $user_electronic = round($user_electronic / 100, 2);
                            }

                            accountLog($this->order['user_id'], 0, $pay_point, '【双十一活动】购买双十一标签商品赠送', 0, $this->order['order_id'], $this->order['order_sn'], $user_electronic, 18);

                            M('task_reward')->where('reward_id', $reward['reward_id'])->setDec('store_count');
                            M('task_reward')->where('reward_id', $reward['reward_id'])->setInc('buy_num');

                            taskLog($this->order['user_id'], $this->task, $reward, $this->order['order_sn'], $user_electronic, $pay_point, 1, 1);
                            break;
                        }
                    }
                }
            }
        }
    }

    // 双11任务 随机红包
    public function returnReward($msg = '')
    {
        $reward_log = M('task_log')
            ->where('order_sn', $this->order['order_sn'])
            ->where('task_id', 1)
            ->where('type', 2)
            ->find();

        if ($reward_log) {
            return true;
        }

        $reward_log = M('task_log')
            ->where('order_sn', $this->order['order_sn'])
            ->where('task_id', 1)
            ->where('type', 1)
            ->find();

        if ($reward_log) {
            accountLog($reward_log['user_id'], 0, -$reward_log['reward_integral'], $msg . '，返回双十一奖励', 0, $this->order['order_id'], $this->order['order_sn'], -$reward_log['reward_electronic'], 10);

            M('task_reward')->where('reward_id', $reward_log['task_reward_id'])->setInc('store_count');
            M('task_reward')->where('reward_id', $reward_log['task_reward_id'])->setDec('buy_num');
            $reward_log['reward_id'] = $reward_log['task_reward_id'];
            $reward_log['description'] = $reward_log['task_reward_desc'];
            taskLog($this->order['user_id'], $this->task, $reward_log, $this->order['order_sn'], -$reward_log['reward_electronic'], -$reward_log['reward_integral'], 2, 1);
        }
    }

    public function checkTaskEnable()
    {
        if (1 != $this->task['is_open'] || $this->task['start_time'] > time() || $this->task['end_time'] < time() || (isset($this->order) && $this->order['add_time'] < $this->task['start_time'])) {
            return false;
        }

        return true;
    }

    public function getTaskInfo()
    {
        if ($this->checkTaskEnable()) {
            return $this->task;
        }

        return false;
    }

    public function doInviteAfter()
    {
        if ($this->checkTaskEnable()) {
            $order_sn = '';
            if ($this->order) {
                $order_sn = $this->order['order_sn'];
            }

            foreach ($this->task['task_reward'] as $tk => $tv) {
                if ($tv['distribut_id'] == $this->distribut_id || 0 == $tv['distribut_id']) {
                    //1.查看任务存不存在，不存在则创建
                    $user_task = M('user_task')
                        ->where('user_id', $this->user['first_leader'])
                        ->where('task_reward_id', $tv['reward_id'])
                        ->where('status', 0)
                        ->where('created_at', 'gt', $this->task['start_time'])
                        ->where('created_at', 'lt', $this->task['end_time'])
                        ->find();
                    $status = 0;
                    if (!$user_task) {
                        // 如果奖励周期是空的话（只奖励一次），则跳过新增
                        if (0 == $tv['cycle']) {
                            $has_task = M('user_task')
                                ->where('user_id', $this->user['first_leader'])
                                ->where('task_reward_id', $tv['reward_id'])
                                ->where('created_at', 'gt', $this->task['start_time'])
                                ->where('created_at', 'lt', $this->task['end_time'])
                                ->find();
                            if ($has_task) {
                                continue;
                            }
                        }
                        if (1 == $tv['invite_num']) {
                            $status = 1;
                        }
                        $data = [
                            'user_id' => $this->user['first_leader'],
                            'task_id' => $this->task['id'],
                            'task_reward_id' => $tv['reward_id'],
                            // 'task_reward_desc' => $tv['description'],
                            'finish_num' => 1,
                            'target_num' => $tv['invite_num'],
                            'status' => $status,
                            'invite_uid_list' => $this->user['user_id'],
                            'order_sn_list' => $order_sn,
                            'created_at' => time(),
                            'finished_at' => $status ? time() : 0,
                        ];
                        $user_task_id = M('user_task')->insertGetId($data);
                    } else {
                        $finish_num = $user_task['finish_num'] + 1;
                        if ($finish_num == $user_task['target_num']) {
                            $status = 1;
                        }
                        if ($user_task['order_sn_list']) {
                            $order_sn_list = $user_task['order_sn_list'] . ',' . $order_sn;
                        } else {
                            $order_sn_list = '';
                        }
                        $update = [
                            'finish_num' => $finish_num,
                            'status' => $status,
                            'invite_uid_list' => $user_task['invite_uid_list'] . ',' . $this->user['user_id'],
                            'order_sn_list' => $order_sn_list,
                            'finished_at' => $status ? time() : 0,
                        ];
                        M('user_task')->where('id', $user_task['id'])->update($update);
                        $user_task_id = $user_task['id'];
                    }
                    if ($status > 0) {
                        $reward_price = '0.00';
                        $reward_num = 0;
                        $reward_coupon_id = 0;
                        if (1 == $tv['reward_type']) {
                            $reward_num = $tv['reward_num'];
                        } elseif (2 == $tv['reward_type']) {
                            $reward_price = $tv['reward_price'];
                        } else {
                            $reward_coupon_id = $tv['reward_coupon_id'];
                        }
                        taskLog($this->user['first_leader'], $this->task, $tv, $order_sn, $reward_price, $reward_num, 1, 0, $reward_coupon_id, $user_task_id);
                    }
                }
            }
        }
    }

    /**
     * 推荐任务奖励
     */
    public function inviteProfit()
    {
        if ($this->checkTaskEnable()) {
            $order_id = $this->order['order_id'];
            // 查看订单商品
            $goodsIds = Db::name('order_goods')->where(['order_id' => $order_id])->getField('goods_id', true);
            $goodsInfo = Db::name('goods')->where(['goods_id' => ['in', $goodsIds]])->field('zone, distribut_id')->select();
            foreach ($goodsInfo as $value) {
                if ($value['zone'] == 3 && $value['distribut_id'] > 0) {
                    $level[] = $value['distribut_id'];
                }
            }
            if (!empty($level)) {
                $level_list = M('distribut_level')->where('level_id', 'in', $level)->order('order_money')->select();
                $level = end($level_list);
                $userInfo = M('users')->master()->field('user_id,distribut_level,first_leader')->where('user_id', $this->order['user_id'])->find() ?: [];
                if (!empty($userInfo) && $userInfo['first_leader'] > 0) {
                    $this->user = $userInfo;
                    $this->distribut_id = $level['level_id'];
                    $this->doInviteAfter();
                }
                return;


                // 订单中含有VIP申请套餐
                $userInviteUid = Db::name('users')->where(['user_id' => $this->order['user_id']])->value('invite_uid');
                if ($userInviteUid != 0) {
                    // 拥有上级
                    // 任务内容
                    $taskReward = Db::name('task_reward')->where(['task_id' => 2])->order('reward_id asc')
                        ->getField('reward_id, invite_num, reward_coupon_id, cycle', true);
                    $userTask = Db::name('user_task')->where(['user_id' => $userInviteUid, 'task_id' => 2])->order('id asc')->select();
                    if (empty($userTask)) {
                        // 添加用户任务
                        $data = [];
                        foreach ($taskReward as $item) {
                            $data[] = [
                                'user_id' => $userInviteUid,
                                'task_id' => 2,
                                'task_reward_id' => $item['reward_id'],
                                'finish_num' => 1,
                                'target_num' => $item['invite_num'],
                                'status' => $item['invite_num'] == 1 ? 1 : 0,
                                'invite_uid_list' => $this->order['user_id'],
                                'order_sn_list' => $this->order['order_sn'],
                                'created_at' => time(),
                                'finished_at' => $item['invite_num'] == 1 ? time() : 0
                            ];
                        }
                        $userTask = new UserTask();
                        $res = $userTask->saveAll($data);
                        $taskReward = array_values($taskReward);
                        $rewardId = $taskReward[0]['reward_id'];
                        $userTaskId = $res[0]['id'];
                    } else {
                        $rewardId = '';
                        $userTaskId = '';
                        // 更新用户任务
                        foreach ($userTask as $key => $item) {
                            $status = 0;
                            if (isset($taskReward[$item['task_reward_id']])) {
                                switch ($taskReward[$item['task_reward_id']]['cycle'] == 1) {
                                    case 0:
                                        $status = 1;
                                        break;
                                    case 1:
                                        $status = 0;
                                        break;
                                    default:
                                        $status = 0;
                                }
                            }
                            $k = ($key - 1 >= 0) ? $key - 1 : 0;
                            Db::name('user_task')->where(['id' => $item['id']])->update([
                                'finish_num' => $item['target_num'],
                                'status' => $status,
                                'invite_uid_list' => $userTask[$k]['invite_uid_list'] . ',' . $this->order['user_id'],
                                'order_sn_list' => $userTask[$k]['order_sn_list'] . ',' . $this->order['order_sn'],
                                'finished_at' => time()
                            ]);
                            $rewardId = $item['task_reward_id'];
                            $userTaskId = $item['id'];
                            break;
                        }
                    }
                    if (!empty($rewardId) && !empty($userTaskId)) {
                        // 任务记录
                        $taskReward = Db::name('task_reward')->where(['reward_id' => $rewardId])->find();
                        $task = Db::name('task')->where(['id' => 2])->field('id, title')->find();
                        taskLog($userInviteUid, $task, $taskReward, $this->order['order_sn'], $taskReward['reward_price'], $taskReward['reward_num'], 1, 0, $taskReward['reward_coupon_id'], $userTaskId);

//                            // 上级奖励自动发放
//                            $taskReward = Db::name('task_reward')->where(['reward_id' => $rewardId])->find();
//                            if ($taskReward['reward_num'] > 0) {
//                                // 积分
//                                accountLog($userInviteUid, 0, $taskReward['reward_num'], '用户领取任务奖励', 0, 0, $this->order['order_sn'], 0, 16);
//                            }
//                            if ($taskReward['reward_price'] > 0) {
//                                // 电子币
//                                accountLog($userInviteUid, 0, 0, '用户领取任务奖励', 0, 0, $this->order['order_sn'], $taskReward['reward_price'], 15);
//                            }
//                            $couponName = '';
//                            $couponMoney = '';
//                            if ($taskReward['reward_coupon_id'] != '0') {
//                                // 优惠券
//                                $couponIds = explode('-', $taskReward['reward_coupon_id']);
//                                $coupon = Db::name('coupon')->where(['id' => ['in', $couponIds]])->field('id, name, money')->select();
//                                $couponData = [];
//                                foreach ($coupon as $item) {
//                                    $couponName .= $item['name'] . '-';
//                                    $couponMoney .= $item['money'] . '-';
//                                    $couponData[] = [
//                                        'cid' => $item['id'],
//                                        'type' => 3,    // 邀请
//                                        'uid' => $userInviteUid,
//                                        'order_id' => 0,
//                                        'get_order_id' => $order_id,
//                                        'send_time' => time()
//                                    ];
//                                }
//                                // 优惠券记录
//                                $couponList = new CouponList();
//                                $couponList->saveAll($couponData);
//                            }
//                            // 任务记录
//                            Db::name('task_log')->add([
//                                'task_id' => 2,
//                                'user_task_id' => $userTaskId,
//                                'task_title' => Db::name('task')->where(['id' => 2])->value('title'),
//                                'task_reward_id' => $taskReward['reward_id'],
//                                'task_reward_desc' => $taskReward['description'],
//                                'user_id' => $userInviteUid,
//                                'order_sn' => $this->order['order_sn'],
//                                'reward_electronic' => $taskReward['reward_price'],
//                                'reward_integral' => $taskReward['reward_num'],
//                                'reward_coupon_id' => $taskReward['reward_coupon_id'],
//                                'reward_coupon_money' => rtrim($couponMoney, '-') ?? 0.00,
//                                'reward_coupon_name' => rtrim($couponName, '-'),
//                                'status' => 1,  // 自动领取
//                                'type' => 1,
//                                'created_at' => time(),
//                                'finished_at' => time()
//                            ]);
                    }
                }
            }
        }
    }

    /**
     * 登录领取检测
     * @return bool
     */
    public function checkLoginProfit()
    {
        if (M('task_log')->where(['task_id' => $this->task['id'], 'user_id' => $this->user['user_id'], 'status' => ['neq', -1]])->value('id')) {
            return false;
        }
        return true;
    }

    /**
     * 登录奖励
     * @return array
     */
    public function loginProfit()
    {
        if ($this->checkTaskEnable()) {
            $allStoreCount = 0;
            foreach ($this->task['task_reward'] as $taskReward) {
                $allStoreCount += $taskReward['store_count'];
            }
            if ($allStoreCount > 0) {
                $reward = [];
                $count = rand(1, $allStoreCount);
                foreach ($this->task['task_reward'] as $taskReward) {
                    $count -= $taskReward['store_count'];
                    if ($count <= 0) {
                        $reward = $taskReward;
                        break;
                    }
                }
                if ($reward) {
                    $pay_point = $user_electronic = 0;
                    list($min, $max) = explode('-', $reward['reward_interval']);
                    switch ($reward['reward_type']) {
                        case 1:
                            // 积分
                            $pay_point = $min + mt_rand() / mt_getrandmax() * ($max - $min);
                            $pay_point = bcadd($pay_point, 0, 2);
                            break;
                        case 2:
                            // 电子币
                            $user_electronic = $min + mt_rand() / mt_getrandmax() * ($max - $min);
                            $user_electronic = bcadd($user_electronic, 0, 2);
                    }
                    // 用户资金记录
                    accountLog($this->user['user_id'], 0, $pay_point, $this->task['title'], 0, 0, 0, $user_electronic, 18, true, $this->task['id']);
                    // 任务记录
                    taskLog($this->user['user_id'], $this->task, $reward, 0, $user_electronic, $pay_point, 1, 1);
                    // 任务奖励更新
                    M('task_reward')->where('reward_id', $reward['reward_id'])->setDec('store_count');
                    M('task_reward')->where('reward_id', $reward['reward_id'])->setInc('buy_num');
                    $result = [
                        'use_start_time' => date('Y-m-d', $this->task['use_start_time']),
                        'use_end_time' => date('Y-m-d', $this->task['use_end_time']),
                        'reward' => $pay_point != 0 ? $pay_point : $user_electronic
                    ];
                    return ['status' => 1, 'msg' => '领取成功', 'result' => $result];
                }
                return ['status' => 0, 'msg' => '很遗憾，红包已被领取完毕'];
            } else {
                return ['status' => 0, 'msg' => '很遗憾，红包已被领取完毕'];
            }
        }
        return ['status' => 0, 'msg' => '活动还未开启'];
    }

    /**
     * 使用登录奖励
     * @param $order
     */
    public function useLoginProfit($order)
    {
        $userProfit = M('task_log')->where(['task_id' => $this->task['id'], 'user_id' => $order['user_id'], 'status' => 1, 'type' => 1, 'finished_at' => 0])
            ->field('reward_integral, reward_electronic')->find();
        if (!empty($userProfit)) {
            if ($userProfit['reward_integral'] != 0) {
                // 使用抵扣积分
                if ($order['integral'] >= $userProfit['reward_integral']) {
                    M('task_log')->where(['task_id' => $this->task['id'], 'user_id' => $order['user_id'], 'status' => 1, 'type' => 1, 'finished_at' => 0])->update([
                        'order_sn' => $order['order_sn'],
                        'finished_at' => time()
                    ]);
                }
            } elseif ($userProfit['reward_electronic'] != 0) {
                // 使用抵扣电子币
                if ($order['user_electronic'] >= $userProfit['reward_electronic']) {
                    M('task_log')->where(['task_id' => $this->task['id'], 'user_id' => $order['user_id'], 'status' => 1, 'type' => 1, 'finished_at' => 0])->update([
                        'order_sn' => $order['order_sn'],
                        'finished_at' => time()
                    ]);
                }
            }
        }
    }

    /**
     * 返还使用登录奖励
     * @param $order
     */
    public function returnLoginProfit()
    {
        M('task_log')->where(['task_id' => $this->task['id'], 'user_id' => $this->order['user_id'], 'order_sn' => $this->order['order_sn']])->update([
            'finished_at' => 0
        ]);
    }
}