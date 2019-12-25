<?php

$config = [
    [
        'name' => 'appid',
        'label' => '开放平台appid',
        'type' => 'text',
        'value' => ''
    ],
    [
        'name' => 'secret',
        'label' => '开放平台secret',
        'type' => 'text',
        'value' => ''
    ]
];
$configValue = [
    'appid' => 'wx02532737c593fd7d',
    'secret' => '43397a2dd91d9b09a4139eb8ceea010b'
];
$data = [
    'code' => 'wechatApp',
    'name' => '微信登录',
    'version' => '1.0',
    'author' => 'yanngH',
    'config' => serialize($config),
    'config_value' => serialize($configValue),
    'desc' => 'APP微信授权登录',
    'status' => 1,
    'type' => 'login',
    'icon' => 'logo.jpg',
    'bank_code' => 'N;',
    'scene' => 3
];

return $data;