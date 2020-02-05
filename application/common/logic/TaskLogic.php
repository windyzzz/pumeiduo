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
        if (1 != $this->task['is_open'] || $this->task['start_time'] > time() || $this->task['end_time'] < time() || $this->order['add_time'] < $this->task['start_time']) {
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


    public function loginProfit()
    {
        if ($this->checkTaskEnable()) {
            foreach ($this->task['task_reward'] as $tk => $tv) {
                // 查看任务存不存在，不存在则创建
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
