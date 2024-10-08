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

class SmsTemplate extends Base
{
    public $send_scene;

    public function _initialize()
    {
        parent::_initialize();

        // 短信使用场景
        $this->send_scene = C('SEND_SCENE');
        $this->assign('send_scene', $this->send_scene);
    }

    public function index()
    {
        $smsTpls = M('sms_template')->select();
        $this->assign('smsTplList', $smsTpls);

        return $this->fetch('sms_template_list');
    }

    /**
     * 添加修改编辑  短信模板
     */
    public function addEditSmsTemplate()
    {
        $id = I('tpl_id/d');
        $model = M('sms_template');

        if (IS_POST) {
            $data = I('post.');
            $data['add_time'] = time();
            //echo "add_time : ".$model->add_time;
            //exit;
            if ($id) {
                $model->update($data);
            } else {
                $id = $model->save($data);
            }
            $this->success('操作成功!!!', U('Admin/SmsTemplate/index'));
            exit;
        }

        if ($id) {
            //进入编辑页面
            $smsTemplate = $model->where('tpl_id', $id)->find();
            $this->assign('smsTpl', $smsTemplate);
            $sceneName = $this->send_scene[$smsTemplate['send_scene']][0];
            $sendscene = $smsTemplate['send_scene'];
            $this->assign('send_name', $sceneName);
            $this->assign('send_scene_id', $sendscene);
        } else {
            //进入添加页面
            //查找已经添加了的短信模板
            $scenes = $model->getField('send_scene', true);
            $filterSendscene = [];
            //过滤已经添加过滤的短信模板
            foreach ($this->send_scene as $key => $value) {
                if (!in_array($key, $scenes)) {
                    $filterSendscene[$key] = $value;
                }
            }
        }

        $this->assign('send_scene', $filterSendscene);

        return $this->fetch('_sms_template');
    }

    /**
     * 删除订单.
     */
    public function delTemplate()
    {
        $model = M('sms_template');
        $row = $model->where('tpl_id ='.$_GET['id'])->delete();
        $return_arr = [];
        if ($row) {
            $return_arr = ['status' => 1, 'msg' => '删除成功', 'data' => ''];   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        } else {
            $return_arr = ['status' => -1, 'msg' => '删除失败', 'data' => ''];
        }

        return $this->ajaxReturn($return_arr);
    }
}
