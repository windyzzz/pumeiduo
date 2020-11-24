<?php

namespace app\admin\controller;


use think\Controller;

class School extends Controller
{
    /**
     * 模块信息
     * @return mixed
     */
    public function module()
    {
        $type = I('type', 'module1');
        $classId = I('class_id', '');

        if (IS_POST) {
            // 更新模块信息
            $param = I('post.');
            $type = $param['type'];
            unset($param['type']);
            if (empty($param['img'])) {
                $this->error('图片上传错误');
            }
            $imgInfo = getimagesize(PUBLIC_PATH . substr($param['img'], strrpos($param['img'], 'public') + 7));
            if (empty($imgInfo)) {
                $this->error('图片上传错误');
            }
            // 更新模块信息
            $param['img'] = json_encode([
                'img' => $param['img'],
                'width' => $imgInfo[0],
                'height' => $imgInfo[1],
                'type' => substr($imgInfo['mime'], strrpos($imgInfo['mime'], '/') + 1),
            ]);
            M('school')->where(['type' => $type])->update($param);
            $this->success('操作成功', U('School/module', ['type' => $type]));
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

        $this->assign('class_id', $classId);
        $this->assign('module', $module);
        $this->assign('class_list', $classList);
        $this->assign('module_class', $moduleClass);
        return $this->fetch();
    }


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
        $this->success('操作成功', U('School/module', ['type' => $type, 'class_id' => $classId]));
    }
}