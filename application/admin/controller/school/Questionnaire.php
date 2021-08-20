<?php

namespace app\admin\controller\school;


use think\Db;
use think\Page;

class Questionnaire extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 配置
     * @return mixed
     */
    public function config()
    {
        if (IS_POST) {
            $param = I('post.');
            $configData = [
                'start_time' => $param['start_time'] ? strtotime($param['start_time']) : 0,
                'end_time' => $param['end_time'] ? strtotime($param['end_time']) : 0,
                'is_open' => $param['is_open']
            ];
            if (!$param['config_id'] || !M('school_article_questionnaire_config')->find()) {
                $configData['add_time'] = NOW_TIME;
                M('school_article_questionnaire_config')->add($configData);
            } else {
                M('school_article_questionnaire_config')->where(['id' => $param['config_id']])->update($configData);
            }
            $this->success('操作成功', U('school.questionnaire/config'));
        }
        // 配置
        $config = M('school_article_questionnaire_config')->find();
        if ($config) {
            $config['start_time'] = date('Y-m-d H:i:s', $config['start_time']);
            $config['end_time'] = date('Y-m-d H:i:s', $config['end_time']);
        }
        // 调查项目
        $count = $caption = M('school_article_questionnaire_caption')->count();
        $page = new Page($count, 10);
        $caption = M('school_article_questionnaire_caption')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('config', $config);
        $this->assign('caption', $caption);
        $this->assign('page', $page);
        return $this->fetch();
    }

    /**
     * 更新修改项目
     * @return mixed
     */
    public function addEditCaption()
    {
        $captionId = I('caption_id', 0);
        if (IS_POST) {
            $data = I('post.');
            if ($captionId) {
                M('school_article_questionnaire_caption')->where(['id' => $captionId])->update($data);
            } else {
                M('school_article_questionnaire_caption')->add($data);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => '']);
        }
        if ($captionId) {
            $caption = M('school_article_questionnaire_caption')->where(['id' => $captionId])->find();
            $this->assign('caption', $caption);
        }

        return $this->fetch('add_edit_caption');
    }


    /**
     * 删除项目
     */
    public function delCaption()
    {
        $id = I('id');
        Db::startTrans();
        M('school_article_questionnaire_caption')->where(['id' => $id])->delete();
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }
}
