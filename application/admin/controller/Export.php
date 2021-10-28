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
        if (strstr($type, ',')) {
            $where = ['type' => ['IN', $type]];
        } else {
            $where = ['type' => $type];
        }
        $count = M('export_file')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('export_file')->where($where)->order('add_time DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page);
        $this->assign('export_list', $list);
        return $this->fetch('file_list');
    }

    /**
     * 删除导出文件
     */
    public function deleteExportFile()
    {
        $fileId = I('file_id');
        $exportFile = M('export_file')->where(['id' => $fileId])->find();
        if (!$exportFile) {
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
        }
        if ($exportFile['status'] == 2) {
            $this->ajaxReturn(['status' => 0, 'msg' => '文件正在导出，不能删除']);
        }
        M('export_file')->where(['id' => $fileId])->delete();
        $file = PUBLIC_PATH . substr($exportFile['path'], strrpos($exportFile['path'], 'public/') + 7) . $exportFile['name'];
        unlink($file);
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }
}
