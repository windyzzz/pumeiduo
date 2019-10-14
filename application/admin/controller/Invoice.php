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

class Invoice extends Base
{
    /*
     * 初始化操作
     */

    public function _initialize()
    {
        parent::_initialize();
        C('TOKEN_ON', false); // 关闭表单令牌验证
    }

    /*
     * 发票列表
     */
    public function index()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 发票列表 ajax.
     *
     * @date 2017/10/23
     */
    public function ajaxindex()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    //开票时间
    public function changetime()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    public function export_invoice()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }
}
