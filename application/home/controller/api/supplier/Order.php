<?php

namespace app\home\controller\api\supplier;


use think\Db;
use think\Exception;

class Order
{
    /**
     * 退换货处理
     * @param $param
     * @return array
     */
    public function returnHandle($param)
    {
        try {
            $return = M('return_goods')->where(['supplier_sale_sn' => $param['after_sale_sn']])->find();
            if (empty($return)) throw new Exception('退换货信息不存在');
            $updateData = [
                'supplier_sale_status' => $param['status'],
                'supplier_sale_remark' => $param['remark']
            ];
            if ($param['status'] == 1) {
                // 供应链收货地址
                $receiveInfo = $param['receive_info'];
                $provinceName = M('region2')->where(['ml_region_id' => $receiveInfo['province']])->value('name');
                $cityName = M('region2')->where(['ml_region_id' => $receiveInfo['city']])->value('name');
                $districtName = M('region2')->where(['ml_region_id' => $receiveInfo['district']])->value('name');
                $townName = M('region2')->where(['ml_region_id' => $receiveInfo['town']])->value('name');
                $supplierReceiveInfo = [
                    'address' => $provinceName . ',' . $cityName . ',' . $districtName . ',' . $townName . ',' . $receiveInfo['address'],
                    'consignee' => $receiveInfo['consignee'],
                    'mobile' => $receiveInfo['mobile']
                ];
                $updateData['supplier_receive_info'] = json_encode($supplierReceiveInfo);
            }
            // 更新退换货信息
            M('return_goods')->where(['id' => $return['id']])->update($updateData);
            return ['status' => 1, 'msg' => '更新成功', 'data' => ['parent_sn' => $return['order_sn']]];
        } catch (Exception $e) {
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }
}