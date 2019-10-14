<?php
return array(
    'code'=> 'alipayApp',
    'name' => '支付宝',
    'version' => '1.0',
    'author' => 'J',
    'desc' => '支付宝App支付 ',
    'icon' => 'logo.jpg',
    'scene' =>3,  // 使用场景 0 PC+手机 1 手机 2 PC 3App
    'config' => array(
        array('name' => 'appId','label'=>'app_id',             'type' => 'text',       'value' => 'app_id'),
        array('name' => 'rsaPrivateKey','label'=>'私钥',       'type' => 'textarea',   'value' => '请填写开发者私钥去头去尾去回车，一行字符串'),
        array('name' => 'alipayrsaPublicKey','label'=>'公钥',  'type' => 'textarea',   'value' => '请填写支付宝公钥，一行字符串'),
        array('name' => 'format','label'=>'格式',       'type' => 'text',       'value' => 'json'),
        array('name' => 'charset','label'=>'编码格式',             'type' => 'text',   'value' => 'UTF-8'),
        array('name' => 'signType','label'=>'签名方式',            'type' => 'text',   'value' => 'RSA2'),
    ),
);
