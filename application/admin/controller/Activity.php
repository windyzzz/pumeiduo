<?php

namespace app\admin\controller;

use app\common\model\CateActivity;
use app\common\model\CateActivityGoods;
use think\Db;
use think\Loader;
use think\Page;

class Activity extends Base
{
    /**
     * 活动楼层列表
     * @return mixed
     */
    public function cate_activity_list()
    {
        $activity = new CateActivity();
        $count = $activity->count();
        $Page = new Page($count, 10);
        $activity_list = $activity->order('start_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page', $Page);
        $this->assign('activity_list', $activity_list);
        return $this->fetch();
    }


    public function cate_activity_info()
    {
        
        return $this->fetch();
    }

    /**
     * 新建/更新主题活动信息
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function cate_activity_save()
    {
        $data = I('post.');
        $activityId = $data['id'];
        // 验证
        $orderPromValidate = Loader::validate('CateActivity');
        if (!$orderPromValidate->batch()->check($data)) {
            $msg = '';
            foreach ($orderPromValidate->getError() as $item) {
                $msg .= $item . '，';
            }
            $return = ['status' => 0, 'msg' => rtrim($msg, '，')];
            $this->ajaxReturn($return);
        }
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($activityId) {
            // 编辑
            Db::name('cate_activity')->where(['id' => $activityId])->update($data);
            Db::name('cate_activity_goods')->where(['cate_act_id' => $activityId])->delete();   // 删除旧商品数据
        } else {
            // 新增
            $activityId = Db::name('cate_activity')->add($data);
        }
        // 活动商品
        $actGoods = [];
        foreach ($data['goods'] as $value) {
            if (isset($value['item_id'])) {
                foreach ($value['item_id'] as $item) {
                    $actGoods[] = [
                        'cate_act_id' => $activityId,
                        'goods_id' => $value['goods_id'],
                        'item_id' => $item
                    ];
                }
            } else {
                $actGoods[] = [
                    'cate_act_id' => $activityId,
                    'goods_id' => $value['goods_id'],
                    'item_id' => 0
                ];
            }
        }
        $cateActGoods = new CateActivityGoods();
        $cateActGoods->saveAll($actGoods);
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
    }
}