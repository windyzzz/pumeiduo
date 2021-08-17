<?php

namespace app\admin\controller\school;


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
                'status' => $param['status']
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
        // 调查项目
        $caption = M('school_article_questionnaire_caption')->select();

        $this->assign('config', $config);
        $this->assign('caption', $caption);
        return $this->fetch();
    }
}
