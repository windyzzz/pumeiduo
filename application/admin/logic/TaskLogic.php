<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

use app\common\model\Task;

class TaskLogic
{
    private $model;

    public function __construct()
    {
        $this->model = new Task();
    }

    public function getList()
    {
        return $this->model->all();
    }

    public function getCount()
    {
        return $this->model->count();
    }

    public function getById($id)
    {
        return $this->model->with(['taskReward' => function ($query) {
            $query->order('reward_id');
        }])->find($id);
    }

    public function store($data)
    {
        return $this->model->update($data);
    }

    public function afterSave($task_id)
    {
        // 超值套组
        $reward = I('reward/a');

        if ($reward) {
            $rewardArr = M('TaskReward')->where("task_id = $task_id")->getField('reward_id,buy_num,store_count,reward_interval,reward_type'); // 查出所有已经存在的图片

            foreach ($rewardArr as $key => $val) {
                if (!in_array($val, $reward)) {
                    M('TaskReward')->where("reward_id = {$key}")->delete();
                }
            }

            $clounm = 'order_num';

            if (2 == $task_id) {
                $clounm = 'invite_num';
            }
            $task = M('task')->find($task_id);
            foreach ($reward as $key => $val) {
                if (null == $val) {
                    continue;
                }
                if (!in_array($val, $rewardArr)) {
                    $val['task_id'] = $task_id;
                    M('TaskReward')->insert($val); // 实例化User对象

                    // 为目前正在进行的用户任务进行修改
                    $user_task = M('UserTask')
                        ->where('task_reward_id', $val['reward_id'])
                        ->where('status', 'eq', 0)
                        ->where('task_id', $task_id)
                        ->select();
                    if ($user_task) {
                        foreach ($user_task as $k => $v) {
                            $update = [];
                            $update['target_num'] = $val[$clounm];
                            // $update['task_reward_desc'] = $val['description'];
                            if ($val[$clounm] <= $v['finish_num']) {
                                $update['status'] = 1;
                                $update['finished_at'] = time();
                                $order_sn_list = explode(',', $v['order_sn_list']);
                                $order_sn = end($order_sn_list);

                                $reward_price = '0.00';
                                $reward_num = 0;
                                $reward_coupon_id = 0;
                                if (1 == $val['reward_type']) {
                                    $reward_num = $val['reward_num'];
                                } elseif (2 == $val['reward_type']) {
                                    $reward_price = $val['reward_price'];
                                } else {
                                    $reward_coupon_id = $val['reward_coupon_id'];
                                }
                                taskLog($v['user_id'], $task, $val, $order_sn, $reward_price, $reward_num, 1, 0, $reward_coupon_id, $v['id']);
                            }
                            M('UserTask')->where('id', $v['id'])->update($update);
                        }
                    }
                }
            }
        } else {
            M('TaskReward')->where("task_id = {$task_id}")->delete();
        }
    }
}
