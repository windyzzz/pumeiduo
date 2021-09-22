<?php

namespace app\admin\controller;


use think\Page;

class Export extends Base
{
    /**
     * 导出列表
     * @return mixed
     */
    public function fileList()
    {
        $type = I('type');
        $count = M('export_file')->where(['type' => $type])->count();
        $page = new Page($count, 10);
        $list = M('export_file')->where(['type' => $type])->order('add_time DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page);
        $this->assign('export_list', $list);
        return $this->fetch('file_list');
    }
}
