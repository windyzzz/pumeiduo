<?php

namespace app\admin\controller;


use app\admin\model\SchoolArticle;
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

    /**
     * 模块信息
     * @param $type
     * @param $classId
     * @return mixed
     */
    public function module($type, $classId)
    {
        if (IS_POST) {
            // 更新模块信息
            $param = I('post.');
            $type = $param['type'];
            if (empty($param['img'])) {
                $this->error('图片上传错误');
            }
            $imgInfo = getimagesize(PUBLIC_PATH . substr($param['img'], strrpos($param['img'], 'public') + 7));
            if (empty($imgInfo)) {
                $this->error('图片上传错误');
            }
            $param['img'] = json_encode([
                'img' => $param['img'],
                'width' => $imgInfo[0],
                'height' => $imgInfo[1],
                'type' => substr($imgInfo['mime'], strrpos($imgInfo['mime'], '/') + 1),
            ]);
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
            $imgInfo = json_decode($module['img'], true);
            $module['img'] = $imgInfo['img'];
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
        return $this->fetch('module');
    }

    /**
     * 增加模块分类
     * @return mixed
     */
    public function addModuleClass()
    {
        if (IS_POST) {
            $param = I('post.');
            $type = $param['type'];
            $callback = $param['call_back'];
            unset($param['type']);
            unset($param['call_back']);
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
                        $param['cover'] = 'url:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1];
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
                    $param['update_time'] = NOW_TIME;
                }
                M('school_article')->where(['id' => $articleId])->update($param);
            } else {
                $param['status'] = 2;   // 预发布
                $param['add_time'] = NOW_TIME;
                M('school_article')->add($param);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功', 'result' => ['type' => $type, 'class_id' => $classId]]);
        }
        if ($articleId) {
            $articleInfo = M('school_article')->where(['id' => $articleId])->find();
            $articleInfo['distribute_level'] = explode(',', $articleInfo['distribute_level']);
            $cover = explode(',', $articleInfo['cover']);
            $articleInfo['cover'] = $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'url:') + 4));
            $articleInfo['publish_time'] = date('Y-m-d H:i:s', $articleInfo['publish_time']);
        } else {
            $articleInfo = [];
            $articleInfo['sort'] = 0;
            $articleInfo['integral'] = 0;
            $articleInfo['publish_time'] = date('Y-m-d H:i:s', time());
        }
        $this->assign('type', $type);
        $this->assign('class_id', $classId);
        $this->assign('info', $articleInfo);
        $this->assign('article_id', $articleId);
        return $this->fetch();
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
            'update_time' => NOW_TIME
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
}
