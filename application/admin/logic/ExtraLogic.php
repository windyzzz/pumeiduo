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

use app\common\model\Extra;
use think\Db;
use think\Exception;

class ExtraLogic
{
    private $model;
    public $error = '';

    public function __construct()
    {
        $this->model = new Extra();
    }

    public function getList()
    {
        $list = $this->model->with(['extraReward'])->order('id desc')->select();
        foreach ($list as $k => $v) {
            if ($v['extra_reward']) {
                foreach ($v['extra_reward'] as $ck => $cv) {
                    $goods_info = M('goods')->field('goods_name,store_count')->where('goods_id', $cv['goods_id'])->find();
                    $list[$k]['extra_reward'][$ck]['goods_name'] = $goods_info['goods_name'];
                    $list[$k]['extra_reward'][$ck]['store_count'] = $goods_info['store_count'];
                }
            }
        }

        return $list;
    }

    public function getCount()
    {
        return $this->model->count();
    }

    public function getLogList($where, $field = '*', $Page)
    {
        $list = Db::name('extra_log')
            ->field($field)
            ->alias('gl')
            ->join('__EXTRA__ e', 'e.id = gl.extra_id', 'LEFT')
            ->where($where)
            ->limit($Page->firstRow, $Page->listRows)
            ->order('gl.id desc')
            ->group('order_sn')
            ->select();

        if ($list) {
            foreach ($list as $k => $v) {
                $goods_list1 = M('extra_log')->where('order_sn', $v['order_sn'])->getField('reward_goods_id', true);
                $goods_list2 = M('extra_log')->where('order_sn', $v['order_sn'])->getField('reward_goods_id,reward_num');

                $goods_list = M('goods')->field('goods_name,goods_id')->where('goods_id', 'in', $goods_list1)->select();
                if ($goods_list) {
                    foreach ($goods_list as $gk => $gv) {
                        $goods_list[$gk]['goods_num'] = $goods_list2[$gv['goods_id']];
                    }
                }
                $list[$k]['goods_list'] = $goods_list;
                $list[$k]['goods_count'] = count($goods_list);
            }
        }

        return $list;
    }

    public function getLogCount($where)
    {
        return M('extra_log')->where($where)->group('order_sn')->count();
    }

    public function getExtraLogById($id, $where)
    {
        $list = Db::name('extra_log')
            ->field('gl.*')
            ->alias('gl')
            ->join('__EXTRA__ e', 'e.id = gl.extra_id', 'LEFT')
            ->where('extra_id', $id)
            ->where($where)
            ->order('id desc')
            ->group('order_sn')
            ->select();
        if ($list) {
            foreach ($list as $k => $v) {
                $goods_list1 = M('extra_log')->where('order_sn', $v['order_sn'])->getField('reward_goods_id', true);
                $goods_list2 = M('extra_log')->where('order_sn', $v['order_sn'])->getField('reward_goods_id,reward_num');

                $goods_list = M('goods')->field('goods_name,goods_id')->where('goods_id', 'in', $goods_list1)->select();
                if ($goods_list) {
                    foreach ($goods_list as $gk => $gv) {
                        $goods_list[$gk]['goods_num'] = $goods_list2[$gv['goods_id']];
                    }
                }
                $list[$k]['goods_list'] = $goods_list;
                $list[$k]['goods_count'] = count($goods_list);
            }
        }

        return $list;
    }

    public function getById($id)
    {
        return $this->model->with(['extraReward' => function ($query) {
            $query->alias('er')->field('er.*,g.goods_name,g.shop_price,g.exchange_integral,g.store_count')->join('__GOODS__ g','g.goods_id = er.goods_id','left')->order('reward_id');
        }])->find($id);
    }

    public function store($data)
    {
        if (0 == $data['type']) {
            $data['cat_id'] = [0];
            $data['cat_id_2'] = [0];
            $data['cat_id_3'] = [0];
        }
        Db::startTrans();
        try {
            $result = $this->model->save($data);
            $extra_id = $this->model->id;
            $result1 = $this->afterSave($extra_id);

            if (!$result || !$result1) {
                throw new Exception('网络异常');
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }

    public function delete($id)
    {
        Db::startTrans();
        try {
            $this->model->where('id', $id)->delete();
        } catch (Exception $e) {
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }

    public function update($data)
    {
        Db::startTrans();
        try {
            if (0 == $data['type']) {
                $data['cat_id'] = [0];
                $data['cat_id_2'] = [0];
                $data['cat_id_3'] = [0];
            }
            $result = $this->model->update($data);
            $extra_id = $data['id'];
            $result1 = $this->afterSave($extra_id);
            if (!$result || !$result1) {
                throw new Exception('网络异常');
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            Db::rollback();

            return false;
        }
        Db::commit();

        return true;
    }

    public function afterSave($extra_id)
    {
        $result = $result1 = true;
        // 超值套组
        $reward = I('reward/a');

        if ($reward) {
            $rewardArr = M('ExtraReward')->where("extra_id = $extra_id")->getField('reward_id,goods_id,goods_price,goods_num'); // 查出所有已经存在的图片

            foreach ($rewardArr as $key => $val) {
                if (!in_array($val, $reward)) {
                    $result = M('ExtraReward')->where("reward_id = {$key}")->delete();
                }
            }

            foreach ($reward as $key => $val) {
                if (null == $val) {
                    continue;
                }
                if (!in_array($val, $rewardArr)) {
                    $val['extra_id'] = $extra_id;
                    $result1 = M('ExtraReward')->insert($val); // 实例化User对象
                }
            }
        } else {
            M('ExtraReward')->where("extra_id = {$extra_id}")->delete();
        }

        return $result && $result1;
    }
}
