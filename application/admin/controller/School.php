<?php

namespace app\admin\controller;


use app\admin\model\SchoolArticle;
use app\admin\model\SchoolArticleResource;
use app\admin\model\SchoolArticleTempResource;
use app\admin\model\SchoolExchange;
use app\admin\model\SchoolStandard;
use app\common\logic\OssLogic;
use think\Db;
use think\Page;

class School extends Base
{
    private $ossClient = null;
    private $appGrade = [];
    private $svipGrade = [];
    private $svipLevel = [];

    public function __construct()
    {
        parent::__construct();
        $this->ossClient = new OssLogic();
        // APP等级列表
        $this->appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $this->svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $this->svipLevel = M('svip_level')->getField('app_level, name', true);
    }

    /**
     * 配置信息
     * @return mixed
     * @throws \Exception
     */
    public function config()
    {
        if (IS_POST) {
            $param = I('post.');
            $keyword = $param['keyword'];
            unset($param['keyword']);
            // 配置
            foreach ($param as $k => $v) {
                switch ($k) {
                    case 'official':
                        if (strstr($v['url'], 'aliyuncs.com')) {
                            // 原图
                            $v['url'] = M('school_config')->where(['type' => 'official'])->value('url');
                        } else {
                            // 新图
                            $filePath = PUBLIC_PATH . substr($v['url'], strrpos($v['url'], '/public/') + 8);
                            $fileName = substr($v['url'], strrpos($v['url'], '/') + 1);
                            $object = 'image/' . date('Y/m/d/H/') . $fileName;
                            $return_url = $this->ossClient->uploadFile($filePath, $object);
                            if (!$return_url) {
                                $this->error('图片上传错误');
                            } else {
                                // 图片信息
                                $imageInfo = getimagesize($filePath);
                                $v['url'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                                unlink($filePath);
                            }
                        }
                        $data = [
                            'type' => $k,
                            'name' => isset($v['name']) ? $v['name'] : '',
                            'url' => isset($v['url']) ? $v['url'] : '',
                            'content' => isset($v['content']) ? $v['content'] : '',
                        ];
                        $config = M('school_config')->where(['type' => $k])->find();
                        if (!empty($config)) {
                            M('school_config')->where(['id' => $config['id']])->update($data);
                        } else {
                            M('school_config')->add($data);
                        }
                        break;
                    case 'popup':
                        if (!$v['content']['is_open']) {
                            $v['content']['is_open'] = 0;
                        }
                        if ($v['content']['is_open'] == 1) {
                            $v['name'] = '弹窗跳转';
                            if (empty($v['url'])) {
                                $this->error('请上传弹窗封面图', U('Admin/School/config'));
                            }
                            if (strstr($v['url'], 'aliyuncs.com')) {
                                // 原图
                                $v['url'] = M('school_config')->where(['type' => 'popup'])->value('url');
                            } else {
                                // 新图
                                $filePath = PUBLIC_PATH . substr($v['url'], strrpos($v['url'], '/public/') + 8);
                                $fileName = substr($v['url'], strrpos($v['url'], '/') + 1);
                                $object = 'image/' . date('Y/m/d/H/') . $fileName;
                                $return_url = $this->ossClient->uploadFile($filePath, $object);
                                if (!$return_url) {
                                    $this->error('图片上传错误');
                                } else {
                                    // 图片信息
                                    $imageInfo = getimagesize($filePath);
                                    $v['url'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                                    unlink($filePath);
                                }
                            }
                            $content = '';
                            foreach ($v['content'] as $key => $value) {
                                if ($key == 'article_id' && $value == 0) {
                                    $this->error('请选择跳转文章', U('Admin/School/config'));
                                }
                                $content .= $key . ':' . $value . ',';
                            }
                            $v['content'] = rtrim($content, ',');
                        }
                        $data = [
                            'type' => $k,
                            'name' => isset($v['name']) ? $v['name'] : '',
                            'url' => isset($v['url']) ? $v['url'] : '',
                            'content' => isset($v['content']) ? $v['content'] : '',
                        ];
                        $config = M('school_config')->where(['type' => $k])->find();
                        if (!empty($config)) {
                            M('school_config')->where(['id' => $config['id']])->update($data);
                        } else {
                            M('school_config')->add($data);
                        }
                        break;
                    case 'standard':
                        $standardData = [];
                        foreach ($v as $item) {
                            if ($item == '' || $item['num'] == 0) continue;
                            $standardData[] = $item;
                        }
                        M('school_standard')->where('1=1')->delete();
                        if (!empty($standardData)) {
                            $schoolStandard = new SchoolStandard();
                            $schoolStandard->saveAll($standardData);
                        }
                        // 缓存记录
                        cache('school_standard', $standardData, 0);
                        break;
                }
            }
            // 关键词
            foreach ($keyword as $key) {
                if ($key['id'] == 0) {
                    if (M('school_article_keyword')->where(['name' => $key['name']])->value('id')) {
                        continue;
                    } else {
                        M('school_article_keyword')->add($key);
                    }
                } else {
                    if (empty($key['name'])) {
                        continue;
                    }
                    M('school_article_keyword')->where(['id' => $key['id']])->update($key);
                }
            }
            $this->success('操作成功', U('Admin/School/config'));
        }
        // 配置
        $schoolConfig = M('school_config')->select();
        $config = [];
        foreach ($schoolConfig as $val) {
            if ($val['type'] == 'official' && !empty($val['url'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            } elseif ($val['type'] == 'popup' && !empty($val['url']) && !empty($val['content'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
                $content = explode(',', $val['content']);
                $val['content'] = [];
                foreach ($content as $v) {
                    $key = substr($v, 0, strrpos($v, ':'));
                    $value = substr($v, strrpos($v, ':') + 1);
                    $val['content'][$key] = $value;
                }
            }
            $config[$val['type']] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }
        // 学习达标设置
        $standard = M('school_standard')->order('type ASC, num ASC')->select();
        $standardCount = count($standard);
        // 轮播图
        $count = M('school_rotate')->where(['module_id' => 0])->count();
        $page = new Page($count, 10);
        $images = M('school_rotate')->where(['module_id' => 0])->limit($page->firstRow . ',' . $page->listRows)->order('sort DESC')->select();
        foreach ($images as &$image) {
            $url = explode(',', $image['url']);
            $image['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            $image['module_type'] = M('school')->where(['type' => $image['module_type']])->value('name');
        }
        // 当前热点模块的文章
        $articleList = M('school_article sa')
            ->join('school_class sc', 'sc.id = sa.class_id')
            ->join('school s', 's.id = sc.module_id')
            ->where([
                's.type' => 'module9',
                'sa.status' => 1
            ])
            ->field('sa.id, sa.title')->select();
        // 热门词
        $keyword = M('school_article_keyword')->select();

        $this->assign('config', $config);
        $this->assign('standard', $standard);
        $this->assign('standard_count', $standardCount);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('images', $images);
        $this->assign('page', $page);
        $this->assign('article_list', $articleList);
        $this->assign('keyword', $keyword);
        $this->assign('keyword_count', count($keyword));
        return $this->fetch();
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
            $where['svip_grade'] = $svipGrade;
        }
        if ($svipLevel = I('svip_level', '')) {
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
        $userList = M('users')->where($where)->order('user_id DESC')->field('user_id, nickname, user_name, school_credit, distribut_level, svip_grade, svip_level');
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
                $user['first_visit']
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
                '用户ID', '用户昵称', '用户名', 'APP等级', '代理商等级', '代理商职级', '课程数量', '乐活豆数量', '首次进入商学院'
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
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
        $where = [
            'usa.user_id' => $userId,
            'usa.article_id' => ['IN', $courseIds],
            'usa.status' => 1
        ];
        $userArticle = M('user_school_article usa')->where($where)->join('school_article sa', 'sa.id = usa.article_id')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
            ->field('usa.user_id, usa.article_id, sa.title, sa.learn_type, sa.status, sa.publish_time, s.name module_name, sc.name class_name');
        if (!$isExport) {
            // 总数
            $count = M('user_school_article usa')->where($where)->join('school_article sa', 'sa.id = usa.article_id')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')->count();
            $page = new Page($count, 10);
            // 列表
            $userArticle = $userArticle->limit($page->firstRow . ',' . $page->listRows)->order('sa.publish_time DESC, sa.sort DESC');
            $list = $userArticle->select();
            $this->assign('user_id', $userId);
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
                $dataList[] = [
                    $item['user_id'],
                    $item['article_id'],
                    $item['title'],
                    $learnType,
                    $item['module_name'],
                    $item['class_name'],
                    $status,
                    date('Y-m-d H:i:s', $item['publish_time']),
                ];
            }
            // 表头
            $headList = [
                '用户ID', '文章ID', '标题', '学习类型', '所属模块', '所属分类', '状态', '发布时间'
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
            $where['u.svip_grade'] = $svipGrade;
        }
        if ($svipLevel = I('svip_level', '')) {
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
            $count = M('user_school_article')->where(['article_id' => ['IN', $courseIds]])->group('user_id')->count();
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
            'status' => 1,
        ];
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
     * 轮播图
     * @return mixed
     */
    public function rotate()
    {
        $id = I('id', 0);
        $moduleId = I('module_id', 0);
        if (IS_POST) {
            $param = I('post.');
            if ($moduleId != 0) {
                $param['module_type'] = M('school')->where(['id' => $moduleId])->value('type');
            }
            if (empty($param['url'])) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请上传轮播图']);
            }
            if (strstr($param['url'], 'aliyuncs.com')) {
                // 原图
                $param['url'] = M('school_rotate')->where(['id' => $id])->value('url');
            } else {
                // 新图
                $filePath = PUBLIC_PATH . substr($param['url'], strrpos($param['url'], '/public/') + 8);
                $fileName = substr($param['url'], strrpos($param['url'], '/') + 1);
                $object = 'image/' . date('Y/m/d/H/') . $fileName;
                $return_url = $this->ossClient->uploadFile($filePath, $object);
                if (!$return_url) {
                    return $this->ajaxReturn(['status' => 0, 'msg' => 'ERROR：' . $this->ossClient->getError()]);
                } else {
                    // 图片信息
                    $imageInfo = getimagesize($filePath);
                    $param['url'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                    unlink($filePath);
                }
            }
            if ($id) {
                M('school_rotate')->where(['id' => $id])->update($param);
            } else {
                $param['add_time'] = NOW_TIME;
                M('school_rotate')->add($param);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
        }
        if ($id) {
            $rotate = M('school_rotate')->where(['id' => $id])->find();
            $url = explode(',', $rotate['url']);
            $rotate['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            if ($rotate['module_type'] != '') {
                $articleList = M('school_article sa')
                    ->join('school_class sc', 'sc.id = sa.class_id')
                    ->join('school s', 's.id = sc.module_id')
                    ->where([
                        's.type' => $rotate['module_type'],
                        'sa.status' => 1
                    ])
                    ->field('sa.id, sa.title')->select();
            }
        } else {
            $rotate['sort'] = 0;
        }
        // 模块列表
        $moduleList = M('school')->where(['is_open' => 1])->select();

        $this->assign('id', $id);
        $this->assign('module_id', $moduleId);
        $this->assign('info', $rotate);
        $this->assign('module_list', $moduleList);
        $this->assign('article_list', $articleList ?? []);
        return $this->fetch();
    }

    /**
     * 删除轮播图
     */
    public function delRotate()
    {
        $id = I('id');
        Db::startTrans();
        M('school_rotate')->where(['id' => $id])->delete();
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    /**
     * 获取分类
     */
    public function getClass()
    {
        $moduleId = I('module_id');
        $class = M('school_class')->where('module_id', $moduleId)->field('id, name')->select();
        $this->ajaxReturn(['status' => 1, 'res' => $class]);
    }

    public function module1()
    {
        $classId = I('class_id', '');
        return $this->module('module1', $classId);
    }

    public function module2()
    {
        $classId = I('class_id', '');
        return $this->module('module2', $classId);
    }

    public function module3()
    {
        $classId = I('class_id', '');
        return $this->module('module3', $classId);
    }

    public function module4()
    {
        $classId = I('class_id', '');
        return $this->module('module4', $classId);
    }

    public function module5()
    {
        $classId = I('class_id', '');
        return $this->module('module5', $classId);
    }

    public function module6()
    {
        $classId = I('class_id', '');
        return $this->module('module6', $classId);
    }

    public function module7()
    {
        $classId = I('class_id', '');
        return $this->module_7('module7', $classId);
    }

    public function module8()
    {
        $classId = I('class_id', '');
        return $this->module_8('module8', $classId);
    }

    public function module9()
    {
        $classId = I('class_id', '');
        return $this->module('module9', $classId);
    }

    /**
     * 模块信息
     * @param $type
     * @param $classId
     * @return mixed
     */
    public function module($type, $classId)
    {
        if (IS_POST) {
            $param = I('post.');
            $type = $param['type'];
            if (empty($param['img'])) {
                $this->error('图片上传错误');
            }
            if (!empty($param['img'])) {
                if (strstr($param['img'], 'aliyuncs.com')) {
                    // 原图
                    $param['img'] = M('school')->where(['type' => $type])->value('img');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($param['img'], strrpos($param['img'], '/public/') + 8);
                    $fileName = substr($param['img'], strrpos($param['img'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $this->ossClient->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $param['img'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
            }
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
            if (empty($param['distribute_level'])) {
                $param['distribute_level'] = '0';
            } else {
                if (in_array('-1', $param['distribute_level'])) {
                    $param['distribute_level'] = '-1';
                } elseif (in_array('0', $param['distribute_level'])) {
                    $param['distribute_level'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($param['distribute_level'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $param['distribute_level'] = rtrim($distributeLevel, ',');
                }
            }
            if (M('school')->where(['type' => $type])->find()) {
                M('school')->where(['type' => $type])->update($param);
            } else {
                M('school')->add($param);
            }
            $this->success('操作成功', U('School/' . $type));
        }
        // 模块信息
        $module = M('school')->where(['type' => $type])->find();
        if (!empty($module['img'])) {
            $img = explode(',', $module['img']);
            $module['img'] = $this->ossClient::url(substr($img[0], strrpos($img[0], 'img:') + 4));
        }
        if (!empty($module)) {
            $module['app_grade'] = explode(',', $module['app_grade']);
            $module['distribute_grade'] = explode(',', $module['distribute_grade']);
            $module['distribute_level'] = explode(',', $module['distribute_level']);
        }
        // 模块分类信息
        $classList = M('school_class')->where(['module_id' => $module['id']])->order('sort DESC')->select();
        $moduleClass = [];
        if ($classId == '' && !empty($classList)) {
            $moduleClass = $classList[0];
            $classId = $moduleClass['id'];
            $moduleClass['app_grade'] = explode(',', $moduleClass['app_grade']);
            $moduleClass['distribute_grade'] = explode(',', $moduleClass['distribute_grade']);
            $moduleClass['distribute_level'] = explode(',', $moduleClass['distribute_level']);
        } else {
            foreach ($classList as $class) {
                if ($classId == $class['id']) {
                    $moduleClass = $class;
                    $moduleClass['app_grade'] = explode(',', $moduleClass['app_grade']);
                    $moduleClass['distribute_grade'] = explode(',', $moduleClass['distribute_grade']);
                    $moduleClass['distribute_level'] = explode(',', $moduleClass['distribute_level']);
                    break;
                }
            }
        }
        // 模块分类文章列表
        $count = M('school_article')->where(['class_id' => $classId])->count();
        $page = new Page($count, 10);
        $schoolArticle = new SchoolArticle();
        $articleList = $schoolArticle->where(['class_id' => $classId, 'status' => ['NEQ', -1]])->limit($page->firstRow . ',' . $page->listRows)->order('sort DESC')->select();

        $this->assign('type', $type);
        $this->assign('class_id', $classId);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('module', $module);
        $this->assign('class_list', $classList);
        $this->assign('module_class', $moduleClass);
        $this->assign('article_list', $articleList);
        $this->assign('page', $page);
        if ($type == 'module6') {
            return $this->fetch('module_6');
        } else {
            return $this->fetch('module');
        }
    }

    public function module_7($type, $classId)
    {
        if (IS_POST) {
            $param = I('post.');
            $type = $param['type'];
            if (empty($param['img'])) {
                $this->error('图片上传错误');
            }
            if (!empty($param['img'])) {
                if (strstr($param['img'], 'aliyuncs.com')) {
                    // 原图
                    $param['img'] = M('school')->where(['type' => $type])->value('img');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($param['img'], strrpos($param['img'], '/public/') + 8);
                    $fileName = substr($param['img'], strrpos($param['img'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $this->ossClient->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $param['img'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
            }
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
            if (empty($param['distribute_level'])) {
                $param['distribute_level'] = '0';
            } else {
                if (in_array('-1', $param['distribute_level'])) {
                    $param['distribute_level'] = '-1';
                } elseif (in_array('0', $param['distribute_level'])) {
                    $param['distribute_level'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($param['distribute_level'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $param['distribute_level'] = rtrim($distributeLevel, ',');
                }
            }
            if (M('school')->where(['type' => $type])->find()) {
                M('school')->where(['type' => $type])->update($param);
            } else {
                M('school')->add($param);
            }
            $this->success('操作成功', U('School/' . $type));
        }
        // 模块信息
        $module = M('school')->where(['type' => $type])->find();
        if (!empty($module['img'])) {
            $img = explode(',', $module['img']);
            $module['img'] = $this->ossClient::url(substr($img[0], strrpos($img[0], 'img:') + 4));
        }
        if (!empty($module)) {
            $module['app_grade'] = explode(',', $module['app_grade']);
            $module['distribute_grade'] = explode(',', $module['distribute_grade']);
            $module['distribute_level'] = explode(',', $module['distribute_level']);
        }
        // 兑换商品列表
        $exchangeGoods = M('school_exchange')->order('sort DESC, id ASC')->select();
        $exchange = [];
        foreach ($exchangeGoods as $k => $v) {
            $exchange[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
            if ($v['item_id']) {
                $exchange[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
            }
            $exchange[$k]['goods_num'] = $v['goods_num'];
            $exchange[$k]['credit'] = $v['credit'];
            $exchange[$k]['is_open'] = $v['is_open'];
            $exchange[$k]['sort'] = $v['sort'];
        }

        $this->assign('type', $type);
        $this->assign('class_id', $classId);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('module', $module);
        $this->assign('exchange', $exchange);
        return $this->fetch('module_7');
    }

    public function module_8($type, $classId)
    {
        if (IS_POST) {
            $param = I('post.');
            $type = $param['type'];
            if (empty($param['img'])) {
                $this->error('图片上传错误');
            }
            if (!empty($param['img'])) {
                if (strstr($param['img'], 'aliyuncs.com')) {
                    // 原图
                    $param['img'] = M('school')->where(['type' => $type])->value('img');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($param['img'], strrpos($param['img'], '/public/') + 8);
                    $fileName = substr($param['img'], strrpos($param['img'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $this->ossClient->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $param['img'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
            }
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
            if (empty($param['distribute_level'])) {
                $param['distribute_level'] = '0';
            } else {
                if (in_array('-1', $param['distribute_level'])) {
                    $param['distribute_level'] = '-1';
                } elseif (in_array('0', $param['distribute_level'])) {
                    $param['distribute_level'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($param['distribute_level'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $param['distribute_level'] = rtrim($distributeLevel, ',');
                }
            }
            if (M('school')->where(['type' => $type])->find()) {
                M('school')->where(['type' => $type])->update($param);
            } else {
                M('school')->add($param);
            }
            $this->success('操作成功', U('School/' . $type));
        }
        // 模块信息
        $module = M('school')->where(['type' => $type])->find();
        if (!empty($module['img'])) {
            $img = explode(',', $module['img']);
            $module['img'] = $this->ossClient::url(substr($img[0], strrpos($img[0], 'img:') + 4));
        }
        if (!empty($module)) {
            $module['app_grade'] = explode(',', $module['app_grade']);
            $module['distribute_grade'] = explode(',', $module['distribute_grade']);
            $module['distribute_level'] = explode(',', $module['distribute_level']);
        }

        $this->assign('type', $type);
        $this->assign('class_id', $classId);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        $this->assign('module', $module);
        return $this->fetch('module_8');
    }

    /**
     * 增加模块分类
     * @return mixed
     */
    public function addModuleClass()
    {
        if (IS_POST) {
            $param = I('post.');
            if (empty($param['module_id'])) {
                $this->error('请先创建模块信息');
            }
            $type = $param['type'];
            $callback = $param['call_back'];
            unset($param['type']);
            unset($param['call_back']);
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
            if (empty($param['distribute_level'])) {
                $param['distribute_level'] = '0';
            } else {
                if (in_array('-1', $param['distribute_level'])) {
                    $param['distribute_level'] = '-1';
                } elseif (in_array('0', $param['distribute_level'])) {
                    $param['distribute_level'] = '0';
                } else {
                    $distributeLevel = '';
                    foreach ($param['distribute_level'] as $level) {
                        $distributeLevel .= $level . ',';
                    }
                    $param['distribute_level'] = rtrim($distributeLevel, ',');
                }
            }
            $classId = M('school_class')->add($param);
            echo "<script>parent.{$callback}('{$type}');</script>";
            exit();
        }

        $moduleId = I('module_id');
        $type = I('type', 'module1');
        $this->assign('module_id', $moduleId);
        $this->assign('module_type', $type);
        $this->assign('app_grade', $this->appGrade);
        $this->assign('svip_grade', $this->svipGrade);
        $this->assign('svip_level', $this->svipLevel);
        return $this->fetch('add_module_class');
    }

    /**
     * 更新模块分类信息
     */
    public function updateModuleClass()
    {
        $param = I('post.');
        $type = $param['type'];
        $classId = $param['class_id'];
        unset($param['type']);
        unset($param['class_id']);
        switch ($param['is_learn']) {
            case 0:
                // 将分类下的文章设置为不规定学习
                M('school_article')->where(['class_id' => $classId])->update(['learn_type' => 0]);
                break;
            case 1:
                // 将分类下没有学习规定的文章设置为必修
                M('school_article')->where(['class_id' => $classId, 'learn_type' => 0])->update(['learn_type' => 1]);
                break;
        }
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
        if (empty($param['distribute_level'])) {
            $param['distribute_level'] = '0';
        } else {
            if (in_array('-1', $param['distribute_level'])) {
                $param['distribute_level'] = '-1';
            } elseif (in_array('0', $param['distribute_level'])) {
                $param['distribute_level'] = '0';
            } else {
                $distributeLevel = '';
                foreach ($param['distribute_level'] as $level) {
                    $distributeLevel .= $level . ',';
                }
                $param['distribute_level'] = rtrim($distributeLevel, ',');
            }
        }
        M('school_class')->where(['id' => $classId])->update($param);
        $this->success('操作成功', U('School/' . $type, ['class_id' => $classId]));
    }

    /**
     * 删除模块分类
     */
    public function delModuleClass()
    {
        $classId = I('class_id');
        Db::startTrans();
        // 删除分类
        M('school_class')->where(['id' => $classId])->delete();
        // 删除分类下文章
        M('school_article')->where(['class_id' => $classId])->delete();
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
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
            $this->error('请先创建分类', U('Admin/School/' . $type));
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
            $this->success('处理成功', U('Admin/School/') . $type . '/class_id/' . $classId);
        }
        if (empty($classId)) {
            $this->error('请先创建模块分类', U('Admin/School/' . $type));
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
     * 添加兑换商品
     * @throws \Exception
     */
    public function addExchange()
    {
        $postData = I('post.');
        $exchangeData = [];
        if (!empty($postData['item'])) {
            foreach ($postData['item'] as $data) {
                $exchangeData[] = [
                    'goods_id' => $data['goods_id'],
                    'item_id' => $data['item_id'] ?? 0,
                    'credit' => $data['credit'],
                    'sort' => $data['sort'],
                    'is_open' => $data['is_open'],
                ];
            }
        }
        Db::startTrans();
        M('school_exchange')->where('1=1')->delete();
        if (!empty($exchangeData)) {
            $schoolExchange = new SchoolExchange();
            $schoolExchange->saveAll($exchangeData);
        }
        Db::commit();
        $this->success('设置成功', U('Admin/School/module7'));
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
        $timeFrom = I('time_from', '') ? strtotime(I('time_from')) : '';
        $timeTo = I('time_to', '') ? strtotime(I('time_to')) : '';
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
        if ($timeFrom && $timeTo) {
            $where['sa.publish_time'] = ['BETWEEN', [$timeFrom, $timeTo]];
        }
        // 排序
        $order = 'sa.publish_time DESC, sa.sort DESC';
        $sort = I('sort', '');
        $sortBy = I('sort_by', '');
        if ($sort && $sortBy) {
            $order = 'sa.' . $sort . ' ' . $sortBy . ', ' . $order;
        }
        $schoolArticle = M('school_article sa')->where($where)->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')->field('sa.*, s.name module_name, sc.name class_name');
        if (!$isExport) {
            // 总数
            $count = M('school_article sa')->where($where)->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')->count();
            $page = new Page($count, 10);
            // 列表
            $schoolArticle = $schoolArticle->limit($page->firstRow . ',' . $page->listRows)->order($order);

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
        if (!$isExport) {
            // 模块列表
            $notModuleType = ['module6', 'module7', 'module8'];
            $module = M('school')->where(['is_open' => 1, 'type' => ['NOT IN', $notModuleType]])->getField('id, name', true);
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
            $this->assign('time_from', I('time_from', ''));
            $this->assign('time_to', I('time_to', ''));
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
}
