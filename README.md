圃美多服务端
===============

## 目录结构

目录结构如下：

~~~
www  WEB部署目录（或者子目录）
├─application           应用目录
|  ├─admin              管理后台目录
|  |  ├─conf
|  |  ├─controller      管理后台控制器目录
|  |  ├─logic           管理后台逻辑目录
|  |  ├─model           管理后台模型目录
|  |  ├─validate        管理后台校验目录
|  |  ├─view            管理后台视图目录
|  |  ├─common.php      
|  |  ├─config.php
|  |  ├─index.html
│  |
│  ├─common             公共模块目录
|  |  ├─behavior        API监听行为目录
|  |  ├─logic           API逻辑目录
|  |  ├─model           API模型目录
|  |  ├─util            API扩展目录
|  |  ├─validate        API校验目录
│  |
│  ├─home               API目录
|  |  ├─behavior        API校验目录
|  |  ├─controller      API控制器目录
|  |  ├─logic
|  |  ├─model
|  |  ├─validate        API校验目录
|  |  ├─view
|  |  ├─bank.php
│  │  ├─common.php      模块函数文件
│  │  ├─config.php      模块配置文件
│  │  ├─html.php
│  │  ├─index.html
│  │  ├─nacigate.php
│  │  └─nginx.conf
│  │
│  ├─command.php        
│  ├─common.php         公共函数文件
│  ├─config.php         公共配置文件
│  ├─database.php       数据库配置文件
│  ├─function.php       公共函数文件
│  ├─route.php          
│  └─tags.php           应用行为扩展定义文件
│
├─code_png
|
├─database              数据迁移记录
│  ├─migrations         数据表记录
│  ├─seeds              数据记录
|
├─extend
|
├─plugins               第三方插件
|
├─public                WEB目录
|
├─response
│
├─thinkphp              框架系统目录
│  ├─lang               语言文件目录
│  ├─library            框架类库目录
│  │  ├─think           Think类库包目录
│  │  └─traits          系统Trait目录
│  │
│  ├─tpl                系统模板目录
│  ├─base.php           基础定义文件
│  ├─console.php        控制台入口文件
│  ├─convention.php     框架惯例配置文件
│  ├─helper.php         助手函数文件
│  ├─phpunit.xml        phpunit配置文件
│  └─start.php          框架入口文件
│
├─extend                扩展类库目录
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                第三方类库目录（Composer依赖库）
├─build.php             自动生成定义文件（参考）
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
├─think                 命令行入口文件
~~~
