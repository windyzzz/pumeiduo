<?php

namespace app\admin\controller;


use think\Page;

class Suggestion extends Base
{
    /**
     * 反馈类型列表
     * @return \think\response\View
     */
    public function suggestion_cate()
    {
        $cate_list = M('suggestion_cate')->field('id, name, sort')->select();
        return view('', compact('cate_list'));
    }

    /**
     * 反馈类型信息
     * @return \think\response\View
     */
    public function cate_info()
    {
        if (request()->isPost()) {
            $data = request()->post();
            M('suggestion_cate')->add($data);
            $this->ajaxReturn(['status' => 1, 'msg' => '添加成功']);
        }
        return view();
    }

    /**
     * 删除反馈类型
     */
    public function cate_del()
    {
        $id = I('id');
        M('suggestion_cate')->where(['id' => $id])->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    /**
     * 意见列表
     * @return \think\response\View
     */
    public function suggestion_list()
    {
        $count = M('suggestion s')->join('suggestion_cate sc', 'sc.id = s.cate_id')->count();
        $page = new Page($count, 10);
        $suggestion_list = M('suggestion s')->join('suggestion_cate sc', 'sc.id = s.cate_id')
            ->join('admin a', 'a.admin_id = s.admin_id', 'LEFT')
            ->field('s.id, s.phone, s.content, s.is_handled, s.create_time, s.handled_time, sc.name cate_name, a.user_name admin_name')
            ->order('s.create_time DESC')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page);
        $this->assign('suggestion_list', $suggestion_list);
        return $this->fetch();
    }

    /**
     * 处理意见
     */
    public function handle()
    {
        $id = I('id', '');
        $is_handled = I('is_handled', 0);
        M('suggestion')->where(['id' => $id])->update([
            'is_handled' => $is_handled,
            'admin_id' => session('admin_id'),
            'handled_time' => time()
        ]);
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
    }

    /**
     * 删除意见
     */
    public function delete()
    {
        $id = I('id', '');
        M('suggestion')->where(['id' => $id])->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
    }
}