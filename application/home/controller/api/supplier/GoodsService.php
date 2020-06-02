<?php

namespace app\home\controller\api\supplier;


class GoodsService extends Base
{
    /**
     * 获取商品规格详情
     * @param array $goodsIds
     * @return array|mixed
     */
    public function getGoodsSpec($goodsIds)
    {
        $data = [
            'goods_id' => implode(',', $goodsIds)
        ];
        return $this->getData('/api/Goods/spec_goods_price', $data);
    }

    /**
     * 获取商品/规格最新库存
     * @param array $goodsData
     * @return array|mixed
     */
    public function getGoodsCount($goodsData)
    {
        $data = [
            'goods' => json_encode($goodsData)
        ];
        return $this->getData('/api/Goods/getGoodsCount', $data);
    }

    /**
     * 检查当前购买区域商品的库存和最低购买数量
     * @param $goodsData
     * @param $province
     * @param $city
     * @param $district
     * @param $town
     * @return array|mixed
     */
    public function checkGoodsRegion($goodsData, $province, $city, $district, $town)
    {
        $data = [
            'goods' => json_encode($goodsData),
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'twon' => $town,
        ];
        return $this->getData('/api/Goods/checkGoodsRegin', $data);
    }
}