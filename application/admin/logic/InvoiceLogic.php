<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

use think\Db;
use think\Model;

class InvoiceLogic extends Model
{
    //发票创建
    public function createInvoice($order)
    {
        $data = [
            'order_id' => $order['order_id'],  //订单id
            'user_id' => $order['user_id'],  //用户id
            'ctime' => time(),              //创建时间
            'invoice_money' => $order['total_amount'] - $order['shipping_price'],
        ];
        $invoiceinfo = Db::name('user_extend')->where(['user_id' => $order['user_id']])->find();
        if ('不开发票' != $invoiceinfo['invoice_desc']) {
            $data['invoice_desc'] = '明细'; //发票内容
            $data['taxpayer'] = $order['taxpayer']; //纳税人识别号
            $data['invoice_title'] = $order['invoice_title']; // 发票抬头
            Db::name('invoice')->add($data);
        }
    }
}
