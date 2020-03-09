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

class MobileApp extends Base
{
    public function android_audit()
    {
        $inc_type = 'android';
        $config = tpCache($inc_type);
        $config['download_link'] = !empty($config['app_path']) ? SITE_URL . '/' . $config['app_path'] : '';
        $this->assign('inc_type', $inc_type);
        $this->assign('config', $config); //当前配置项
        return $this->fetch();
    }

    public function ios_audit()
    {
        $inc_type = 'ios';
        if (IS_POST) {
            $param = I('post.');
            $data = [
//                ['is_audit' => $param['is_audit'],
                'app_version' => $param['app_version'],
                'app_log' => $param['app_log'],
                'app_path' => $param['app_path'],
                'is_update' => $param['is_update'],
                'is_force' => $param['is_force']
            ];
            tpCache($inc_type, $data);
            return $this->success('操作成功', url('MobileApp/ios_audit'));
        }
        $this->assign('inc_type', $inc_type);
        $this->assign('config', tpCache($inc_type)); //当前配置项
        return $this->fetch();
    }

    /**
     * 修改配置.
     */
    public function handle()
    {
        $param = I('post.');
        $inc_type = 'android';

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

        $data = [
//                ['is_audit' => $param['is_audit'],
            'app_version' => $param['app_version'],
            'app_log' => $param['app_log'],
            'app_path' => $param['app_path'],
            'is_update' => $param['is_update'],
            'is_force' => $param['is_force']
        ];
        tpCache($inc_type, $data);

//        if (!$file) {
//            return $this->success('保存成功，但是没有文件上传', url('MobileApp/android_audit'));
//        }

        return $this->success('操作成功', url('MobileApp/android_audit'));
    }
}
