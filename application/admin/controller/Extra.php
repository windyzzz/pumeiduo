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

use app\admin\logic\ExtraLogic;
use app\admin\validate\Extra as ExtraValidate;
use think\AjaxPage;
use think\Db;
use think\Page;

class Extra extends Base
{
    private $service;

    public function __construct(ExtraLogic $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->getList();

        $count = $this->service->getCount();

        $page = new Page($count, 20);

        return view('extra/index', compact('list', 'page'));
    }

    public function add()
    {
        $cat_list = Db::name('goods_category')->where('parent_id = 0')->select();
        $this->assign('cat_list', $cat_list);

        return view('extra/add');
    }

    public function store(ExtraValidate $validate)
    {
        $data = I('post.');

        if (!$validate->check($data)) {
            return json(['status' => 0, 'msg' => $validate->getError(), 'result' => null]);
        }

        $result = $this->service->store($data);

        if (!$result) {
            return json(['status' => 0, 'msg' => '新增加价购活动失败.', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '新增加价购活动成功.', 'result' => null]);
    }

    public function update(ExtraValidate $validate)
    {
        $data = I('post.');
        if (!$validate->check($data)) {
            return json(['status' => 0, 'msg' => $validate->getError(), 'result' => null]);
        }

        $result = $this->service->update($data);

        if (!$result) {
            return json(['status' => 0, 'msg' => '编辑加价购活动失败.错误信息:'.$this->service->error, 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '编辑加价购活动成功.', 'result' => null]);
    }

    /**
     * 加价购活动详情页.
     *
     * @return \think\response\View
     */
    public function extra_info()
    {
        $id = I('id');

        return view('log', compact('id'));
    }

    /**
     * 删除加价购活动.
     *
     * @return \think\response\Json
     */
    public function delete()
    {
        $id = I('id');

        $result = $this->service->delete($id);

        if (!$result) {
            return json(['status' => 0, 'msg' => '删除加价购活动失败.', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '删除加价购活动成功.', 'result' => null]);
    }

    public function info()
    {
        $id = I('id');
        $info = $this->service->getById($id);

        $info['type'] = 0 != $info['cat_id'] ? 1 : 0;
        $info['category'] = explode(',', $info['cat_id']);
        $info['category2'] = explode(',', $info['cat_id_2']);
        $info['category3'] = explode(',', $info['cat_id_3']);
        $cat_list = Db::name('goods_category')->where('parent_id = 0')->select();
        $this->assign('cat_list', $cat_list);

        return view('extra/info', compact('info'));
    }

    public function order_list()
    {
        $reward_id = I('reward_id');

        $order_sn = M('extra_log')->where('extra_reward_id', $reward_id)->where('type', 2)->getField('order_sn', true);

        if ($order_sn) {
            $list = M('extra_log')->where('extra_reward_id', $reward_id)->where('type', 1)->where('order_sn', 'not in', $order_sn)->select();
        } else {
            $list = M('extra_log')->where('extra_reward_id', $reward_id)->where('type', 1)->select();
        }
        $this->assign('reward_id', $reward_id);

        return view('', compact('list'));
    }

    public function export_order_list()
    {
        $reward_id = I('reward_id');

        $order_sn = M('extra_log')->where('extra_reward_id', $reward_id)->where('type', 2)->getField('order_sn', true);

        if ($order_sn) {
            $list = M('extra_log')->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at')->where('extra_reward_id', $reward_id)->where('type', 1)->where('order_sn', 'not in', $order_sn)->select();
        } else {
            $list = M('extra_log')->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at')->where('extra_reward_id', $reward_id)->where('type', 1)->select();
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

    public function userExtra()
    {
        return $this->fetch('user_extra');
    }

    /*
     *Ajax首页
     */
    public function ajaxUserExtra()
    {
        $begin = $this->begin;
        $end = $this->end;

        // 搜索条件
        $condition = [];
        $keyType = I('keytype');

        $keywords = I('keywords', '', 'trim');

        $extra_id = I('id/d', 0);
        $extra_id ? $condition['extra_id'] = $extra_id : false;

        $user_id = ($keyType && 'user_id' == $keyType) ? $keywords : I('user_id', 0);
        $user_id ? $condition['user_id'] = $user_id : false;

        if ($begin && $end) {
            $condition['created_at'] = ['between', "$begin,$end"];
        }

        I('extra_id') ? $condition['extra_id'] = I('extra_id') : false;
        ('' !== I('status')) ? $condition['status'] = I('status') : false;

        $order_sn = ($keyType && 'order_sn' == $keyType) ? $keywords : I('order_sn', '', 'trim');
        $order_sn ? $condition['order_sn'] = $order_sn : false;

        $status = (new \app\common\model\Extra())->status;

        $count = $this->service->getLogCount($condition);

        $Page = new AjaxPage($count, 20);
        $show = $Page->show();

        $extra_log = $this->service->getLogList($condition, 'gl.*', $Page);

        $this->assign('extra_log', $extra_log);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('status', $status);

        return $this->fetch('ajax_user_extra');
    }

    public function export_user_extra()
    {
        $begin = $this->begin;
        $end = $this->end;

        // 搜索条件
        $condition = [];
        $keyType = I('keytype');
        $extras = I('extras', '', 'trim');
        $user_id = I('user_id', '', 'trim');

        $user_id = ($keyType && 'user_id' == $keyType) ? $extras : I('user_id', 0);
        $user_id ? $condition['user_id'] = $user_id : false;

        if ($begin && $end) {
            $condition['created_at'] = ['between', "$begin,$end"];
        }

        I('extra_id') ? $condition['extra_id'] = I('extra_id') : false;
        ('' !== I('status')) ? $condition['status'] = I('status') : false;

        $ids = I('ids');

        if ($ids) {
            $condition['gl.id'] = ['in', $ids];
        }
        $status = (new \app\common\model\Extra())->status;

        $list = Db::name('extra_log')
            ->field('gl.*')
            ->alias('gl')
            ->join('__EXTRA__ e', 'e.id = gl.extra_id', 'LEFT')
            ->where($condition)
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

        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单编号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">奖励加价购信息</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">状态</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">记录时间</td>';
        $strTable .= '</tr>';
        if (is_array($list)) {
            foreach ($list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px" rowspan="'.$val['goods_count'].'">'.$val['user_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;" rowspan="'.$val['goods_count'].'">&nbsp;'.$val['order_sn'].' </td>';
                foreach ($val['goods_list'] as $gk => $gv) {
                    if ($gk < 1) {
                        $strTable .= '<td style="text-align:left;font-size:12px;">商品名称：'.$gv['goods_name'].' 购买数量：'.$gv['goods_num'].'</td>';
                        unset($val['goods_list'][$gk]);
                    }
                }
                $strTable .= '<td style="text-align:left;font-size:12px;" rowspan="'.$val['goods_count'].'">'.$status[$val['status']].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;" rowspan="'.$val['goods_count'].'">'.date('Y-m-d H:i:s', $val['created_at']).'</td>';
                $strTable .= '</tr>';
                foreach ($val['goods_list'] as $gk => $gv) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">商品名称：'.$gv['goods_name'].' 购买数量：'.$gv['goods_num'].'</td>';
                    $strTable .= '</tr>';
                }
            }
        }

        $strTable .= '</table>';

        unset($list);
        downloadExcel($strTable, 'extra_log');
        exit();
    }
}
