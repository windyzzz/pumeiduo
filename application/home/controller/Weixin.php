<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller;

use app\common\logic\wechat\WechatUtil;
use app\common\logic\WechatLogic;
use think\Db;

class Weixin
{
    /**
     * 处理接收推送消息.
     */
    public function index()
    {
        $config = Db::name('wx_user')->find();
        if (0 == $config['wait_access']) {
            ob_clean();
            exit($_GET['echostr']);
        }
        $logic = new WechatLogic($config);
        $logic->handleMessage();
    }

    public function createMeun()
    {
        //----------------------------------1------------------------------------

        $menu['button']['0']['name'] = '积分';

        $menu['button']['0']['sub_button']['0']['type'] = 'view';
        $menu['button']['0']['sub_button']['0']['name'] = '积分商城';
        $menu['button']['0']['sub_button']['0']['url'] = 'http://www.boshenmarketing.cn/';

        $menu['button']['0']['sub_button']['1']['type'] = 'view';
        $menu['button']['0']['sub_button']['1']['name'] = '微商城';
        $menu['button']['0']['sub_button']['1']['url'] = 'http://www.boshenmarketing.com/index.php?m=pro&c=index&a=index';

        $menu['button']['0']['sub_button']['2']['type'] = 'view';
        $menu['button']['0']['sub_button']['2']['name'] = '优品专区';
        $menu['button']['0']['sub_button']['2']['url'] = 'http://www.boshenmarketing.com/index.php?m=pro&c=index&a=term&id=44';

        //----------------------------------2------------------------------------

        $menu['button']['1']['name'] = '活动内容';

        $menu['button']['1']['sub_button']['0']['type'] = 'view';
        $menu['button']['1']['sub_button']['0']['name'] = '最新活动';
        $menu['button']['1']['sub_button']['0']['url'] = 'http://www.boshenmarketing.com/index.php?m=article&c=index&a=index';

        $menu['button']['1']['sub_button']['1']['type'] = 'view';
        $menu['button']['1']['sub_button']['1']['name'] = '分享有礼';
        $menu['button']['1']['sub_button']['1']['url'] = 'http://www.boshenmarketing.com/index.php?m=article&c=index&a=index';

        $menu['button']['1']['sub_button']['2']['type'] = 'view';
        $menu['button']['1']['sub_button']['2']['name'] = '在线调研';
        $menu['button']['1']['sub_button']['2']['url'] = 'http://www.boshenmarketing.com/index.php?m=article&c=index&a=index';

        $menu['button']['1']['sub_button']['3']['type'] = 'view';
        $menu['button']['1']['sub_button']['3']['name'] = '秒杀专区';
        $menu['button']['1']['sub_button']['3']['url'] = 'http://www.boshenmarketing.com/index.php?m=article&c=index&a=index';

        //------------------------------------3-------------------------------------

        $menu['button']['2']['name'] = '积分管理';

        $menu['button']['2']['sub_button']['0']['type'] = 'view';
        $menu['button']['2']['sub_button']['0']['name'] = '积分查询';
        $menu['button']['2']['sub_button']['0']['url'] = 'http://www.boshenmarketing.cn/admin/index';

        $menu['button']['2']['sub_button']['1']['type'] = 'view';
        $menu['button']['2']['sub_button']['1']['name'] = '订单查询';
        $menu['button']['2']['sub_button']['1']['url'] = 'http://www.boshenmarketing.com/';

        $menu['button']['2']['sub_button']['2']['type'] = 'view';
        $menu['button']['2']['sub_button']['2']['name'] = '购物车';
        $menu['button']['2']['sub_button']['2']['url'] = 'http://www.boshenmarketing.com/';

        $menu['button']['2']['sub_button']['3']['type'] = 'view';
        $menu['button']['2']['sub_button']['3']['name'] = '积分规则';
        $menu['button']['2']['sub_button']['3']['url'] = 'http://www.boshenmarketing.com/';
        $WechatUtil = new WechatUtil();
        $res = $WechatUtil->createMenu($menu);

        if ($res) {
            exit('success');
        }
        exit('fail');
    }
}
