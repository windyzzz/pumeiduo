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
    'app\home\command\supplier\Region',                  // 匹配保存供应链地区数据
    'app\home\command\supplier\Goods',                   // 供应链商品更新价格属性
    "app\home\command\shop\UserOrderPvCheck",            // 每月检查会员个人业绩
    "app\home\command\shop\CreateUserChain",             // 记录用户推荐链
    "app\home\command\shop\MonthlyAgentGrade",           // 经销商每月定级
    "app\home\command\shop\UpdateUserAccount",           // 更新用户代理商资金信息
    "app\home\command\shop\MonthlyBonus",                // 月结奖金
    "app\home\command\shop\CheckUserReferrerChain",      // 检查会员推荐关系链条
];
