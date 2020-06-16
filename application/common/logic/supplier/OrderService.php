<?php

namespace app\common\logic\supplier;


class OrderService extends Base
{

    /**
     * 提交订单
     * @param array $orderArr
     * @return array|mixed
     */
    public function submitOrder($orderArr)
    {
        $orderData = [];
        foreach ($orderArr as $order) {
            $orderData[] = [
                'order_sn' => $order['order_sn'],
                'consignee' => $order['consignee'],
                'province' => $order['province'],
                'city' => $order['city'],
                'district' => $order['district'],
                'twon' => $order['twon'],
                'address' => $order['address'],
                'mobile' => $order['mobile'],
                'goods_price' => $order['goods_price'],
                'total_amount' => $order['total_amount'],
                'note' => $order['user_note'],
                'order_goods' => json_encode($order['order_goods'])
            ];
        }
        $data = [
            'app_url' => \think\Env::get('SUPPLIER.CALLBACK.DOMAIN') . '',
            'order' => $orderData
        ];
        return $this->getData('/api/Order/submitOrder', $data);
    }
}