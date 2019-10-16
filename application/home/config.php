<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$home_config = [
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
    //默认错误跳转对应的模板文件
    'dispatch_error_tmpl' => 'public:dispatch_jump',
    //默认成功跳转对应的模板文件
    'dispatch_success_tmpl' => 'public:dispatch_jump',
    'controller_auto_search' => true,
    //redis储存数据的时间
    'redis_time' => 86400 * 5,
    'redis_days' => 5
];

$html_config = include_once 'html.php';

return array_merge($home_config, $html_config);
