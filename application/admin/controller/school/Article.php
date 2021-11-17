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

    private function articleWhere()
    {
        $where = [];
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
        return $where;
    }

    private function articleWhereOr()
    {
        $whereOr = [];
        $realName = I('real_name', '');
        if (!empty($realName)) {
            $param['real_name'] = htmlspecialchars_decode($realName);
            $whereOr['u.real_name'] = ['LIKE', '%' . $realName . '%'];
            $whereOr['si.real_name'] = ['LIKE', '%' . $realName . '%'];
        }
        return $whereOr;
    }

    /**
     * 获取用户学习的模块数量
     * @param $user
     * @return array
     */
    private function checkUserModuleNum($user)
    {
        $moduleNum = M('user_school_article usa')
            ->join('school_article sa', 'sa.id = usa.article_id')
            ->join('school_class sc', 'sc.id = sa.class_id')
            ->where(['usa.user_id' => $user['user_id']])
            ->group('sc.module_id')
            ->count('usa.id');
        return ['module_num' => $moduleNum];
    }

    /**
     * 检查用户是否满足课程数量达标
     * @param array $user 用户信息
     * @param array $courseIds 学习课程IDs
     * @param bool $isCheck 是否检查达标
     * @param bool $isLearned 是否已经学习完成
     * @param string $timeFrom 学习时间开始
     * @param string $timeTo 学习时间结束
     * @return array
     */
    private function checkUserCourseNum($user, $courseIds, $isCheck = false, $isLearned = false, $timeFrom = '', $timeTo = '')
    {
        $where = [
            'user_id' => $user['user_id'],
            'article_id' => ['IN', $courseIds],
        ];
        if ($isCheck || $isLearned) $where['status'] = 1;
        if ($timeFrom && $timeTo) $where['finish_time'] = ['BETWEEN', [$timeFrom, $timeTo]];
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
     * 导出用户学习课程列表
     * @param array $where
     * @param array $ext
     */
    private function exportUserCourseList($where = [], $ext = [])
    {
        // 数据表
        $table = 'users u';
        // join连接
        $join = [
            ['svip_info si', 'si.user_id = u.user_id', 'LEFT'],
        ];
        // 排序
        $order = ['u.user_id' => 'DESC'];
        // 字段
        $field = 'u.*, si.real_name svip_real_name, si.svip_activate_time, si.svip_upgrade_time, si.svip_referee_number, si.grade_referee_num1, si.grade_referee_num2, si.grade_referee_num3, si.grade_referee_num4, si.network_parent_user_name, si.network_parent_real_name, si.customs_user_name, si.customs_real_name';
        $path = UPLOAD_PATH . 'school/excel/' . date('Y-m-d') . '/';
        $name = 'userCourseList_' . date('Y-m-d_H-i-s') . '.csv';
        // 导出记录
        M('export_file')->add([
            'type' => 'school_user_course',
            'path' => $path,
            'name' => $name,
            'table' => $table,
            'join' => json_encode($join),
            'condition' => json_encode($where),
            'order' => json_encode($order),
            'field' => $field,
            'ext' => json_encode($ext),
            'add_time' => NOW_TIME
        ]);
    }

    /**
     * 导出用户学习课程额外数据列表
     * @param array $where
     * @param array $ext
     */
    private function exportUserCourseListExt($where = [], $ext = [])
    {
        // 数据表
        $table = 'users u';
        // join连接
        $join = [
            ['svip_info si', 'si.user_id = u.user_id', 'LEFT'],
            ['user_school_article usa', 'u.user_id = usa.user_id', 'LEFT'],
            ['school_article sa', 'sa.id = usa.article_id', 'LEFT']
        ];
        // 排序
        $order = ['u.user_id' => 'DESC'];
        // 字段
        $field = 'u.*, si.real_name svip_real_name, si.svip_activate_time, si.svip_upgrade_time, si.svip_referee_number, si.grade_referee_num1, si.grade_referee_num2, si.grade_referee_num3, si.grade_referee_num4, si.network_parent_user_name, si.network_parent_real_name, si.customs_user_name, si.customs_real_name,
        usa.article_id, usa.status learn_status, usa.add_time, usa.finish_time, usa.is_questionnaire, sa.class_id, sa.title, sa.learn_type, sa.status, sa.publish_time';
        $path = UPLOAD_PATH . 'school/excel/' . date('Y-m-d') . '/';
        $name = 'userCourseList_ext_' . date('Y-m-d_H-i-s') . '.csv';
        // 导出记录
        M('export_file')->add([
            'type' => 'school_user_course_ext',
            'path' => $path,
            'name' => $name,
            'table' => $table,
            'join' => json_encode($join),
            'condition' => json_encode($where),
            'order' => json_encode($order),
            'field' => $field,
            'ext' => json_encode($ext),
            'add_time' => NOW_TIME
        ]);
    }

    /**
     * 导出用户结业情况列表
     * @param array $where
     * @param array $ext
     */
    private function exportUserGraduateList($where = [], $ext = [])
    {
        // 数据表
        $table = 'users u';
        // join连接
        $join = [
            ['svip_info si', 'si.user_id = u.user_id', 'LEFT'],
        ];
        // 排序
        $order = ['u.user_id' => 'DESC'];
        // 字段
        $field = 'u.*, si.real_name svip_real_name, si.svip_activate_time, si.svip_upgrade_time, si.svip_referee_number, si.grade_referee_num1, si.grade_referee_num2, si.grade_referee_num3, si.grade_referee_num4, si.network_parent_user_name, si.network_parent_real_name, si.customs_user_name, si.customs_real_name';
        $path = UPLOAD_PATH . 'school/excel/' . date('Y-m-d') . '/';
        $name = 'userGraduateList_' . date('Y-m-d_H-i-s') . '.csv';
        // 导出记录
        M('export_file')->add([
            'type' => 'school_user_graduate',
            'path' => $path,
            'name' => $name,
            'table' => $table,
            'join' => json_encode($join),
            'condition' => json_encode($where),
            'order' => json_encode($order),
            'field' => $field,
            'ext' => json_encode($ext),
            'add_time' => NOW_TIME
        ]);
    }

    /**
     * 用户学习课程列表
     * @return mixed
     */
    public function userCourseList()
    {
        $isExport = I('is_export', '');     // 是否导出
        // 基础where
        $where = $this->articleWhere();
//        $where['u.distribut_level'] = ['NEQ', 1];
        // 基础whereOr
        $whereOr = $this->articleWhereOr();
        // 时间where
        $activateTimeFrom = I('activate_time_from', '') ? htmlspecialchars_decode(I('activate_time_from')) : '';
        if (strpos($activateTimeFrom, '+')) $activateTimeFrom = str_replace('+', ' ', $activateTimeFrom);
        $activateTimeTo = I('activate_time_to', '') ? htmlspecialchars_decode(I('activate_time_to')) : '';
        if (strpos($activateTimeTo, '+')) $activateTimeTo = str_replace('+', ' ', $activateTimeTo);
        if ($activateTimeFrom && $activateTimeTo) {
            $where['si.svip_activate_time'] = ['BETWEEN', [strtotime($activateTimeFrom), strtotime($activateTimeTo)]];
        }
        $upgradeTimeFrom = I('upgrade_time_from', '') ? htmlspecialchars_decode(I('upgrade_time_from')) : '';
        if (strpos($upgradeTimeFrom, '+')) $upgradeTimeFrom = str_replace('+', ' ', $upgradeTimeFrom);
        $upgradeTimeTo = I('upgrade_time_to', '') ? htmlspecialchars_decode(I('upgrade_time_to')) : '';
        if (strpos($upgradeTimeTo, '+')) $upgradeTimeTo = str_replace('+', ' ', $upgradeTimeTo);
        if ($upgradeTimeFrom && $upgradeTimeTo) {
            $where['si.svip_upgrade_time'] = ['BETWEEN', [strtotime($upgradeTimeFrom), strtotime($upgradeTimeTo)]];
        }
        // 学习时间
        $learnTimeFrom = I('learn_time_from', '') ? htmlspecialchars_decode(I('learn_time_from')) : '';
        if (strpos($learnTimeFrom, '+')) $learnTimeFrom = str_replace('+', ' ', $learnTimeFrom);
        $learnTimeTo = I('learn_time_to', '') ? htmlspecialchars_decode(I('learn_time_to')) : '';
        if (strpos($learnTimeTo, '+')) $learnTimeTo = str_replace('+', ' ', $learnTimeTo);
        $ext = ['learn_time_from' => strtotime($learnTimeFrom), 'learn_time_to' => strtotime($learnTimeTo), 'where_or' => $whereOr];
        // 列表数据
        $userList = M('users u')->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')->where($where)->order('u.user_id DESC')
            ->field('u.*, si.real_name svip_real_name, si.svip_activate_time, si.svip_upgrade_time, si.svip_referee_number, si.grade_referee_num1, si.grade_referee_num2, si.grade_referee_num3, si.grade_referee_num4, si.network_parent_user_name, si.network_parent_real_name, si.customs_user_name, si.customs_real_name');
        if ($isExport) {
            $this->exportUserCourseList($where, $ext);
            $this->exportUserCourseListExt($where, $ext);
            $this->ajaxReturn(['status' => 1, 'msg' => '添加导出队列成功，请耐心等待后台导出']);
        } else {
            // 用户总数
            $count = M('users u')->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')->where($where);
            if (!empty($whereOr)) {
                $count = $count->where(function ($query) use ($whereOr) {
                    $query->whereOr($whereOr);
                });
            }
            $count = $count->count();
            $page = new Page($count, 10);
            // 用户列表
            $userList = $userList->limit($page->firstRow . ',' . $page->listRows);
        }
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $userList = $userList->select();
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
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
            $res = $this->checkUserCourseNum($userData, $courseIds, false, false, strtotime($learnTimeFrom), strtotime($learnTimeTo));
            $user['course_num'] = $res['course_num'];
            // 用户首次进入商学院的时间
            $firstVisit = M('user_school_config')->where(['type' => 'first_visit', 'user_id' => $user['user_id']])->value('add_time');
            $user['first_visit'] = $firstVisit ? date('Y-m-d H:i:s', $firstVisit) : '';
        }
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('select_app_grade', I('app_grade', ''));
        $this->assign('select_svip_grade', I('svip_grade', ''));
        $this->assign('select_svip_level', I('svip_level', ''));
        $this->assign('user_id', I('user_id'));
        $this->assign('user_name', I('user_name'));
        $this->assign('nickname', I('nickname'));
        $this->assign('real_name', I('real_name'));
        $this->assign('activate_time_from', $activateTimeFrom);
        $this->assign('activate_time_to', $activateTimeTo);
        $this->assign('upgrade_time_from', $upgradeTimeFrom);
        $this->assign('upgrade_time_to', $upgradeTimeTo);
        $this->assign('learn_time_from', $learnTimeFrom);
        $this->assign('learn_time_to', $learnTimeTo);
        $this->assign('page', $page);
        $this->assign('list', $userList);
        return $this->fetch('user_course_list');
    }

    /**
     * 用户学习课程文章列表
     * @return mixed
     */
    public function userCourseArticleList()
    {
        $isExport = I('is_export', '');     // 是否导出
        $moduleId = I('module_id', 0);
        $classId = I('class_id', 0);
        $userId = I('user_id');
        $title = I('title');
        $learnStatus = I('learn_status', '');
        $questionnaireStatus = I('questionnaire_status', '');
        $articleWhere = [
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ];
        // 模块列表
        $notModuleType = ['module6', 'module7', 'module8'];
        $moduleList = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
        // 模块分类列表
        if ($moduleId) {
            $classList = M('school_class')->where(['module_id' => $moduleId, 'is_learn' => 1])->getField('id, name', true);
        }
        // 模块分类
        if ($classId) {
            $articleWhere['class_id'] = $classId;
        }
        // 学习课程id
        $courseIds = M('school_article')->where($articleWhere)->getField('id', true);
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
        $timeFrom = I('time_from', '') && I('time_from') !== 'time_to' ? htmlspecialchars_decode(I('time_from')) : '';
        if (strpos($timeFrom, '+')) $timeFrom = str_replace('+', ' ', $timeFrom);
        $timeTo = I('time_to', '') ? htmlspecialchars_decode(I('time_to')) : '';
        if (strpos($timeTo, '+')) $timeTo = str_replace('+', ' ', $timeTo);
        if ($timeFrom && $timeTo) {
            $where['usa.finish_time'] = ['BETWEEN', [strtotime($timeFrom), strtotime($timeTo)]];
        }
        $userArticle = M('user_school_article usa')->where($where)->join('school_article sa', 'sa.id = usa.article_id')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
            ->field('usa.user_id, usa.article_id, usa.status learn_status, usa.add_time, usa.finish_time, usa.is_questionnaire, sa.title, sa.learn_type, sa.status, sa.publish_time, s.name module_name, sc.name class_name');
        if (!$isExport) {
            // 总数
            $count = M('user_school_article usa')->where($where)->join('school_article sa', 'sa.id = usa.article_id')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')->count();
            $page = new Page($count, 10);
            // 列表
            $userArticle = $userArticle->limit($page->firstRow . ',' . $page->listRows)->order('sa.publish_time DESC, sa.sort DESC');
            $list = $userArticle->select();
            $this->assign('module_id', $moduleId);
            $this->assign('module_list', $moduleList);
            $this->assign('class_id', $classId);
            $this->assign('class_list', $classList ?? []);
            $this->assign('user_id', $userId);
            $this->assign('title', $title);
            $this->assign('learn_status', $learnStatus !== '' ? (int)$learnStatus : '');
            $this->assign('questionnaire_status', $questionnaireStatus !== '' ? (int)$questionnaireStatus : '');
            $this->assign('time_from', $timeFrom);
            $this->assign('time_to', $timeTo);
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
                    date('Y-m-d H:i:s', $item['add_time']),
                    $item['finish_time'] ? date('Y-m-d H:i:s', $item['finish_time']) : '',
                    $learnStatusDesc,
                    $questionnaireStatusDesc
                ];
            }
            // 表头
            $headList = [
                '用户ID', '文章ID', '标题', '学习类型', '所属模块', '所属分类', '发布状态', '发布时间', '学习开始时间', '学习完成时间', '学习状态', '是否完成调查问卷'
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
        // 基础where
        $where = $this->articleWhere();
        // 基础whereOr
        $whereOr = $this->articleWhereOr();
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
        $where['article_id'] = ['IN', $courseIds];
        // 学习时间
        $learnTimeFrom = I('learn_time_from', '') ? htmlspecialchars_decode(I('learn_time_from')) : '';
        if (strpos($learnTimeFrom, '+')) $learnTimeFrom = str_replace('+', ' ', $learnTimeFrom);
        $learnTimeTo = I('learn_time_to', '') ? htmlspecialchars_decode(I('learn_time_to')) : '';
        if (strpos($learnTimeTo, '+')) $learnTimeTo = str_replace('+', ' ', $learnTimeTo);
        // 列表数据
        $userCourseLog = M('user_school_article usa')
            ->join('users u', 'u.user_id = usa.user_id')
            ->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')
            ->join('distribut_level dl', 'dl.level_id = u.distribut_level')
            ->where($where)
            ->group('usa.user_id')
            ->field('u.user_id, u.nickname, u.user_name, u.school_credit, u.distribut_level, u.svip_grade, u.svip_level, u.real_name, si.real_name svip_real_name');
        if (!$isExport && $isReach === '') {
            // 用户学习课程记录总数
            $count = M('user_school_article usa')
                ->join('users u', 'u.user_id = usa.user_id')
                ->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')
                ->join('distribut_level dl', 'dl.level_id = u.distribut_level')
                ->where($where)
                ->group('usa.user_id');
            if (!empty($whereOr)) {
                $count = $count->where(function ($query) use ($whereOr) {
                    $query->whereOr($whereOr);
                });
            }
            $count = $count->count();
            // 用户课程学习记录
            $page = new Page($count, 10);
            $userCourseLog = $userCourseLog->limit($page->firstRow . ',' . $page->listRows);
        }
        if (!empty($whereOr)) {
            $userCourseLog = $userCourseLog->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
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
            $res = $this->checkUserCourseNum($user, $courseIds, true, false, $learnTimeFrom ? strtotime($learnTimeFrom) : '', $learnTimeTo ? strtotime($learnTimeTo) : '');
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
                $log['svip_real_name'] ?? $log['real_name'],
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
                $this->assign('select_app_grade', I('app_grade', ''));
                $this->assign('select_svip_grade', I('svip_grade', ''));
                $this->assign('select_svip_level', I('svip_level', ''));
                $this->assign('user_id', I('user_id'));
                $this->assign('user_name', I('user_name'));
                $this->assign('nickname', I('nickname'));
                $this->assign('real_name', I('real_name'));
                $this->assign('is_reach', $isReach);
                $this->assign('learn_time_from', $learnTimeFrom);
                $this->assign('learn_time_to', $learnTimeTo);
                $this->assign('page', $page);
                $this->assign('log', $userCourseLog);
                return $this->fetch('user_standard_list');
            } else {
                $this->assign('app_grade', $this->appGrade);
                $this->assign('svip_grade', $this->svipGrade);
                $this->assign('svip_level', $this->svipLevel);
                $this->assign('select_app_grade', I('app_grade', ''));
                $this->assign('select_svip_grade', I('svip_grade', ''));
                $this->assign('select_svip_level', I('svip_level', ''));
                $this->assign('user_id', I('user_id'));
                $this->assign('user_name', I('user_name'));
                $this->assign('nickname', I('nickname'));
                $this->assign('real_name', I('real_name'));
                $this->assign('is_reach', (int)$isReach);
                $this->assign('learn_time_from', $learnTimeFrom);
                $this->assign('learn_time_to', $learnTimeTo);
                $this->assign('log', $userCourseLog);
                return $this->fetch('user_standard_list_2');
            }
        } else {
            // 表头
            $headList = [
                '用户ID', '用户昵称', '用户名', '真实姓名', 'APP等级', '代理商等级', '代理商职级', '课程数量', '乐活豆数量', '是否达标'
            ];
            toCsvExcel($dataList, $headList, 'user_standard_list');
        }
    }

    /**
     * 用户结业情况列表
     * @return mixed
     */
    public function userGraduateList()
    {
        $isExport = I('is_export', '');     // 是否导出
        // 模块列表
        $notModuleType = ['module6', 'module7', 'module8'];
        $moduleList = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
        // 模块分类列表
        $moduleId = I('module_id', 0);
        if (!$moduleId) {
            $moduleId = M('school_class sc')->join('school s', 's.id = sc.module_id')->where(['sc.is_learn' => 1])->order('sc.module_id ASC, sc.sort DESC')->value('s.id');
        }
        $classList = M('school_class')->where(['module_id' => $moduleId, 'is_learn' => 1])->getField('id, name', true);
        // 模块分类
        $classId = I('class_id', 0);
        if (!$classId) {
            $classId = M('school_class')->where(['is_learn' => 1])->order('module_id ASC, sort DESC')->value('id');
            if (!$classId) {
                $this->error('请先设置模块分类为学习课程', U('school.module/config'));
            }
        }
        // 模块分类下的课程列表
        $courseIds = M('school_article')->where(['class_id' => $classId, 'learn_type' => ['IN', [1, 2]], 'status' => 1])->getField('id', true);
        if (empty($courseIds)) {
            $className = M('school_class')->where(['id' => $classId])->value('name');
            $this->error('请先设置分类：' . $className . ' 下的文章为学习课程', U('school.module/config'));
        }
        $totalCourseNum = count($courseIds);
        // 基础where
        $where = $this->articleWhere();
        // 基础whereOr
        $whereOr = $this->articleWhereOr();
        // 时间where
        $activateTimeFrom = I('activate_time_from', '') ? htmlspecialchars_decode(I('activate_time_from')) : '';
        if (strpos($activateTimeFrom, '+')) $activateTimeFrom = str_replace('+', ' ', $activateTimeFrom);
        $activateTimeTo = I('activate_time_to', '') ? htmlspecialchars_decode(I('activate_time_to')) : '';
        if (strpos($activateTimeTo, '+')) $activateTimeTo = str_replace('+', ' ', $activateTimeTo);
        if ($activateTimeFrom && $activateTimeTo) {
            $where['si.svip_activate_time'] = ['BETWEEN', [strtotime($activateTimeFrom), strtotime($activateTimeTo)]];
        }
        $upgradeTimeFrom = I('upgrade_time_from', '') ? htmlspecialchars_decode(I('upgrade_time_from')) : '';
        if (strpos($upgradeTimeFrom, '+')) $upgradeTimeFrom = str_replace('+', ' ', $upgradeTimeFrom);
        $upgradeTimeTo = I('upgrade_time_to', '') ? htmlspecialchars_decode(I('upgrade_time_to')) : '';
        if (strpos($upgradeTimeTo, '+')) $upgradeTimeTo = str_replace('+', ' ', $upgradeTimeTo);
        if ($upgradeTimeFrom && $upgradeTimeTo) {
            $where['si.svip_upgrade_time'] = ['BETWEEN', [strtotime($upgradeTimeFrom), strtotime($upgradeTimeTo)]];
        }
        // 学习时间
        $learnTimeFrom = I('learn_time_from', '') ? htmlspecialchars_decode(I('learn_time_from')) : '';
        if (strpos($learnTimeFrom, '+')) $learnTimeFrom = str_replace('+', ' ', $learnTimeFrom);
        $learnTimeTo = I('learn_time_to', '') ? htmlspecialchars_decode(I('learn_time_to')) : '';
        if (strpos($learnTimeTo, '+')) $learnTimeTo = str_replace('+', ' ', $learnTimeTo);
        // 列表数据
        $userList = M('users u')->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')->where($where)->order('u.user_id DESC')
            ->field('u.*, si.real_name svip_real_name, si.svip_activate_time, si.svip_upgrade_time, si.svip_referee_number, si.grade_referee_num1, si.grade_referee_num2, si.grade_referee_num3, si.grade_referee_num4, si.network_parent_user_name, si.network_parent_real_name, si.customs_user_name, si.customs_real_name');
        if ($isExport) {
            $ext = ['module_id' => $moduleId, 'class_id' => $classId, 'learn_time_from' => strtotime($learnTimeFrom), 'learn_time_to' => strtotime($learnTimeTo), 'where_or' => $whereOr];
            $this->exportUserGraduateList($where, $ext);
            $this->ajaxReturn(['status' => 1, 'msg' => '添加导出队列成功，请耐心等待后台导出']);
        } else {
            // 用户总数
            $count = M('users u')->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')->where($where);
            if (!empty($whereOr)) {
                $count = $count->where(function ($query) use ($whereOr) {
                    $query->whereOr($whereOr);
                });
            }
            $count = $count->count();
            $page = new Page($count, 10);
            // 用户列表
            $userList = $userList->limit($page->firstRow . ',' . $page->listRows);
        }
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $userList = $userList->select();
        foreach ($userList as &$user) {
            $user['total_course_num'] = $totalCourseNum;
            $user['course_num'] = 0;        // 学习课程数量
            $user['is_graduate'] = 0;       // 是否已结业
            // APP等级
            $user['app_grade_name'] = $this->appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $this->svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $this->svipLevel[$user['svip_level']] : '';
            // 用户已学习完成课程数量
            $userData = [
                'user_id' => $user['user_id'],
                'app_grade' => $user['distribut_level'],
                'svip_grade' => $user['svip_grade'],
                'svip_level' => $user['svip_level'],
            ];
            $res = $this->checkUserCourseNum($userData, $courseIds, false, true, strtolower($learnTimeFrom), strtotime($learnTimeTo));
            $userLearnedCourseNum = $user['course_num'] = $res['course_num'];
            // 是否已结业
            if ($userLearnedCourseNum == $totalCourseNum) {
                $user['is_graduate'] = 1;
            }
        }
        $this->assign('module_list', $moduleList);
        $this->assign('class_list', $classList);
        $this->assign('module_id', $moduleId);
        $this->assign('class_id', $classId);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('select_app_grade', I('app_grade', ''));
        $this->assign('select_svip_grade', I('svip_grade', ''));
        $this->assign('select_svip_level', I('svip_level', ''));
        $this->assign('user_id', I('user_id'));
        $this->assign('user_name', I('user_name'));
        $this->assign('nickname', I('nickname'));
        $this->assign('real_name', I('real_name'));
        $this->assign('activate_time_from', $activateTimeTo);
        $this->assign('activate_time_to', $activateTimeFrom);
        $this->assign('upgrade_time_from', $upgradeTimeFrom);
        $this->assign('upgrade_time_to', $upgradeTimeTo);
        $this->assign('learn_time_from', $learnTimeFrom);
        $this->assign('learn_time_to', $learnTimeTo);
        $this->assign('page', $page);
        $this->assign('list', $userList);
        return $this->fetch('user_graduate_list');
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
        $where = ['sa.learn_type' => ['IN', [1, 2]], 'sa.delete_time' => 0];
        $title = htmlspecialchars_decode(trim(I('title', '')));
        $moduleId = I('module_id', '');
        $classId = I('class_id', '');
        $publishTimeFrom = I('publish_time_from', '') ? htmlspecialchars_decode(I('publish_time_from')) : '';
        if (strpos($publishTimeFrom, '+')) $publishTimeFrom = str_replace('+', ' ', $publishTimeFrom);
        $publishTimeTo = I('publish_time_to', '') ? htmlspecialchars_decode(I('publish_time_to')) : '';
        if (strpos($publishTimeTo, '+')) $publishTimeTo = str_replace('+', ' ', $publishTimeTo);
        $learnTimeFrom = I('learn_time_from', '') ? htmlspecialchars_decode(I('learn_time_from')) : '';
        if (strpos($learnTimeFrom, '+')) $learnTimeFrom = str_replace('+', ' ', $learnTimeFrom);
        $learnTimeTo = I('learn_time_to', '') ? htmlspecialchars_decode(I('learn_time_to')) : '';
        if (strpos($learnTimeTo, '+')) $learnTimeTo = str_replace('+', ' ', $learnTimeTo);
        $isExport = I('is_export', 0);
        if ($title) {
            $where['sa.title'] = ['LIKE', '%' . $title . '%'];
        }
        if ($moduleId) {
            $where['s.id'] = $moduleId;
            $classList = M('school_class')->where('module_id', $moduleId)->getField('id, name', true);
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
            $where['sa.publish_time'] = ['BETWEEN', [strtotime($publishTimeFrom), strtotime($publishTimeTo)]];
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
            $item['publish_time_desc'] = $item['publish_time'] > 0 ? date('Y-m-d H:i:s', $item['publish_time']) : '';
            if ($learnTimeFrom && $learnTimeTo) {
                // 根据学习时间统计学习人数
                $learnCount = M('user_school_article')->where([
                    'article_id' => $item['id'],
                    'is_learn' => 1,
                    'finish_time' => ['BETWEEN', [strtotime($learnTimeFrom), strtotime($learnTimeTo)]]
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
            $moduleList = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
            $this->assign('title', $title);
            $this->assign('module_id', $moduleId);
            $this->assign('module_list', $moduleList);
            $this->assign('class_id', $classId);
            $this->assign('class_list', $classList ?? []);
            $this->assign('app_grade', $this->appGrade);
            $this->assign('svip_grade', $this->svipGrade);
            $this->assign('svip_level', $this->svipLevel);
            $this->assign('select_app_grade', $appGrade);
            $this->assign('select_svip_grade', $svipGrade);
            $this->assign('select_svip_level', $svipLevel);
            $this->assign('publish_time_from', $publishTimeFrom);
            $this->assign('publish_time_to', $publishTimeTo);
            $this->assign('learn_time_from', $learnTimeFrom);
            $this->assign('learn_time_to', $learnTimeTo);
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
        // 基础where
        $where = $this->articleWhere();
        $where['usa.article_id'] = I('article_id', 0);
        // 基础whereOr
        $whereOr = $this->articleWhereOr();
        // 学习时间
        $timeFrom = I('time_from', '') && I('time_from') !== 'time_to' ? htmlspecialchars_decode(I('time_from')) : '';
        if (strpos($timeFrom, '+')) $timeFrom = str_replace('+', ' ', $timeFrom);
        $timeTo = I('time_to', '') ? htmlspecialchars_decode(I('time_to')) : '';
        if (strpos($timeTo, '+')) $timeTo = str_replace('+', ' ', $timeTo);
        if ($timeFrom && $timeTo) {
            $where['usa.finish_time'] = ['BETWEEN', [strtotime($timeFrom), strtotime($timeTo)]];
        }
        $userList = M('user_school_article usa')
            ->join('school_article sa', 'sa.id = usa.article_id')
            ->join('school_class sc', 'sc.id = sa.class_id')
            ->join('school s', 's.id = sc.module_id')
            ->join('users u', 'u.user_id = usa.user_id')
            ->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')
            ->where($where)
            ->order('u.user_id DESC')
            ->field('sa.*, s.name module_name, sc.name class_name, u.user_id, u.nickname, u.user_name, u.real_name, u.school_credit, u.distribut_level, u.svip_grade, u.svip_level, si.real_name svip_real_name, usa.status, usa.add_time, usa.finish_time');
        if (!$isExport) {
            // 用户总数
            $count = M('user_school_article usa')
                ->join('school_article sa', 'sa.id = usa.article_id')
                ->join('school_class sc', 'sc.id = sa.class_id')
                ->join('school s', 's.id = sc.module_id')
                ->join('users u', 'u.user_id = usa.user_id')
                ->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')
                ->where($where);
            if (!empty($whereOr)) {
                $count = $count->where(function ($query) use ($whereOr) {
                    $query->whereOr($whereOr);
                });
            }
            $count = $count->count();
            $page = new Page($count, 10);
            // 用户列表
            $userList = $userList->limit($page->firstRow . ',' . $page->listRows);
        }
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
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
                $user['svip_real_name'] ?? $user['real_name'],
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
            $this->assign('select_app_grade', I('app_grade', ''));
            $this->assign('select_svip_grade', I('svip_grade', ''));
            $this->assign('select_svip_level', I('svip_level', ''));
            $this->assign('user_id', I('user_id'));
            $this->assign('user_name', I('user_name'));
            $this->assign('nickname', I('nickname'));
            $this->assign('real_name', I('real_name'));
            $this->assign('time_from', $timeFrom);
            $this->assign('time_to', $timeTo);
            $this->assign('page', $page);
            $this->assign('list', $userList);
            return $this->fetch('course_user_list');
        } else {
            // 表头
            $headList = [
                '文章ID', '标题', '学习类型', '所属模块', '所属分类', '用户ID', '用户昵称', '用户名', '真实姓名', 'APP等级', '代理商等级', '代理商职级', '乐活豆数量', '学习状态', '学习开始时间', '学习完成时间'
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

    /**
     * 模块学习用户列表
     * @return mixed
     */
    public function moduleUserList()
    {
        $isExport = I('is_export', 0);
        if (!$isExport) {
            // 用户总数
            $sqlContent = $this->moduleUserSqlContent(false, true);
            $count = DB::query($sqlContent['sql'])[0]['user_count'];
            $page = new Page($count, 10);
            // 用户列表（分页）
            $sqlContent = $this->moduleUserSqlContent(false, false, $page->firstRow, $page->listRows);
            $userList = DB::query($sqlContent['sql']);
        } else {
            // 用户列表
            $this->moduleUserSqlContent(true);
            $this->ajaxReturn(['status' => 1, 'msg' => '添加导出队列成功，请耐心等待后台导出']);
        }
        $dataList = [];
        foreach ($userList as $key => &$user) {
            $user['course_num'] = 0;        // 学习课程数量
            $user['is_graduate'] = 0;       // 是否已结业
            $user['module_num'] = 1;        // 学习模块数量
            // APP等级
            $user['app_grade_name'] = $this->appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $this->svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $this->svipLevel[$user['svip_level']] : '';
            // 用户已学习完成课程数量
            $userData = [
                'user_id' => $user['user_id'],
                'app_grade' => $user['distribut_level'],
                'svip_grade' => $user['svip_grade'],
                'svip_level' => $user['svip_level'],
            ];
            $res = $this->checkUserCourseNum($userData, $sqlContent['course_ids'], false, true);
            $userLearnedCourseNum = $user['course_num'] = $res['course_num'];
            // 是否已结业
            if ($userLearnedCourseNum == $sqlContent['total_course_num']) {
                $user['is_graduate'] = 1;
            }
            // 用户已学习的模块数量
            if (I('module_id', -1) == -1) {
                $res = $this->checkUserModuleNum($userData);
                $user['module_num'] = $res['module_num'];
            }
            $dataList[$key] = [
                $sqlContent['module_name'],
                $sqlContent['total_course_num'],
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                $user['school_credit'],
                $user['course_num']
            ];
            if ($sqlContent['module_id'] != -1) {
                $dataList[$key][] = $user['is_graduate'] == 1 ? '已结业' : '未结业';
            } else {
                $dataList[$key][] = $user['module_num'];
            }
        }
        // 模块列表
        $notModuleType = ['module6', 'module7', 'module8'];
        $moduleList = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
        $this->assign('module_id', I('module_id', -1));
        $this->assign('module_list', $moduleList);
        $this->assign('total_course_num', $sqlContent['total_course_num']);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('select_app_grade', I('app_grade', ''));
        $this->assign('select_svip_grade', I('svip_grade', ''));
        $this->assign('select_svip_level', I('svip_level', ''));
        $this->assign('user_id', I('user_id', ''));
        $this->assign('user_name', I('user_name', ''));
        $this->assign('nickname', I('nickname', ''));
        $this->assign('real_name', I('real_name', ''));
        $this->assign('page', $page);
        $this->assign('list', $userList);
        return $this->fetch('module_user_list');
    }

    /**
     * 模块学习用户构建sql
     * @param bool $isExport
     * @param bool $isPage
     * @param int $offset
     * @param int $length
     * @return array
     */
    private function moduleUserSqlContent($isExport = false, $isPage = false, $offset = 0, $length = 0)
    {
        if ($isPage) {
            $content = 'count(user_article.article_count) user_count';
        } else {
            $content = '*';
        }
        $sql = "SELECT
                    $content
                FROM
                    (
                SELECT
                    count( usa.article_id ) article_count,
                    s.NAME module_name,
                    `u`.`user_id`,
                    `u`.`nickname`,
                    `u`.`user_name`,
                    `u`.`real_name`,
                    `u`.`school_credit`,
                    `u`.`distribut_level`,
                    `u`.`svip_grade`,
                    `u`.`svip_level`,
                    `usa`.`status`,
                    `usa`.`add_time`,
                    `usa`.`finish_time`,
                    `si`.`real_name` AS `svip_real_name`
                FROM
                    tp_user_school_article usa
                    INNER JOIN `tp_school_article` `sa` ON `sa`.`id` = `usa`.`article_id`
                    INNER JOIN `tp_school_class` `sc` ON `sc`.`id` = `sa`.`class_id`
                    INNER JOIN `tp_school` `s` ON `s`.`id` = `sc`.`module_id`
                    INNER JOIN `tp_users` `u` ON `u`.`user_id` = `usa`.`user_id` 
                    LEFT JOIN `tp_svip_info` `si` ON `si`.`user_id` = `u`.`user_id` 
                WHERE
                    `sa`.`delete_time` = 0 
                    AND `sa`.`status` = 1";
        $moduleId = I('module_id', -1);
        if ($moduleId != -1) {
            $moduleName = M('school')->where(['id' => $moduleId])->value('name');
            // 分类列表
            $classIds = M('school_class')->where(['module_id' => $moduleId])->getField('id', true);
            // 模块下的课程列表
            $courseIds = M('school_article')->where(['class_id' => ['IN', $classIds], 'learn_type' => ['IN', [1, 2]], 'status' => 1])->getField('id', true);
            $totalCourseNum = count($courseIds);
            $sql .= " AND `s`.`id` = $moduleId";
        } else {
            // 所有模块的课程列表
            $courseIds = M('school_article')->where(['learn_type' => ['IN', [1, 2]], 'status' => 1])->getField('id', true);
            $totalCourseNum = count($courseIds);
        }
        if ($totalCourseNum > 0) {
            $sql .= " AND `usa`.`article_id` IN (" . implode(',', $courseIds) . ")";
        }
        if ($appGrade = I('app_grade', '')) {
            $sql .= " AND `u`.`distribut_level` = $appGrade";
        }
        if ($svipGrade = I('svip_grade', '')) {
            $sql .= " AND `u`.`distribut_level` = 3 AND `u`.`svip_grade` = $svipGrade";
        }
        if ($svipLevel = I('svip_level', '')) {
            $sql .= " AND `u`.`distribut_level` = 3 AND `u`.`svip_level` = $svipLevel";
        }
        if ($userId = I('user_id', '')) {
            $sql .= " AND `u`.`user_id` = $userId";
        }
        if ($username = I('user_name', '')) {
            $sql .= " AND `u`.`user_name` = $username";
        }
        if ($nickname = I('nickname', '')) {
            $sql .= " AND `u`.`nickname` = $nickname";
        }
        if ($trueName = I('real_name', '')) {
            $sql .= " AND ( `u`.`real_name` LIKE '%$trueName%' OR `si`.`real_name` LIKE '%$trueName%' )";
        }
        $sql .= " GROUP BY `usa`.`user_id` ORDER BY u.user_id DESC";
        if (!$isPage && $length > 0) {
            $sql .= " LIMIT $offset, $length";
        }
        $sql .= " ) user_article";
        $return = ['sql' => $sql, 'module_id' => $moduleId, 'module_name' => $moduleName ?? '所有模块', 'course_ids' => $courseIds, 'total_course_num' => $totalCourseNum];
        if (!$isExport) {
            return $return;
        } else {
            $path = UPLOAD_PATH . 'school/excel/' . date('Y-m-d') . '/';
            $name = 'moduleUserList_' . date('Y-m-d_H-i-s') . '.csv';
            // 导出记录
            M('export_file')->add([
                'type' => 'module_user_list',
                'path' => $path,
                'name' => $name,
                'table' => '',
                'field' => json_encode($return),
                'add_time' => NOW_TIME
            ]);
        }
    }

    /**
     * 模块学习用户列表
     * @return mixed
     */
    public function moduleUserList_bak()
    {
        $isExport = I('is_export', 0);
        $where = ['sa.delete_time' => 0, 'usa.status' => 1];
        $moduleId = I('module_id', -1);
        if ($moduleId != -1) {
            $moduleName = M('school')->where(['id' => $moduleId])->value('name');
            // 分类列表
            $classIds = M('school_class')->where(['module_id' => $moduleId])->getField('id', true);
            // 模块下的课程列表
            $courseIds = M('school_article')->where(['class_id' => ['IN', $classIds], 'learn_type' => ['IN', [1, 2]], 'status' => 1])->getField('id', true);
            $totalCourseNum = count($courseIds);
            $where['s.id'] = $moduleId;
        } else {
            // 所有模块的课程列表
            $courseIds = M('school_article')->where(['learn_type' => ['IN', [1, 2]], 'status' => 1])->getField('id', true);
            $totalCourseNum = count($courseIds);
        }
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
        // 用户列表
        $userList = M('user_school_article usa')
            ->join('school_article sa', 'sa.id = usa.article_id')
            ->join('school_class sc', 'sc.id = sa.class_id')
            ->join('school s', 's.id = sc.module_id')
            ->join('users u', 'u.user_id = usa.user_id')
            ->where($where)
            ->order('u.user_id DESC')
            ->group('usa.user_id')
            ->field('count(usa.article_id) article_count, s.name module_name, u.user_id, u.nickname, u.user_name, u.school_credit, u.distribut_level, u.svip_grade, u.svip_level, usa.status, usa.add_time, usa.finish_time');
        if (!$isExport) {
            // 用户总数
            $count = M('user_school_article usa')
                ->join('school_article sa', 'sa.id = usa.article_id')
                ->join('school_class sc', 'sc.id = sa.class_id')
                ->join('school s', 's.id = sc.module_id')
                ->join('users u', 'u.user_id = usa.user_id')
                ->where($where)
                ->group('usa.user_id')
                ->count();
            $page = new Page($count, 10);
            // 用户列表
            $userList = $userList->limit($page->firstRow . ',' . $page->listRows);
        }
        $userList = $userList->select();
        $dataList = [];
        foreach ($userList as &$user) {
            $user['course_num'] = 0;        // 学习课程数量
            $user['is_graduate'] = 0;       // 是否已结业
            // APP等级
            $user['app_grade_name'] = $this->appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $this->svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $this->svipLevel[$user['svip_level']] : '';
            // 用户已学习完成课程数量
            $userData = [
                'user_id' => $user['user_id'],
                'app_grade' => $user['distribut_level'],
                'svip_grade' => $user['svip_grade'],
                'svip_level' => $user['svip_level'],
            ];
            $res = $this->checkUserCourseNum($userData, $courseIds, false, true);
            $userLearnedCourseNum = $user['course_num'] = $res['course_num'];
            // 是否已结业
            if ($userLearnedCourseNum == $totalCourseNum) {
                $user['is_graduate'] = 1;
            }
            $dataList[] = [
                $moduleName ?? '所有模块',
                $totalCourseNum,
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                $user['school_credit'],
                $user['course_num'],
                $user['is_graduate'] == 1 ? '已结业' : '未结业'
            ];
        }
        if (!$isExport) {
            // 模块列表
            $notModuleType = ['module6', 'module7', 'module8'];
            $moduleList = M('school')->where(['type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
            $this->assign('module_id', $moduleId);
            $this->assign('module_list', $moduleList);
            $this->assign('total_course_num', $totalCourseNum);
            $this->assign('app_grade', $this->appGrade);
            $this->assign('svip_grade', $this->svipGrade);
            $this->assign('svip_level', $this->svipLevel);
            $this->assign('select_app_grade', $appGrade);
            $this->assign('select_svip_grade', $svipGrade);
            $this->assign('select_svip_level', $svipLevel);
            $this->assign('user_id', $userId);
            $this->assign('user_name', $username);
            $this->assign('nickname', $nickname);
            $this->assign('page', $page);
            $this->assign('list', $userList);
            return $this->fetch('module_user_list');
        } else {
            // 表头
            $headList = [
                '模块', '课程总数', '用户ID', '用户昵称', '用户名', 'APP等级', '代理商等级', '代理商职级', '乐活豆数量', '已学习完成课程数量', '是否已结业'
            ];
            toCsvExcel($dataList, $headList, 'module_user_list');
        }
    }

    /**
     * 导出素材文章
     */
    public function exportResourceArticle()
    {
        // 数据表
        $table = '';
        $path = UPLOAD_PATH . 'school/excel/' . date('Y-m-d') . '/';
        $name = 'resourceArticle_' . date('Y-m-d_H-i-s') . '.csv';
        // 导出记录
        M('export_file')->add([
            'type' => 'school_resource_article',
            'path' => $path,
            'name' => $name,
            'table' => $table,
            'add_time' => NOW_TIME
        ]);
        $this->ajaxReturn(['status' => 1, 'msg' => '添加导出队列成功，请耐心等待后台导出']);
    }

    /**
     * 素材下载记录
     * @return mixed
     */
    public function resourceDownloadList()
    {
        $isExport = I('is_export', 0);
        // 基础where
        $where = $this->articleWhere();
        $articleId = I('article_id');
        $where['usa.article_id'] = $articleId;
        // 文章属性
        $articleInfo = M('school_article sa')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
            ->where(['sa.id' => $articleId])->field('s.name module_name, sc.name class_name, sa.id article_id')->find();
        // 基础whereOr
        $whereOr = $this->articleWhereOr();
        // 列表数据
        $userList = M('user_school_article usa')
            ->join('users u', 'u.user_id = usa.user_id')
            ->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')
            ->where($where)
            ->order('usa.add_time DESC')
            ->field('u.*, si.real_name svip_real_name, si.svip_activate_time, si.svip_upgrade_time, si.svip_referee_number, si.grade_referee_num1, si.grade_referee_num2, si.grade_referee_num3, si.grade_referee_num4, si.network_parent_user_name, si.network_parent_real_name, si.customs_user_name, si.customs_real_name, usa.add_time');
        if (!$isExport) {
            // 用户学习课程记录总数
            $count = M('user_school_article usa')
                ->join('users u', 'u.user_id = usa.user_id')
                ->join('svip_info si', 'si.user_id = u.user_id', 'LEFT')
                ->where($where);
            if (!empty($whereOr)) {
                $count = $count->where(function ($query) use ($whereOr) {
                    $query->whereOr($whereOr);
                });
            }
            $count = $count->count();
            // 用户课程学习记录
            $page = new Page($count, 10);
            $userList = $userList->limit($page->firstRow . ',' . $page->listRows);
        }
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $userList = $userList->select();
        $dataList[] = [
            $articleInfo['module_name'] . ' - ' . $articleInfo['class_name'] . ' - ' . $articleInfo['article_id'], '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
        ];     // 导出数据
        foreach ($userList as $k => &$user) {
            // APP等级
            $user['app_grade_name'] = $this->appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $this->svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $this->svipLevel[$user['svip_level']] : '';
            $dataList[] = [
                '',
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['svip_real_name'] ?? $user['real_name'],
                date('Y-m-d H:i:s', $user['add_time']),
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                $user['svip_activate_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_activate_time']) : '',
                $user['svip_upgrade_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_upgrade_time']) : '',
                $user['svip_referee_number'] ?? 0,
                $user['grade_referee_num1'] ?? 0,
                $user['grade_referee_num2'] ?? 0,
                $user['grade_referee_num3'] ?? 0,
                $user['grade_referee_num4'] ?? 0,
                $user['network_parent_user_name'] ?? '',
                $user['network_parent_real_name'] ?? '',
                $user['customs_user_name'] ?? '',
                $user['customs_real_name'] ?? '',
            ];
        }
        if (!$isExport) {
            $this->assign('article_id', $articleId);
            $this->assign('article_info', $articleInfo);
            $this->assign('app_grade', $this->appGrade);
            $this->assign('svip_grade', $this->svipGrade);
            $this->assign('svip_level', $this->svipLevel);
            $this->assign('select_app_grade', I('app_grade', ''));
            $this->assign('select_svip_grade', I('svip_grade', ''));
            $this->assign('select_svip_level', I('svip_level', ''));
            $this->assign('user_id', I('user_id', ''));
            $this->assign('user_name', I('user_name', ''));
            $this->assign('nickname', I('nickname', ''));
            $this->assign('real_name', I('real_name', ''));
            $this->assign('page', $page);
            $this->assign('list', $userList);
            return $this->fetch('resource_download_list');
        } else {
            // 表头
            $headList = [
                '素材文章',
                '用户ID', '用户昵称', '用户名', '真实姓名', '下载时间', 'APP等级', '代理商等级', '代理商职级',
                '211系统激活时间', '211系统升级代理商时间', '推荐总人数', '推荐游客人数', '推荐优享会员人数', '推荐尊享会员人数', '推荐代理商人数',
                '服务人用户名', '服务人真实姓名', '服务中心用户名', '服务中心真实姓名',
            ];
            toCsvExcel($dataList, $headList, 'resource_download_list');
        }
    }

    /**
     * 用户课程总览导出
     */
    public function exportUserCourseAll()
    {
        // 基础where
        $where = $this->articleWhere();
        // 基础whereOr
        $whereOr = $this->articleWhereOr();
        $ext = ['where_or' => $whereOr];
        // 数据表
        $table = 'users u';
        // join连接
        $join = [
            ['svip_info si', 'si.user_id = u.user_id', 'LEFT'],
        ];
        // 排序
        $order = ['u.user_id' => 'DESC'];
        // 字段
        $field = 'u.*, si.real_name svip_real_name, si.svip_activate_time, si.svip_upgrade_time, si.svip_referee_number, si.grade_referee_num1, si.grade_referee_num2, si.grade_referee_num3, si.grade_referee_num4, si.network_parent_user_name, si.network_parent_real_name, si.customs_user_name, si.customs_real_name';
        $path = UPLOAD_PATH . 'school/excel/' . date('Y-m-d') . '/';
        $name = 'userCourseAll_' . date('Y-m-d_H-i-s') . '.csv';
        // 导出记录
        M('export_file')->add([
            'type' => 'school_user_course_all',
            'path' => $path,
            'name' => $name,
            'table' => $table,
            'join' => json_encode($join),
            'condition' => json_encode($where),
            'order' => json_encode($order),
            'field' => $field,
            'ext' => json_encode($ext),
            'add_time' => NOW_TIME
        ]);
        $this->ajaxReturn(['status' => 1, 'msg' => '添加导出队列成功，请耐心等待后台导出']);
    }
}
