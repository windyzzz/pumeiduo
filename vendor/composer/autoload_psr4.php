<?php

// autoload_psr4.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'think\\testing\\' => array($vendorDir . '/topthink/think-testing/src'),
    'think\\migration\\' => array($vendorDir . '/topthink/think-migration/src'),
    'think\\helper\\' => array($vendorDir . '/topthink/think-helper/src'),
    'think\\composer\\' => array($vendorDir . '/topthink/think-installer/src'),
    'think\\captcha\\' => array($vendorDir . '/topthink/think-captcha/src'),
    'think\\' => array($baseDir . '/thinkphp/library/think', $vendorDir . '/topthink/think-image/src'),
    'plugins\\' => array($baseDir . '/plugins'),
    'Symfony\\Polyfill\\Mbstring\\' => array($vendorDir . '/symfony/polyfill-mbstring'),
    'Symfony\\Component\\Yaml\\' => array($vendorDir . '/symfony/yaml'),
    'Symfony\\Component\\DomCrawler\\' => array($vendorDir . '/symfony/dom-crawler'),
    'Phinx\\' => array($vendorDir . '/topthink/think-migration/phinx/src/Phinx'),
    'OSS\\' => array($vendorDir . '/aliyuncs/oss-sdk-php/src/OSS'),
    'Doctrine\\Instantiator\\' => array($vendorDir . '/doctrine/instantiator/src/Doctrine/Instantiator'),
);
