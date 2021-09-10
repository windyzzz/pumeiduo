<?php

namespace app\admin\controller\school;


use app\common\model\SchoolArticleQuestionnaireOption;
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
        $caption = M('school_article_questionnaire_caption')->limit($page->firstRow . ',' . $page->listRows)->order('sort DESC')->select();

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
            $data['score_list'] = '';
            switch ($data['type']) {
                case 1:
                    if (!$data['max_score']) {
                        $data['score_list'] = '1';
                    } else {
                        for ($i = 1; $i <= $data['max_score']; $i++) {
                            $data['score_list'] .= $i . ',';
                        }
                        $data['score_list'] = rtrim($data['score_list'], ',');
                    }
                    break;
                case 3:
                case 4:
                    if (empty($data['option'])) {
                        $this->ajaxReturn(['status' => 0, 'msg' => '请添加选项内容', 'result' => '']);
                    }
            }
            if ($captionId) {
                M('school_article_questionnaire_caption')->where(['id' => $captionId])->update($data);
            } else {
                $captionId = M('school_article_questionnaire_caption')->add($data);
            }
            if (in_array($data['type'], [3, 4])) {
                M('school_article_questionnaire_option')->where(['caption_id' => $captionId])->delete();
                $optionData = [];
                foreach ($data['option'] as $op) {
                    $optionData[] = [
                        'caption_id' => $captionId,
                        'content' => $op
                    ];
                }
                (new SchoolArticleQuestionnaireOption())->saveAll($optionData);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => '']);
        }
        if ($captionId) {
            $caption = M('school_article_questionnaire_caption')->where(['id' => $captionId])->find();
            $option = M('school_article_questionnaire_option')->where(['caption_id' => $captionId])->select();
            $this->assign('caption', $caption);
            $this->assign('option', $option);
        }
        $this->assign('option_count', !empty($option) ? count($option) : 0);
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

    /**
     * 问卷调查数据统计
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function statistics()
    {
        $isExport = I('is_export', 0);
        $where = [
            'show_questionnaire' => 1
        ];
        $title = htmlspecialchars_decode(trim(I('title', '')));
        $moduleId = I('module_id', '');
        $classId = I('class_id', '');
        if ($title) {
            $where['sa.title'] = ['LIKE', '%' . $title . '%'];
        }
        // 模块列表
        $notModuleType = ['module6', 'module7', 'module8'];
        $module = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
        if ($moduleId) {
            $where['s.id'] = $moduleId;
            $class = M('school_class')->where('module_id', $moduleId)->getField('id, name', true);
        }
        if ($classId) {
            $where['sc.id'] = $classId;
        }
        // 问卷调查主体内容
        $caption = Db::name('school_article_questionnaire_caption')->where(['is_open' => 1])->order('sort DESC')->field('id, title, type, max_score')->select();
        if ($caption) {
            // 总人数
            $userCount = Db::name('users')->count('user_id');
            // 文章列表
            $order = 'sa.publish_time DESC, sa.sort DESC';
            $articleList = Db::name('school_article sa')
                ->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
                ->where($where)
                ->order($order)
                ->field('sa.id, sa.title, s.name module_name, sc.name class_name');
            if (!$isExport) {
                $count = Db::name('school_article sa')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')->where($where)->count('sa.id');
                $page = new Page($count, 10);
                $articleList = $articleList->limit($page->firstRow . ',' . $page->listRows);
            }
            $articleList = $articleList->select();
            $dataList = [];
            foreach ($articleList as &$article) {
                // 文章调查问卷回答
                $answer = Db::name('school_article_questionnaire_answer')->where(['article_id' => $article['id']])->field('caption_id, score, content')->select();
                // 文章调查问卷人数
                $finishCount = Db::name('school_article_questionnaire_answer')->where(['article_id' => $article['id']])->group('user_id')->count('user_id');
                $articleCaption = [];
                $captionSum = 0;
                $captionAvg = 0;
                foreach ($caption as $key => $item) {
                    switch ($item['type']) {
                        case 1:
                            $typeDesc = '评分';
                            break;
                        case 2:
                            $typeDesc = '评价';
                            break;
                        case 3:
                            $typeDesc = '单选';
                            break;
                        case 4:
                            $typeDesc = '多选';
                            break;
                    }
                    $answerCount = 0;
                    $answerSum = 0;
                    foreach ($answer as $value) {
                        if ($value['caption_id'] == $item['id']) {
                            $answerCount += 1;
                            $answerSum += $value['score'];
                        }
                    }
                    // 文章问卷调查主体数据
                    $articleCaption[$key] = [
                        'title' => $item['title'],
                        'type' => $item['type'],
                        'type_desc' => $typeDesc,
                        'max_score' => $item['max_score'],
                        'avg_score' => $answerCount != 0 ? bcdiv($answerSum, $answerCount, 1) : '0.0',
                    ];
                    // 问卷总分
                    $captionSum += $item['max_score'];
                    // 问卷平均分
                    $captionAvg += $articleCaption[$key]['avg_score'];
                }
                $article['caption_list'] = $articleCaption;
                $article['caption_list_count'] = count($articleCaption);
                $article['caption_sum'] = $captionSum . '';
                $article['caption_avg'] = $captionAvg . '';
                $article['user_count'] = $userCount;
                $article['finish_count'] = $finishCount;
                if ($isExport) {
                    $captionTitle = '';
                    $captionType = '';
                    $captionMax = '';
                    $captionAvg = '';
                    foreach ($articleCaption as $key => $item) {
                        $captionTitle .= ($key + 1) . '、' . $item['title'] . "\n\r";
                        $captionType .= $item['type_desc'] . "\n\r";
                        $captionMax .= $item['max_score'] . "\n\r";
                        $captionAvg .= $item['avg_score'] . "\n\r";
                    }
                    $dataList[] = [
                        $article['id'],
                        $article['title'],
                        $article['module_name'],
                        $article['class_name'],
                        $captionTitle,
                        $captionType,
                        $captionMax,
                        $captionAvg,
                        $article['caption_sum'],
                        $article['caption_avg'],
                        $article['user_count'],
                        $article['finish_count'],
                    ];
                }
            }
        } else {
            $page = new Page(0, 10);
            $articleList = [];
            $dataList = [];
        }
        if (!$isExport) {
            $this->assign('title', $title);
            $this->assign('module_id', $moduleId);
            $this->assign('module', $module);
            $this->assign('class_id', $classId);
            $this->assign('class', $class ?? []);
            $this->assign('page', $page);
            $this->assign('list', $articleList);
            return $this->fetch('statistics_list');
        } else {
            // 表头
            $headList = [
                '课程ID', '课程标题', '所属模块', '所属分类', '问卷题目标题', '问卷题目类型', '问卷题目总分', '问卷题目平均分',
                '问卷总分', '问卷平均分', '总人数', '完成问卷人数'
            ];
            toCsvExcel($dataList, $headList, 'questionnaire_statistics_list');
        }
    }
}
