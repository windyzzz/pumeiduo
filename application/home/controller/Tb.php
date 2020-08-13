<?php

namespace app\home\controller;

use app\common\model\DeliveryDoc;
use app\common\model\HtnsDeliveryLog;
use app\common\model\GoodsImages;
use app\common\model\SupplierGoodsSpec;
use think\Controller;
use think\Db;

class Tb extends Controller
{

    function __construct()
    {
        parent::__construct();
    }

    /**
     * 发送给erp系统
     */
    function send_system()
    {
        $where = array(
            'status' => 0
        );
        $tb = M('tb')->where($where)->order('add_time asc')->limit(10)->select();

        if ($tb) {
            include_once "plugins/Tb.php";
            $Tb = new \Tb();
            foreach ($tb as $k => $v) {
                $tb_data = array();
                $tb_data['type'] = $v['type'];
                $tb_data['system'] = $v['from_system'];
                $tb_data['tb_sn'] = $v['tb_sn'];
                switch ($v['type']) {
                    case 6:
                        // 订单
                        $order_add_time = M('order')->where(array('order_id' => $v['from_id']))->getField('add_time');
                        if (empty($order_add_time) || $order_add_time < 1561910400) {
                            // 旧订单不处理
                            M('tb')->where(array('id' => $v['id']))->update(array('tb_time' => NOW_TIME, 'status' => 1, 'msg' => '旧订单不处理'));
                            continue 2;
                        }
                        $tb_data['data'] = $this->send_order($v['from_id']);
                        break;
                    case 8:
                        // 申请代理
                        $tb_data['data'] = $this->send_apply_customs($v['from_id']);
                        break;
                    case 9:
                        // 退还库存
                        $tb_data['data'] = $this->send_order_refund($v['from_id']);
                        break;
                    case 11:
                        // 订单pv
                        $tb_data['data'] = $this->send_order_pv($v['from_id']);
                        break;
                }
                $request_send = $Tb->tb_now($v['system'], $tb_data);
                if ($request_send['status'] == 1) {
                    // 同步成功  改变状态
                    M('tb')->where(array('id' => $v['id']))->data(array('tb_time' => NOW_TIME, 'status' => 1, 'msg' => ''))->save();
                    if ($v['type'] == 11) {
                        // 订单pv
                        M('order')->where(['order_id' => $v['from_id']])->update(['pv_send' => 1]);
                    }
                } else {
                    M('tb')->where(array('id' => $v['id']))->data(array('tb_time' => NOW_TIME, 'status' => 0, 'msg' => $request_send['msg']))->save();
                }
            }
        }
    }

    /*function send_order_refund1($return_id){
        $arr = array(
            78=>'2019-09-24 14:43:56',
            80=>'2019-09-24 15:00:06',
            81=>'2019-09-24 14:59:43',
            82=>'2019-09-24 14:58:48',
            83=>'2019-09-24 14:59:22'
        );

        $return_goods = Db::name('return_goods')->where(['id' => $return_id])->find();
        $order_goods = Db::name('order_goods')->where(['rec_id' => $return_goods['rec_id']])->find();
        $goods_sn = M('goods')->where(array('goods_id'=>$return_goods['goods_id']))->getField('goods_sn');

        $return_goods_data = array(
            'order_sn'=>$return_goods['order_sn'],
            'goods_sn'=>$goods_sn,
            'goods_num'=>$return_goods['goods_num'],
            'spec_key'=>$return_goods['spec_key'],
            'is_send'=>$order_goods['is_send'],
            'add_time'=>strtotime($arr[$return_id]),

        );
        return $return_goods_data;
    }

    function bu(){
        $arr = array(
            78,
            80,
            81,
            82,
            83
        );
        foreach($arr as $k=>$v){
            include_once "plugins/Tb.php";
            $TbLogic = new \Tb();
            $badd_tb_zx = $TbLogic->add_tb(3,11,$v,0);
        }


    }*/

    /**
     * 退还库存
     */
    function send_order_refund($return_id)
    {
        $return_goods = Db::name('return_goods')->where(['id' => $return_id])->find();
        $order_goods = Db::name('order_goods')->where(['rec_id' => $return_goods['rec_id']])->find();
        $goods_sn = M('goods')->where(array('goods_id' => $return_goods['goods_id']))->getField('goods_sn');
        $return_goods_data = array(
            'order_sn' => $return_goods['order_sn'],
            'goods_sn' => $goods_sn,
            'goods_num' => $return_goods['goods_num'],
            'spec_key' => $return_goods['spec_key'],
            'is_send' => $order_goods['is_send']
        );

        return $return_goods_data;
    }

    /**
     * 发送申请
     * @param $apply_id
     * @return mixed
     */
    function send_apply_customs($apply_id)
    {
        $apply_customs = M('apply_customs')->where(array('id' => $apply_id))->find();

        $referee_user_name = '';
        if ($apply_customs['referee_user_id']) {
            $referee_user_name = M('users')->where(array('user_id' => $apply_customs['referee_user_id']))->getField('user_name');
        }

        $apply_customs_data = array(
            'user_id' => $apply_customs['user_id'],
            'referee_user_name' => $referee_user_name,
            'true_name' => $apply_customs['true_name'],
            'id_card' => $apply_customs['id_card'],
            'mobile' => $apply_customs['mobile'],
            'add_time' => $apply_customs['add_time'],
            'cancel_time' => $apply_customs['cancel_time'],
            'status' => $apply_customs['status'],
        );

        return $apply_customs_data;
    }

    function send_order($order_id)
    {
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $order_id]);
        $order = $orderObj->append(['full_address', 'orderGoods', 'adminOrderButton'])->toArray();
        $orderGoods = $order['orderGoods'];

        //获取套组商品
        //$order_goods_tao = M('order_goods_tao')->where(array('order_id'=>$order_id))->select();
        //$this->assign('order_goods_tao',$order_goods_tao);
        $data = array();
        $data['order'] = array(
            'order_sn' => $order['order_sn'],
            'order_type' => $order['order_type'],
            'order_status' => $order['order_status'],
            'shipping_status' => $order['shipping_status'],
            'pay_status' => $order['pay_status'],
            'consignee' => $order['consignee'],
            'id_card' => $order['id_card'],
            'country' => $order['country'],
            'province' => $order['province'],
            'city' => $order['city'],
            'district' => $order['district'],
            'address' => $order['full_address'],
            'mobile' => $order['mobile'],
            'shipping_code' => $order['shipping_code'],
            'shipping_name' => $order['shipping_name'],
            'pay_code' => $order['pay_code'],
            'goods_price' => $order['goods_price'],
            'shipping_price' => $order['shipping_price'],
            'order_amount' => $order['order_amount'],
            'total_amount' => $order['total_amount'],
            'add_time' => $order['add_time'],
            'shipping_time' => $order['shipping_time'],
            'confirm_time' => $order['confirm_time'],
            'pay_time' => $order['pay_time'],
            'user_note' => $order['user_note'],
            'goods_area' => 3,
            'parent_sn' => $order['parent_id'] > 0 ? M('order')->where(['order_id' => $order['parent_id']])->value('order_sn') : '',
            'supplier_order_sn' => $order['supplier_order_sn'],
            'supplier_order_status' => $order['supplier_order_status'],
        );
        //$user = get_user_info($order['user_id'],0,'','user_name,true_name,mobile');
        $delivery_record = M('delivery_doc')->where('order_id=' . $order_id)->order('id desc')->limit(1)->find();
        $data['order']['invoice_no'] = $delivery_record ? $delivery_record['invoice_no'] : '';

        $data['order_goods'] = array();
        foreach ($orderGoods as $k => $v) {
            $data['order_goods'][] = array(
                'goods_sn' => $v['goods_sn'],
                'goods_name' => $v['goods_name'],
                'goods_num' => $v['goods_num'],
                'goods_price' => $v['goods_price'],
                'member_goods_price' => $v['member_goods_price'],
                'spec_key' => $v['spec_key'],
                'spec_key_name' => $v['spec_key_name'],
                'other_rec_id' => $v['rec_id'],
                'order_sn2' => $v['order_id2'] > 0 ? M('order')->where(['order_id' => $v['order_id2']])->value('order_sn') : ''
            );
        }
        return $data;
    }

    /**
     * 订单pv信息
     * @param $orderId
     * @return mixed
     */
    function send_order_pv($orderId)
    {
        $orderData = M('order o')->join('users u', 'u.user_id = o.user_id')
            ->where(['o.order_id' => $orderId])
            ->field('u.user_name, o.order_sn, o.order_pv, o.order_amount, o.user_electronic')->find();
        // 查看是否有处理完的售后
        $returnGoods = M('return_goods')->where(['order_id' => $orderId, 'status' => ['NOT IN', [-2, -1, 0]]])->field('refund_money, refund_electronic')->select();
        $goodsPrice = bcadd($orderData['order_amount'], $orderData['user_electronic'], 2);
        if (!empty($returnGoods)) {
            foreach ($returnGoods as $return) {
                $goodsPrice = bcsub($goodsPrice, bcadd($return['refund_money'], $return['refund_electronic'], 2), 2);
            }
        }
        $orderData['goods_price'] = $goodsPrice;
        unset($orderData['order_amount']);
        unset($orderData['user_electronic']);
        return $orderData;
    }

    /**
     * ===================================================================
     * 接受仓储系统的更新
     * @return string
     */
    function get_system()
    {
        $tb_data = $_POST['result'];
        if ($tb_data) {
            $tb_data = json_decode($tb_data, true);
            $type = $tb_data['type'];
            $data = $tb_data['data'];
            $tb_sn = $tb_data['tb_sn'];
            $get_id = M('tb_get')->data(array('tb_sn' => $tb_sn, 'data' => json_encode($tb_data), 'add_time' => NOW_TIME))->add();
            if ($type == 1) {//更新商品
                $back = $this->save_goods($data);
            } else if ($type == 2) {//更新品牌
                $back = $this->save_brand($data);
            } else if ($type == 3) {//更新供货商
                $back = $this->save_suppliers($data);
            } else if ($type == 4) {//更新供货商
                $back = $this->save_type($data);
            } else if ($type == 5) {//更新库存
                $back = $this->save_stock($data);
            } else if ($type == 6) {//更新订单
                $back = $this->save_order_v2($data);
            } else if ($type == 7) {//更新快递
                $back = $this->save_logistics($data);
            } else if ($type == 8) {//更新申请代理
                $back = $this->save_apply_customs($data);
            } else {
                $back = false;
            }
            if ($back !== true) {
                M('tb_get')->where(array('id' => $get_id))->data(array('msg' => $back, 'status' => 0))->save();
                return json_encode(array('status' => 0, 'msg' => $back));
            } else {
                M('tb_get')->where(array('id' => $get_id))->data(array('msg' => 'success', 'status' => 1))->save();
                return json_encode(array('status' => 1, 'msg' => 'success'));
            }
        }
    }

    /**
     * 更新代理
     */
    function save_apply_customs($apply_data)
    {
        Db::startTrans();
        $save_data = array(
            'status' => 1,
            'success_time' => $apply_data['success_time']
        );

        $user_info = get_user_info($apply_data['user_id'], 0, '');

        $apply_customs = M('apply_customs')->where(array('user_id' => $apply_data['user_id'], 'status' => array('neq', 1)))->data($save_data)->save();
        $busers = M('users')->where(array('user_id' => $apply_data['user_id'], 'distribut_level' => array('neq', 3)))->data(array('distribut_level' => 3, 'user_name' => $apply_data['bind_user_name'], 'bind_uid' => $apply_data['user_id'], 'bind_time' => NOW_TIME))->save();

        //级别记录
        $logDistribut = logDistribut('', $apply_data['user_id'], 3, $user_info['distribut_level'], 1);

        if ($apply_customs && $busers && $logDistribut) {
            Db::commit();
        } else {
            Db::rollback();
        }
        return true;
    }

    /**
     * 更新订单状态和快递信息
     * @param $order_data
     * @return bool
     */
    function save_order($order_data)
    {
        $order = $order_data['order'];
        $data = array(
            'shipping_status' => $order['shipping_status'],
            'shipping_code' => $order['shipping_code'],
            'shipping_name' => $order['shipping_name'],
            'shipping_time' => $order['shipping_time'],
        );
        M('order')->where(array('order_sn' => $order['order_sn']))->data($data)->save();
        $order_id = M('order')->where(array('order_sn' => $order['order_sn']))->getField('order_id');
        $delivery_doc = $order_data['delivery_doc'];
        unset($delivery_doc['id']);
        $delivery_doc['order_id'] = $order_id;

        M('order_goods')->where(array('order_id' => $order_id, 'is_send' => 0))->data(array('is_send' => 1))->save();

        M('delivery_doc')->data($delivery_doc)->add();
        return true;
    }

    /**
     * 更新订单状态和快递信息
     * @param $orderData
     * @return bool
     * @throws \Exception
     */
    function save_order_v2($orderData)
    {
        // 更新订单信息
        $order = $orderData['order'];
        $data = [
            'shipping_status' => $order['shipping_status'],
            'shipping_code' => $order['shipping_code'],
            'shipping_name' => $order['shipping_name'],
            'shipping_time' => $order['shipping_time'],
            'delivery_type' => $order['delivery_type'],
            'pay_status' => $order['pay_status'],
            'pay_time' => $order['pay_time'],
            'supplier_order_status' => $order['supplier_order_status'],
            'supplier_pay_status' => $order['supplier_pay_status'],
            'supplier_shipping_status' => $order['supplier_shipping_status'],
        ];
        M('order')->where(array('order_sn' => $order['order_sn']))->data($data)->save();
        // 更新订单物流信息
        $orderInfo = M('order')->where(array('order_sn' => $order['order_sn']))->field('order_id, order_sn, user_id, order_type, parent_id')->find();
        $deliveryData = [];
        $sendRecId = [];
        if (!empty($orderData['delivery_doc'])) {
            if ($orderInfo['order_type'] == 3 && $orderInfo['parent_id'] > 0) {
                // 子订单物流信息
                $parentOrder = M('order')->where(['order_id' => $orderInfo['parent_id']])->field('order_id, order_sn, user_id')->find();
                foreach ($orderData['delivery_doc'] as $delivery) {
                    $deliveryData[] = [
                        'order_id' => $parentOrder['order_id'],
                        'order_sn' => $parentOrder['order_sn'],
                        'rec_id' => $delivery['other_rec_id'],
                        'goods_num' => $delivery['goods_num'],
                        'user_id' => $parentOrder['user_id'],
                        'admin_id' => $delivery['admin_id'],
                        'consignee' => $delivery['consignee'],
                        'zipcode' => $delivery['zipcode'],
                        'mobile' => $delivery['mobile'],
                        'country' => $delivery['country'],
                        'province' => $delivery['province'],
                        'city' => $delivery['city'],
                        'district' => $delivery['district'],
                        'address' => $delivery['address'],
                        'shipping_code' => $delivery['shipping_code'],
                        'shipping_name' => $delivery['shipping_name'],
                        'shipping_price' => $delivery['shipping_price'],
                        'invoice_no' => $delivery['invoice_no'],
                        'tel' => $delivery['tel'],
                        'note' => $delivery['note'],
                        'best_time' => $delivery['best_time'],
                        'is_del' => $delivery['is_del'],
                        'create_time' => time(),
                        'htns_status' => $delivery['htns_status']
                    ];
                    if (!empty($delivery['invoice_no'])) {
                        $sendRecId[] = $delivery['other_rec_id'];
                    }
                }
            } else {
                foreach ($orderData['delivery_doc'] as $delivery) {
                    $deliveryData[] = [
                        'order_id' => $orderInfo['order_id'],
                        'order_sn' => $orderInfo['order_sn'],
                        'rec_id' => $delivery['other_rec_id'],
                        'goods_num' => $delivery['goods_num'],
                        'user_id' => $orderInfo['user_id'],
                        'admin_id' => $delivery['admin_id'],
                        'consignee' => $delivery['consignee'],
                        'zipcode' => $delivery['zipcode'],
                        'mobile' => $delivery['mobile'],
                        'country' => $delivery['country'],
                        'province' => $delivery['province'],
                        'city' => $delivery['city'],
                        'district' => $delivery['district'],
                        'address' => $delivery['address'],
                        'shipping_code' => $delivery['shipping_code'],
                        'shipping_name' => $delivery['shipping_name'],
                        'shipping_price' => $delivery['shipping_price'],
                        'invoice_no' => $delivery['invoice_no'],
                        'tel' => $delivery['tel'],
                        'note' => $delivery['note'],
                        'best_time' => $delivery['best_time'],
                        'is_del' => $delivery['is_del'],
                        'create_time' => time(),
                        'htns_status' => $delivery['htns_status']
                    ];
                    if (!empty($delivery['invoice_no'])) {
                        $sendRecId[] = $delivery['other_rec_id'];
                    }
                }
            }
        }
        if (!empty($deliveryData)) {
            M('delivery_doc')->where(['order_id' => $orderInfo['order_id']])->delete();
            $deliveryDoc = new DeliveryDoc();
            $deliveryDoc->saveAll($deliveryData);
            M('order_goods')->where(['rec_id' => ['IN', $sendRecId]])->where(['is_send' => 0])->update(['is_send' => 1]);
        }
        // 更新HTNS物流配送记录
        $htnsDeliveryData = [];
        if (!empty($orderData['htns_delivery_log'])) {
            foreach ($orderData['htns_delivery_log'] as $delivery) {
                $htnsDeliveryData[] = [
                    'order_id' => $orderInfo['order_id'],
                    'goods_name' => $delivery['goods_name'],
                    'goods_num' => $delivery['goods_num'],
                    'status' => $delivery['status'],
                    'create_time' => $delivery['create_time'],
                    'time_zone' => $delivery['time_zone']
                ];
            }
        }
        if (!empty($htnsDeliveryData)) {
            M('htns_delivery_log')->where(['order_id' => $orderInfo['order_id']])->delete();
            $htnsDeliveryDoc = new HtnsDeliveryLog();
            $htnsDeliveryDoc->saveAll($htnsDeliveryData);
        }
        return true;
    }

    function save_stock($goods)
    {
        $isSupply = M('goods')->where(array('goods_sn' => $goods['goods_sn']))->value('is_supply');
        if ($isSupply == 1) {
            return true;
        }

        $spec_goods_price = !empty($goods['spec_goods_price']) ? $goods['spec_goods_price'] : '';

        //更新主商品库存
        M('goods')->where(array('goods_sn' => $goods['goods_sn']))->data(array('store_count' => $goods['stock']))->save();

        if ($spec_goods_price) {
            //获取旧规格
            foreach ($spec_goods_price as $key => $val) {
                M('spec_goods_price')->where(array('item_sn' => $key))->data(array('store_count' => $val))->save();
            }
        } else {
            //一键代发产品  获取子规格
            $goods_info = M('goods')->field('trade_type,goods_id')->where(array('goods_sn' => $goods['goods_sn']))->find();

            if ($goods_info['trade_type'] == 2) {
                $spec_goods_price = M('spec_goods_price')->where(array('goods_id' => $goods_info['goods_id']))->field('item_id')->order('item_id asc')->select();

                if ($spec_goods_price) {
                    $count = count($spec_goods_price);
                    $yushu = $goods['stock'] % $count;
                    $stock = ($goods['stock'] - $yushu) / $count;

                    foreach ($spec_goods_price as $k => $v) {
                        if ($k == 0) {
                            $my_stock = $stock + $yushu;
                        } else {
                            $my_stock = $stock;
                        }
                        M('spec_goods_price')->where(array('goods_id' => $goods_info['goods_id'], 'item_id' => $v['item_id']))->data(array('store_count' => $my_stock))->save();
                    }
                }
            }
        }
        return true;
    }

    function ceshi_stock()
    {
        $str = '{"type":"5","tb_sn":"7731901562284061","data":{"goods_sn":"K217156","stock":119}}';
        $arr = json_decode($str, true);
        $this->save_stock($arr['data']);
    }

    function save_type($data)
    {
        //更新模型与规格
        //模型  删除后 添加
        M('goods_type')->where('1=1')->delete();
        if ($data['type']) {
            foreach ($data['type'] as $k => $v) {
                $save_data = array(
                    'id' => $v['id'],
                    'name' => $v['name']
                );
                M('goods_type')->data($save_data)->add();
            }
        }

        //规格  删除后 添加
        M('spec')->where('1=1')->delete();
        if ($data['spec']) {
            foreach ($data['spec'] as $k => $v) {
                $save_data = array(
                    'id' => $v['id'],
                    'name' => $v['name'],
                    'type_id' => $v['type_id'],
                    'order' => $v['order'],
                    'search_index' => $v['search_index']
                );
                M('spec')->data($save_data)->add();
            }
        }

        //规格明细 删除后 添加
        M('spec_item')->where('1=1')->delete();
        if ($data['spec_item']) {
            foreach ($data['spec_item'] as $k => $v) {
                $save_data = array(
                    'id' => $v['id'],
                    'spec_id' => $v['spec_id'],
                    'item' => $v['item']
                );
                M('spec_item')->data($save_data)->add();
            }
        }
        return true;
    }

    function save_suppliers($suppliers)
    {
        $suppliers_old = M('suppliers')->getField('suppliers_id,suppliers_name,is_check');
        foreach ($suppliers as $k => $v) {
            if (isset($suppliers_old[$v['id']])) {
                unset($suppliers_old[$v['id']]);//
                M('suppliers')->where(array('suppliers_id' => $v['id']))->data(array('suppliers_name' => $v['name']))->save(); //更新
            } else {
                M('suppliers')->data(array('suppliers_name' => $v['name'], 'suppliers_id' => $v['id']))->add();//新增
            }
        }
        if ($suppliers_old) {//如果还有则删除
            foreach ($suppliers_old as $k => $v) {
                M('suppliers')->where(array('suppliers_id' => $v['id']))->delete();//删除
            }
        }
        return true;
    }

    function save_brand($brand)
    {
        $brand_old = M('brand')->getField('id,name,cat_id');
        foreach ($brand as $k => $v) {
            if (isset($brand_old[$v['id']])) {
                unset($brand_old[$v['id']]);//
                M('brand')->where(array('id' => $v['id']))->data(array('name' => $v['name']))->save(); //更新
            } else {
                M('brand')->data(array('name' => $v['name'], 'id' => $v['id']))->add();//新增
            }
        }
        if ($brand_old) {//如果还有则删除
            foreach ($brand_old as $k => $v) {
                M('brand')->where(array('id' => $v['id']))->delete();//删除
            }
        }
        return true;
    }

    function save_goods($goods)
    {
        $area3 = $goods['area3'];
        if ($goods['is_supply'] == 1) {
            $trade_type = 3;
        } elseif ($goods['is_one_send'] == 1) {
            $trade_type = 2;
        } else {
            $trade_type = 1;
        }
        $isSupply = 0;  // 供应链商品
        if ($goods['is_supply'] == 1 && $goods['supplier_goods_id'] != 0) {
            $isSupply = 1;
        }
        $goods_data = array(
            'goods_sn' => $goods['goods_sn'],
            'goods_name' => $goods['goods_name'],
            'brand_id' => $goods['brand_id'],//品牌id
            'suppliers_id' => $goods['suppliers_id'],//供应商id
            'cost_price' => $goods['cost_price'],//成本价
            'weight' => $goods['weight'],//重量 - 克
            'on_time' => $goods['on_time'],//上架时间戳
            'out_time' => $goods['out_time'],//下架时间戳
            'trade_type' => $trade_type,
            'is_supply' => $isSupply,
            'keywords' => $goods['keywords'],
            'goods_remark' => $goods['goods_remark'],
            'goods_content' => $goods['goods_content'],
            'video' => $goods['video'],
            'supplier_goods_id' => $goods['supplier_goods_id'],
            'is_abroad' => $goods['is_abroad'],
            'is_free_shipping' => $isSupply
        );
        if ($isSupply == 1) {
            // 供应链商品直接更新库存 缩略图
            $goods_data['store_count'] = $goods['store_count'];
            $goods_data['original_img'] = $goods['original_img'];
        }
        if ($goods['is_one_send'] == 0) {
            $goods_data['goods_type'] = $goods['goods_type'];//模型
        }

        //检查该商品为新商品
        $agoods = M('goods')->where(array('goods_sn' => $goods_data['goods_sn']))->field('goods_id')->find();

        $goods_data['is_area_show'] = $area3 == 1 ? 1 : 0; //是否可以显示在本区
        if ($agoods) {
            //存在于重销区
            if ($area3 == 0) {
                //不显示本区 直接下架
                $goods_data['is_on_sale'] = 0;
            }
            unset($goods_data['goods_name']);
            M('goods')->where(array('goods_id' => $agoods['goods_id']))->data($goods_data)->save();
            $goods_id = $agoods['goods_id'];
        } else {
            //不存在于重销区 可以显示在重销区 则新增
            if ($goods_data['is_area_show'] == 0) {  //之前没有、现在也不在本区  则不导入
                return true;
            }
            $goods_data['commission'] = tpCache('distribut.default_rate');//新增商品  默认分成
            $goods_data['is_on_sale'] = 0;//新增商品  默认下架
            $goods_data['sort'] = M('goods')->max('sort') + 1;  // 新增商品的排序
            $goods_id = M('goods')->data($goods_data)->add();
        }

        //规格
        $spec_goods_price_old = M('spec_goods_price')->where(array('goods_id' => $goods_id))->getField('key,key_name,item_sn');
        if (isset($goods['spec_goods_price'])) {
            //获取旧规格
            foreach ($goods['spec_goods_price'] as $key => $val) {
                if ($val['key'] != '') {
                    $save_data = array(
                        'goods_id' => $goods_id,
                        'item_sn' => $val['item_sn'],
                        'key' => $val['key'],
                        'key_name' => $val['key_name'],
                        'store_count' => $val['store_count'],
                        'spec_img' => $val['image'],
                        'price' => $val['m_price'],
                        'supplier_goods_spec' => $val['supplier_goods_spec'],
                    );
                    if (isset($spec_goods_price_old[$val['key']])) {
                        //更新
                        M('spec_goods_price')->where(array('goods_id' => $goods_id, 'key' => $val['key']))->data($save_data)->save();
                        unset($spec_goods_price_old[$val['key']]);
                    } else {
                        //新增
                        M('spec_goods_price')->data($save_data)->add();
                    }
                }
            }
        }
        //删除
        if ($spec_goods_price_old && $goods_data['trade_type'] == 1) {
            //一键待发 不要删除规格
            foreach ($spec_goods_price_old as $key => $val) {
                M('spec_goods_price')->where(array('goods_id' => $goods_id, 'key' => $val['key']))->delete();
            }
        }

        //删除旧套组
        M('goods_series')->where(array('goods_id' => $goods_id))->delete();
        if (!empty($goods['tao_arr'])) {
            foreach ($goods['tao_arr'] as $key => $val) {
                //获取商品id  规格id
                $goods_sn = $val['goods_sn'];
                $child_goods_id = M('goods')->where(array('goods_sn' => $goods_sn))->getField('goods_id');
                $item_sn = $val['item_sn'];
                $item_id = 0;
                if ($item_sn) {
                    $item_id = M('spec_goods_price')->where(array('goods_id' => $child_goods_id, 'item_sn' => $item_sn))->getField('item_id');
                }
                $save_data = array(
                    'g_id' => $child_goods_id,
                    'goods_id' => $goods_id,
                    'item_id' => $item_id,
                    'g_number' => $val['stock']
                );
                M('goods_series')->data($save_data)->add();
            }
        }

        //商品图片
        if (!empty($goods['goods_images'])) {
            M('goods_images')->where(['goods_id' => $goods_id])->delete();
            $goodsImagesData = [];
            foreach ($goods['goods_images'] as $image) {
                $goodsImagesData[] = [
                    'goods_id' => $goods_id,
                    'image_url' => $image['image_url']
                ];
            }
            (new GoodsImages())->saveAll($goodsImagesData);
        }

        //供应商商品规格标识
        if (!empty($goods['supplier_goods_spec'])) {
            $supplierId = 0;
            $goodsSpecData = [];
            foreach ($goods['supplier_goods_spec'] as $spec) {
                $supplierId = $spec['supplier_id'];
                $goodsSpecData[] = [
                    'spec_id' => $spec['spec_id'],
                    'name' => $spec['name'],
                    'supplier_id' => $spec['supplier_id'],
                ];
            }
            if (!empty($goodsSpecData)) {
                M('supplier_goods_spec')->where(['supplier_id' => $supplierId])->delete();
                (new SupplierGoodsSpec())->saveAll($goodsSpecData);
            }
        }

        return true;
    }

    function save_logistics($logistics)
    {
        $logistics_old = M('Shipping')->getField('shipping_code,shipping_name,shipping_id');
        foreach ($logistics as $k => $v) {
            if (isset($logistics_old[$v['code']])) {
                unset($logistics_old[$v['code']]);//
                M('Shipping')->where(array('shipping_code' => $v['code']))->data(array('shipping_name' => $v['name']))->save(); //更新
            } else {
                M('Shipping')->data(array('shipping_code' => $v['code'], 'shipping_name' => $v['name']))->add();//新增
            }
        }
        if ($logistics_old) {//如果还有则删除
            foreach ($logistics_old as $k => $v) {
                M('Shipping')->where(array('shipping_code' => $v['shipping_code']))->delete();//删除
            }
        }
        return true;
    }

}