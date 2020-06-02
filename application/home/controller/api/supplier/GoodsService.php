<?php

namespace app\home\controller\api\supplier;


class GoodsService extends Base
{
    /**
     * 查询商品区域购买限制
     * @param array $goodsIds
     * @param $province
     * @param $city
     * @param $district
     * @param $town
     * @param array $specKey
     * @return array|mixed
     */
    public function queryGoodsCheck($goodsIds, $province, $city, $district, $town, $specKey = [])
    {
        $data = [
            'goods_id' => implode(',', $goodsIds),
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'twon' => $town,
            'spec_key' => !empty($specKey) ? $specKey : '',
            'type' => 'address_check'
        ];
        return $this->getData('/api/Goodscheck/queryGoodsCheck', $data);
    }
}