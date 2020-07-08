<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

use app\common\logic\supplier\OrderService;
use app\common\logic\Token as TokenLogic;
use app\common\logic\wechat\WechatUtil;
use app\common\model\SpecGoodsPrice;
use think\Db;

/**
 * Class orderLogic.
 */
class OrderLogic
{
    protected $user_id = 0;

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * 取消订单.
     *
     * @param $user_id |用户ID
     * @param $order_id |订单ID
     * @param string $action_note 操作备注
     * @param boolean $is_admin 是否后台操作
     *
     * @return array
     */
    public function cancel_order($user_id, $order_id, $action_note = '您取消了订单', $is_admin = false)
    {
        $order = M('order')->where(['order_id' => $order_id, 'user_id' => $user_id])->find();
        //检查是否未支付订单 已支付联系客服处理退款
        if (empty($order)) {
            return ['status' => 0, 'msg' => '订单不存在', 'result' => ''];
        }
        if (3 == $order['order_status']) {
            return ['status' => 0, 'msg' => '该订单已取消', 'result' => ''];
        }

        if (1 == $order['shipping_status']) {
            return ['status' => 0, 'msg' => '该订单已发货，不能取消', 'result' => ''];
        }

        if ($order['order_status'] == 1 && $is_admin == false) {
            return ['status' => 0, 'msg' => '该订单已确认，不能取消订单', 'result' => ''];
        }

        if ($order['pay_status'] == 1 && $is_admin == false) {//已支付 检查是否是vip升级单
            $has_vip_order = M('order')
                ->alias('o')
                ->field('o.order_id')
                ->join('order_goods og', 'o.order_id = og.order_id')
                ->join('goods g', 'g.goods_id = og.goods_id and g.zone=3')
                ->where(array('o.order_id' => $order_id))
                ->find();
            if ($has_vip_order) {
                return ['status' => 0, 'msg' => 'VIP升级套装，不能取消订单', 'result' => ''];
            }
        }

        //检查是否未支付的订单
        if (($order['pay_status'] > 0 || $order['order_status'] > 0) && $order['order_amount'] > 0) {
//            if ($_SERVER['SERVER_ADDR'] != '61.238.101.138') {
//                return array('status' => 0, 'msg' => '支付状态或订单状态不允许', 'result' => '');
//            }
            //获取记录表信息
            //$log = M('account_log')->where(array('order_id'=>$order_id))->find();
            $res = false;
            if ('weixinJsApi' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/weixinJsApi/weixinJsApi.class.php';
                $payment_obj = new \weixinJsApi();
                $result = $payment_obj->refund1($order, $order['order_amount']);
                $msg = $result['return_msg'];
                if ('SUCCESS' == $result['return_code'] && 'SUCCESS' == $result['result_code']) {
                    $res = true;
                }
            } elseif ('weixin' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/weixin/weixin.class.php';
                $payment_obj = new \weixin();
                $result = $payment_obj->refund1($order, $order['order_amount']);
                $msg = $result['return_msg'];
                if ('SUCCESS' == $result['return_code'] && 'SUCCESS' == $result['result_code']) {
                    $res = true;
                }
            } elseif ('alipayMobile' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/alipayMobile/alipayMobile.class.php';
                $payment_obj = new \alipayMobile();
                $result = $payment_obj->refund1($order, $order['order_amount'], $action_note);
                $msg = $result->sub_msg;
                if ('10000' == $result->code) {
                    $res = true;
                }
            } elseif ('alipay' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/alipay/alipay.class.php';
                $payment_obj = new \alipay();
                $result = $payment_obj->refund1($order, $order['order_amount'], $action_note);
                $msg = $result->sub_msg;
                if ('10000' == $result->code) {
                    $res = true;
                }
            } elseif ('alipayApp' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/alipayApp/alipayApp.class.php';
                $payment_obj = new \alipayApp();
                $result = $payment_obj->refund($order, $order['order_amount'], $action_note);
                $msg = $result->sub_msg;
                if ('10000' == $result->code) {
                    $res = true;
                }
            } elseif ('weixinApp' == $order['pay_code']) {
                include_once PLUGIN_PATH . 'payment/weixinApp/weixinApp.class.php';
                $payment_obj = new \weixinApp();
                $result = $payment_obj->refund($order, $order['order_amount']);
                if ($result['return_code'] == 'FAIL') {
                    $msg = $result['return_msg'];
                } elseif ($result['result_code'] == 'FAIL') {
                    $msg = $result['err_code_des'];
                } else {
                    $res = true;
                }
            } elseif ('' == $order['pay_code']) {
                $res = true;
            } else {
                return ['status' => 0, 'msg' => '暂不支付退款方式', 'result' => ''];
            }
            if (!$res) {
                $msg = isset($msg) ? $msg : '支付平台退款错误';
                return ['status' => 0, 'msg' => '退款失败,' . $msg, 'result' => '错误原因:' . $msg];
            }

            // 如果有微信公众号 则推送一条消息到微信
            $user = Db::name('OauthUsers')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
            if ($user) {
                $wx_content = "您刚刚取消了一笔订单。\n订单编号:{$order['order_sn']}";
                $wechat = new WechatUtil();
                $wechat->sendMsg($user['openid'], 'text', $wx_content);
            }
        }

        Db::startTrans();
        if ($order['pay_status'] > 0) {

            M('order')->where(['order_id' => $order['order_id']])->save(['pay_status' => 3, 'cancel_time' => time()]); //更改订单状态

            // 追回等级
            $update_info = M('distribut_log')->where('order_sn', $order['order_sn'])->where('type', 1)->find();
            if ($update_info) {
                $is_distribut = 0;
                $level = $update_info['old_level'];
                if ($level > 1) {
                    $is_distribut = 1;
                }
                M('users')->where('user_id', $user_id)->update([
                    'distribut_level' => $level,
                    'is_distribut' => $is_distribut,
                ]);

                logDistribut($order['order_sn'], $user_id, $level, $update_info['new_level'], 2);

                // 分销追回 (下级)
                if (1 == $level) {
                    M('rebate_log')->where('user_id', $user_id)->update(['money' => 0, 'point' => 0, 'confirm_time' => time(), 'remark' => '取消订单，追回佣金']);
                } elseif (2 == $level) {
                    M('rebate_log')->where('user_id', $user_id)->where('type', 1)->update(['money' => 0, 'point' => 0, 'confirm_time' => time(), 'remark' => '取消订单，追回佣金']);
                }

                // 推荐人奖励追回
                $firstLeaderAccount = M('account_log')->where([
                    'order_id' => $order_id,
                    'type' => 14
                ])->select();
                if (!empty($firstLeaderAccount)) {
                    foreach ($firstLeaderAccount as $account) {
                        if ($account['user_money'] > 0) {
                            accountLog($account['user_id'], -$account['user_money'], 0, '推广318套组奖励金额追回', 0, $order_id, '', 0, 14, false);
                        }
                        if ($account['pay_points']) {
                            accountLog($account['user_id'], 0, -$account['pay_points'], '推广318套组奖励积分追回', 0, $order_id, '', 0, 14, false);
                        }
                    }
                }
            }
        }

        // $OrderGoods = M('OrderGoods')->field('goods_id')->where('order_id',$order_id)->select();
        // foreach ($OrderGoods as $k => $v) {
        //     if(M('Goods')->where(['goods_id'=>$v['goods_id'], 'zone'=> 3, 'distribut_id' => ['gt',0]])->find())
        //     {
        //         // 1. 先查找该用户升级过的记录
        //         $level = 1;
        //         $is_distribut = 0;
        //         $levelRecord = M('Order')->alias('oi')
        //         ->join('__ORDER_GOODS__ og','oi.order_id = og.order_id','LEFT')
        //         ->join('__GOODS__ g','g.goods_id = og.goods_id','LEFT')
        //         ->where([
        //             'oi.user_id' => $user_id,
        //             'oi.order_id' => ['neq',$order_id],
        //             'oi.order_status' => ['not in',[3,5]],
        //             'oi.pay_status' => 1,
        //             'g.zone' => 3,
        //             'g.distribut_id' => ['gt',0],
        //         ])
        //         ->getField('g.distribut_id');
        //         // ->select();
        //         // dump($levelRecord);
        //         // exit;
        //         if($levelRecord > 0){
        //             $level = $levelRecord;
        //         }
        //         if($level > 1)
        //         {
        //             $is_distribut = 1;
        //         }
        //         M('users')->where('user_id',$user_id)->update([
        //             'distribut_level' => $level,
        //             'is_distribut' => $is_distribut
        //         ]);
        //     }
        // }

        //有余额支付的情况
        if ($order['user_money'] > 0 || $order['integral'] > 0 || $order['user_electronic'] > 0) {
            accountLog($user_id, $order['user_money'], $order['integral'], '订单取消回退', 0, $order['order_id'], $order['order_sn'], $order['user_electronic'], 10);
        }

        // 双十一活动奖励追回
        $taskLogic = new TaskLogic();
        $taskLogic->setOrder($order);
        $taskLogic->returnReward('用户取消订单');

        // 返还使用登录奖励
        $taskLogic = new TaskLogic(4);
        $taskLogic->setOrder($order);
        $taskLogic->returnLoginProfit();

        // 赠品活动奖励追回
        $giftLogic = new \app\common\logic\order\GiftLogic();
        $giftLogic->setOrder($order);
        $giftLogic->returnReward();

        // 加价购
        $task = new \app\common\logic\order\ExtraLogic();
        $task->setOrder($order);
        $task->returnReward();

        // 分销追回 (上级)
        M('rebate_log')->where('order_sn', $order['order_sn'])->update(['status' => 4, 'confirm_time' => time(), 'remark' => '追回佣金']);

        // 退还券
        if ($order['coupon_id'] > 0) {
            $res = ['use_time' => 0, 'status' => 0, 'order_id' => 0];
            M('coupon_list')->where(['order_id' => $order_id, 'uid' => $user_id])->save($res);
            M('coupon')->where(['id' => $order['coupon_id']])->setDec('use_num', 1);
        }

        $row = M('order')->where(['order_id' => $order_id, 'user_id' => $user_id])->save(['order_status' => 3, 'cancel_time' => time()]);
        $reduce = tpCache('shopping.reduce');
        if (1 == $reduce || empty($reduce)) {
            $this->alterReturnGoodsInventory($order);
        }

        logOrder($order_id, $action_note, '取消订单');
        if (!$row) {
            Db::rollback();
            return ['status' => 0, 'msg' => '操作失败', 'result' => ''];
        }
        // 更新缓存
        $user = Db::name('users')->where('user_id', $user_id)->find();
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);

        // 供应链订单取消
        if ($order['order_type'] == 3 && !empty($order['supplier_order_sn'])) {
            $cOrderSn = M('order')->where(['parent_id' => $order['order_id'], 'order_type' => 3])->value('order_sn');
            $res = (new OrderService())->cancelOrder($cOrderSn);
            if ($res['status'] == 0) {
                Db::rollback();
                return $res;
            }
        }

        Db::commit();
        return ['status' => 1, 'msg' => '操作成功', 'result' => ''];
    }

    public function getDecMoney($order_id, $level)
    {
        $money = [];

        $order_goods = M('OrderGoods')
            ->alias('a')
            ->field('a.*,o.add_time')
            ->join('__ORDER__ o', 'o.order_id = a.order_id', 'LEFT')
            ->where('a.order_id', $order_id)
            ->select();

        foreach ($order_goods as $ok => $ov) {
            $money[$ov['rec_id']]['money'] = $this->getRongMoney(bcdiv(bcmul(bcmul($ov['final_price'], $ov['goods_num'], 2), $ov['commission'], 2), 100, 2), $level, $ov['add_time'], $ov['goods_id']);
            $money[$ov['rec_id']]['point'] = $this->getRongPoint($ov['goods_id'], $level);
        }

        return $money;
    }

    function getRongMoney($money, $level, $order_time, $goods_id = 0, $is_zone = false)
    {
        if ($order_time < bonus_time()) {
            $distribut_rate = 25 / 100;
            return round($money * $distribut_rate, 2);
        } else {
            $zone = 0;
            if ($goods_id) {
                $zone = M('goods')->where(array('goods_id' => $goods_id))->getField('zone');
            }
            if ($level == 1 && ($zone == 3 || $is_zone == true)) {
                return tpCache('distribut.referee_vip_money');
            } else {
                if (1 == $level) {
                    $distribut_rate = tpCache('distribut.first_rate') / 100;
                } elseif (2 == $level) {
                    $distribut_rate = tpCache('distribut.second_rate') / 100;
                } elseif (3 == $level) {
                    $distribut_rate = tpCache('distribut.third_rate') / 100;
                } else {
                    $distribut_rate = tpCache('distribut.shop_rate') / 100;
                }
            }
            return round($money * $distribut_rate, 2);
        }
    }

    function getRongPoint($goods_id, $level)
    {
        if ($level == 1) {
            $zone = M('goods')->where(array('goods_id' => $goods_id))->getField('zone');
            if ($zone == 3) {
                return tpCache('distribut.referee_vip_point');
            }
        }
        return 0;
    }

    public function addReturnGoods($rec_id, $order, $cOrder = [])
    {
        $data = I('post.');
        $confirm_time_config = tpCache('shopping.auto_service_date'); //后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            return ['result' => -1, 'msg' => '已经超过' . ($confirm_time_config ?: 0) . '天内退货时间'];
        }

        $img = $this->uploadReturnGoodsImg();
        if (1 !== $img['status']) {
            return $img;
        }
        $data['imgs'] = $img['result'] ?: ($data['imgs'] ?: ''); //兼容小程序，多传imgs
        if ($data['imgs']) {
            $new_images = [];

            $images = explode(' , ', $data['imgs']);

            foreach ($images as $k => $v) {
                //  $base_img是获取到前端传递的src里面的值，也就是我们的数据流文件
                $ext = '.png';
                $base_img = $v;

                if (preg_match('/data:image\/jpg;base64,/', $v)) {
                    $base_img = str_replace('data:image/jpg;base64,', '', $v);
                    $ext = '.jpg';
                }
                if (preg_match('/data:image\/jpeg;base64,/', $v)) {
                    $base_img = str_replace('data:image/jpeg;base64,', '', $v);
                    $ext = '.jpeg';
                }
                if (preg_match('/data:image\/png;base64,/', $v)) {
                    $base_img = str_replace('data:image/png;base64,', '', $v);
                    $ext = '.png';
                }

                $img = base64_decode($base_img);

                $save_path = 'return';
                $savePath = PUBLIC_PATH . 'upload/user/' . cookie('user_id') . '/' . $save_path . '/';
                $savePath1 = 'public/upload/user/' . cookie('user_id') . '/' . $save_path . '/';
                $time = time() . rand(0, 99999);
                $fullName = $savePath . $time . $ext;
                $fullName1 = $savePath1 . $time . $ext;

                if (!file_exists($savePath) && !mkdir($savePath, 0777, true)) {
                    $data = [
                        'msg' => '目录创建失败',
                        'status' => -1,
                    ];
                    echo json_encode($data);
                    exit;
                } elseif (!is_writeable($savePath)) {
                    $data = [
                        'msg' => '目录没有写权限',
                        'status' => -1,
                    ];
                    echo json_encode($data);
                    exit;
                }

                if (!(file_put_contents($fullName, $img) && file_exists($fullName))) { //移动失败
                    $data = [
                        'msg' => '写入文件内容错误',
                        'status' => -1,
                        'return' => $img,
                    ];
                    echo json_encode($data);
                    exit;
                } //移动成功

                $new_images[] = $fullName1;
            }
        }

        if ($new_images) {
            $data['imgs'] = implode(',', $new_images);
        }

        $data['addtime'] = time();
        $data['user_id'] = $order['user_id'];
        $data['order_id'] = $order['order_id'];
        $data['order_sn'] = $order['order_sn'];

        $order_goods = M('order_goods')->where(['rec_id' => $rec_id])->find();
        $data['goods_id'] = $order_goods['goods_id'];
        $data['spec_key'] = $order_goods['spec_key'];
        if ($data['type'] < 2) {
            $useRapplyReturnMoney = $order_goods['final_price'] * $data['goods_num'];    //要退的总价 商品购买单价*申请数量
            $userExpenditureMoney = $order['goods_price'] - $order['order_prom_amount'] - $order['coupon_price'];    //用户实际使用金额
//            $rate = round($useRapplyReturnMoney / $userExpenditureMoney, 2);
//            $shipping_rate = $order['shipping_price'] / $order['total_amount'];
            $user_electronic = round($order['user_electronic'] - $order['user_electronic'] * $order['shipping_price'] / $order['total_amount'], 2);

            // $data['refund_integral'] = floor($useRapplyReturnMoney / $userExpenditureMoney*$order['integral']);//该退积分支付
            $data['refund_integral'] = $order_goods['use_integral'] * $order_goods['goods_num']; //该退积分支付
            $data['refund_electronic'] = $useRapplyReturnMoney / $userExpenditureMoney * $user_electronic; //该退电子币
            // $integralDeductionMoney = $data['refund_integral']/tpCache('shopping.point_rate') ;  //积分抵了多少钱，要扣掉
            $integralDeductionMoney = $data['refund_integral'];  //积分抵了多少钱，要扣掉
            if ($order['order_amount'] > 0) {
                $order_amount = $order['order_amount'] + $order['paid_money'];   //三方支付总额，预售要退定金
                if ($order_amount > $order['shipping_price']) {
                    $data['refund_money'] = round($useRapplyReturnMoney / $userExpenditureMoney * ($order_amount - $order['shipping_price']), 2); //退款金额
                    $data['refund_deposit'] = round($useRapplyReturnMoney / $userExpenditureMoney * $order['user_money'], 2);
                } else {
                    $data['refund_deposit'] = round($useRapplyReturnMoney / $userExpenditureMoney * ($order['user_money'] - $order['shipping_price'] + $order['paid_money']) - $integralDeductionMoney, 2); //该退余额支付部分
                }
            } else {
                $data['refund_deposit'] = round($useRapplyReturnMoney - $integralDeductionMoney, 2); //该退余额支付部分
            }
        }

        // 更新分成记录
        M('rebate_log')->where('order_sn', $data['order_sn'])->update(['status' => 6]);
        if ($order_goods['goods_pv'] == 0) {
            // 冻结分成
            $rebate_list = M('rebate_log')->where('order_sn', $data['order_sn'])->select();
            if ($rebate_list) {
                foreach ($rebate_list as $rk => $rv) {
                    $money = $this->getDecMoney($rv['order_id'], $rv['level']);
                    $dec_money = $money[$rec_id]['money'];
                    $dec_point = $money[$rec_id]['point'];
                    M('rebate_log')->where('id', $rv['id'])->update([
                        'money' => ['exp', "money - {$dec_money}"],
                        'point' => ['exp', "point - {$dec_point}"],
                        'freeze_money' => ['exp', "freeze_money + {$dec_money}"],
                    ]);
                }
            }
        }

        // $dec_money = $data['refund_money'] * $goods_commission / 100;
        if (isset($cOrder) && $cOrder['order_type'] == 3) {
            $data['is_supply'] = 1;
        }
        if (!empty($data['id'])) {
            $result = M('return_goods')->where(['id' => $data['id']])->save($data);
        } else {
            $result = M('return_goods')->add($data);
        }

        if ($result) {
            M('order')->where('order_sn', $order['order_sn'])->update(['order_status' => 6]);
            return ['status' => 1, 'msg' => '申请成功'];
        }

        return ['status' => -1, 'msg' => '申请失败'];
    }

    /**
     * 添加售后申请（新）
     * @param $recId
     * @param $type
     * @param $order
     * @param $orderGoods
     * @param $data
     * @param array $cOrder 子订单信息
     * @return array
     */
    public function addReturnGoodsNew($recId, $type, $order, $orderGoods, $data, $cOrder = [])
    {
        $returnData['rec_id'] = $recId;
        $returnData['type'] = $type;
        $returnData['is_receive'] = $data['is_receive'] ?? 1;
        $returnData['reason'] = $data['return_reason'] ?? '';
        $returnData['describe'] = $data['describe'] ?? '';
        $image = !empty($data['voucher']) ? $data['voucher'] : [];

        $confirmTimeConfig = tpCache('shopping.auto_service_date');   // 后台设置多少天内可申请售后
        $confirmTime = $confirmTimeConfig * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirmTime && !empty($order['confirm_time'])) {
            return ['status' => 0, 'msg' => '已经超过' . $confirmTimeConfig . '天内退货时间'];
        }

        if (!empty($image)) {
            if (is_array($image)) {
                $returnData['imgs'] = implode(',', $image);
            } else {
                $returnData['imgs'] = $image;
            }
        }
        $returnData['addtime'] = time();
        $returnData['user_id'] = $order['user_id'];
        $returnData['order_id'] = $order['order_id'];
        $returnData['order_sn'] = $order['order_sn'];
        $returnData['goods_id'] = $orderGoods['goods_id'];
        $returnData['goods_num'] = $orderGoods['goods_num'];
        $returnData['spec_key'] = $orderGoods['spec_key'];
        $returnData['spec_key_name'] = $orderGoods['spec_key_name'];

        if ($type < 2) {
            $useApplyReturnMoney = $orderGoods['final_price'] * $orderGoods['goods_num'];    // 要退的总价 商品购买单价*申请数量
            $userExpenditureMoney = $order['goods_price'] - $order['order_prom_amount'] - $order['coupon_price'];    // 用户实际使用金额
            $user_electronic = round($order['user_electronic'] - $order['user_electronic'] * $order['shipping_price'] / $order['total_amount'], 2);
            // 该退积分支付
            $returnData['refund_integral'] = round($orderGoods['use_integral'] * $orderGoods['goods_num'], 2);
            // 该退电子币
            $returnData['refund_electronic'] = round($useApplyReturnMoney / $userExpenditureMoney * $user_electronic, 2);
            $integralDeductionMoney = $returnData['refund_integral'];  // 积分抵了多少钱，要扣掉
            if ($order['order_amount'] > 0) {
                $order_amount = $order['order_amount'] + $order['paid_money'];   // 三方支付总额，预售要退定金
                if ($order_amount > $order['shipping_price']) {
                    // 退款金额
                    $returnData['refund_money'] = round($useApplyReturnMoney / $userExpenditureMoney * ($order_amount - $order['shipping_price']), 2);
                    // 退款余额
                    $returnData['refund_deposit'] = round($useApplyReturnMoney / $userExpenditureMoney * $order['user_money'], 2);
                } else {
                    $returnData['refund_deposit'] = round($useApplyReturnMoney / $userExpenditureMoney * ($order['user_money'] - $order['shipping_price'] + $order['paid_money']) - $integralDeductionMoney, 2); //该退余额支付部分
                }
            } else {
                $returnData['refund_deposit'] = round($useApplyReturnMoney - $integralDeductionMoney, 2); //该退余额支付部分
            }
        }

        Db::startTrans();
        // 更新分成记录状态
        M('rebate_log')->where('order_sn', $order['order_sn'])->update(['sale_service' => 1]);
        if ($type < 2 && $orderGoods['goods_pv'] == 0) {
            // 冻结分成
            $rebate_list = M('rebate_log')->where('order_sn', $order['order_sn'])->select();
            foreach ($rebate_list as $rk => $rv) {
                $money = $this->getDecMoney($rv['order_id'], $rv['level']);
                $dec_money = $money[$recId]['money'];
                $dec_point = $money[$recId]['point'];
                M('rebate_log')->where('id', $rv['id'])->update([
                    'money' => ['exp', "money - {$dec_money}"],
                    'point' => ['exp', "point - {$dec_point}"],
                    'freeze_money' => ['exp', "freeze_money + {$dec_money}"],
                ]);
            }
        }
        // 售后记录
        if (isset($cOrder) && $cOrder['order_type'] == 3) {
            $returnData['is_supply'] = 1;
        }
        $returnId = M('return_goods')->add($returnData);
        if ($returnId) {
            // 更新订单
            M('order')->where('order_sn', $order['order_sn'])->update(['order_status' => 6]);
            Db::commit();
            return ['status' => 1, 'msg' => '申请成功', 'result' => ['return_id' => $returnId]];
        }
        Db::rollback();
        return ['status' => 0, 'msg' => '申请失败'];
    }

    /**
     * 上传退换货图片，兼容小程序.
     *
     * @return array
     */
    public function uploadReturnGoodsImg()
    {
        $return_imgs = '';
        if ($_FILES['return_imgs']['tmp_name']) {
            $files = request()->file('return_imgs');
            if (is_object($files)) {
                $files = [$files]; //可能是一张图片，小程序情况
            }
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];
            $dir = UPLOAD_PATH . 'return_goods/';
            if (!($_exists = file_exists($dir))) {
                $isMk = mkdir($dir);
            }
            $parentDir = date('Ymd');
            foreach ($files as $key => $file) {
                $info = $file->rule($parentDir)->validate($validate)->move($dir, true);
                if ($info) {
                    $filename = $info->getFilename();
                    $new_name = '/' . $dir . $parentDir . '/' . $filename;
                    $return_imgs[] = $new_name;
                } else {
                    return ['status' => -1, 'msg' => $file->getError()]; //上传错误提示错误信息
                }
            }
            if (!empty($return_imgs)) {
                $return_imgs = implode(',', $return_imgs); // 上传的图片文件
            }
        }

        return ['status' => 1, 'msg' => '操作成功', 'result' => $return_imgs];
    }

    /**
     * 获取可申请退换货订单商品
     *
     * @param $sale_t
     * @param $keywords
     * @param $user_id
     *
     * @return array
     */
    public function getReturnGoodsIndex($sale_t, $keywords, $user_id)
    {
        if ($keywords) {
            $condition['order_sn'] = $keywords;
        }
        if (1 == $sale_t) {
            //三个月内
            $condition['add_time'] = ['gt', 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'];
        } elseif (2 == $sale_t) {
            //三个月前
            $condition['add_time'] = ['lt', 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'];
        }
        $condition['user_id'] = $user_id;
        $condition['pay_status'] = 1;
        $condition['shipping_status'] = 1;
        $condition['deleted'] = 0;
        $count = M('order')->where($condition)->count();
        $Page = new \think\Page($count, 10);
        $show = $Page->show();
        $order_list = M('order')->where($condition)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $data = M('order_goods')->where(['order_id' => $v['order_id'], 'is_send' => ['lt', 2]])->select();
            if (!empty($data)) {
                $order_list[$k]['goods_list'] = $data;
            } else {
                unset($order_list[$k]);  //除去没有可申请的订单
            }
        }

        return [
            'order_list' => $order_list,
            'page' => $show,
        ];
    }

    /**
     * 获取退货列表.
     *
     * @param type $keywords
     * @param type $addtime
     * @param type $status
     *
     * @return type
     */
    public function getReturnGoodsList($keywords, $addtime, $status, $user_id = 0)
    {
        if ($keywords) {
            $where['order_sn|goods_name'] = ['like', "%$keywords%"];
        }
        if ('0' === $status || !empty($status)) {
            $where['status'] = $status;
        }
        if (1 == $addtime) {
            $where['addtime'] = ['gt', (time() - 90 * 24 * 3600)];
        }
        if (2 == $addtime) {
            $where['addtime'] = ['lt', (time() - 90 * 24 * 3600)];
        }
        $query = M('return_goods')->alias('r')->field('r.*,g.goods_name')
            ->join('__ORDER__ o', 'r.order_id = o.order_id AND o.deleted = 0 AND o.user_id=' . $user_id)
            ->join('__GOODS__ g', 'r.goods_id = g.goods_id', 'LEFT')
            ->where($where);
        $query2 = clone $query;
        $count = $query->count();
        $page = new \think\Page($count, 10);
        $list = $query2->order('id desc')->limit($page->firstRow, $page->listRows)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where('goods_id in (' . implode(',', $goods_id_arr) . ')')->getField('goods_id,goods_name');
        }

        return [
            'goodsList' => $goodsList,
            'return_list' => $list,
            'page' => $page->show(),
        ];
    }

    /**
     * 删除订单.
     *
     * @param type $order_id
     *
     * @return type
     */
    public function delOrder($order_id)
    {
        $validate = validate('order');
        if (!$validate->scene('del')->check(['order_id' => $order_id])) {
            return ['status' => 0, 'msg' => $validate->getError()];
        }
        if (empty($this->user_id)) {
            return ['status' => -1, 'msg' => '非法操作'];
        }
        $row = M('order')->where(['user_id' => $this->user_id, 'order_id' => $order_id])->update(['deleted' => 1]);
        if (!$row) {
            M('order_goods')->where(['order_id' => $order_id])->update(['deleted' => 1]);

            return ['status' => -1, 'msg' => '删除失败'];
        }

        return ['status' => 1, 'msg' => '删除成功'];
    }

    /**
     * 记录取消订单.
     */
    public function recordRefundOrder($user_id, $order_id, $user_note, $consignee, $mobile)
    {
        $order = M('order')->where(['order_id' => $order_id, 'user_id' => $user_id])->find();
        if (!$order) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }
        $order_return_num = M('return_goods')->where(['order_id' => $order_id, 'user_id' => $user_id, 'status' => ['neq', 5]])->count();
        if ($order_return_num > 0) {
            return ['status' => -1, 'msg' => '该订单中有商品正在申请售后'];
        }
        $order_status = 3; //已取消

        $order_info['order_status'] = $order_status;
        if ($mobile) {
            $order_info['mobile'] = $mobile;
        }
        if ($consignee) {
            $order_info['consignee'] = $consignee;
        }
        if ($user_note) {
            $order_info['user_note'] = $user_note;
            $data['action_note'] = $user_note;
        }

        $result = M('order')->where(['order_id' => $order_id])->update($order_info);
        if (!$result) {
            return ['status' => 0, 'msg' => '操作失败'];
        }

        $data['order_id'] = $order_id;
        $data['action_user'] = $user_id;
        $data['order_status'] = $order_status;
        $data['pay_status'] = $order['pay_status'];
        $data['shipping_status'] = $order['shipping_status'];
        $data['log_time'] = time();
        $data['status_desc'] = '用户取消已付款订单';
        M('order_action')->add($data); //订单操作记录
        return ['status' => 1, 'msg' => '提交成功'];
    }

    /**
     *    生成兑换码
     * 长度 =3位 + 4位 + 2位 + 3位  + 1位 + 5位随机  = 18位.
     *
     * @param $order
     *
     * @return mixed
     */
    public function make_virtual_code($order)
    {
        $order_goods = M('order_goods')->where(['order_id' => $order['order_id']])->find();
        $goods = M('goods')->where(['goods_id' => $order_goods['goods_id']])->find();
        M('order')->where(['order_id' => $order['order_id']])->save(['order_status' => 1, 'shipping_time' => time()]);
        $perfix = mt_rand(100, 999);
        $perfix .= sprintf('%04d', $order['user_id'] % 10000)
            . sprintf('%02d', (int)$order['user_id'] % 100) . sprintf('%03d', (float)microtime() * 1000);

        for ($i = 0; $i < $order_goods['goods_num']; ++$i) {
            $order_code[$i]['order_id'] = $order['order_id'];
            $order_code[$i]['user_id'] = $order['user_id'];
            $order_code[$i]['vr_code'] = $perfix . sprintf('%02d', (int)$i % 100) . rand(5, 1);
            $order_code[$i]['pay_price'] = $goods['shop_price'];
            $order_code[$i]['vr_indate'] = $goods['virtual_indate'];
            $order_code[$i]['vr_invalid_refund'] = $goods['virtual_refund'];
        }

        $res = checkEnableSendSms('7');

        //生成虚拟订单, 向用户发送短信提醒
        if ($res && 1 == $res['status']) {
            $sender = $order['mobile'];
            $goods_name = $goods['goods_name'];
            $goods_name = getSubstr($goods_name, 0, 10);
            $params = ['goods_name' => $goods_name];
            sendSms('7', $sender, $params);
        }

        return M('vr_order_code')->insertAll($order_code);
    }

    /**
     * 自动取消订单.
     */
    public function abolishOrder()
    {
        $set_time = 1; //自动取消时间/天 默认1天
        $abolishtime = strtotime("-$set_time day");
        $order_where = [
            'user_id' => $this->user_id,
            'add_time' => ['lt', $abolishtime],
            'pay_status' => 0,
            'order_status' => 0,
        ];
        $order = Db::name('order')->where($order_where)->getField('order_id', true);
        foreach ($order as $key => $value) {
            $result = $this->cancel_order($this->user_id, $value);
        }

        return $result;
    }

    /**
     * 添加预售商品订单.
     *
     * @param $user_id
     * @param $address_id
     * @param $invoice_title
     * @param $act_id
     * @param $pre_sell_price
     * @param $taxpayer
     *
     * @return array
     */
    public function addPreSellOrder($user_id, $address_id, $invoice_title, $act_id, $pre_sell_price, $taxpayer = '')
    {
        // 仿制灌水 1天只能下 50 单
        $order_count = M('Order')->where("user_id= $user_id and order_sn like '" . date('Ymd') . "%'")->count(); // 查找购物车商品总数量
        if ($order_count >= 50) {
            return ['status' => -9, 'msg' => '一天只能下50个订单', 'result' => ''];
        }
        $address = M('UserAddress')->where(['address_id' => $address_id])->find();
        $data = [
            'order_sn' => date('YmdHis') . rand(1000, 9999), // 订单编号
            'user_id' => $user_id, // 用户id
            'consignee' => $address['consignee'], // 收货人
            'province' => $address['province'], //'省份id',
            'city' => $address['city'], //'城市id',
            'district' => $address['district'], //'县',
            'twon' => $address['twon'], // '街道',
            'address' => $address['address'], //'详细地址',
            'mobile' => $address['mobile'], //'手机',
            'zipcode' => $address['zipcode'], //'邮编',
            'email' => $address['email'], //'邮箱',
            'invoice_title' => $invoice_title, //'发票抬头',
            'taxpayer' => $taxpayer, //'纳税人识别号',
            'goods_price' => $pre_sell_price['cut_price'] * $pre_sell_price['goods_num'], //'商品价格',
            'total_amount' => $pre_sell_price['cut_price'] * $pre_sell_price['goods_num'], // 订单总额
            'add_time' => time(), // 下单时间
            'prom_type' => 4,
            'prom_id' => $act_id,
        ];
        if (0 == $pre_sell_price['deposit_price']) {
            //无定金
            $data['order_amount'] = $pre_sell_price['cut_price'] * $pre_sell_price['goods_num']; //'应付款金额',
        } else {
            //有定金
            $data['order_amount'] = $pre_sell_price['deposit_price'] * $pre_sell_price['goods_num']; //'应付款金额',
        }
        $order_id = Db::name('order')->insertGetId($data);
//        M('goods_activity')->where(array('act_id'=>$act_id))->setInc('act_count',$pre_sell_price['goods_num']);
        if (false === $order_id) {
            return ['status' => -8, 'msg' => '添加订单失败', 'result' => null];
        }
        logOrder($order_id, '您提交了订单，请等待系统确认', '提交订单', $user_id);
        $order = M('Order')->where("order_id = $order_id")->find();
        $goods_activity = M('goods_activity')->where(['act_id' => $act_id])->find();
        $goods = M('goods')->where(['goods_id' => $goods_activity['goods_id']])->find();
        $data2['order_id'] = $order_id; // 订单id
        $data2['goods_id'] = $goods['goods_id']; // 商品id
        $data2['goods_name'] = $goods['goods_name']; // 商品名称
        $data2['goods_sn'] = $goods['goods_sn']; // 商品货号
        $data2['goods_num'] = $pre_sell_price['goods_num']; // 购买数量
        $data2['final_price'] = $pre_sell_price['cut_price']; // 市场价
        $data2['goods_price'] = $goods['shop_price']; // 商品团价
        $data2['cost_price'] = $goods['cost_price']; // 成本价
        $data2['member_goods_price'] = $pre_sell_price['cut_price']; //预售价钱
        $data2['give_integral'] = $goods_activity['integral']; // 购买商品赠送积分
        $data2['prom_type'] = 4; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠 ,4 预售商品
        $data2['prom_id'] = $goods_activity['act_id'];
        Db::name('order_goods')->insert($data2);
        // 如果有微信公众号 则推送一条消息到微信
        $user = M('OauthUsers')->where(['user_id' => $user_id, 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ('weixin' == $user['oauth']) {
            $wx_content = "你刚刚下了一笔预售订单:{$order['order_sn']} 尽快支付,过期失效!";
            $wechat = new WechatUtil();
            $wechat->sendMsg($user['openid'], 'text', $wx_content);
        }

        return ['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_sn']]; // 返回新增的订单id
    }

    /**
     * 获取订单 order_sn.
     *
     * @return string
     */
    public function get_order_sn()
    {
        $order_sn = null;
        // 保证不会有重复订单号存在
        while (true) {
            $order_sn = date('YmdHis') . rand(1000, 9999); // 订单编号
            $order_sn_count = M('order')->where('order_sn = ' . $order_sn)->count();
            if (0 == $order_sn_count) {
                break;
            }
        }

        return $order_sn;
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

    /**
     * 取消订单后改变库存，根据不同的规格，商品活动修改对应的库存.
     *
     * @param $order
     * @param $rec_id |订单商品表id 如果有只返还订单某个商品的库存,没有返还整个订单
     */
    public function alterReturnGoodsInventory($order, $rec_id = '')
    {
        if ($rec_id) {
            $orderGoodsWhere['rec_id'] = $rec_id;
            $retunn_info = Db::name('return_goods')->where($orderGoodsWhere)->select(); //查找购买数量和购买规格
            $order_goods_prom = Db::name('order_goods')->where($orderGoodsWhere)->find(); //购买时参加的活动
            $order_goods = $retunn_info;
            $order_goods[0]['prom_type'] = $order_goods_prom['prom_type'];
            $order_goods[0]['prom_id'] = $order_goods_prom['prom_id'];
        } else {
            $orderGoodsWhere = ['order_id' => $order['order_id']];
            $order_goods = Db::name('order_goods')->where($orderGoodsWhere)->select(); //查找购买数量和购买规格
        }

        foreach ($order_goods as $key => $val) {
            if (!empty($val['spec_key'])) { // 先到规格表里面扣除数量
                $SpecGoodsPrice = new SpecGoodsPrice();
                $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
                if ($specGoodsPrice) {
                    $specGoodsPrice->store_count = $specGoodsPrice->store_count + $val['goods_num'];
                    $specGoodsPrice->save(); //有规格则增加商品对应规格的库存
                }
            } else {
                M('goods')->where(['goods_id' => $val['goods_id']])->setInc('store_count', $val['goods_num']); //没有规格则增加商品库存
            }

            //套组返回库存
            if (2 == $val['sale_type']) {
                /*$g_list = M('GoodsSeries')->where('goods_id', $val['goods_id'])->select();
                if ($g_list) {
                    foreach ($g_list as $k => $v) {
                        if ($v['item_id']) {
                            $SpecGoodsPrice = new SpecGoodsPrice();
                            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
                            if ($specGoodsPrice) {
                                $specGoodsPrice->store_count = $specGoodsPrice->store_count + $val['goods_num'] * $v['g_number'];
                                $specGoodsPrice->save(); //有规格则增加商品对应规格的库存
                            }
                        } else {
                            M('Goods')->where('goods_id', $v['g_id'])->setInc('store_count', $v['g_number'] * $val['goods_num']);
                            M('Goods')->where('goods_id', $v['g_id'])->setDec('sales_sum', $v['g_number'] * $val['goods_num']);
                        }
                    }
                }*/
            }
            update_stock_log($order['user_id'], +$val['goods_num'], $val, $order['order_sn']); //库存日志

            Db::name('Goods')->where('goods_id', $val['goods_id'])->setDec('sales_sum', $val['goods_num']); // 减少商品销售量
            //更新活动商品购买量
            // if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            if (1 == $val['prom_type']) {
                $GoodsPromFactory = new GoodsPromFactory();
                $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
                $prom = $goodsPromLogic->getPromModel();
                if (0 == $prom['is_end']) {
                    $tb = 1 == $val['prom_type'] ? 'flash_sale' : 'group_buy';
                    M($tb)->where('id', $val['prom_id'])->setDec('buy_num', $val['goods_num']);
                    M($tb)->where('id', $val['prom_id'])->setDec('order_num', 1);
                }
            }
        }
    }

    /**
     * 获取订单商品
     * @param $orderId
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderGoods($orderId)
    {
        if (is_array($orderId)) {
            $where['og.order_id'] = ['in', $orderId];
        } else {
            $where['og.order_id'] = $orderId;
        }
        $orderGoods = Db::name('order_goods og')
            ->join('goods g', 'g.goods_id = og.goods_id', 'LEFT')
            ->join('return_goods rg', 'rg.rec_id = og.rec_id', 'LEFT')
            ->join('spec_goods_price sgp', 'sgp.goods_id = og.goods_id AND sgp.`key` = og.spec_key', 'LEFT')
            ->where($where)->field('og.*, sgp.item_id, g.commission, g.original_img, rg.status return_status, g.zone')->select();
        foreach ($orderGoods as $k => $goods) {
            $orderGoods[$k]['is_return'] = M('ReturnGoods')->where('rec_id', $goods['rec_id'])->find() ? 1 : 0;
            $orderGoods[$k]['status_desc'] = isset($goods['return_status']) ? C('REFUND_STATUS')[$goods['return_status']] : '';
        }
        return $orderGoods;
    }

    /**
     * 根据订单商品记录ID获取商品
     * @param $recId
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderGoodsById($recId)
    {
        $where['og.rec_id'] = $recId;
        $orderGoods = Db::name('order_goods og')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->join('spec_goods_price sgp', 'sgp.goods_id = og.goods_id AND sgp.`key` = og.spec_key', 'LEFT')
            ->where($where)->field('og.*, sgp.item_id, g.original_img, g.supplier_goods_id')->find();
        return $orderGoods;
    }

    /**
     * 获取退货商品信息
     * @param $returnId
     * @param $page
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getReturnGoods($returnId, $page = '')
    {
        if (is_array($returnId)) {
            $where['rg.id'] = ['in', $returnId];
        } else {
            $where['rg.id'] = $returnId;
        }
        $returnGoods = Db::name('return_goods rg')
            ->join('order_goods og', 'og.rec_id = rg.rec_id')
            ->join('goods g', 'g.goods_id = og.goods_id')
            ->join('spec_goods_price sgp', 'sgp.goods_id = og.goods_id AND sgp.`key` = og.spec_key', 'LEFT')
            ->where($where)->field('rg.*, og.goods_sn, og.goods_name, og.spec_key_name, sgp.item_id, g.original_img')
            ->order('addtime desc');
        if ($page) {
            $returnGoods = $returnGoods->limit($page->firstRow . ',' . $page->listRows);
        }
        return $returnGoods->select();
    }

    /**
     * 订单发送到供应链系统
     * @param $orderId
     * @param $time
     */
    public function supplierOrderSend($orderId, $time)
    {
        $order = M('order')->where(['parent_id' => $orderId, 'order_type' => 3])->find();
        $orderGoods = M('order_goods')->where(['order_id2' => $order['order_id']])->field('supplier_goods_id goods_id, goods_num, spec_key, member_goods_price final_price')->select();
        // 发送到供应链系统
        $orderData = [
            'order_sn' => $order['order_sn'],
            'consignee' => $order['consignee'],
            'province' => M('region2')->where(['id' => $order['province']])->value('ml_region_id'),
            'city' => M('region2')->where(['id' => $order['city']])->value('ml_region_id'),
            'district' => M('region2')->where(['id' => $order['district']])->value('ml_region_id'),
            'twon' => M('region2')->where(['parent_id' => $order['district'], 'status' => 1])->value('ml_region_id') ?? 0,
            'address' => $order['address'],
            'mobile' => $order['mobile'],
            'goods_price' => $order['goods_price'],
            'total_amount' => $order['total_amount'],
            'note' => $order['user_note'],
            'order_goods' => $orderGoods
        ];
        $res = (new OrderService())->submitOrder($orderData);
        if ($res['status'] == 0) {
            // 发送失败
            $updata = ['supplier_order_status' => 2, 'supplier_submit_time' => $time, 'supplier_submit_remark' => $res['msg']];
        } else {
            // 发送成功
            $updata = ['supplier_order_status' => 1, 'supplier_submit_time' => $time, 'supplier_order_sn' => $res['data']['order']['order_sn']];
        }
        M('order')->where(['order_id' => $order['order_id']])->update($updata);
    }
}
