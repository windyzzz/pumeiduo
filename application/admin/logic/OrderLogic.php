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

use app\common\logic\OrderLogic as OrderService;
use app\common\logic\WechatLogic;
use app\common\model\CouponList;
use app\common\model\UserTask;
use think\Db;

class OrderLogic
{
    /**
     * @param array $condition 搜索条件
     * @param string $order 排序方式
     * @param int $start limit开始行
     * @param int $page_size 获取数量
     */
    public function getOrderList($condition, $order = '', $start = 0, $page_size = 20)
    {
        $res = M('order')->where($condition)->limit("$start,$page_size")->order($order)->select();

        return $res;
    }

    /*
     * 根据商品型号获取商品
     */
    public function get_spec_goods($goods_id_arr)
    {
        if (!is_array($goods_id_arr)) {
            return false;
        }
        foreach ($goods_id_arr as $key => $val) {
            $arr = [];
            $goods = M('goods')->where("goods_id = $key")->find();
            $arr['goods_id'] = $key; // 商品id
            $arr['goods_name'] = $goods['goods_name'];
            $arr['goods_sn'] = $goods['goods_sn'];
            $arr['market_price'] = $goods['market_price'];
            $arr['goods_price'] = $goods['shop_price'];
            $arr['cost_price'] = $goods['cost_price'];
            $arr['member_goods_price'] = $goods['shop_price'];
            foreach ($val as $k => $v) {
                $arr['goods_num'] = $v['goods_num']; // 购买数量
                // 如果这商品有规格
                if ('key' != $k) {
                    $arr['spec_key'] = $k;
                    $spec_goods = M('spec_goods_price')->where("goods_id = $key and `key` = '{$k}'")->find();
                    $arr['spec_key_name'] = $spec_goods['key_name'];
                    $arr['member_goods_price'] = $arr['goods_price'] = $spec_goods['price'];
                    $arr['sku'] = $spec_goods['sku']; // 参考 sku  http://www.zhihu.com/question/19841574
                }
                $order_goods[] = $arr;
            }
        }

        return $order_goods;
    }

    /*
     * 订单操作记录
     */
    public function orderActionLog($order_id, $action, $note = '')
    {
        $order = M('order')->where(['order_id' => $order_id])->find();
        $data['order_id'] = $order_id;
        $data['action_user'] = session('admin_id');
        $data['action_note'] = $note;
        $data['order_status'] = $order['order_status'];
        $data['pay_status'] = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['log_time'] = time();
        $data['status_desc'] = $action;

        return M('order_action')->add($data); //订单操作记录
    }

    /*
     * 获取订单商品总价格
     */
    public function getGoodsAmount($order_id)
    {
        $sql = "SELECT SUM(goods_num * goods_price) AS goods_amount FROM __PREFIX__order_goods WHERE order_id = {$order_id}";
        $res = DB::query($sql);

        return $res[0]['goods_amount'];
    }

    /**
     * 得到发货单流水号.
     */
    public function get_delivery_sn()
    {
//        /* 选择一个随机的方案 */send_http_status('310');
        mt_srand((float)microtime() * 1000000);

        return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    /*
     * 获取当前可操作的按钮
     */
    public function getOrderButton($order)
    {
        /*
         *  操作按钮汇总 ：付款、设为未付款、确认、取消确认、无效、去发货、确认收货、申请退货
         *
         */
        $os = $order['order_status']; //订单状态
        $ss = $order['shipping_status']; //发货状态
        $ps = $order['pay_status']; //支付状态
        $pt = $order['prom_type']; //订单类型：0默认1抢购2团购3优惠4预售5虚拟6拼团
        $btn = [];
        if ('cod' == $order['pay_code']) {
            if (0 == $os && 0 == $ss) {
                if (6 != $pt) {
                    $btn['confirm'] = '确认';
                }
            } elseif (1 == $os && (0 == $ss || 2 == $ss)) {
                $btn['delivery'] = '去发货';
                if (6 != $pt) {
                    $btn['cancel'] = '取消确认';
                }
            } elseif (1 == $ss && 1 == $os && 0 == $ps) {
                $btn['pay'] = '付款';
            } elseif (1 == $ps && 1 == $ss && 1 == $os) {
                if (6 != $pt) {
                    $btn['pay_cancel'] = '设为未付款';
                }
            }
        } else {
            if (0 == $ps && 0 == $os || 2 == $ps) {
                $btn['pay'] = '付款';
            } elseif (0 == $os && 1 == $ps) {
                if (6 != $pt) {
                    $btn['pay_cancel'] = '设为未付款';
                    $btn['confirm'] = '确认';
                }
            } elseif (1 == $os && 1 == $ps && (0 == $ss || 2 == $ss)) {
                if (6 != $pt) {
                    $btn['cancel'] = '取消确认';
                }
                $btn['delivery'] = '去发货';
            }
        }

        if (1 == $ss && 1 == $os && 1 == $ps) {
//        	$btn['delivery_confirm'] = '确认收货';
            $btn['refund'] = '申请退货';
        } elseif (2 == $os || 4 == $os) {
            $btn['refund'] = '申请退货';
        } elseif (3 == $os || 5 == $os) {
            $btn['remove'] = '移除';
        }
        if (5 != $os && $ps == 0) {
            $btn['invalid'] = '无效';
        }

        return $btn;
    }

    public function orderProcessHandle($order_id, $act, $ext = [])
    {
        $updata = [];
        switch ($act) {
            case 'pay': //付款
                $order_sn = M('order')->where("order_id = $order_id")->getField('order_sn');
                update_pay_status($order_sn, $ext); // 调用确认收货按钮
                return true;
            case 'pay_cancel': //取消付款
                $updata['pay_status'] = 0;
                $this->order_pay_cancel($order_id);

                return true;
            case 'confirm': //确认订单
                $updata['order_status'] = 1;
                // 查看订单商品
                $goodsIds = Db::name('order_goods')->where(['order_id' => $order_id])->getField('goods_id', true);
                $goods = Db::name('goods')->where(['goods_id' => ['in', $goodsIds]])->getField('zone', true);
                foreach ($goods as $value) {
                    if ($value == 3) {
                        $isVip = 1;
                        break;
                    }
                }
                if (isset($isVip) && $isVip == 1) {
                    // 订单中含有VIP申请套餐
                    $order = Db::name('order')->where(['order_id' => $order_id])->field('order_sn, user_id')->find();
                    $userInviteUid = Db::name('users')->where(['user_id' => $order['user_id']])->value('invite_uid');
                    if ($userInviteUid != 0) {
                        // 拥有上级
                        // 任务内容
                        $taskReward = Db::name('task_reward')->where(['task_id' => 2])->order('reward_id asc')
                            ->getField('reward_id, invite_num, reward_coupon_id', true);
                        $userTask = Db::name('user_task')->where(['user_id' => $userInviteUid, 'task_id' => 2])->order('id asc')->select();
                        if (empty($userTask)) {
                            // 添加用户任务
                            $data = [];
                            foreach ($taskReward as $item) {
                                $data[] = [
                                    'user_id' => $userInviteUid,
                                    'task_id' => 2,
                                    'task_reward_id' => $item['reward_id'],
                                    'finish_num' => 1,
                                    'target_num' => $item['invite_num'],
                                    'status' => $item['invite_num'] == 1 ? 1 : 0,
                                    'invite_uid_list' => $order['user_id'],
                                    'order_sn_list' => $order['order_sn'],
                                    'created_at' => time(),
                                    'finished_at' => $item['invite_num'] == 1 ? time() : 0
                                ];
                            }
                            $userTask = new UserTask();
                            $res = $userTask->saveAll($data);
                            $taskReward = array_values($taskReward);
                            $rewardId = $taskReward[0]['reward_id'];
                            $userTaskId = $res[0]['id'];
                        } else {
                            $rewardId = '';
                            $userTaskId = '';
                            // 更新用户任务
                            foreach ($userTask as $key => $item) {
                                if ($item['status'] == 0) {
                                    $k = ($key - 1 >= 0) ? $key - 1 : 0;
                                    Db::name('user_task')->where(['id' => $item['id']])->update([
                                        'finish_num' => $item['target_num'],
                                        'status' => 1,
                                        'invite_uid_list' => $userTask[$k]['invite_uid_list'] . ',' . $order['user_id'],
                                        'order_sn_list' => $userTask[$k]['order_sn_list'] . ',' . $order['order_sn'],
                                        'finished_at' => time()
                                    ]);
                                    $rewardId = $item['task_reward_id'];
                                    $userTaskId = $item['id'];
                                    break;
                                }
                            }
                        }
                        // 上级奖励自动发放
                        if (!empty($rewardId) && !empty($userTaskId)) {
                            $taskReward = Db::name('task_reward')->where(['reward_id' => $rewardId])->find();
                            // 优惠券信息
                            $couponIds = explode('-', $taskReward['reward_coupon_id']);
                            $coupon = Db::name('coupon')->where(['id' => ['in', $couponIds]])->field('id, name, money')->select();
                            $couponName = '';
                            $couponMoney = '';
                            $couponData = [];
                            foreach ($coupon as $item) {
                                $couponName .= $item['name'] . '-';
                                $couponMoney .= $item['money'] . '-';
                                $couponData[] = [
                                    'cid' => $item['id'],
                                    'type' => 3,    // 邀请
                                    'uid' => $userInviteUid,
                                    'order_id' => 0,
                                    'get_order_id' => $order_id,
                                    'send_time' => time()
                                ];
                            }
                            // 优惠券记录
                            $couponList = new CouponList();
                            $couponList->saveAll($couponData);
                            // 任务记录
                            Db::name('task_log')->add([
                                'task_id' => 2,
                                'user_task_id' => $userTaskId,
                                'task_title' => Db::name('task')->where(['id' => 2])->value('title'),
                                'task_reward_id' => $taskReward['reward_id'],
                                'task_reward_desc' => $taskReward['description'],
                                'user_id' => $userInviteUid,
                                'order_sn' => $order['order_sn'],
                                'reward_electronic' => $taskReward['reward_price'],
                                'reward_integral' => $taskReward['reward_interval'],
                                'reward_coupon_id' => $taskReward['reward_coupon_id'],
                                'reward_coupon_money' => rtrim($couponMoney, '-'),
                                'reward_coupon_name' => rtrim($couponName, '-'),
                                'status' => 1,  // 自动领取
                                'type' => 1,
                                'created_at' => time(),
                                'finished_at' => time()
                            ]);
                        }
                    }
                }
                break;
            case 'cancel': //取消确认
                $updata['order_status'] = 0;
                break;
            case 'invalid': //作废订单
                $logic = new OrderService();
                $user_id = M('Order')->where('order_id', $order_id)->getField('user_id');
                $logic->cancel_order($user_id, $order_id, '管理员取消订单', true);
//                $updata['order_status'] = 3;
                break;
            case 'remove': //移除订单
                $this->delOrder($order_id);
                break;
            case 'delivery_confirm'://确认收货
                confirm_order($order_id); // 调用确认收货按钮
                return true;
            default:
                return true;
        }

        return M('order')->where("order_id=$order_id")->save($updata); //改变订单状态
    }

    //管理员取消付款
    public function order_pay_cancel($order_id)
    {
        //如果这笔订单已经取消付款过了
        $count = M('order')->where("order_id = $order_id and pay_status = 1")->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if (0 == $count) {
            return false;
        }
        // 找出对应的订单
        $order = M('order')->where("order_id = $order_id")->find();
        // 增加对应商品的库存
        $orderGoodsArr = M('OrderGoods')->where("order_id = $order_id")->select();
        foreach ($orderGoodsArr as $key => $val) {
            if (!empty($val['spec_key'])) {// 有选择规格的商品
                // 先到规格表里面增加数量 再重新刷新一个 这件商品的总数量
                $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
                $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
                $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
                refresh_stock($val['goods_id']);
            } else {
                $specGoodsPrice = null;
                M('Goods')->where("goods_id = {$val['goods_id']}")->setInc('store_count', $val['goods_num']); // 增加商品总数量
            }
            M('Goods')->where("goods_id = {$val['goods_id']}")->setDec('sales_sum', $val['goods_num']); // 减少商品销售量
            //更新活动商品购买量
            if (1 == $val['prom_type'] || 2 == $val['prom_type']) {
                $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
                $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
                $prom = $goodsPromLogic->getPromModel();
                if (0 == $prom['is_end']) {
                    $tb = 1 == $val['prom_type'] ? 'flash_sale' : 'group_buy';
                    M($tb)->where('id', $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                    M($tb)->where('id', $val['prom_id'])->setInc('order_num');
                }
            }
        }
        // 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        M('order')->where("order_id=$order_id")->save(['pay_status' => 0]);
        update_user_level($order['user_id']);
        // 记录订单操作日志
        logOrder($order['order_id'], '订单取消付款', '付款取消', $order['user_id']);
        //分销设置
        M('rebate_log')->where("order_id = {$order['order_id']}")->save(['status' => 0]);
    }

    /**
     *    处理发货单.
     *
     * @param array $data 查询数量
     *
     * @return array
     *
     * @throws \think\Exception
     */
    public function deliveryHandle($data)
    {
        $orderModel = new \app\common\model\Order();
        $orderObj = $orderModel::get(['order_id' => $data['order_id']]);
        $order = $orderObj->append(['full_address', 'orderGoods'])->toArray();
        $orderGoods = $order['orderGoods'];
        $selectGoods = $data['goods'];

        if (1 == $data['shipping_status']) {
            if (!$this->updateOrderShipping($data, $order, $orderGoods, $selectGoods)) {
                return ['status' => 0, 'msg' => '操作失败！！'];
            }
        } else {
            $update = [
                'send_type' => $data['send_type'],
                'delivery_type' => $data['delivery_type']
            ];
            switch ($data['send_type']) {
                case 0:
                    // 手动填物流信息
                    $is_delivery = 0;
                    switch ($data['delivery_type']) {
                        case 1:
                            // 统一发货
                            $deliverData = [
                                'order_id' => $order['order_id'],
                                'order_sn' => $order['order_sn'],
                                'user_id' => $order['user_id'],
                                'admin_id' => session('admin_id'),
                                'consignee' => $order['consignee'],
                                'zipcode' => $order['zipcode'],
                                'mobile' => $order['mobile'],
                                'country' => $order['country'],
                                'province' => $order['province'],
                                'city' => $order['city'],
                                'district' => $order['district'],
                                'address' => $order['address'],
                                'shipping_code' => $data['shipping_code'],
                                'shipping_name' => $data['shipping_name'],
                                'shipping_price' => $order['shipping_price'],
                                'invoice_no' => $data['invoice_no'],
                                'note' => $data['note'],
                                'create_time' => time(),
                                'send_type' => $data['send_type'],
                            ];
                            // 记录物流信息
                            $docId = M('delivery_doc')->add($deliverData);
                            // 更新订单商品记录
                            foreach ($orderGoods as $k => $v) {
                                if ($v['is_send'] >= 1) {
                                    ++$is_delivery;
                                }
                                if (0 == $v['is_send'] && in_array($v['rec_id'], $selectGoods)) {
                                    $res['is_send'] = 1;
                                    $res['delivery_id'] = $docId;
                                    M('order_goods')->where('rec_id=' . $v['rec_id'])->save($res);
                                    ++$is_delivery;
                                }
                            }
                            break;
                        case 2:
                            // 分开发货
                            $data['note'] = '';
                            foreach ($data['order_goods'] as $goods) {
                                $deliverData = [
                                    'order_id' => $order['order_id'],
                                    'order_sn' => $order['order_sn'],
                                    'rec_id' => $goods['rec_id'],
                                    'user_id' => $order['user_id'],
                                    'admin_id' => session('admin_id'),
                                    'consignee' => $order['consignee'],
                                    'zipcode' => $order['zipcode'],
                                    'mobile' => $order['mobile'],
                                    'country' => $order['country'],
                                    'province' => $order['province'],
                                    'city' => $order['city'],
                                    'district' => $order['district'],
                                    'address' => $order['address'],
                                    'shipping_code' => $data['shipping_code'],
                                    'shipping_name' => $data['shipping_name'],
                                    'shipping_price' => $order['shipping_price'],
                                    'invoice_no' => $goods['invoice_no'],
                                    'note' => $goods['note'],
                                    'create_time' => time(),
                                    'send_type' => $data['send_type'],
                                ];
                                // 记录物流信息
                                $docId = M('delivery_doc')->add($deliverData);
                                // 更新订单商品记录
                                foreach ($orderGoods as $k => $v) {
                                    if ($v['is_send'] >= 1) {
                                        ++$is_delivery;
                                    }
                                    if (0 == $v['is_send'] && $v['rec_id'] == $goods['rec_id']) {
                                        $res['is_send'] = 1;
                                        $res['delivery_id'] = $docId;
                                        M('order_goods')->where('rec_id=' . $v['rec_id'])->save($res);
                                        ++$is_delivery;
                                    }
                                }
                                $data['note'] .= $goods['note'] . '，';
                            }
                            $data['note'] = rtrim($data['note'], ',');
                            break;
                        default:
                            return ['status' => 0, 'msg' => '操作失败！！'];
                    }
                    $update['shipping_code'] = $data['shipping_code'];
                    $update['shipping_name'] = $data['shipping_name'];
                    $update['shipping_time'] = time();
                    if ($is_delivery === 0) {
                        $update['shipping_status'] = 1;
                    } elseif ($is_delivery == count($orderGoods)) {
                        $update['shipping_status'] = 1;
                    } else {
                        $update['shipping_status'] = 2;
                    }
                    break;
                case 3:
                    // 无需物流
                    $update['shipping_code'] = 'NO_NEED';
                    $update['shipping_name'] = '无需物流';
                    $update['shipping_time'] = time();
                    $update['shipping_status'] = 3;
                    break;
                default:
                    return ['status' => 0, 'msg' => '操作失败！！'];
            }
            // 更新订单状态
            M('order')->where('order_id=' . $data['order_id'])->save($update);
        }
        // 操作日志
        $s = $this->orderActionLog($order['order_id'], 'delivery', $data['note']);

        // //商家发货, 发送短信给客户
        // $res = checkEnableSendSms("5");
        // if ($res && $res['status'] ==1) {
        //     $user_id = $data['user_id'];
        //     $users = M('users')->where('user_id', $user_id)->getField('user_id , nickname , mobile' , true);
        //     if($users){
        //         $nickname = $users[$user_id]['nickname'];
        //         $sender = $users[$user_id]['mobile'];
        //         $params = array('user_name'=>$nickname , 'consignee'=>$data['consignee']);
        //         $resp = sendSms("5", $sender, $params,'');
        //     }
        // }

        //       // 发送微信模板消息通知
        //       $wechat = new WechatLogic;
        //       $wechat->sendTemplateMsgOnDeliver($data);

        if ($s) {
            return ['status' => 1, 'msg' => '发货成功'];
        }
        return ['status' => 0, 'msg' => '发货失败'];
    }

    /**
     * 修改订单发货信息
     * @param array $data
     * @param array $order
     * @param array $orderGoods
     * @param array $selectGoods
     * @return bool
     */
    public function updateOrderShipping($data = [], $order = [], $orderGoods = [], $selectGoods = [])
    {
//        echo '<pre>';
//        print_r($data);
//        echo '</pre>';
//        exit();
        // 删除之前的记录
        M('delivery_doc')->where(['order_id' => $data['order_id']])->delete();
        $update = [
            'send_type' => $data['send_type'],
            'delivery_type' => $data['delivery_type']
        ];
        switch ($data['send_type']) {
            case 0:
                // 手动填物流信息
                $is_delivery = 0;
                switch ($data['delivery_type']) {
                    case 1:
                        // 统一发货
                        $deliverData = [
                            'order_id' => $order['order_id'],
                            'order_sn' => $order['order_sn'],
                            'user_id' => $order['user_id'],
                            'admin_id' => session('admin_id'),
                            'consignee' => $order['consignee'],
                            'zipcode' => $order['zipcode'],
                            'mobile' => $order['mobile'],
                            'country' => $order['country'],
                            'province' => $order['province'],
                            'city' => $order['city'],
                            'district' => $order['district'],
                            'address' => $order['address'],
                            'shipping_code' => $data['shipping_code'],
                            'shipping_name' => $data['shipping_name'],
                            'shipping_price' => $order['shipping_price'],
                            'invoice_no' => $data['invoice_no'],
                            'note' => $data['note'],
                            'create_time' => time(),
                            'send_type' => $data['send_type'],
                        ];
                        // 记录物流信息
                        $docId = M('delivery_doc')->add($deliverData);
                        // 更新订单商品记录
                        foreach ($orderGoods as $k => $v) {
                            if ($v['is_send'] >= 1) {
                                ++$is_delivery;
                            }
                            if (0 == $v['is_send'] && in_array($v['rec_id'], $selectGoods)) {
                                $res['is_send'] = 1;
                                $res['delivery_id'] = $docId;
                                M('order_goods')->where('rec_id=' . $v['rec_id'])->save($res);
                                ++$is_delivery;
                            }
                        }
                        break;
                    case 2:
                        // 分开发货
                        $data['note'] = '';
                        foreach ($data['order_goods'] as $goods) {
                            $deliverData = [
                                'order_id' => $order['order_id'],
                                'order_sn' => $order['order_sn'],
                                'rec_id' => $goods['rec_id'],
                                'user_id' => $order['user_id'],
                                'admin_id' => session('admin_id'),
                                'consignee' => $order['consignee'],
                                'zipcode' => $order['zipcode'],
                                'mobile' => $order['mobile'],
                                'country' => $order['country'],
                                'province' => $order['province'],
                                'city' => $order['city'],
                                'district' => $order['district'],
                                'address' => $order['address'],
                                'shipping_code' => $data['shipping_code'],
                                'shipping_name' => $data['shipping_name'],
                                'shipping_price' => $order['shipping_price'],
                                'invoice_no' => $goods['invoice_no'],
                                'note' => $goods['note'],
                                'create_time' => time(),
                                'send_type' => $data['send_type'],
                            ];
                            // 记录物流信息
                            $docId = M('delivery_doc')->add($deliverData);
                            // 更新订单商品记录
                            foreach ($orderGoods as $k => $v) {
                                if ($v['is_send'] >= 1) {
                                    ++$is_delivery;
                                }
                                if (0 == $v['is_send'] && $v['rec_id'] == $goods['rec_id']) {
                                    $res['is_send'] = 1;
                                    $res['delivery_id'] = $docId;
                                    M('order_goods')->where('rec_id=' . $v['rec_id'])->save($res);
                                    ++$is_delivery;
                                }
                            }
                            $data['note'] .= $goods['note'] . '，';
                        }
                        $data['note'] = rtrim($data['note'], ',');
                        break;
                    default:
                        return false;
                }
                $update['shipping_code'] = $data['shipping_code'];
                $update['shipping_name'] = $data['shipping_name'];
                $update['shipping_time'] = time();
                if ($is_delivery === 0) {
                    $update['shipping_status'] = 1;
                } elseif ($is_delivery == count($orderGoods)) {
                    $update['shipping_status'] = 1;
                } else {
                    $update['shipping_status'] = 2;
                }
                break;
            case 3:
                // 无需物流
                $update['shipping_code'] = 'NO_NEED';
                $update['shipping_name'] = '无需物流';
                $update['shipping_time'] = time();
                $update['shipping_status'] = 3;
                break;
            default:
                return false;
        }
        // 更新物流信息
        M('order')->where(['order_id' => $data['order_id']])->save($update);
        // 操作日志
        return $this->orderActionLog($order['order_id'], '订单修改发货信息', $data['note']);
    }

    /**
     * 获取地区名字.
     *
     * @param int $p
     * @param int $c
     * @param int $d
     *
     * @return string
     */
    public function getAddressName($p = 0, $c = 0, $d = 0)
    {
        $p = M('region2')->where(['id' => $p])->field('name')->find();
        $c = M('region2')->where(['id' => $c])->field('name')->find();
        $d = M('region2')->where(['id' => $d])->field('name')->find();

        return $p['name'] . ',' . $c['name'] . ',' . $d['name'] . ',';
    }

    /**
     * 删除订单.
     */
    public function delOrder($order_id)
    {
        $order = M('order')->where(['order_id' => $order_id])->find();
        if (empty($order)) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }
        $del_order = M('order')->where(['order_id' => $order_id])->delete();
        $del_order_goods = M('order_goods')->where(['order_id' => $order_id])->delete();
        if (empty($del_order) && empty($del_order_goods)) {
            return ['status' => -1, 'msg' => '订单删除失败'];
        }

        return ['status' => 1, 'msg' => '删除成功'];
    }

    /**
     * 当订单里商品都退货完成，将订单状态改成关闭.
     *
     * @param $order_id
     */
    public function closeOrderByReturn($order_id)
    {
        $order_goods_list = Db::name('order_goods')->where(['order_id' => $order_id])->select();
        $order_goods_count = count($order_goods_list);
        $order_goods_return_count = 0; //退货个数
        for ($i = 0; $i < $order_goods_count; ++$i) {
            if (3 == $order_goods_list[$i]['is_send']) {
                ++$order_goods_return_count;
            }
        }
        if ($order_goods_count == $order_goods_return_count) {
            $res = Db::name('order')->where(['order_id' => $order_id])->update(['order_status' => 5]);
            if (!$res) {
                return false;
            }
        }

        return true;
    }

    /**
     * 退货，取消订单，处理优惠券.
     *
     * @param $return_info
     */
    public function disposereRurnOrderCoupon($return_info)
    {
        $coupon_list = M('coupon_list')->where(['uid' => $return_info['user_id'], 'order_id' => $return_info['order_id']])->find();    //有没有关于这个商品的优惠券
        if (!empty($coupon_list)) {
            $update_coupon_data = ['status' => 0, 'use_time' => 0, 'order_id' => 0];
            M('coupon_list')->where(['id' => $coupon_list['id'], 'status' => 1])->save($update_coupon_data); //符合条件的，优惠券就退给他
        }
        //追回赠送优惠券,一般退款才会走这里
        $coupon_info = M('coupon_list')->where(['uid' => $return_info['user_id'], 'get_order_id' => $return_info['order_id']])->find();
        if (!empty($coupon_info)) {
            if (1 == $coupon_info['status']) { //如果优惠券被使用,那么从退款里扣
                $coupon = M('coupon')->where(['id' => $coupon_info['cid']])->find();
                if ($return_info['refund_money'] > $coupon['money']) {
                    //退款金额大于优惠券金额，先从这里扣
                    $return_info['refund_money'] = $return_info['refund_money'] - $coupon['money'];
                    M('return_goods')->where(['id' => $return_info['id']])->save(['refund_money' => $return_info['refund_money']]);
                } else {
                    $return_info['refund_deposit'] = $return_info['refund_deposit'] - $coupon['money'];
                    M('return_goods')->where(['id' => $return_info['id']])->save(['refund_deposit' => $return_info['refund_deposit']]);
                }
            } else {
                M('coupon_list')->where(['id' => $coupon_info['id']])->delete();
                M('coupon')->where(['id' => $coupon_info['cid']])->setDec('send_num');
            }
        }
    }

    public function getRefundGoodsMoney($return_goods)
    {
        $order_goods = M('order_goods')->where(['rec_id' => $return_goods['rec_id']])->find();
        if (1 == $return_goods['is_receive']) {
            if ($order_goods['give_integral'] > 0) {
                $user = get_user_info($return_goods['user_id']);
                if ($order_goods['give_integral'] > $user['pay_points']) {
                    //积分被使用则从退款金额里扣
                    $return_goods['refund_money'] = $return_goods['refund_money'] - $order_goods['give_integral'] / 100;
                }
            }
            $coupon_info = M('coupon_list')->where(['uid' => $return_goods['user_id'], 'get_order_id' => $return_goods['order_id']])->find();
            if (!empty($coupon_info)) {
                if (1 == $coupon_info['status']) { //如果优惠券被使用,那么从退款里扣
                    $coupon = M('coupon')->where(['id' => $coupon_info['cid']])->find();
                    if ($return_goods['refund_money'] > $coupon['money']) {
                        $return_goods['refund_money'] = $return_goods['refund_money'] - $coupon['money']; //退款金额大于优惠券金额
                    }
                }
            }
        }

        return $return_goods['refund_money'];
    }

    //订单发货在线下单、电子面单
    public function submitOrderExpress($data, $orderGoods)
    {
        return ['status' => 0, 'msg' => '该功能暂未开放'];
    }

    //识别单号
    public function distinguishExpress()
    {
        require_once PLUGIN_PATH . 'kdniao/kdniao.php';
        $kdniao = new \kdniao();
        $data['LogisticCode'] = I('invoice_no');
        $res = $kdniao->getOrderTracesByJson(json_encode($data));
    }
}
