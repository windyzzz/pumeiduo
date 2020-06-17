<?php

namespace app\common\logic\supplier;


class OrderService extends Base
{

    /**
     * 提交订单
     * @param $order
     * @return array|mixed
     */
    public function submitOrder($order)
    {
        $order['order_goods'] = json_encode($order['order_goods']);
        $data = [
            'app_url' => \think\Env::get('SUPPLIER.CALLBACK.DOMAIN') . '',
            'order' => json_encode($order)
        ];
        return $this->getData('/api/Order/submitOrder', $data);
    }
}