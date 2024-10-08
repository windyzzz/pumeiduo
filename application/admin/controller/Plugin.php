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

use think\Db;

class Plugin extends Base
{
    public function _initialize()
    {
        parent::_initialize();
        //  更新插件
        $this->insertPlugin($this->scanPlugin());
    }

    public function index()
    {
        $plugin_list = M('plugin')->select();
        $plugin_list = group_same_key($plugin_list, 'type');
        $this->assign('payment', $plugin_list['payment']);
        $this->assign('login', $plugin_list['login']);
        $this->assign('function', $plugin_list['function']);
        $this->assign('type', I('type'));

        return $this->fetch();
    }

    /**
     * 插件安装卸载.
     */
    public function install()
    {
        $condition['type'] = I('get.type');
        $condition['code'] = I('get.code');
        $update['status'] = I('get.install');
        $model = M('plugin');

        //如果是功能插件
        if ('function' == $condition['type']) {
            include_once "plugins/function/{$condition['code']}/plugins.class.php";
            $plugin = new \plugins();
            if (1 == $update['status']) { // 安装
                $execute_sql = $plugin->install_sql(); // 执行安装sql 语句
                $info = $plugin->install();  // 执行 插件安装代码
            } else { // 卸载
                $execute_sql = $plugin->uninstall_sql(); // 执行卸载sql 语句
                $info = $plugin->uninstall(); // 执行插件卸载代码
            }
            // 如果安装卸载 有误则不再往下 执行
            if (0 === $info['status']) {
                exit(json_encode($info));
            }
            // 程序安装没错了, 再执行 sql
            DB::execute($execute_sql);
        }
        //卸载插件时 删除配置信息
        if (0 == $update['status']) {
            $row = DB::name('plugin')->where($condition)->delete();
        } else {
            $row = $model->where($condition)->save($update);
        }
//        $row = $model->where($condition)->save($update);
        //安装时更新配置信息(读取最新的配置)
        if ('payment' == $condition['type'] && $update['status']) {
            $file = PLUGIN_PATH.$condition['type'].'/'.$condition['code'].'/config.php';
            $config = include $file;
            $add['bank_code'] = serialize($config['bank_code']);
            $add['config'] = serialize($config['config']);
            $add['config_value'] = '';
            $model->where($condition)->save($add);
        }

        if ($row) {
            $info['status'] = 1;
            $info['msg'] = $update['status'] ? '安装成功!' : '卸载成功!';
        } else {
            $info['status'] = 0;
            $info['msg'] = $update['status'] ? '安装失败' : '卸载失败';
        }
        exit(json_encode($info));
    }

    /**
     * 插件目录扫描.
     *
     * @return array 返回目录数组
     */
    private function scanPlugin()
    {
        $plugin_list = [];
        $plugin_list['payment'] = $this->dirscan(C('PAYMENT_PLUGIN_PATH'));
        $plugin_list['login'] = $this->dirscan(C('LOGIN_PLUGIN_PATH'));
        $plugin_list['function'] = $this->dirscan(C('FUNCTION_PLUGIN_PATH'));

        foreach ($plugin_list as $k => $v) {
            foreach ($v as $k2 => $v2) {
                if (!file_exists(PLUGIN_PATH.$k.'/'.$v2.'/config.php')) {
                    unset($plugin_list[$k][$k2]);
                } else {
                    $plugin_list[$k][$v2] = include PLUGIN_PATH.$k.'/'.$v2.'/config.php';
                    unset($plugin_list[$k][$k2]);
                }
            }
        }

        return $plugin_list;
    }

    /**
     * 获取插件目录列表.
     *
     * @param $dir
     *
     * @return array
     */
    private function dirscan($dir)
    {
        $dirArray = [];
        if (false != ($handle = opendir($dir))) {
            $i = 0;
            while (false !== ($file = readdir($handle))) {
                //去掉"“.”、“..”以及带“.xxx”后缀的文件
                if ('.' != $file && '..' != $file && !strpos($file, '.')) {
                    $dirArray[$i] = $file;
                    ++$i;
                }
            }
            //关闭句柄
            closedir($handle);
        }

        return $dirArray;
    }

    /**
     * 更新插件到数据库.
     *
     * @param $plugin_list array 本地插件数组
     */
    private function insertPlugin($plugin_list)
    {
        $save_path = UPLOAD_PATH.'logistics/';
        $source_path = PLUGIN_PATH.'shipping/';

        $new_arr = []; // 本地
        //插件类型
        foreach ($plugin_list as $pt => $pv) {
            //  本地对比数据库
            foreach ($pv as $t => $v) {
                $tmp['code'] = $v['code'];
                $tmp['type'] = $pt;
                $new_arr[] = $tmp;
                // 对比数据库 本地有 数据库没有
                $is_exit = M('plugin')->where(['type' => $pt, 'code' => $v['code']])->find();
                if (empty($is_exit)) {
                    if ('shipping' == $pt) {
                        @copy($source_path.$v['code'].'/'.$v['icon'], $save_path.$v['code'].'.jpg');
                        $add['icon'] = $v['icon'];
                    } else {
                        $add['icon'] = $v['icon'];
                    }
                    $add['code'] = $v['code'];
                    $add['name'] = $v['name'];
                    $add['version'] = $v['version'];
                    $add['author'] = $v['author'];
                    $add['desc'] = $v['desc'];
                    $add['bank_code'] = serialize($v['bank_code']);
                    $add['type'] = $pt;
                    $add['scene'] = $v['scene'];
                    $add['config'] = empty($v['config']) ? '' : serialize($v['config']);
                    M('plugin')->add($add);
                }
            }
        }
        //数据库有 本地没有
//        foreach($d_list as $k=>$v){
//            if(!in_array($v,$new_arr)){
//                M('plugin')->where($v)->delete();
//            }
//        }
    }

    /*
     * 插件信息配置
     */
    public function setting()
    {
        $condition['type'] = I('get.type');
        $condition['code'] = I('get.code');
        $model = M('plugin');

        $row = $model->where($condition)->find();
        if (!$row) {
            exit($this->error('不存在该插件'));
        }

        $row['config'] = unserialize($row['config']);
        // $data = array();
        // $data['name'] = 'sign_type';
        // $data['label'] = '签名方式';
        // $data['type'] = 'text';
        // $data['value'] = '';
        // $row['config'][] =$data;
        // $value = unserialize($row['config_value']);
        // $value['sign_type'] = 'RSA2';
        // $model->where($condition)->save(array('config_value'=>serialize($value)));
        // $model->where($condition)->save(array('config'=>serialize($row['config'])));
        // dump($row['config']);
        // dump($value);
        // exit;
        if (IS_POST) {
            $config = I('post.config/a');

            //空格过滤
            $config = trim_array_element($config);
            if ($config) {
                $config = serialize($config);
            }
            $row = $model->where($condition)->save(['config_value' => $config]);
            if ($row) {
                exit($this->success('操作成功'));
            }
            exit($this->error('操作失败'));
        }

        $this->assign('plugin', $row);
        $this->assign('config_value', unserialize($row['config_value']));

        return $this->fetch();
    }

    /**
     * 检查插件是否存在.
     *
     * @return mixed
     */
    private function checkExist()
    {
        $condition['type'] = I('get.type');
        $condition['code'] = I('get.code');

        $model = M('plugin');
        $row = $model->where($condition)->find();
        if (!$row && false) {
            exit($this->error('不存在该插件'));
        }

        return $row;
    }

    public function check_str($str)
    {
        //$pat ='/[a-zA-Z\x{4e00}-\x{9fa5}]*$/u';// '/^[a-zA-Z0-9_]*$/';
        $pat = '/[a-zA-Z\x{4e00}-\x{9fa5}]/u'; // '/^[a-zA-Z0-9_]*$/';
        if (!preg_match($pat, $str)) {
            return  false;
        }

        return true;
    }
}
