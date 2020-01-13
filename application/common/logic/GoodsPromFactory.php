<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

use app\common\logic\team\TeamActivityLogic;

/**
 * 商品活动工厂类
 * Class CatsLogic.
 */
class GoodsPromFactory
{
    /**
     * @param $goods|商品实例
     * @param $spec_goods_price|规格实例
     *
     * @return FlashSaleLogic|GroupBuyLogic|PromGoodsLogic
     */
    public function makeModule($goods, $spec_goods_price)
    {
        switch ($goods['prom_type']) {
            case 1:
                return new FlashSaleLogic($goods, $spec_goods_price);
            case 2:
                return new GroupBuyLogic($goods, $spec_goods_price);
            case 3:
                return new PromGoodsLogic($goods, $spec_goods_price);
            case 6:
                return new TeamActivityLogic($goods, $spec_goods_price);
            default:
                return [];
        }
    }

    /**
     * 检测是否符合商品活动工厂类的使用.
     *
     * @param $promType |活动类型
     *
     * @return bool
     */
    public function checkPromType($promType)
    {
        if (in_array($promType, array_values([1, 2, 3, 6]))) {
            return true;
        }

        return false;
    }
}
