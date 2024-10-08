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

class Template extends Base
{
    /**
     *  模板列表.
     */
    public function templateList()
    {
        $t = I('t', 'pc'); // pc or  mobile
        $m = ('pc' == $t) ? 'home' : 'mobile';
        $arr = scandir("./template/$t/");
        foreach ($arr as $key => $val) {
            if ('.' == $val || '..' == $val || strstr($val, 'svn') || strstr($val, 'DS_Store')) {
                continue;
            }
            $template_config[$val] = include "./template/$t/$val/config.php";
        }
        $this->assign('t', $t);
        // $default_theme =  tpCache("hidden.{$t}_default_theme"); // //$default_theme = M('Config')->where("name='{$t}_default_theme'")->getField('value');
        $template_arr = include APP_PATH."/$m/html.php";
        $this->assign('default_theme', $template_arr['template']['default_theme']);
        $this->assign('template_config', $template_config);

        return $this->fetch();
    }

    /**
     * 魔板选择.
     */
    public function changeTemplate()
    {
        $t = I('t', 'pc'); // pc or  mobile
        $key = I('key/s', '');

        $arr = scandir("./template/$t/");
        foreach ($arr as $k => $val) {
            if ('.' == $val || '..' == $val || strstr($val, 'svn') || strstr($val, 'DS_Store')) {
                continue;
            }
            $template[] = $val;
        }
        if (!in_array($key, $template)) {
            return $this->error('非法模板!');
        }

        $m = ('pc' == $t) ? 'home' : 'mobile';

        //$default_theme = tpCache("hidden.{$t}_default_theme"); // 获取原来的配置
        //tpCache("hidden.{$t}_default_theme",$_GET['key']);
        //tpCache('hidden',array("{$t}_default_theme"=>$_GET['key']));
        // 修改文件配置
        if (!is_writeable(APP_PATH."$m/html.php")) {
            return '文件/'.APP_PATH."$m/html.php不可写,不能启用魔板,请修改权限!!!";
        }

        $config_html = "<?php
return [
            'template'               => [
            // 模板引擎类型 支持 php think 支持扩展
            'type'         => 'Think',
            // 模板路径
            'view_path'    => './template/$t/$key/',
            // 模板后缀
            'view_suffix'  => 'html',
            // 模板文件名分隔符
            'view_depr'    => DS,
            // 模板引擎普通标签开始标记
            'tpl_begin'    => '{',
            // 模板引擎普通标签结束标记
            'tpl_end'      => '}',
            // 标签库标签开始标记
            'taglib_begin' => '<',
            // 标签库标签结束标记
            'taglib_end'   => '>',
            //模板文件名
            'default_theme'     => '$key',
        ],
        'view_replace_str'  =>  [
            '__PUBLIC__'=>'/public',
            '__STATIC__' => '/template/$t/$key/static',
            '__ROOT__'=>''
        ]
    ];
?>";
        file_put_contents(APP_PATH."/$m/html.php", $config_html);
        delFile('./runtime');
        $this->success('操作成功!!!', U('admin/template/templateList', ['t' => $t]));
    }

    public function mobile_index()
    {
        return $this->fetch();
    }

    public function block_item_add()
    {
        $model_mb_special = Model('mb_special');

        $param = [];
        $param['special_id'] = $_POST['special_id'];
        $param['item_type'] = $_POST['item_type'];

        //广告只能添加一个
        if ('adv_list' == $param['item_type']) {
            $result = $model_mb_special->isMbSpecialItemExist($param);
            if ($result) {
                echo json_encode(['error' => '广告条板块只能添加一个']);
                die;
            }
        }
        //限时折扣只能添加一个
        if ('goods1' == $param['item_type']) {
            $result = $model_mb_special->isMbSpecialItemExist($param);
            if ($result) {
                echo json_encode(['error' => '限时折扣板块只能添加一个']);
                die;
            }
        }
        //抢购板块只能添加一个
        if ('goods2' == $param['item_type']) {
            $result = $model_mb_special->isMbSpecialItemExist($param);
            if ($result) {
                echo json_encode(['error' => '抢购板块只能添加一个']);
                die;
            }
        }

        $item_info = $model_mb_special->addMbSpecialItem($param);
        if ($item_info) {
            echo json_encode($item_info);
            die;
        }
        echo json_encode(['error' => '添加失败']);
        die;
    }

    public function block_item_del()
    {
        $model_mb_special = Model('mb_special');

        $condition = [];
        $condition['item_id'] = $_POST['item_id'];

        $result = $model_mb_special->delMbSpecialItem($condition, $_POST['special_id']);
        if ($result) {
            echo json_encode(['message' => '删除成功']);
            die;
        }
        echo json_encode(['error' => '删除失败']);
        die;
    }

    public function block_item_edit()
    {
        $model_mb_special = Model('mb_special');

        $item_info = $model_mb_special->getMbSpecialItemInfoByID($_GET['item_id']);
        Tpl::output('item_info', $item_info);

        if (0 == $item_info['special_id']) {
            $this->show_menu('index_edit');
        } else {
            $this->show_menu('special_item_list');
        }
        Tpl::setDirquna('mobile');
        Tpl::showpage('mb_special_item.edit');
    }

    /**
     * 专题项目保存.
     */
    public function block_item_saveOp()
    {
        $model_mb_special = Model('mb_special');

        $result = $model_mb_special->editMbSpecialItemByID(['item_data' => $_POST['item_data']], $_POST['item_id'], $_POST['special_id']);

        if ($result) {
            if ($_POST['special_id'] == $model_mb_special::INDEX_SPECIAL_ID) {
                showMessage(L('nc_common_save_succ'), urlAdminMobile('mb_special', 'index_edit'));
            } else {
                showMessage(L('nc_common_save_succ'), urlAdminMobile('mb_special', 'special_edit', ['special_id' => $_POST['special_id']]));
            }
        } else {
            showMessage(L('nc_common_save_succ'), '');
        }
    }
}
