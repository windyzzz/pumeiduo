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
            'app_url' => \think\Env::get('SUPPLIER.CALLBACK.DOMAIN') . 'index.php/supplier.notice/deliveryData',
            'order' => json_encode($order)
        ];
        return $this->getData('/api/Order/submitOrder', $data);
    }

    /**
     * 确认订单收货
     * @param $orderSn
     * @return array|mixed
     */
    public function confirmOrder($orderSn)
    {
        $data = [
            'order_sn' => $orderSn
        ];
        return $this->getData('?m=api&c=order&a=receOrder', $data);
    }

    /**
     * 取消订单
     * @param $orderSn
     * @return array|mixed
     */
    public function cancelOrder($orderSn)
    {
        $data = [
            'order_sn' => $orderSn
        ];
        return $this->getData('?m=api&c=order&a=refund', $data);
    }

    /**
     * 查询物流
     * @param $orderSn
     * @param $goodsId
     * @return array|mixed
     */
    public function getExpress($orderSn, $goodsId)
    {
        $data = [
            'order_sn' => $orderSn,
            'Goods_id' => $goodsId
        ];
        return $this->getData('?m=api&c=order&a=getExpress', $data);
    }

    /**
     * 提交售后
     * @param $returnGoods
     * @return array|mixed
     */
    public function refundOrder($returnGoods)
    {
        $data = [
            'return_goods' => json_encode($returnGoods),
        ];
        return $this->getData('?m=api&c=order&a=refundOrder', $data);
    }

    /**
     * 取消售后
     * @param $returnGoods
     * @param $afterSaleSn
     * @return array|mixed
     */
    public function closeRefundOrder($returnGoods, $afterSaleSn)
    {
        $data = [
            'return_goods' => json_encode($returnGoods),
            'after_sale_sn' => $afterSaleSn,
            'status' => -2,
        ];
        return $this->getData('?m=api&c=order&a=closeRefundOrder', $data);
    }

    /**
     * 获取售后服务信息
     * @param $afterSaleSn
     * @return array|mixed
     */
    public function afterSaleInfo($afterSaleSn)
    {
        $data = [
            'after_sale_sn' => $afterSaleSn
        ];
        return $this->getData('?m=api&c=Aftersale&a=queryAftersaleInfo', $data);
    }
}