<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

use think\Page;

class MobileApp extends Base
{
    public function android_audit()
    {
        $inc_type = 'android';
        $config = M('config')->where(['inc_type' => $inc_type])->group('name')->order('id desc')->getField('name, value', true);
        if (!empty($config['show_version'])) {
            $config['show_version'] = explode(';', $config['show_version']);
        } else {
            $config['show_version'] = [];
        }
        if (!empty($config['update_version'])) {
            $config['update_version'] = explode(';', $config['update_version']);
        } else {
            $config['update_version'] = [];
        }
        $versionList = M('app_log')->where(['type' => 'android'])->order('app_version ASC')->getField('app_version', true);
//        foreach ($versionList as $k => $version) {
//            if ($version == $config['app_version']) {
//                // 不显示当前版本
//                unset($versionList[$k]);
//            }
//        }

        $this->assign('inc_type', $inc_type);
        $this->assign('config', $config);    // 当前配置项
        $this->assign('version_list', $versionList);
        return $this->fetch();
    }

    public function ios_audit()
    {
        $inc_type = 'ios';
        $config = M('config')->where(['inc_type' => $inc_type])->group('name')->order('id desc')->getField('name, value', true);
        if (!empty($config['show_version'])) {
            $config['show_version'] = explode(';', $config['show_version']);
        } else {
            $config['show_version'] = [];
        }
        if (!empty($config['update_version'])) {
            $config['update_version'] = explode(';', $config['update_version']);
        } else {
            $config['update_version'] = [];
        }
        $versionList = M('app_log')->where(['type' => 'ios'])->order('app_version ASC')->getField('app_version', true);
//        foreach ($versionList as $k => $version) {
//            if ($version == $config['app_version']) {
//                // 不显示当前版本
//                unset($versionList[$k]);
//            }
//        }

        $this->assign('inc_type', $inc_type);
        $this->assign('config', $config);    // 当前配置项
        $this->assign('version_list', $versionList);
        return $this->fetch();
    }

    /**
     * 修改配置.
     */
    public function handle()
    {
        $param = I('post.');
        $inc_type = $param['inc_type'];
        // 配置信息
        $config = M('config')->where(['inc_type' => $inc_type])->getField('name, value', true);
        unset($param['inc_type']);
        unset($param['show_version_all']);
        unset($param['update_version_all']);
        if (isset($param['show_version'])) {
            $param['show_version'] = implode(';', $param['show_version']);
        } else {
            $param['show_version'] = '';
        }
        if (isset($param['update_version'])) {
            $param['update_version'] = implode(';', $param['update_version']);
        } else {
            $param['update_version'] = '';
        }
        foreach ($param as $k => $v) {
            if (isset($config[$k])) {
                M('config')->where(['inc_type' => $inc_type, 'name' => $k])->update(['value' => $v]);
            } else {
                M('config')->add([
                    'inc_type' => $inc_type,
                    'name' => $k,
                    'value' => $v
                ]);
            }
        }
        // 更新记录
        $appLog = M('app_log')->where(['type' => $inc_type, 'app_version' => $param['app_version']])->find();
        $param['update_time'] = time();
        if (empty($appLog)) {
            $param['type'] = $inc_type;
            $param['create_time'] = time();
            M('app_log')->add($param);
        } else {
            M('app_log')->where(['id' => $appLog['id']])->update($param);
        }

        switch ($inc_type) {
            case 'ios':
                return $this->success('操作成功', url('MobileApp/ios_audit'));
            case 'android':
                return $this->success('操作成功', url('MobileApp/android_audit'));
        }

//        $file = request()->file('app_path');
//        if ($file) {
//            $result = $this->validate(
//                ['android_app' => $file],
//                ['android_app' => 'fileSize:40000000000000|fileExt:apk'],
//                ['android_app.fileSize' => '上传文件过大', 'android_app.fileExt' => '文件格式不正确']
//            );
//            if (true !== $result) {
//                return $this->error('上传文件出错：' . $result, url('MobileApp/android_audit'));
//            }
//            $savePath = UPLOAD_PATH . 'appfile/';
//            $saveName = 'android_' . $param['app_version'] . '_' . date('Ymd_His') . '.' . pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
//            $info = $file->move($savePath, $saveName);
//            if (!$info) {
//                return $this->error('文件保存出错', url('MobileApp/android_audit'));
//            }
//            $return_url = $savePath . $info->getSaveName();
//            tpCache($inc_type, ['app_path' => $return_url]);
//        }
//        if (!$file) {
//            return $this->success('保存成功，但是没有文件上传', url('MobileApp/android_audit'));
//        }
    }

    /**
     * APP版本记录
     * @return mixed
     */
    public function appLog()
    {
        $type = I('type');
        $count = M('app_log')->where(['type' => $type])->count();
        $page = new Page($count, 10);
        $appLog = M('app_log')->where(['type' => $type])->order('app_version desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        foreach ($appLog as &$log) {
            $log['show_version'] = explode(';', $log['show_version']);
            $log['update_version'] = explode(';', $log['update_version']);
        }

        $this->assign('type', $type);
        $this->assign('page', $page);
        $this->assign('list', $appLog);
        return $this->fetch('app_log');
    }
}
