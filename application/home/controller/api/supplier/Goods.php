<?php

namespace app\home\controller\api\supplier;


class Goods
{
    /**
     * 供应链商品错误处理
     * @param $type
     * @param $supplierGoodsId
     */
    public function goodsErrorHandle($type, $supplierGoodsId)
    {
        $syncData = [
            'supplier_goods_id' => $supplierGoodsId
        ];
        switch ($type) {
            case 1:
                // 库存不足
                $syncData['store_count'] = 0;
                break;
            case 2:
                // 失效
                $syncData['goods_state'] = 0;
                break;
            case 3:
                // 下架
                $syncData['is_on_sale'] = 0;
                break;
        }
        return (new Sync())->sendSyncData(3, $type, $syncData);
    }
}