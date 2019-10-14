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

use think\Page;

class Topic extends Base
{
    public function index()
    {
        return $this->fetch();
    }

    public function topic()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $topic_id = I('get.topic_id');
        if ($topic_id) {
            $topic_info = D('topic')->where('topic_id='.$topic_id)->find();
            $this->assign('info', $topic_info);
        }

        $this->assign('URL_upload', U('Admin/Ueditor/imageUp', ['savepath' => 'topic']));
        $this->assign('URL_fileUp', U('Admin/Ueditor/fileUp', ['savepath' => 'topic']));
        $this->assign('URL_scrawlUp', U('Admin/Ueditor/scrawlUp', ['savepath' => 'topic']));
        $this->assign('URL_getRemoteImage', U('Admin/Ueditor/getRemoteImage', ['savepath' => 'topic']));
        $this->assign('URL_imageManager', U('Admin/Ueditor/imageManager', ['savepath' => 'topic']));
        $this->assign('URL_imageUp', U('Admin/Ueditor/imageUp', ['savepath' => 'topic']));
        $this->assign('URL_getMovie', U('Admin/Ueditor/getMovie', ['savepath' => 'topic']));
        $this->assign('URL_Home', '');

        return $this->fetch();
    }

    public function topicList()
    {
        $Ad = M('topic');
        $p = $this->request->param('p');
        $res = $Ad->order('ctime')->page($p.',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $val['topic_state'] = $val['topic_state'] > 1 ? '已发布' : '未发布';
                $val['ctime'] = date('Y-m-d H:i', $val['ctime']);
                $list[] = $val;
            }
        }
        $this->assign('list', $list); // 赋值数据集
        $count = $Ad->count(); // 查询满足要求的总记录数
        $Page = new Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
    $this->assign('pager', $Page);
        $this->assign('page', $show); // 赋值分页输出
        return $this->fetch();
    }

    public function topicHandle()
    {
        $data = I('post.');
        $data['topic_content'] = $_POST['topic_content']; // 这个内容不做转义
        if ('add' == $data['act']) {
            $data['ctime'] = time();
            $r = D('topic')->add($data);
        }
        if ('edit' == $data['act']) {
            $r = D('topic')->where('topic_id='.$data['topic_id'])->save($data);
        }

        if ('del' == $data['act']) {
            $r = D('topic')->where('topic_id='.$data['topic_id'])->delete();
            if ($r) {
                exit(json_encode(1));
            }
        }

        if (false !== $r) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result' => '']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => '']);
        }
    }
}
