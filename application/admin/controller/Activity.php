<?php

namespace app\admin\controller;

use app\common\model\CateActivity;
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
    }
}