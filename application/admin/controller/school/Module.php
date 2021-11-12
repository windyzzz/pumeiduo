<?php

namespace app\admin\controller\school;

use app\admin\model\SchoolArticle;
use app\admin\model\SchoolExchange;
use app\admin\model\SchoolStandard;
use think\Db;
use think\Page;

class Module extends Base
{
    public function __construct()
    {
        parent::__construct();
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
                                $this->error('请上传弹窗封面图', U('school.module/config'));
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
                                    $this->error('请选择跳转文章', U('school.module/config'));
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
            if (isset($keyword) && is_array($keyword)) {
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
            }
            $this->success('操作成功', U('school.module/config'));
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

    public function config2()
    {
        if (IS_POST) {
            $param = I('post.');
            // 配置
            foreach ($param as $k => $v) {
                switch ($k) {
                    case 'publicize':
                        if (strstr($v['url'], 'aliyuncs.com')) {
                            // 原图
                            $v['url'] = M('school_config')->where(['type' => 'publicize'])->value('url');
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
                }
            }
            $this->success('操作成功', U('school.module/config2'));
        }
        // 配置
        $schoolConfig = M('school_config')->select();
        $config = [];
        foreach ($schoolConfig as $val) {
            if ($val['type'] == 'publicize' && !empty($val['url'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            }
            $config[$val['type']] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }

        $this->assign('config', $config);
        return $this->fetch();
    }

    /**
     * 删除热门词
     */
    public function delKeyword()
    {
        $id = I('id');
        M('school_article_keyword')->where(['id' => $id])->delete();
        $this->ajaxReturn(['status' => 1]);
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
        $where = ['module_id' => I('module_id')];
        $isLearn = I('is_learn', 0);
        if ($isLearn) {
            $where['is_learn'] = $isLearn;
        }
        $class = M('school_class')->where($where)->field('id, name')->select();
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
            if (isset($param['is_top'])) {
                if ($param['is_top'] == 0) {
                    $param['top_btn'] = '';
                } elseif (M('school')->where(['type' => ['NEQ', 'module9'], 'is_top' => 1])->count('id') == 3) {
                    $this->error('已经有2个模块置顶了');
                }
            }
            if (M('school')->where(['type' => $type])->find()) {
                M('school')->where(['type' => $type])->update($param);
            } else {
                M('school')->add($param);
            }
            $this->success('操作成功', U('school.module/' . $type));
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
        $count = M('school_article')->where(['class_id' => $classId, 'status' => ['NEQ', -1]])->count();
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
            if (isset($param['is_top'])) {
                if ($param['is_top'] == 0) {
                    $param['top_btn'] = '';
                } elseif (M('school')->where(['type' => ['NEQ', 'module9'], 'is_top' => 1])->count('id') == 3) {
                    $this->error('已经有2个模块置顶了');
                }
            }
            if (M('school')->where(['type' => $type])->find()) {
                M('school')->where(['type' => $type])->update($param);
            } else {
                M('school')->add($param);
            }
            $this->success('操作成功', U('school.module/' . $type));
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
            if (isset($param['is_top'])) {
                if ($param['is_top'] == 0) {
                    $param['top_btn'] = '';
                } elseif (M('school')->where(['type' => ['NEQ', 'module9'], 'is_top' => 1])->count('id') == 3) {
                    $this->error('已经有2个模块置顶了');
                }
            }
            if (M('school')->where(['type' => $type])->find()) {
                M('school')->where(['type' => $type])->update($param);
            } else {
                M('school')->add($param);
            }
            $this->success('操作成功', U('school.module/' . $type));
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
        $this->success('操作成功', U('school.module/' . $type, ['class_id' => $classId]));
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
        $this->success('设置成功', U('school.module/module7'));
    }
}
