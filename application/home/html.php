<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

return [
            'template' => [
            // 模板引擎类型 支持 php think 支持扩展
            'type' => 'Think',
            // 模板路径
            'view_path' => '',
            // 模板后缀
            'view_suffix' => 'html',
            // 模板文件名分隔符
            'view_depr' => DS,
            // 模板引擎普通标签开始标记
            'tpl_begin' => '{',
            // 模板引擎普通标签结束标记
            'tpl_end' => '}',
            // 标签库标签开始标记
            'taglib_begin' => '<',
            // 标签库标签结束标记
            'taglib_end' => '>',
            //模板文件名
            'default_theme' => 'rainbow',
        ],
        'view_replace_str' => [
            '__PUBLIC__' => '/public',
            '__STATIC__' => '/template/pc/rainbow/static',
            '__ROOT__' => '',
        ],
    ];
