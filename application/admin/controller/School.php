<?php

namespace app\admin\controller;


use app\admin\model\SchoolArticle;
use app\admin\model\SchoolArticleResource;
use app\admin\model\SchoolExchange;
use app\admin\model\SchoolStandard;
use app\common\logic\OssLogic;
use think\Db;
use think\Page;

class School extends Base
{
    private $ossClient = null;

    public function __construct()
    {
        parent::__construct();
        $this->ossClient = new OssLogic();
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
            $this->success('操作成功', U('Admin/School/config'));
        }
        // 配置
        $schoolConfig = M('school_config')->select();
        $config = [];
        foreach ($schoolConfig as $val) {
            if ($val['type'] == 'official' && !empty($val['url'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            }
            $config[$val['type']] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }
        // 学习达标设置
        $standard = M('school_standard')->order('type ASC, num DESC')->select();
        $standardCount = count($standard);
        $svipLevel = M('svip_level')->order('app_level ASC')->select();
        // 轮播图
        $count = M('school_rotate')->where(['module_id' => 0])->count();
        $page = new Page($count, 10);
        $images = M('school_rotate')->where(['module_id' => 0])->limit($page->firstRow . ',' . $page->listRows)->order('sort DESC')->select();
        foreach ($images as &$image) {
            $url = explode(',', $image['url']);
            $image['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            $image['module_type'] = M('school')->where(['type' => $image['module_type']])->value('name');
        }

        $this->assign('config', $config);
        $this->assign('standard', $standard);
        $this->assign('standard_count', $standardCount);
        $this->assign('svip_level', $svipLevel);
        $this->assign('images', $images);
        $this->assign('page', $page);
        return $this->fetch();
    }


    public function userStandardList()
    {
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
        // 用户学习课程记录总数
        $count = M('user_school_article')->where(['article_id' => ['IN', $courseIds]])->group('user_id')->count();
        // 用户课程学习记录
        $page = new Page($count, 10);
        $userCourseLog = M('user_school_article usa')->join('users u', 'u.user_id = usa.user_id')
            ->join('distribut_level dl', 'dl.level_id = u.distribut_level')
            ->where(['article_id' => ['IN', $courseIds]])->group('usa.user_id')
            ->field('u.user_id, u.nickname, u.user_name, u.school_credit, u.distribut_level, dl.level_name')
            ->limit($page->firstRow . ',' . $page->listRows)->select();
        foreach ($userCourseLog as &$log) {
            $log['is_reach'] = 0;       // 未达标
            $log['course_num'] = 0;     // 用户课程数量
            // 检查是否达标
            /*
             * 查看课程数量
             */
            $user = [
                'user_id' => $log['user_id'],
                'user_name' => $log['user_name'],
                'distribut_level' => $log['distribut_level']
            ];
            $res = $this->checkUserCourseNum($user, $courseIds);
            if ($res['status'] == -1) {
                $this->error($res['msg']);
            }
            $log['course_num'] = $res['course_num'];
            if ($res['user_level'] > 3) {
                if (cache('svip_level')) {
                    $log['level_name'] = cache('svip_level')[$res['user_level']];
                } else {
                    $svipLevel = M('svip_level')->getField('app_level, name', true);
                    cache('svip_level', $svipLevel, 0);
                    $log['level_name'] = $svipLevel[$res['user_level']];
                }
            }
            if ($res['status'] == 1) {
                $log['is_reach'] = 1;
            }
            /*
             * 查看乐活豆数量
             */

        }
        echo '<pre>';
        print_r($userCourseLog);
        echo '</pre>';
        exit();
    }

    /**
     * 检查用户是否满足课程数量达标
     * @param array $user 用户信息
     * @param array $courseIds 学习课程IDs
     * @return array
     */
    private function checkUserCourseNum($user, $courseIds)
    {
        $where = [
            'user_id' => $user['user_id'],
            'article_id' => ['IN', $courseIds],
            'status' => 1,
        ];
        // 用户学习课程记录总数
        $userCourseNum = M('user_school_article')->where($where)->group('user_id')->getField('count(article_id) as count');
        $userCourseNum = $userCourseNum ?? 0;
        $userLevel = $user['distribut_level'];
        if ($userLevel == 3) {
            if (cache('svip_level_' . $user['user_id'])) {
                $userLevel = cache('svip_level_' . $user['user_id']);
            } else {
                // 拥有代理商等级划分，需要从代理商查询用户的代理商等级
                $url = C('SERVER_URL') . '/index.php/Crond/get_user_grade/user_name/' . $user['user_name'];
                $res = httpRequest($url);
                if (!$res) {
                    return ['status' => -1, 'msg' => $user['user_id'] . '代理商等级获取检查失败'];
                }
                $res = json_decode($res, true);
                if (isset($res['status']) && $res['status'] == 1) {
                    $userLevel = $res['station'] + 2;
                    cache('svip_level_' . $user['user_id'], $userLevel, 3600);
                } else {
                    return ['status' => -1, 'msg' => '代理商等级获取检查失败'];
                }
            }
        }
        // 学习规则达标设置
        $return = ['status' => 0, 'course_num' => $userCourseNum, 'user_level' => $userLevel];
        $schoolStandard = cache('school_standard');
        foreach ($schoolStandard as $v) {
            if ($v['type'] == 2) {
                continue;
            }
            if ($userLevel == $v['distribute_level']) {
                if ($userCourseNum >= $v['num']) {
                    $return['status'] = 1;
                }
                break;
            }
        }
        return $return;
    }


    public function exportUserStandardList()
    {
        // 学习课程id
        $courseIds = M('school_article')->where([
            'learn_type' => ['IN', [1, 2]],
            'status' => 1,
        ])->getField('id', true);
        // 学习课程总数量
        $courseNum = count($courseIds);
        // 记录时间
        $source = I('source', 1);
        switch ($source) {
            case 1:
                // 当前一个月
                $from = strtotime(date('Y-m-01 00:00:00', time()));
                $to = strtotime(date('Y-m-t 23:59:59', time()));
                break;
            case 2:
                // 指定时间段
                $from = strtotime(I('time_from'));
                $to = strtotime(I('time_to'));
                break;
        }
        $where = [
            'article_id' => ['IN', $courseIds],
            'status' => 1,
            'finish_time' => ['BETWEEN', [$from, $to]]
        ];
        // 用户学习课程记录列表
        $userLog = M('user_school_article usa')->join('users u', 'u.user_id = usa.user_id')
            ->where($where)
            ->group('usa.user_id')
            ->field('u.user_id, u.nickname, u.user_name, count(article_id) as count')
            ->select();
        // 学习规则达标设置
        $schoolStandard = cache('school_standard');
        if (empty($schoolStandard)) {
            $userLog = [];
        } else {
            if (!empty($courseIds)) {
                $level = I('level', '');
                foreach ($schoolStandard as $k => $standard) {
                    $schoolStandard[$k]['course_num'] = $standard['course_percent'] / 100 * $courseNum;
                }
                $schoolStandard = array_reverse($schoolStandard);
                foreach ($userLog as $k1 => $log) {
                    foreach ($schoolStandard as $k2 => $standard) {
                        if ($log['count'] < $standard['course_num']) {
                            if ($level && $level != $standard['level']) {
                                unset($userLog[$k1]);
                            } else {
                                $userLog[$k1]['course_level'] = $standard['level'];
                            }
                            break 1;
                        } elseif ($k2 == (count($schoolStandard) - 1)) {
                            if ($level && $level != $standard['level']) {
                                unset($userLog[$k1]);
                            } else {
                                $userLog[$k1]['course_level'] = $standard['level'];
                            }
                            break 1;
                        }
                    }
                }
            }
        }
        // 表头
        $headList = [
            '用户', '课程数量', '达标等级'
        ];
        // 表数据
        $dataList = [];
        foreach ($userLog as $log) {
            if (!empty($log['nickname'])) {
                $userName = $log['nickname'];
            } elseif (!empty($log['user_name'])) {
                $userName = $log['user_name'];
            } else {
                $userName = '用户：' . $log['user_id'];
            }
            $dataList[] = [
                $userName,
                $log['count'],
                $log['course_level']
            ];
        }
        toCsvExcel($dataList, $headList, 'user_standard_list');
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
            if (!empty($param['url'])) {
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
            $imageInfo = M('school_rotate')->where(['id' => $id])->find();
            $url = explode(',', $imageInfo['url']);
            $imageInfo['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
        } else {
            $imageInfo['sort'] = 0;
        }
        // 模块列表
        $moduleList = M('school')->select();

        $this->assign('id', $id);
        $this->assign('module_id', $moduleId);
        $this->assign('info', $imageInfo);
        $this->assign('module_list', $moduleList);
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
            $module['distribute_level'] = explode(',', $module['distribute_level']);
        }
        // 模块分类信息
        $classList = M('school_class')->where(['module_id' => $module['id']])->order('sort DESC')->select();
        $moduleClass = [];
        if ($classId == '' && !empty($classList)) {
            $moduleClass = $classList[0];
            $classId = $moduleClass['id'];
            $moduleClass['distribute_level'] = explode(',', $moduleClass['distribute_level']);
        } else {
            foreach ($classList as $class) {
                if ($classId == $class['id']) {
                    $moduleClass = $class;
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
            $module['distribute_level'] = explode(',', $module['distribute_level']);
        }

        $this->assign('type', $type);
        $this->assign('class_id', $classId);
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
            // 等级限制
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
                M('school_article')->add($param);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => ['type' => $type, 'class_id' => $classId]]);
        }
        if (empty($classId)) {
            $this->error('请先创建分类', U('Admin/School/' . $type));
        }
        if ($articleId) {
            $articleInfo = M('school_article')->where(['id' => $articleId])->find();
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
            // 等级限制
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
}
