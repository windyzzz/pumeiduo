<?php

namespace app\admin\controller\school;

use app\admin\model\SchoolArticleResource;
use app\admin\model\SchoolArticleTempResource;
use think\Db;
use think\Page;

class Article extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 用户学习课程列表
     * @return mixed
     */
    public function userCourseList()
    {
        $isExport = I('is_export', '');     // 是否导出
//        $where = ['distribut_level' => ['NEQ', 1]];
        $where = [];
        if ($appGrade = I('app_grade', '')) {
            $where['distribut_level'] = $appGrade;
        }
        if ($svipGrade = I('svip_grade', '')) {
            $where['distribut_level'] = 3;
            $where['svip_grade'] = $svipGrade;
        }
        if ($svipLevel = I('svip_level', '')) {
            $where['distribut_level'] = 3;
            $where['svip_level'] = $svipLevel;
        }
        if ($userId = I('user_id', '')) {
            $where['user_id'] = $userId;
        }
        if ($username = I('user_name', '')) {
            $where['user_name'] = $username;
        }
        if ($nickname = I('nickname', '')) {
            $where['nickname'] = $nickname;
        }
        $userList = M('users')->where($where)->order('user_id DESC');
        if (!$isExport) {
            // 用户总数
            $count = M('users')->where($where)->count();
            $page = new Page($count, 10);
            // 用户列表
            $userList = $userList->limit($page->firstRow . ',' . $page->listRows);
        }
        $userList = $userList->select();
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
        $timeFrom = I('time_from', '') ? strtotime(I('time_from')) : '';
        $timeTo = I('time_to', '') ? strtotime(I('time_to')) : '';
        $dataList = [];     // 导出数据
        foreach ($userList as &$user) {
            $user['course_num'] = 0;    // 学习课程数量
            // APP等级
            $user['app_grade_name'] = $this->appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $this->svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $this->svipLevel[$user['svip_level']] : '';
            // 用户达标课程数量
            $userData = [
                'user_id' => $user['user_id'],
                'app_grade' => $user['distribut_level'],
                'svip_grade' => $user['svip_grade'],
                'svip_level' => $user['svip_level'],
            ];
            $res = $this->checkUserCourseNum(false, $userData, $courseIds, $timeFrom, $timeTo);
            $user['course_num'] = $res['course_num'];
            // 用户首次进入商学院的时间
            $firstVisit = M('user_school_config')->where(['type' => 'first_visit', 'user_id' => $user['user_id']])->value('add_time');
            $user['first_visit'] = $firstVisit ? date('Y-m-d H:i:s', $firstVisit) : '';
            $dataList[] = [
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                $user['course_num'],
                $user['school_credit'],
                $user['first_visit'],
                $user['svip_activate_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_activate_time']) : '',
                $user['svip_upgrade_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_upgrade_time']) : '',
                $user['svip_referee_number'],
                $user['grade_referee_num1'],
                $user['grade_referee_num2'],
                $user['grade_referee_num3'],
                $user['grade_referee_num4'],
            ];
        }
        if (!$isExport) {
            $this->assign('app_grade', $this->appGrade);
            $this->assign('svip_grade', $this->svipGrade);
            $this->assign('svip_level', $this->svipLevel);
            $this->assign('select_app_grade', $appGrade);
            $this->assign('select_svip_grade', $svipGrade);
            $this->assign('select_svip_level', $svipLevel);
            $this->assign('user_id', $userId);
            $this->assign('user_name', $username);
            $this->assign('nickname', $nickname);
            $this->assign('time_from', I('time_from', ''));
            $this->assign('time_to', I('time_to', ''));
            $this->assign('page', $page);
            $this->assign('list', $userList);
            return $this->fetch('user_course_list');
        } else {
            // 表头
            $headList = [
                '用户ID', '用户昵称', '用户名', 'APP等级', '代理商等级', '代理商职级', '课程数量', '乐活豆数量', '首次进入商学院',
                '211系统激活时间', '211系统升级代理商时间', '推荐总人数', '推荐游客人数', '推荐优享会员人数', '推荐尊享会员人数', '推荐代理商人数'
            ];
            toCsvExcel($dataList, $headList, 'user_course_list');
        }
    }

    /**
     * 用户学习课程文章列表
     * @return mixed
     */
    public function userCourseArticleList()
    {
        $isExport = I('is_export', '');     // 是否导出
        $userId = I('user_id');
        $title = I('title');
        $learnStatus = I('learn_status', '');
        $questionnaireStatus = I('questionnaire_status', '');
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
        $where = [
            'usa.user_id' => $userId,
            'usa.article_id' => ['IN', $courseIds],
        ];
        if (trim($title)) {
            $where['sa.title'] = ['LIKE', '%' . htmlspecialchars_decode(trim($title)) . '%'];
        }
        if ($learnStatus !== '') {
            $where['usa.status'] = $learnStatus;
        }
        if ($questionnaireStatus !== '') {
            $where['usa.is_questionnaire'] = $questionnaireStatus;
        }
        $userArticle = M('user_school_article usa')->where($where)->join('school_article sa', 'sa.id = usa.article_id')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
            ->field('usa.user_id, usa.article_id, usa.status learn_status, usa.is_questionnaire, sa.title, sa.learn_type, sa.status, sa.publish_time, s.name module_name, sc.name class_name');
        if (!$isExport) {
            // 总数
            $count = M('user_school_article usa')->where($where)->join('school_article sa', 'sa.id = usa.article_id')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')->count();
            $page = new Page($count, 10);
            // 列表
            $userArticle = $userArticle->limit($page->firstRow . ',' . $page->listRows)->order('sa.publish_time DESC, sa.sort DESC');
            $list = $userArticle->select();
            $this->assign('user_id', $userId);
            $this->assign('title', $title);
            $this->assign('learn_status', $learnStatus !== '' ? (int)$learnStatus : '');
            $this->assign('questionnaire_status', $questionnaireStatus !== '' ? (int)$questionnaireStatus : '');
            $this->assign('page', $page);
            $this->assign('list', $list);
            return $this->fetch('user_course_article_list');
        } else {
            $list = $userArticle->select();
            $dataList = [];
            foreach ($list as $item) {
                switch ($item['learn_type']) {
                    case 1:
                        $learnType = '必修';
                        break;
                    case 2:
                        $learnType = '选修';
                        break;
                }
                switch ($item['status']) {
                    case 1:
                        $status = '已发布';
                        break;
                    case 2:
                        $status = '预发布';
                        break;
                    case 3:
                        $status = '不发布';
                        break;
                }
                switch ($item['learn_status']) {
                    case 0:
                        $learnStatusDesc = '未完成';
                        break;
                    case 1:
                        $learnStatusDesc = '已完成';
                        break;
                }
                switch ($item['is_questionnaire']) {
                    case 0:
                        $questionnaireStatusDesc = '未完成';
                        break;
                    case 1:
                        $questionnaireStatusDesc = '已完成';
                        break;
                }
                $dataList[] = [
                    $item['user_id'],
                    $item['article_id'],
                    $item['title'],
                    $learnType,
                    $item['module_name'],
                    $item['class_name'],
                    $status,
                    date('Y-m-d H:i:s', $item['publish_time']),
                    $learnStatusDesc,
                    $questionnaireStatusDesc
                ];
            }
            // 表头
            $headList = [
                '用户ID', '文章ID', '标题', '学习类型', '所属模块', '所属分类', '发布状态', '发布时间', '学习状态', '是否完成调查问卷'
            ];
            toCsvExcel($dataList, $headList, 'user_course_article_list');
        }
    }

    /**
     * 用户学习达标列表
     * @return mixed
     */
    public function userStandardList()
    {
        $isExport = I('is_export', '');     // 是否导出
        $isReach = I('is_reach', '');       // 是否达标
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
        $where = ['article_id' => ['IN', $courseIds]];
        if ($appGrade = I('app_grade', '')) {
            $where['u.distribut_level'] = $appGrade;
        }
        if ($svipGrade = I('svip_grade', '')) {
            $where['u.distribut_level'] = 3;
            $where['u.svip_grade'] = $svipGrade;
        }
        if ($svipLevel = I('svip_level', '')) {
            $where['u.distribut_level'] = 3;
            $where['u.svip_level'] = $svipLevel;
        }
        if ($userId = I('user_id', '')) {
            $where['u.user_id'] = $userId;
        }
        if ($username = I('user_name', '')) {
            $where['user_name'] = $username;
        }
        if ($nickname = I('nickname', '')) {
            $where['nickname'] = $nickname;
        }
        $userCourseLog = M('user_school_article usa')
            ->join('users u', 'u.user_id = usa.user_id')
            ->join('distribut_level dl', 'dl.level_id = u.distribut_level')
            ->where($where)
            ->group('usa.user_id')
            ->field('u.user_id, u.nickname, u.user_name, u.school_credit, u.distribut_level, u.svip_grade, u.svip_level');
        if (!$isExport && $isReach === '') {
            // 用户学习课程记录总数
            $count = M('user_school_article usa')
                ->join('users u', 'u.user_id = usa.user_id')
                ->join('distribut_level dl', 'dl.level_id = u.distribut_level')
                ->where($where)
                ->group('usa.user_id')->count();
            // 用户课程学习记录
            $page = new Page($count, 10);
            $userCourseLog = $userCourseLog->limit($page->firstRow . ',' . $page->listRows);
        }
        $userCourseLog = $userCourseLog->select();
        $dataList = [];     // 导出数据
        foreach ($userCourseLog as $k => &$log) {
            $log['is_reach'] = 0;       // 未达标
            $log['course_num'] = 0;     // 用户课程数量
            // APP等级
            $log['app_grade_name'] = $this->appGrade[$log['distribut_level']];
            // 代理商等级
            $log['svip_grade_name'] = $log['distribut_level'] == 3 ? $this->svipGrade[$log['svip_grade']] : '';
            // 代理商等级
            $log['svip_level_name'] = $log['distribut_level'] == 3 ? $this->svipLevel[$log['svip_level']] : '';
            // 检查是否达标
            /*
             * 查看课程数量
             */
            $user = [
                'user_id' => $log['user_id'],
                'app_grade' => $log['distribut_level'],
                'svip_grade' => $log['svip_grade'],
                'svip_level' => $log['svip_level'],
            ];
            $res = $this->checkUserCourseNum(true, $user, $courseIds);
            $log['course_num'] = $res['course_num'];
            if ($res['status'] == 1) {
                $log['is_reach'] = 1;
            } else {
                /*
                 * 查看乐活豆数量
                 */
                $user = [
                    'school_credit' => $log['school_credit'],
                    'app_grade' => $log['distribut_level'],
                    'svip_grade' => $log['svip_grade'],
                    'svip_level' => $log['svip_level'],
                ];
                $res = $this->checkUserSchoolCredit($user);
                if ($res['status'] == 1) {
                    $log['is_reach'] = 1;
                }
            }
            if ($isReach !== '') {
                if ($log['is_reach'] != $isReach) {
                    unset($userCourseLog[$k]);
                    continue;
                }
            }
            $dataList[] = [
                $log['user_id'],
                $log['nickname'],
                $log['user_name'],
                $log['app_grade_name'],
                $log['svip_grade_name'],
                $log['svip_level_name'],
                $log['course_num'],
                $log['school_credit'],
                $log['is_reach'] == 1 ? '已达标' : '未达标'
            ];
        }
        if (!$isExport) {
            if ($isReach === '') {
                $this->assign('app_grade', $this->appGrade);
                $this->assign('svip_grade', $this->svipGrade);
                $this->assign('svip_level', $this->svipLevel);
                $this->assign('select_app_grade', $appGrade);
                $this->assign('select_svip_grade', $svipGrade);
                $this->assign('select_svip_level', $svipLevel);
                $this->assign('user_id', $userId);
                $this->assign('user_name', $username);
                $this->assign('nickname', $nickname);
                $this->assign('is_reach', $isReach);
                $this->assign('page', $page);
                $this->assign('log', $userCourseLog);
                return $this->fetch('user_standard_list');
            } else {
                $this->assign('app_grade', $this->appGrade);
                $this->assign('svip_grade', $this->svipGrade);
                $this->assign('svip_level', $this->svipLevel);
                $this->assign('select_app_grade', $appGrade);
                $this->assign('select_svip_grade', $svipGrade);
                $this->assign('select_svip_level', $svipLevel);
                $this->assign('user_id', $userId);
                $this->assign('user_name', $username);
                $this->assign('nickname', $nickname);
                $this->assign('is_reach', (int)$isReach);
                $this->assign('log', $userCourseLog);
                return $this->fetch('user_standard_list_2');
            }
        } else {
            // 表头
            $headList = [
                '用户ID', '用户昵称', '用户名', 'APP等级', '代理商等级', '代理商职级', '课程数量', '乐活豆数量', '是否达标'
            ];
            toCsvExcel($dataList, $headList, 'user_standard_list');
        }
    }

    /**
     * 检查用户是否满足课程数量达标
     * @param bool $isCheck 是否检查达标
     * @param array $user 用户信息
     * @param array $courseIds 学习课程IDs
     * @param string $timeFrom 学习时间开始
     * @param string $timeTo 学习时间结束
     * @return array
     */
    private function checkUserCourseNum($isCheck, $user, $courseIds, $timeFrom = '', $timeTo = '')
    {
        $where = [
            'user_id' => $user['user_id'],
            'article_id' => ['IN', $courseIds],
        ];
        if ($isCheck) $where['status'] = 1;
        if ($timeFrom && $timeTo) {
            $where['finish_time'] = ['BETWEEN', [$timeFrom, $timeTo]];
        }
        // 用户学习课程记录总数
        $userCourseNum = M('user_school_article')->where($where)->getField('count(article_id) as count');
        $userCourseNum = $userCourseNum ?? 0;
        // 学习规则达标设置
        $return = ['status' => 0, 'course_num' => $userCourseNum];
        if ($isCheck) {
            $schoolStandard = cache('school_standard');
            if ($schoolStandard && is_array($schoolStandard)) {
                foreach ($schoolStandard as $v) {
                    if ($v['type'] == 2) {
                        continue;
                    }
                    if ($user['app_grade'] == $v['app_grade'] && $user['svip_grade'] == $v['distribute_grade'] && $user['svip_level'] == $v['distribute_level']) {
                        if ($userCourseNum >= $v['num']) {
                            $return['status'] = 1;
                        }
                        break;
                    }
                }
            }
        }
        return $return;
    }

    /**
     * 检查用户是否满足乐活豆数量达标
     * @param $user
     * @return array
     */
    private function checkUserSchoolCredit($user)
    {
        // 学习规则达标设置
        $return = ['status' => 0];
        $schoolStandard = cache('school_standard');
        if ($schoolStandard && is_array($schoolStandard)) {
            foreach ($schoolStandard as $v) {
                if ($v['type'] == 1) {
                    continue;
                }
                if ($user['app_grade'] == $v['app_grade'] && $user['svip_grade'] == $v['distribute_grade'] && $user['svip_level'] == $v['distribute_level']) {
                    if ($user['school_credit'] >= $v['num']) {
                        $return['status'] = 1;
                    }
                    break;
                }
            }
        }
        return $return;
    }

    /**
     * 分类文章
     * @return mixed
     */
    public function article()
    {
        $type = I('type');
        $classId = I('class_id');
        $articleId = I('article_id', 0);
        if (IS_POST) {
            $param = I('post.');
            unset($param['type']);
            unset($param['article_id']);
            // 验证参数
            $validate = validate('School');
            if (!$validate->scene('article_add')->check($param)) {
                return $this->ajaxReturn(['status' => 0, 'msg' => $validate->getError()]);
            }
            // 是否是学习课程
            $schoolClass = M('school_class')->where(['id' => $param['class_id']])->find();
            if (empty($schoolClass)) {
                return $this->ajaxReturn(['status' => 0, 'msg' => '模块分类不存在']);
            } else {
                if ($schoolClass['is_learn'] == 1 && $param['learn_type'] == 0) {
                    return $this->ajaxReturn(['status' => 0, 'msg' => '学习课程分类下的文章需要选择必修或选修']);
                }
            }
            // APP等级限制
            if (empty($param['app_grade'])) {
                $param['app_grade'] = '0';
            } else {
                if (in_array('-1', $param['app_grade'])) {
                    $param['app_grade'] = '-1';
                } elseif (in_array('0', $param['app_grade'])) {
                    $param['app_grade'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($param['app_grade'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $param['app_grade'] = rtrim($distributeLevel, ',');
                }
            }
            // 代理商等级限制
            if (empty($param['distribute_grade'])) {
                $param['distribute_grade'] = '0';
            } else {
                if (in_array('-1', $param['distribute_grade'])) {
                    $param['distribute_grade'] = '-1';
                } elseif (in_array('0', $param['distribute_grade'])) {
                    $param['distribute_grade'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($param['distribute_grade'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $param['distribute_grade'] = rtrim($distributeLevel, ',');
                }
            }
            // 代理商职级限制
            if (empty($param['distribute_level'])) {
                $param['distribute_level'] = '0';
            } else {
                if (in_array('0', $param['distribute_level'])) {
                    $param['distribute_level'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($param['distribute_level'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $param['distribute_level'] = rtrim($distributeLevel, ',');
                }
            }
            // 封面图上传到OSS服务器
            if (!empty($param['cover'])) {
                if (strstr($param['cover'], 'aliyuncs.com')) {
                    // 原图
                    $param['cover'] = M('school_article')->where(['id' => $articleId])->value('cover');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($param['cover'], strrpos($param['cover'], '/public/') + 8);
                    $fileName = substr($param['cover'], strrpos($param['cover'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $this->ossClient->uploadFile($filePath, $object);
                    if (!$return_url) {
                        return $this->ajaxReturn(['status' => 0, 'msg' => 'ERROR：' . $this->ossClient->getError()]);
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $param['cover'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
            }
            // 发布时间
            $param['publish_time'] = strtotime($param['publish_time']);
            if ($articleId) {
                $publishTime = M('school_article')->where(['id' => $articleId])->value('publish_time');
                if ($publishTime != $param['publish_time']) {
                    $param['status'] = 2;   // 预发布
                    $param['up_time'] = NOW_TIME;
                }
                M('school_article')->where(['id' => $articleId])->update($param);
            } else {
                $param['status'] = 2;   // 预发布
                $param['add_time'] = NOW_TIME;
                $articleId = M('school_article')->add($param);
            }
            // 内容的视频、音频处理
            if (strpos($param['content'], 'src=&quot;/public/') !== false) {
                $content = explode('src=&quot;/public/', $param['content']);
                $tempContent = [];
                foreach ($content as $key => $value) {
                    if ($key == 0) continue;
                    $tempContent[] = [
                        'article_id' => $articleId,
                        'local_path' => '/public/' . substr($value, 0, 61)
                    ];
                }
                M('school_article_temp_resource')->where(['article_id' => $articleId])->delete();
                (new SchoolArticleTempResource())->saveAll($tempContent);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => ['type' => $type, 'class_id' => $classId]]);
        }
        if (empty($classId)) {
            $this->error('请先创建分类', U('school.module/' . $type));
        }
        if ($articleId) {
            $articleInfo = M('school_article')->where(['id' => $articleId])->find();
            $articleInfo['app_grade'] = explode(',', $articleInfo['app_grade']);
            $articleInfo['distribute_grade'] = explode(',', $articleInfo['distribute_grade']);
            $articleInfo['distribute_level'] = explode(',', $articleInfo['distribute_level']);
            $cover = explode(',', $articleInfo['cover']);
            $articleInfo['cover'] = $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'img:') + 4));
            $articleInfo['publish_time'] = date('Y-m-d H:i:s', $articleInfo['publish_time']);
        } else {
            $articleInfo = [];
            $articleInfo['sort'] = 0;
            $articleInfo['integral'] = 0;
            $articleInfo['learn_time'] = 300;
            $articleInfo['credit'] = 0;
            $articleInfo['publish_time'] = date('Y-m-d H:i:s', time());
        }
        $this->assign('type', $type);
        $this->assign('class_id', $classId);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('info', $articleInfo);
        $this->assign('article_id', $articleId);
        return $this->fetch();
    }

    /**
     * 分类文章（素材专区）
     * @return mixed
     * @throws \Exception
     */
    public function article_6()
    {
        $type = I('type');
        $classId = I('class_id');
        $articleId = I('article_id', 0);
        if (IS_POST) {
            $postData = I('post.');
            $file = request()->file('file');    // 附件
            // 验证参数
            $validate = validate('School');
            if (!$validate->scene('article_add_6')->check($postData)) {
                $this->error($validate->getError());
            }
            Db::startTrans();
            // 文章信息
            $articleParam = [
                'class_id' => $classId,
                'content' => $postData['content'],
                'sort' => $postData['sort'],
                'integral' => $postData['integral'],
                'publish_time' => strtotime($postData['publish_time']),
                'status' => 2,   // 预发布
            ];
            if (!empty($file)) {
                // 上传附件
                $savePath = 'school/' . date('Y') . '/' . date('m-d') . '/';
                $info = $file->move(UPLOAD_PATH . $savePath, false);  // 保留文件原名
                if ($info) {
                    $url = '/' . UPLOAD_PATH . $savePath . $info->getSaveName();
                    // 上传到OSS服务器
                    $res = (new Oss)->uploadFile('file', $url);
                    if ($res['status'] == 0) {
                        $this->error('ERROR：' . $res['msg']);
                    } else {
                        $fileLink = 'url:' . $res['object'] . ',type:' . $info->getExtension();
                        unset($info);
                        unlink(PUBLIC_PATH . substr($url, strrpos($url, 'public') + 7));
                        $articleParam['file'] = $fileLink;
                    }
                }
            }
            // APP等级限制
            if (empty($postData['app_grade'])) {
                $articleParam['app_grade'] = '0';
            } else {
                if (in_array('-1', $postData['app_grade'])) {
                    $articleParam['app_grade'] = '-1';
                } elseif (in_array('0', $postData['app_grade'])) {
                    $articleParam['app_grade'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($postData['app_grade'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $articleParam['app_grade'] = rtrim($distributeLevel, ',');
                }
            }
            // 代理商等级限制
            if (empty($postData['distribute_grade'])) {
                $articleParam['distribute_grade'] = '0';
            } else {
                if (in_array('-1', $postData['distribute_grade'])) {
                    $articleParam['distribute_grade'] = '-1';
                } elseif (in_array('0', $postData['distribute_grade'])) {
                    $articleParam['distribute_grade'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($postData['distribute_grade'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $articleParam['distribute_grade'] = rtrim($distributeLevel, ',');
                }
            }
            // 代理商职级限制
            if (empty($postData['distribute_level'])) {
                $articleParam['distribute_level'] = '0';
            } else {
                if (in_array('-1', $postData['distribute_level'])) {
                    $articleParam['distribute_level'] = '-1';
                } elseif (in_array('0', $postData['distribute_level'])) {
                    $articleParam['distribute_level'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($postData['distribute_level'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $articleParam['distribute_level'] = rtrim($distributeLevel, ',');
                }
            }
            if ($articleId > 0) {
                /*
                 * 更新
                 */
                // 素材信息
                $resourceParam = [];
                switch ($postData['upload_content']) {
                    case 1:
                        if (empty($postData['image'])) {
                            $this->error('请上传图片');
                        }
                        // 文章原本的图片素材
                        $articleImage = [];
                        $articleResource = M('school_article_resource')->where(['article_id' => $articleId, 'image' => ['NEQ', '']])->select();
                        if (!empty($articleResource)) {
                            foreach ($articleResource as $resource) {
                                $image = explode(',', $resource['image']);
                                $articleImage[substr($image[0], strrpos($image[0], 'img:') + 4)] = [
                                    'width' => substr($image[1], strrpos($image[1], 'width:') + 6),
                                    'height' => substr($image[2], strrpos($image[2], 'height:') + 7),
                                    'type' => substr($image[3], strrpos($image[3], 'type:') + 5),
                                ];
                            }
                        }
                        // 上传到OSS服务器
                        foreach ($postData['image'] as $image) {
                            if (empty($image)) {
                                continue;
                            }
                            if (strstr($image, 'aliyuncs.com')) {
                                // 原本的图片
                                $image = substr($image, strrpos($image, 'image'));
                                $resourceParam[] = [
                                    'image' => 'img:' . $image . ',width:' . $articleImage[$image]['width'] . ',height:' . $articleImage[$image]['height'] . ',type:' . $articleImage[$image]['type'],
                                    'get_image_info' => 1,
                                    'video' => ''
                                ];
                                continue;
                            }
                            $filePath = PUBLIC_PATH . substr($image, strrpos($image, '/public/') + 8);
                            $fileName = substr($image, strrpos($image, '/') + 1);
                            $object = 'image/' . date('Y/m/d/H/') . $fileName;
                            $return_url = $this->ossClient->uploadFile($filePath, $object);
                            if (!$return_url) {
                                $this->error('ERROR：' . $this->ossClient->getError());
                            } else {
                                // 图片信息
                                $imageInfo = getimagesize($filePath);
                                $resourceParam[] = [
                                    'image' => 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1),
                                    'get_image_info' => 1,
                                    'video' => ''
                                ];
                                unlink($filePath);
                            }
                        }
                        break;
                    case 2:
                        if (empty($postData['video'])) {
                            $this->error('请上传视频');
                        }
                        if (strstr($postData['video'], 'http')) {
                            // 原本的视频
                            $resourceParam[] = [
                                'video' => substr($postData['video'], strrpos($postData['video'], 'video')),
                                'video_cover' => $postData['video_cover'],
                                'video_axis' => $postData['video_axis'],
                                'image' => '',
                            ];
                        } else {
                            // 处理视频封面图
                            $videoCover = getVideoCoverImages($postData['video'], 'upload/school/video_cover/temp/');
                            $resourceParam[] = [
                                'video' => $postData['video'],
                                'video_cover' => $videoCover['path'],
                                'video_axis' => $videoCover['axis'],
                                'image' => '',
                            ];
                        }
                        break;
                }
                // 文章信息
                $articleParam['up_time'] = NOW_TIME;
                M('school_article')->where(['id' => $articleId])->update($articleParam);
                // 文章素材信息
                if (!empty($resourceParam)) {
                    M('school_article_resource')->where(['article_id' => $articleId])->delete();
                    foreach ($resourceParam as &$resource) {
                        $resource['article_id'] = $articleId;
                        $resource['add_time'] = NOW_TIME;
                    }
                    $articleResource = new SchoolArticleResource();
                    $articleResource->saveAll($resourceParam);
                }
            } else {
                /*
                 * 添加
                 */
                // 素材信息
                $resourceParam = [];
                switch ($postData['upload_content']) {
                    case 1:
                        if (empty($postData['image'])) {
                            $this->error('请上传图片');
                        }
                        // 上传到OSS服务器
                        foreach ($postData['image'] as $image) {
                            if (empty($image)) {
                                continue;
                            }
                            $filePath = PUBLIC_PATH . substr($image, strrpos($image, '/public/') + 8);
                            $fileName = substr($image, strrpos($image, '/') + 1);
                            $object = 'image/' . date('Y/m/d/H/') . $fileName;
                            $return_url = $this->ossClient->uploadFile($filePath, $object);
                            if (!$return_url) {
                                $this->error('ERROR：' . $this->ossClient->getError());
                            } else {
                                // 图片信息
                                $imageInfo = getimagesize($filePath);
                                $resourceParam[] = [
                                    'image' => 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1),
                                    'get_image_info' => 1,
                                    'video' => ''
                                ];
                                unlink($filePath);
                            }
                        }
                        break;
                    case 2:
                        if (empty($postData['video'])) {
                            $this->error('请上传视频');
                        }
                        // 处理视频封面图
                        $videoCover = getVideoCoverImages($postData['video'], 'upload/school/video_cover/temp/');
                        $resourceParam[] = [
                            'video' => $postData['video'],
                            'video_cover' => $videoCover['path'],
                            'video_axis' => $videoCover['axis'],
                            'image' => '',
                        ];
                        break;
                }
                // 文章信息
                $articleParam['add_time'] = NOW_TIME;
                $articleId = M('school_article')->add($articleParam);
                // 文章素材信息
                if (!empty($resourceParam)) {
                    foreach ($resourceParam as &$resource) {
                        $resource['article_id'] = $articleId;
                        $resource['add_time'] = NOW_TIME;
                    }
                    $articleResource = new SchoolArticleResource();
                    $articleResource->saveAll($resourceParam);
                }
            }
            Db::commit();
            $this->success('处理成功', U('school.module/') . $type . '/class_id/' . $classId);
        }
        if (empty($classId)) {
            $this->error('请先创建模块分类', U('school.module/' . $type));
        }
        if ($articleId) {
            $articleInfo = M('school_article')->where(['id' => $articleId])->find();
            $articleInfo['app_grade'] = explode(',', $articleInfo['app_grade']);
            $articleInfo['distribute_grade'] = explode(',', $articleInfo['distribute_grade']);
            $articleInfo['distribute_level'] = explode(',', $articleInfo['distribute_level']);
            $articleInfo['publish_time'] = date('Y-m-d H:i:s', $articleInfo['publish_time']);
            // 文章素材信息
            $articleResource = M('school_article_resource')->where(['article_id' => $articleId])->select();
            foreach ($articleResource as &$resource) {
                if (!empty($resource['image'])) {
                    $image = explode(',', $resource['image']);
                    $resource['image'] = $this->ossClient::url(substr($image[0], strrpos($image[0], 'img:') + 4));
                    $articleInfo['upload_content'] = 1;
                }
                if (!empty($resource['video'])) {
                    $resource['video'] = $this->ossClient::url($resource['video']);
                    $articleInfo['upload_content'] = 2;
                }
            }
            // 附件
            if (!empty($articleInfo['file'])) {
                $file = explode(',', $articleInfo['file']);
                $articleInfo['file'] = substr($file[0], strrpos($file[0], '/') + 1);
            }
        } else {
            $articleInfo = [];
            $articleInfo['sort'] = 0;
            $articleInfo['integral'] = 0;
            $articleInfo['publish_time'] = date('Y-m-d H:i:s', time());
            $articleInfo['upload_content'] = 1;
            $articleResource = [];
        }
        $this->assign('type', $type);
        $this->assign('class_id', $classId);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('info', $articleInfo);
        $this->assign('resource', $articleResource);
        $this->assign('article_id', $articleId);
        return $this->fetch('article_6');
    }

    /**
     * 停止发布文章
     */
    public function stopArticle()
    {
        $type = I('type');
        $classId = I('class_id');
        $articleId = I('article_id', 0);
        M('school_article')->where(['id' => $articleId])->update([
            'status' => 3,
            'up_time' => NOW_TIME
        ]);
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => ['type' => $type, 'class_id' => $classId]]);
    }

    /**
     * 重新发布文章
     */
    public function startArticle()
    {
        $type = I('type');
        $classId = I('class_id');
        $articleId = I('article_id', 0);
        M('school_article')->where(['id' => $articleId])->update([
            'status' => 1,
            'up_time' => NOW_TIME
        ]);
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => ['type' => $type, 'class_id' => $classId]]);
    }

    /**
     * 删除文章
     */
    public function delArticle()
    {
        $type = I('type');
        $classId = I('class_id');
        $articleId = I('article_id', 0);
        M('school_article')->where(['id' => $articleId])->update([
            'status' => -1,
            'delete_time' => NOW_TIME,
        ]);
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => ['type' => $type, 'class_id' => $classId]]);
    }

    /**
     * 根据类型获取文章列表
     */
    public function ajaxGetArticle()
    {
        $moduleType = I('module_type');
        $articleList = M('school_article sa')
            ->join('school_class sc', 'sc.id = sa.class_id')
            ->join('school s', 's.id = sc.module_id')
            ->where([
                's.type' => $moduleType,
                'sa.status' => 1
            ])
            ->field('sa.id, sa.title')->select();
        $this->ajaxReturn(['status' => 1, 'result' => $articleList]);
    }

    /**
     * 课程列表
     * @return mixed
     */
    public function courseList()
    {
        // 条件
        $where = ['sa.learn_type' => ['IN', [1, 2]]];
        $title = htmlspecialchars_decode(trim(I('title', '')));
        $moduleId = I('module_id', '');
        $classId = I('class_id', '');
        $publishTimeFrom = I('publish_time_from', '') ? strtotime(I('publish_time_from')) : '';
        $publishTimeTo = I('publish_time_to', '') ? strtotime(I('publish_time_to')) : '';
        $learnTimeFrom = I('learn_time_from', '') ? strtotime(I('learn_time_from')) : '';
        $learnTimeTo = I('learn_time_to', '') ? strtotime(I('learn_time_to')) : '';
        $isExport = I('is_export', 0);
        if ($title) {
            $where['sa.title'] = ['LIKE', '%' . $title . '%'];
        }
        if ($moduleId) {
            $where['s.id'] = $moduleId;
            $class = M('school_class')->where('module_id', $moduleId)->getField('id, name', true);
        }
        if ($classId) {
            $where['sc.id'] = $classId;
        }
        if ($appGrade = I('app_grade', '')) {
            $where['sa.app_grade'] = ['LIKE', '%' . $appGrade . '%'];
        }
        if ($svipGrade = I('svip_grade', '')) {
            $where['sa.distribute_grade'] = ['LIKE', '%' . $svipGrade . '%'];
        }
        if ($svipLevel = I('svip_level', '')) {
            $where['sa.distribute_level'] = ['LIKE', '%' . $svipLevel . '%'];
        }
        if ($publishTimeFrom && $publishTimeTo) {
            $where['sa.publish_time'] = ['BETWEEN', [$publishTimeFrom, $publishTimeTo]];
        }
        // 排序
        $order = 'sa.publish_time DESC, sa.sort DESC';
        $sort = I('sort', '');
        $sortBy = I('sort_by', '');
        if ($sort && $sortBy) {
            $order = 'sa.' . $sort . ' ' . $sortBy . ', ' . $order;
        }
        $schoolArticle = M('school_article sa')
            ->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
            ->where($where)
            ->order($order)
            ->field('sa.*, s.name module_name, sc.name class_name');
        if (!$isExport) {
            // 总数
            $count = M('school_article sa')->where($where)->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')->count();
            $page = new Page($count, 10);
            // 列表
            $schoolArticle = $schoolArticle->limit($page->firstRow . ',' . $page->listRows);
        }
        // 数据
        $list = $schoolArticle->select();
        $dataList = [];
        foreach ($list as &$item) {
            if ($item['app_grade'] == 0) {
                $item['app_grade_list'] = ['所有人' . "\r\n"];
            } else {
                $appGradeArr = explode(',', $item['app_grade']);
                foreach ($appGradeArr as $value) {
                    $item['app_grade_list'][] = $this->appGrade[$value] . "\r\n";
                }
            }
            if ($item['distribute_grade'] == 0) {
                $item['distribute_grade_list'] = ['所有人' . "\r\n"];
            } else {
                $svipGradeArr = explode(',', $item['distribute_grade']);
                foreach ($svipGradeArr as $value) {
                    $item['distribute_grade_list'][] = $this->svipGrade[$value] . "\r\n";
                }
            }
            if ($item['distribute_level'] == 0) {
                $item['distribute_level_list'] = ['所有人' . "\r\n"];
            } else {
                $svipLevelArr = explode(',', $item['distribute_level']);
                foreach ($svipLevelArr as $value) {
                    $item['distribute_level_list'][] = $this->svipLevel[$value] . "\r\n";
                }
            }
            switch ($item['learn_type']) {
                case 1:
                    $item['learn_type_desc'] = '必修';
                    break;
                case 2:
                    $item['learn_type_desc'] = '选修';
                    break;
            }
            switch ($item['status']) {
                case 1:
                    $item['status_desc'] = '已发布';
                    break;
                case 2:
                    $item['status_desc'] = '预发布';
                    break;
                case 3:
                    $item['status_desc'] = '不发布';
                    break;
            }
            $item['publish_time_desc'] = date('Y-m-d H:i:s', $item['publish_time']);
            if ($learnTimeFrom && $learnTimeTo) {
                // 根据学习时间统计学习人数
                $learnCount = M('user_school_article')->where([
                    'article_id' => $item['id'],
                    'is_learn' => 1,
                    'finish_time' => ['BETWEEN', [$learnTimeFrom, $learnTimeTo]]
                ])->count('user_id');
                $item['learn'] = $learnCount ?? 0;
            }
            if ($isExport) {
                $dataList[] = [
                    $item['id'],
                    $item['title'],
                    $item['learn_type_desc'],
                    $item['module_name'],
                    $item['class_name'],
                    "\r\n" . implode(' ', $item['app_grade_list']),
                    "\r\n" . implode(' ', $item['distribute_grade_list']),
                    "\r\n" . implode(' ', $item['distribute_level_list']),
                    $item['learn'],
                    $item['share'],
                    $item['click'],
                    $item['status_desc'],
                    $item['publish_time_desc'],
                ];
            }
        }
        if (!$isExport) {
            // 模块列表
            $notModuleType = ['module6', 'module7', 'module8'];
            $module = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
            $this->assign('title', $title);
            $this->assign('module_id', $moduleId);
            $this->assign('module', $module);
            $this->assign('class_id', $classId);
            $this->assign('class', $class ?? []);
            $this->assign('app_grade', $this->appGrade);
            $this->assign('svip_grade', $this->svipGrade);
            $this->assign('svip_level', $this->svipLevel);
            $this->assign('select_app_grade', $appGrade);
            $this->assign('select_svip_grade', $svipGrade);
            $this->assign('select_svip_level', $svipLevel);
            $this->assign('publish_time_from', I('publish_time_from', ''));
            $this->assign('publish_time_to', I('publish_time_to', ''));
            $this->assign('learn_time_from', I('learn_time_from', ''));
            $this->assign('learn_time_to', I('learn_time_to', ''));
            $this->assign('sort', $sort);
            $this->assign('sort_by', $sortBy);
            $this->assign('page', $page);
            $this->assign('list', $list);
            return $this->fetch('course_list');
        } else {
            // 表头
            $headList = [
                '文章ID', '标题', '学习类型', '所属模块', '所属分类', 'APP等级限制', '代理商等级限制', '代理商职级限制', '学习人数', '分享人数', '点击数', '状态', '发布时间'
            ];
            toCsvExcel($dataList, $headList, 'course_list');
        }
    }

    /**
     * 课程用户列表
     * @return mixed
     */
    public function courseUserList()
    {
        $isExport = I('is_export', '');     // 是否导出
        $where = ['usa.article_id' => I('article_id', 0)];
        if ($appGrade = I('app_grade', '')) {
            $where['u.distribut_level'] = $appGrade;
        }
        if ($svipGrade = I('svip_grade', '')) {
            $where['u.distribut_level'] = 3;
            $where['u.svip_grade'] = $svipGrade;
        }
        if ($svipLevel = I('svip_level', '')) {
            $where['u.distribut_level'] = 3;
            $where['u.svip_level'] = $svipLevel;
        }
        if ($userId = I('user_id', '')) {
            $where['u.user_id'] = $userId;
        }
        if ($username = I('user_name', '')) {
            $where['u.user_name'] = $username;
        }
        if ($nickname = I('nickname', '')) {
            $where['u.nickname'] = $nickname;
        }
        $timeFrom = I('time_from', '') ? strtotime(I('time_from')) : '';
        $timeTo = I('time_to', '') ? strtotime(I('time_to')) : '';
        if ($timeFrom && $timeTo) {
            $where['usa.finish_time'] = ['BETWEEN', [$timeFrom, $timeTo]];
        }
        $userList = M('user_school_article usa')
            ->join('school_article sa', 'sa.id = usa.article_id')
            ->join('school_class sc', 'sc.id = sa.class_id')
            ->join('school s', 's.id = sc.module_id')
            ->join('users u', 'u.user_id = usa.user_id')
            ->where($where)
            ->order('u.user_id DESC')
            ->field('sa.*, s.name module_name, sc.name class_name, u.user_id, u.nickname, u.user_name, u.school_credit, u.distribut_level, u.svip_grade, u.svip_level, usa.status, usa.add_time, usa.finish_time');
        if (!$isExport) {
            // 用户总数
            $count = M('user_school_article usa')->join('users u', 'u.user_id = usa.user_id')->where($where)->count();
            $page = new Page($count, 10);
            // 用户列表
            $userList = $userList->limit($page->firstRow . ',' . $page->listRows);
        }
        $userList = $userList->select();
        $dataList = [];
        foreach ($userList as &$user) {
            // APP等级
            $user['app_grade_name'] = $this->appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $this->svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $this->svipLevel[$user['svip_level']] : '';
            switch ($user['learn_type']) {
                case 1:
                    $user['learn_type_desc'] = '必修';
                    break;
                case 2:
                    $user['learn_type_desc'] = '选修';
                    break;
            }
            switch ($user['status']) {
                case 0:
                    $user['status_desc'] = '未完成';
                    break;
                case 1:
                    $user['status_desc'] = '已完成';
                    break;
            }
            $user['add_time_desc'] = date('Y-m-d H:i:s', $user['add_time']);
            $user['finish_time_desc'] = $user['finish_time'] != 0 ? date('Y-m-d H:i:s', $user['finish_time']) : '';
            $dataList[] = [
                $user['id'],
                $user['title'],
                $user['learn_type_desc'],
                $user['module_name'],
                $user['class_name'],
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                $user['school_credit'],
                $user['status_desc'],
                $user['add_time_desc'],
                $user['finish_time_desc'],
            ];
        }
        if (!$isExport) {
            $this->assign('article_id', I('article_id', 0));
            $this->assign('app_grade', $this->appGrade);
            $this->assign('svip_grade', $this->svipGrade);
            $this->assign('svip_level', $this->svipLevel);
            $this->assign('select_app_grade', $appGrade);
            $this->assign('select_svip_grade', $svipGrade);
            $this->assign('select_svip_level', $svipLevel);
            $this->assign('user_id', $userId);
            $this->assign('user_name', $username);
            $this->assign('nickname', $nickname);
            $this->assign('time_from', I('time_from', ''));
            $this->assign('time_to', I('time_to', ''));
            $this->assign('page', $page);
            $this->assign('list', $userList);
            return $this->fetch('course_user_list');
        } else {
            // 表头
            $headList = [
                '文章ID', '标题', '学习类型', '所属模块', '所属分类', '用户ID', '用户昵称', '用户名', 'APP等级', '代理商等级', '代理商职级', '乐活豆数量', '学习状态', '开始学习时间', '学习完成时间'
            ];
            toCsvExcel($dataList, $headList, 'course_user_list');
        }
    }

    /**
     * 迁移文章
     * @return mixed
     */
    public function transferArticle()
    {
        if (IS_POST) {
            $param = I('post.');
            if (empty($param['module_id'])) {
                $this->error('请先选择模块');
            }
            if (empty($param['class_id'])) {
                $this->error('请先选择分类');
            }
            if (empty($param['old_class_id']) && empty($param['article_ids'])) {
                $this->error('请先选择文章');
            }
            if (!empty($param['old_class_id'])) {
                M('school_article')->where(['class_id' => $param['old_class_id']])->update(['class_id' => $param['class_id']]);
            } elseif (!empty($param['article_ids'])) {
                M('school_article')->where(['id' => ['IN', $param['article_ids']]])->update(['class_id' => $param['class_id']]);
            }
            $callback = $param['call_back'];
            $type = M('school s')->join('school_class sc', 'sc.module_id = s.id')->where(['sc.id' => $param['jump_class_id']])->value('s.type');
            echo "<script>parent.{$callback}('{$type}');</script>";
            exit();
        }
        $jumpClass_id = I('jump_class_id', '');
        $oldClassId = I('old_class_id', '');
        $articleIds = I('article_ids', '');
        // 模块列表
        $notModuleType = ['module6', 'module7', 'module8'];
        $module = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
        $this->assign('module', $module);
        $this->assign('jump_class_id', $jumpClass_id);
        $this->assign('old_class_id', $oldClassId);
        $this->assign('article_ids', $articleIds);
        return $this->fetch('transfer_article');
    }
}
