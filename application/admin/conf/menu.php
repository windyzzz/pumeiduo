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
    'system' => ['name' => '系统', 'child' => [
        ['name' => '设置', 'child' => [
            ['name' => '商城设置', 'act' => 'index', 'op' => 'System'],
            // array('name'=>'支付方式','act'=>'index1','op'=>'System'),
            ['name' => '地区&配送', 'act' => 'region', 'op' => 'Tools'],
            ['name' => '短信模板', 'act' => 'index', 'op' => 'SmsTemplate'],
            // array('name'=>'接口对接','act'=>'index3','op'=>'System'),
            // array('name'=>'验证码设置','act'=>'index4','op'=>'System'),
            // array('name'=>'自定义导航栏','act'=>'navigationList','op'=>'System'),
            // array('name'=>'友情链接','act'=>'linkList','op'=>'Article'),
            // array('name'=>'自提点','act'=>'index','op'=>'Pickup'),
            ['name' => '运费模板', 'act' => 'index', 'op' => 'Freight'],
            ['name' => '快递公司', 'act' => 'index', 'op' => 'Shipping'],
            ['name' => '节日设置', 'act' => 'icon', 'op' => 'System'],
            ['name' => '银行列表', 'act' => 'bankList', 'op' => 'System'],
            ['name' => '清除缓存', 'act' => 'cleanCache', 'op' => 'System'],
        ]],
        ['name' => '会员', 'child' => [
            ['name' => '会员列表', 'act' => 'index', 'op' => 'User'],
            ['name' => '会员等级', 'act' => 'levelList', 'op' => 'User'],
            ['name' => '会员合并', 'act' => 'merge', 'op' => 'User'],
            ['name' => '会员解绑', 'act' => 'unbind', 'op' => 'User'],
            ['name' => '推荐关系查询', 'act' => 'relation', 'op' => 'User'],
            // array('name'=>'充值记录','act'=>'recharge','op'=>'User'),
            ['name' => '提现申请', 'act' => 'withdrawals', 'op' => 'User'],
            // array('name'=>'汇款记录','act'=>'remittance','op'=>'User'),
            // array('name'=>'会员整合','act'=>'integrate','op'=>'User'),
            ['name' => '会员签到', 'act' => 'signList', 'op' => 'User'],
            ['name' => '金卡审核', 'act' => 'apply_customs', 'op' => 'User'],
            ['name' => '金卡资格', 'act' => 'jinka', 'op' => 'User'],
            ['name' => '会员信息修改记录', 'act' => 'usersEditLog', 'op' => 'User'],
            ['name' => 'APP登陆会员', 'act' => 'appLoginStatistics', 'op' => 'User'],
            ['name' => 'VIP升级记录', 'act' => 'distributeLog', 'op' => 'Distribut'],
        ]],
        ['name' => '广告', 'child' => [
            ['name' => '广告列表', 'act' => 'adList', 'op' => 'Ad'],
            ['name' => '广告位置', 'act' => 'positionList', 'op' => 'Ad'],
            ['name' => 'APP活动弹窗', 'act' => 'popupList', 'op' => 'Ad'],
        ]],
        ['name' => '文章', 'child' => [
            ['name' => '文章列表', 'act' => 'articleList', 'op' => 'Article'],
            ['name' => '文章分类', 'act' => 'categoryList', 'op' => 'Article'],
            ['name' => '帮助中心分类', 'act' => 'helpCenterCate', 'op' => 'Article'],
            ['name' => '常见问题', 'act' => 'questionCate', 'op' => 'Article'],
            ['name' => '系统消息', 'act' => 'messageList', 'op' => 'Article'],
            ['name' => '消息推送', 'act' => 'pushList', 'op' => 'Article'],
            // array('name' => '帮助管理', 'act'=>'help_list', 'op'=>'Article'),
            // array('name' =>'友情链接',  'act'=>'linkList','op'=>'Article'),
            // array('name' => '公告管理', 'act'=>'notice_list', 'op'=>'Article'),
            // array('name' => '专题列表', 'act'=>'topicList', 'op'=>'Topic'),
        ]],
        ['name' => '权限', 'child' => [
            ['name' => '管理员列表', 'act' => 'index', 'op' => 'Admin'],
            ['name' => '角色管理', 'act' => 'role', 'op' => 'Admin'],
            ['name' => '权限资源列表', 'act' => 'right_list', 'op' => 'System'],
            ['name' => '管理员日志', 'act' => 'log', 'op' => 'Admin'],
            ['name' => '供应商列表', 'act' => 'supplier', 'op' => 'Admin'],
        ]],

        /*array('name' => '模板','child'=>array(
                array('name' => '模板设置', 'act'=>'templateList', 'op'=>'Template'),
                array('name' => '自定义手机模板', 'act'=>'index', 'op'=>'Block'),
                array('name' => '自定义页面', 'act'=>'pageList', 'op'=>'Block'),
                array('name' => '手机首页', 'act'=>'mobile_index', 'op'=>'Template'),
        )),
        array('name' => '数据','child'=>array(
                array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools'),
                array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools'),
                //array('name' => 'ecshop数据导入', 'act'=>'ecshop', 'op'=>'Tools'),
                //array('name' => '淘宝csv导入', 'act'=>'taobao', 'op'=>'Tools'),
                //array('name' => 'SQL查询', 'act'=>'log', 'op'=>'Admin'),
        ))*/
    ]],

    'shop' => ['name' => '商城', 'child' => [
        ['name' => '商品', 'child' => [
            ['name' => '商品列表', 'act' => 'goodsList', 'op' => 'Goods'],
            // array('name' => '淘宝导入', 'act'=>'index', 'op'=>'Import'),
            ['name' => '商品分类', 'act' => 'categoryList', 'op' => 'Goods'],
            ['name' => '库存日志', 'act' => 'stock_list', 'op' => 'Goods'],
            ['name' => '商品模型', 'act' => 'goodsTypeList', 'op' => 'Goods'],
            ['name' => '商品规格', 'act' => 'specList', 'op' => 'Goods'],
            ['name' => '品牌列表', 'act' => 'brandList', 'op' => 'Goods'],
            ['name' => '商品属性', 'act' => 'goodsAttributeList', 'op' => 'Goods'],
            ['name' => '评论列表', 'act' => 'index', 'op' => 'Comment'],
            // array('name' => '商品咨询', 'act'=>'ask_list', 'op'=>'Comment'),
        ]],
        ['name' => '订单', 'child' => [
            ['name' => '订单列表', 'act' => 'index', 'op' => 'Order'],
            //['name' => '虚拟订单', 'act' => 'virtual_list', 'op' => 'Order'],
            ['name' => '发货单', 'act' => 'delivery_list', 'op' => 'Order'],
            ['name' => '退款单', 'act' => 'refund_order_list', 'op' => 'Order'],
            ['name' => '退换货', 'act' => 'return_list', 'op' => 'Order'],
            ['name' => '添加订单', 'act' => 'add_order', 'op' => 'Order'],
            ['name' => '订单日志', 'act' => 'order_log', 'op' => 'Order'],
            // array('name' => '发票管理','act'=>'index', 'op'=>'Invoice'),
            //       array('name' => '拼团列表','act'=>'team_list','op'=>'Team'),
            //       array('name' => '拼团订单','act'=>'order_list','op'=>'Team'),
        ]],
        ['name' => '促销', 'child' => [
            ['name' => '抢购管理', 'act' => 'flash_sale', 'op' => 'Promotion'],
            ['name' => '团购管理', 'act' => 'group_buy_list', 'op' => 'Promotion'],
            ['name' => '商品优惠促销', 'act' => 'prom_goods_list', 'op' => 'Promotion'],
            ['name' => '订单优惠促销', 'act' => 'order_prom_list', 'op' => 'Promotion'],
            //array('name' => '订单促销', 'act'=>'prom_order_list', 'op'=>'Promotion'),
            ['name' => '优惠券', 'act' => 'index', 'op' => 'Coupon'],
            ['name' => '满单赠品活动', 'act' => 'gift', 'op' => 'Promotion'],
            ['name' => '指定商品赠品活动', 'act' => 'gift2', 'op' => 'Promotion'],
            ['name' => '加价购', 'act' => 'index', 'op' => 'Extra'],
            // array('name' => '预售管理','act'=>'pre_sell_list', 'op'=>'Promotion'),
            // array('name' => '拼团管理','act'=>'index', 'op'=>'Team'),
        ]],

        ['name' => '分销', 'child' => [
            // array('name' => '分销商品列表', 'act'=>'goods_list', 'op'=>'Distribut'),
            // array('name' => '分销商列表', 'act'=>'distributor_list', 'op'=>'Distribut'),
            // array('name' => '分销关系', 'act'=>'tree', 'op'=>'Distribut'),
            // array('name' => '分销商等级', 'act'=>'grade_list', 'op'=>'Distribut'),
            ['name' => '分成日志', 'act' => 'rebate_log', 'op' => 'Distribut'],
        ]],

        ['name' => '微信', 'child' => [
            ['name' => '公众号配置', 'act' => 'index', 'op' => 'Wechat'],
            ['name' => '微信菜单管理', 'act' => 'menu', 'op' => 'Wechat'],
            ['name' => '自动回复', 'act' => 'auto_reply', 'op' => 'Wechat'],
            ['name' => '粉丝列表', 'act' => 'fans_list', 'op' => 'Wechat'],
            ['name' => '模板消息', 'act' => 'template_msg', 'op' => 'Wechat'],
            ['name' => '素材管理', 'act' => 'materials', 'op' => 'Wechat'],
        ]],

        ['name' => '任务', 'child' => [
            ['name' => '任务配置', 'act' => 'config', 'op' => 'Task'],
            ['name' => '任务管理', 'act' => 'index', 'op' => 'Task'],
            ['name' => '任务详情', 'act' => 'userTask', 'op' => 'Task'],
        ]],

        ['name' => '自定义', 'child' => [
            ['name' => '搜索热词管理', 'act' => 'index', 'op' => 'KeyWord'],
            ['name' => '分类主题活动', 'act' => 'cate_activity_list', 'op' => 'Activity'],
        ]],

        ['name' => '建议', 'child' => [
            ['name' => '反馈类型', 'act' => 'suggestion_cate', 'op' => 'Suggestion'],
            ['name' => '投诉与建议', 'act' => 'suggestion_list', 'op' => 'Suggestion'],
        ]],

        ['name' => '韩国购', 'child' => [
            ['name' => '设置', 'act' => 'config', 'op' => 'Abroad'],
        ]],

        ['name' => '社区', 'child' => [
            ['name' => '分类', 'act' => 'category', 'op' => 'Community'],
            ['name' => '文章', 'act' => 'article', 'op' => 'Community'],
        ]],

        // array('name' => '统计','child' => array(
        // 		array('name' => '销售概况', 'act'=>'index', 'op'=>'Report'),
        // 		array('name' => '销售排行', 'act'=>'saleTop', 'op'=>'Report'),
        // 		array('name' => '会员排行', 'act'=>'userTop', 'op'=>'Report'),
        // 		array('name' => '销售明细', 'act'=>'saleList', 'op'=>'Report'),
        // 		array('name' => '会员统计', 'act'=>'user', 'op'=>'Report'),
        // 		array('name' => '运营概览', 'act'=>'finance', 'op'=>'Report'),
        // 		array('name' => '平台支出记录','act'=>'expense_log','op'=>'Report'),
        // )),
    ]],

    'mobile' => ['name' => '模板', 'child' => [
        ['name' => '设置', 'child' => [
            ['name' => '模板设置', 'act' => 'templateList', 'op' => 'Template'],
            ['name' => '手机支付', 'act' => 'templateList', 'op' => 'Template'],
            ['name' => '微信二维码', 'act' => 'templateList', 'op' => 'Template'],
            ['name' => '第三方登录', 'act' => 'templateList', 'op' => 'Template'],
            ['name' => '导航管理', 'act' => 'finance', 'op' => 'Report'],
            ['name' => '广告管理', 'act' => 'finance', 'op' => 'Report'],
            ['name' => '广告位管理', 'act' => 'finance', 'op' => 'Report'],
        ]],
    ]],

    'resource' => ['name' => '插件', 'child' => [
        ['name' => '云服务', 'child' => [
            ['name' => '插件库', 'act' => 'index', 'op' => 'Plugin'],
            //array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools'),
            //array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools'),
        ]],
        ['name' => 'App', 'child' => [
            ['name' => '安卓APP管理', 'act' => 'android_audit', 'op' => 'MobileApp'],
            ['name' => '苹果APP管理', 'act' => 'ios_audit', 'op' => 'MobileApp'],
        ]],
    ]],

    'finance' => ['name' => '财务', 'child' => [
        ['name' => '奖金结算', 'child' => [
            ['name' => '系统日度计算', 'act' => 'commissionLog', 'op' => 'Finance'],
            ['name' => '系统月度计算', 'act' => 'commissionLogMonth', 'op' => 'Finance'],
            ['name' => '系统年度计算', 'act' => 'commissionLogYear', 'op' => 'Finance'],
        ]],
        ['name' => '账户信息', 'child' => [
            ['name' => '多账户流水', 'act' => 'account_list', 'op' => 'Finance'],
            ['name' => '供应链账户', 'act' => 'supplierAccount', 'op' => 'Finance'],
        ]],
    ]],

    'statistics' => ['name' => '统计', 'child' => [
        ['name' => '销售结算', 'child' => [
            ['name' => '系统日度计算', 'act' => 'index', 'op' => 'Report'],
            ['name' => '系统月度计算', 'act' => 'indexMonth', 'op' => 'Report'],
            ['name' => '系统年度计算', 'act' => 'indexYear', 'op' => 'Report'],
            ['name' => '销售排行', 'act' => 'saleTop', 'op' => 'Report'],
            ['name' => '会员排行', 'act' => 'userTop', 'op' => 'Report'],
            ['name' => '销售明细', 'act' => 'saleList', 'op' => 'Report'],
            ['name' => '会员统计', 'act' => 'user', 'op' => 'Report'],
//				array('name' => '运营概览', 'act'=>'finance', 'op'=>'Report'),
            ['name' => '平台支出记录', 'act' => 'expense_log', 'op' => 'Report'],
        ]],
        ['name' => '点击下载量', 'child' => [
            ['name' => 'H5页点击统计', 'act' => 'clickList', 'op' => 'Report'],
            ['name' => 'APP下载统计', 'act' => 'downloadList', 'op' => 'Report'],
        ]],
        // array('name' => '账户信息','child' => array(
        // 	array('name' => '多账户流水', 'act'=>'account_list', 'op'=>'Finance'),
        // )),
    ]],
];
