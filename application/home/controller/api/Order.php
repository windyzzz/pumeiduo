<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller\api;

use app\common\logic\CartLogic;
use app\common\logic\CommentLogic;
use app\common\logic\CouponLogic;
use app\common\logic\MessageLogic;
use app\common\logic\OrderLogic;
use app\common\logic\Pay;
use app\common\logic\UsersLogic;
use app\common\util\TpshopException;
use think\Db;
use think\Hook;
use think\Page;
use think\Request;

class Order extends Base
{
    public $user_id = 0;
    public $user = [];

    public function __construct()
    {
        parent::__construct();
        // 1. 检查登陆
        $params['user_token'] = $this->userToken;
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);

        $user = session('user');
        if ($user) {
            $this->user = $user;
            $this->user_id = $user['user_id'];
        }
    }

    /*
     * 订单列表
     */
    public function order_list()
    {
        $return = [];

        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();
        $return['user_message_count'] = $user_message_count;

        //用户中心面包屑导航
        $navigate_user = navigate_user();
        $return['navigate_user'] = $navigate_user;

        $where = ' user_id=:user_id';
        $bind['user_id'] = $this->user_id;
        //条件搜索
        if (I('get.type')) {
            $where .= C(strtoupper(I('get.type')));
        }
        // 搜索订单 根据商品名称 或者 订单编号
        $search_key = trim(I('search_key'));
        if ($search_key) {
            $where .= ' and (order_sn like :search_key1 or order_id in (select order_id from `' . C('database.prefix') . 'order_goods` where goods_name like :search_key2) ) ';
            $bind['search_key1'] = "%$search_key%";
            $bind['search_key2'] = "%$search_key%";
        }
        $where .= ' and prom_type < 5 '; //虚拟拼团订单不列出来
        $where .= ' and deleted != 1 '; //虚拟拼团订单不列出来

        $count = M('order')->where($where)->bind($bind)->count();
        $Page = new Page($count, 10);

        $show = $Page->show();
        $order_str = 'order_id DESC';
        $order_list = M('order')->order($order_str)->where($where)->bind($bind)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性

            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $number_amount = 0;
            $total_give_integral = 0;
            $order_list[$k]['goods_list'] = $data['result'];
            foreach ($order_list[$k]['goods_list'] as $glk => $glv) {
                $number_amount += $glv['goods_num'];
                $total_give_integral += $glv['give_integral'];

                if ($glv['zone'] == 3) {
                    $order_list[$k]['cancel_btn'] = 0;
                }
            }
            $order_list[$k]['number_amount'] = $number_amount;
            $order_list[$k]['total_give_integral'] = $total_give_integral;
            $order_list[$k]['cancel_time'] = $order_list[$k]['add_time'] + 1 * 60 * 60;
            $order_list[$k]['add_time'] = date('Y-m-d H:i:s', $order_list[$k]['add_time']);

            if (4 == $order_list[$k]['prom_type']) {
                $pre_sell_item = M('goods_activity')->where(['act_id' => $order_list[$k]['prom_id']])->find();
                $pre_sell_item = array_merge($pre_sell_item, unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            } else {
                $order_list[$k]['pre_sell_is_finished'] = -1; //没有参与预售的订单
            }

            if (2 == $order_list[$k]['prom_type']) {
                $group_detail = M('GroupDetail')->where('group_id', $order_list[$k]['prom_id'])->select();
                foreach ($group_detail as $gdk => $gdv) {
                    $order_sn = explode(',', $gdv['order_sn_list']);
                    if (in_array($order_list[$k]['order_sn'], $order_sn)) {
                        $order_list[$k]['group_status'] = C('GROUP_STATUS')[$gdv['status']];
                        break;
                    }
                }
            }

            //发货单号
            $order_list[$k]['invoice_no'] = M('delivery_doc')->where('order_sn', $v['order_sn'])->getField('invoice_no');
        }
        $number_amount = $count;
        $return['order_status'] = C('ORDER_STATUS');
        $return['group_status'] = C('GROUP_STATUS');
        $return['shipping_status'] = C('SHIPPING_STATUS');
        $return['pay_status'] = C('PAY_STATUS');
        $return['page'] = $show;
        $return['lists'] = $order_list;
        $return['active'] = 'order_list';
        $return['number_amount'] = $number_amount;
        $return['active_status'] = I('get.type');

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 订单详情
     */
    public function order_detail()
    {
        $id = I('get.id/d');
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        if (!$order_info) {
            return json(['status' => 0, 'msg' => '没有获取到订单信息', 'result' => null]);
        }
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (5 == $order_info['prom_type']) {   //虚拟订单
            $this->redirect(U('virtual/virtual_order', ['order_id' => $id]));
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];

        $order_info['add_time'] = date('Y-m-d H:i:s', $order_info['add_time']);
        $order_info['pay_time'] = $order_info['pay_time'] ? date('Y-m-d H:i:s', $order_info['pay_time']) : 0;
        $order_info['shipping_time'] = $order_info['shipping_time'] ? date('Y-m-d H:i:s', $order_info['shipping_time']) : 0;
        $order_info['confirm_time'] = $order_info['confirm_time'] ? date('Y-m-d H:i:s', $order_info['confirm_time']) : 0;

        $can_return = $order_info['end_sale_time'] > time() ? true : false;

        $total_give_integral = 0;
        foreach ($order_info['goods_list'] as $gk => $gv) {
            $total_give_integral += $gv['give_integral'] * $gv['goods_num'];
            $order_info['goods_list'][$gk]['can_return'] = $can_return;
        }
        $order_info['total_give_integral'] = $total_give_integral;
        if (4 == $order_info['prom_type']) {
            $pre_sell_item = M('goods_activity')->where(['act_id' => $order_info['prom_id']])->find();
            $pre_sell_item = array_merge($pre_sell_item, unserialize($pre_sell_item['ext_info']));
            $order_info['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
            $order_info['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
            $order_info['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            $order_info['pre_sell_deliver_goods'] = $pre_sell_item['deliver_goods'];
        } else {
            $order_info['pre_sell_is_finished'] = -1; //没有参与预售的订单
        }

        if (2 == $order_info['prom_type']) {
            $group_detail = M('group_detail')->where('group_id', $order_info['prom_id'])->select();
            foreach ($group_detail as $gkey => $gvalue) {
                $order_sn = explode(',', $gvalue['order_sn_list']);
                if (in_array($order_info['order_sn'], $order_sn)) {
                    $group_activity = M('group_buy')->find($order_info['prom_id']);
                    $order_info['group_buy_detail'] = $gvalue;
                    $order_info['group_buy_detail']['status'] = C('GROUP_STATUS')[$gvalue['status']];
                    $order_info['group_buy_detail']['time'] = date('Y-m-d H:i:s', $gvalue['time']);
                    $order_info['group_buy_detail']['time_desc'] = 2 == $gvalue['status'] ? '成团时间' : '开团时间';
                    $order_info['group_buy_detail']['batch_num'] = $group_activity['group_goods_num'];
                    break;
                }
            }
        }

        //获取订单进度条
        $sql = "SELECT action_id,log_time,status_desc,order_status FROM ((SELECT * FROM __PREFIX__order_action WHERE order_id = :id AND status_desc <>'' ORDER BY action_id) AS a) GROUP BY status_desc ORDER BY action_id";
        $bind['id'] = $id;
        $items = DB::query($sql, $bind);
        $items_count = count($items);

        $ids = $order_info['province'] . ',' . $order_info['city'] . ',' . $order_info['district'];
        $region_list = M('region2')->where('id in (' . $ids . ')')->getField('id,name');
        $invoice_no = M('DeliveryDoc')->where('order_id', $id)->getField('invoice_no', true);
        $order_info['invoice_no'] = implode(' , ', $invoice_no);
        $order_return_num = M('return_goods')->where(['order_id' => $id, 'user_id' => $this->user_id])->count();
        $order_info['is_return'] = $order_return_num > 0 ? 1 : 0;

        //发货单号
        $order_info['invoice_no'] = M('delivery_doc')->where('order_sn', $order_info['order_sn'])->getField('invoice_no');

        //获取订单操作记录
        $order_action = M('order_action')->field('*,FROM_UNIXTIME(log_time,"%Y-%m-%d %H:%i:%s") as add_time')->where(['order_id' => $id])->select();

        //双十一任务奖励
        $task_log = M('task_log')->where('order_sn', $order_info['order_sn'])->where('task_id', 1)->where('type', 1)->find();
        if (!$task_log) {
            $task_log['id'] = 0;
        }
        $task_log['created_at'] = date('Y-m-d H:i:s', $task_log['created_at']);
        $order_info['task_reward'] = $task_log;

        $return['order_status'] = C('ORDER_STATUS');
        $return['group_status'] = C('GROUP_STATUS');
        $return['shipping_status'] = C('SHIPPING_STATUS');
        $return['pay_status'] = C('PAY_STATUS');
        $return['region_list'] = $region_list;
        $return['order_info'] = $order_info;
        $return['order_action'] = $order_action;
        $return['active'] = 'order_list';

        //获取购物券
        $coupon_info = M('coupon_list')->alias('cl')
            ->where(array('cl.get_order_id' => $order_info['order_id']))
            ->join('coupon c', 'cl.cid = c.id')->field('c.name,c.condition,c.money,c.image_url as coupon_image_url,c.id')->find();

        if ($coupon_info) {
            $return['is_has_coupon'] = 1;
            $return['coupon_id'] = $coupon_info['id'];
            $return['coupon_name'] = $coupon_info['name'];
            $coupon_money = $coupon_info['money'];
            $coupon_money = bcadd($coupon_money, 0, 1);
            $coupon_money = str_replace('.0', '', $coupon_money);
            $return['coupon_dis'] = $coupon_money . '折';
            $return['coupon_image_url'] = $coupon_info['coupon_image_url'];
        } else {
            $return['coupon_id'] = 0;
            $return['is_has_coupon'] = 0;
            $return['coupon_name'] = '';
            $return['coupon_dis'] = '';
            $return['coupon_image_url'] = '';
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function del_order()
    {
        $order_id = I('order_id/d', 0);

        $orderLogic = new OrderLogic();
        $orderLogic->setUserId($this->user_id);
        $return = $orderLogic->delOrder($order_id);

        return json($return);
        //return json(['status'=>1,'msg'=>'删除成功',U('Home/Order/order_list', array('type'=>$order_type)), 'result'=>null]);
    }

    public function del_refund_goods()
    {
        $order_id = I('id/d', 0);

        M('return_goods')->where('id', $id)->update(['status' => 6]);

        return json(['status' => 1, 'msg' => '删除成功', 'result' => null]);
    }

    /*
     * 取消订单
     */
    public function cancel_order()
    {
        $id = I('id/d');
        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data = $logic->cancel_order($this->user_id, $id);

        return json($data);
    }

    public function cancel_order_info()
    {
        $order_id = I('order_id/d', 0);
        $order = M('order')->where(['order_id' => $order_id, 'order_status' => 3, 'pay_status' => 1])->find();
        $return['order'] = $order;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    //取消订单弹窗
    public function refund_order()
    {
        $order_id = I('get.order_id/d');

        $order = M('order')
            ->field('order_id,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount,total_amount')
            ->where(['order_id' => $order_id, 'user_id' => $this->user_id])
            ->find();

        if (!$order) {
            return json(['status' => 0, 'msg' => '订单不存在', 'result' => null]);
        }

        $return['user'] = $this->user;
        $return['order'] = $order;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    //申请取消订单
    public function record_refund_order()
    {
        $order_id = input('post.order_id', 0);
        $user_note = input('post.user_note', '');
        $consignee = input('post.consignee', '');
        $mobile = input('post.mobile', '');

        $logic = new OrderLogic();
        $return = $logic->recordRefundOrder($this->user_id, $order_id, $user_note, $consignee, $mobile);

        return json($return);
    }

    public function virtual_order()
    {
        $Order = new \app\common\model\Order();
        $order_id = I('get.order_id/d');
        $map['order_id'] = $order_id;
        $map['user_id'] = $this->user_id;
        $orderobj = $Order->where($map)->find();
        if (!$orderobj) {
            return json(['status' => 0, 'msg' => '没有获取到订单信息', 'result' => null]);
        }
        // 添加属性  包括按钮显示属性 和 订单状态显示属性
        $order_info = $orderobj->append(['order_status_detail', 'order_button', 'order_goods'])->toArray();
        //获取订单操作记录
        $order_action = M('order_action')->where(['order_id' => $order_id])->select();
        $return['order_status'] = C('ORDER_STATUS');
        $return['pay_status'] = C('PAY_STATUS');
        $return['order_info'] = $order_info;
        $return['order_action'] = $order_action;

        if (1 == $order_info['pay_status'] && 3 != $order_info['order_status']) {
            $vrorder = M('vr_order_code')->where(['order_id' => $order_id])->select();
            $return['vrorder'] = $vrorder;
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 评论晒单
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status', -1);
        $logic = new CommentLogic();
        $data = $logic->getComment($user_id, $status); //获取评论列表
        $return['page'] = $data['show']; // 赋值分页出
        $return['comment_page'] = $data['page'];
        $return['comment_list'] = $data['result'];
        $return['active'] = 'comment';

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 删除评价.
     */
    public function delComment()
    {
        $comment_id = I('comment_id');
        if (empty($comment_id)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        $comment = Db::name('comment')->where('comment_id', $comment_id)->find();
        if ($this->user_id != $comment['user_id']) {
            return json(['status' => 0, 'msg' => '不能删除别人的评论', 'result' => null]);
        }
        Db::name('reply')->where('comment_id', $comment_id)->delete();
        Db::name('comment')->where('comment_id', $comment_id)->delete();

        return json(['status' => 1, 'msg' => '删除评论成功', 'result' => null]);
    }

    /**
     *  点赞.
     *
     * @author lxl
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(['comment_id' => $comment_id])->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $result['success'] = 0;
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(['comment_id' => $comment_id])->save($comment_data);
            $result['success'] = 1;
        }

        return json($result);
    }

    /**
     * 添加回复.
     *
     * @author dyr
     */
    public function reply_add()
    {
        $comment_id = I('post.comment_id/d');
        $reply_id = I('post.reply_id/d', 0);
        $content = I('post.content');
        $to_name = I('post.to_name', '');
        $goods_id = I('post.goods_id/d');
        $reply_data = [
            'comment_id' => $comment_id,
            'parent_id' => $reply_id,
            'content' => $content,
            'user_name' => $this->user['nickname'],
            'to_name' => $to_name,
            'reply_time' => time(),
        ];
        $where = ['o.user_id' => $this->user_id, 'og.goods_id' => $goods_id, 'o.pay_status' => 1];
        $user_goods_count = Db::name('order')
            ->alias('o')
            ->join('__ORDER_GOODS__ og', 'o.order_id = og.order_id', 'LEFT')
            ->where($where)
            ->count();
        if ($user_goods_count > 0) {
            M('reply')->add($reply_data);
            M('comment')->where(['comment_id' => $comment_id])->setInc('reply_num');
            $json['status'] = 1;
            $json['msg'] = '回复成功';
        } else {
            $json['status'] = -1;
            $json['msg'] = '只有购买过该商品才能进行评价';
        }
        $json['result'] = null;

        return json($json);
    }

    // 确认收货
    public function order_confirm()
    {
        $id = I('post.order_id/d', 0);
        $data = confirm_order($id, $this->user_id);

        return json($data);
    }

    /**
     * 可申请退换货.
     */
    public function return_goods_index()
    {
        $sale_t = I('sale_t/i', 0);
        $keywords = I('keywords');
        $model = new OrderLogic();
        $data = $model->getReturnGoodsIndex($sale_t, $keywords, $this->user_id);
        $return['store_list'] = $data['store_list'];
        $return['order_list'] = $data['order_list'];
        $return['page'] = $data['show'];

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 申请退货.
     */
    public function return_goods(Request $request)
    {
        $rec_id = I('rec_id', 0);
        $return_goods = M('return_goods')->where(['rec_id' => $rec_id])->find();
        if (!empty($return_goods)) {
            return json(['status' => 0, 'msg' => '已经提交过退货申请!', 'result' => null]);
        }
        $order_goods = M('order_goods')->where(['rec_id' => $rec_id])->find();
        $order_goods['goods_img'] = M('goods')->where(['goods_id' => $order_goods['goods_id']])->getField('original_img');
        $order = M('order')->where(['order_id' => $order_goods['order_id'], 'user_id' => $this->user_id])->find();
        $confirm_time_config = tpCache('shopping.auto_service_date'); //后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            return json(['status' => 0, 'msg' => '已经超过' . $confirm_time_config . '天内退货时间', 'result' => null]);
        }
        if (empty($order)) {
            return json(['status' => 0, 'msg' => '非法操作', 'result' => null]);
        }
        if ($request->isPost()) {
            $model = new OrderLogic();
            $res = $model->addReturnGoods($rec_id, $order);  //申请售后
            if (1 == $res['status']) {
                return json(['status' => 1, 'msg' => $res['msg'], 'result' => null]);
            }

            return json(['status' => 0, 'msg' => $res['msg'], 'result' => null]);
        }
        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = 0;
        $return_address = M('region2')->where('id in (' . implode(',', $region_id) . ')')->getField('id,name');
        $order_info = array_merge($order, $order_goods);  //合并数组
        $return['return_address'] = $return_address;
        $return['return_type'] = C('RETURN_TYPE');
        $return['goods'] = $order_goods;
        $return['order'] = $order;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 退换货列表.
     */
    public function return_goods_list()
    {
        $where = " rg.user_id=$this->user_id ";
        $where .= ' and rg.status!=6 ';
        // 搜索订单 根据商品名称 或者 订单编号
        $search_key = trim(I('search_key'));
        if ($search_key) {
            $where .= " and rg.order_sn=$search_key";
        }

        $count = M('return_goods')->alias('rg')->where($where)->count();
        $page = new Page($count, 10);

        $list = M('return_goods')
            ->alias('rg')
            ->join('__ORDER_GOODS__ og', 'rg.goods_id = og.goods_id and rg.order_id = og.order_id', 'LEFT')
            ->where($where)
            ->order('id desc')
            ->limit("{$page->firstRow},{$page->listRows}")
            ->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where('goods_id', 'in', implode(',', $goods_id_arr))->getField('goods_id,goods_name,original_img,shop_price,exchange_integral');
        }
        $state = C('REFUND_STATUS');
        $return['state'] = $state;
        $return['goodsList'] = $goodsList;
        $return['list'] = $list;
        $return['page'] = $page->show(); // 赋值分页出
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     *  退货详情.
     */
    public function return_goods_info(Request $request)
    {
        $id = I('id/d', 0);
        $ReturnGoodsModel = new \app\common\model\ReturnGoods();
        $return_goods = $ReturnGoodsModel::get(['id' => $id, 'user_id' => $this->user_id]);
        if (empty($return_goods)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        if ($request->isPost()) {
            $data = I('post.');
            $data['delivery'] = serialize($data['delivery']);
            $data['status'] = 2;
            M('return_goods')->where(['id' => $data['id'], 'user_id' => $this->user_id])->save($data);

            return json(['status' => 1, 'msg' => '发货提交成功', 'result' => null]);
        }
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        $return_goods['delivery'] = unserialize($return_goods['delivery']);  //订单的物流信息，服务类型为换货会显示
        $return_goods['third_payment'] = '';
        $orderInfo = M('order')->field('pay_code,order_amount')->where('order_id', $return_goods['order_id'])->find();
        if (0 == $return_goods['refund_type']) {
            if ('alipayMobile' == $orderInfo['pay_code']) {
                $return_goods['third_payment'] = '支付宝';
            } else {
                $return_goods['third_payment'] = '微信';
            }
        }
        $return_goods['order_amount'] = $orderInfo['order_amount'];
        $return_goods['addtime'] = date('Y-m-d H:i:s', $return_goods['addtime']);
        if ($return_goods['imgs']) {
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        }
        $goods = M('goods')->where('goods_id', $return_goods['goods_id'])->find();
        $return['state'] = C('REFUND_STATUS');
        $return['return_type'] = C('RETURN_TYPE');
        $return['goods'] = $goods;
        $return['return_goods'] = $return_goods;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function return_goods_refund()
    {
        $order_sn = I('order_sn');
        $where = ['user_id' => $this->user_id];
        if ($order_sn) {
            $where['order_sn'] = $order_sn;
        }
        $where['status'] = 5;
        $count = M('return_goods')->where($where)->count();
        $page = new Page($count, 10);
        $list = M('return_goods')->where($where)->order('id desc')->limit($page->firstRow, $page->listRows)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where('goods_id in (' . implode(',', $goods_id_arr) . ')')->getField('goods_id,goods_name');
        }
        $return['goodsList'] = $goodsList;
        $state = C('REFUND_STATUS');
        $return['list'] = $list;
        $return['state'] = $state;
        $return['page'] = $page->show(); // 赋值分页出
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 取消服务单.
     */
    public function return_goods_cancel()
    {
        $id = I('id/d', 0);
        if (empty($id)) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        $res = M('return_goods')->where(['id' => $id, 'user_id' => $this->user_id])->save(['status' => -2, 'canceltime' => time()]);

        // 如果订单没有售后的商品，订单状态变回已确认
        $order_id = M('return_goods')->where(['id' => $id, 'user_id' => $this->user_id])->getField('order_id');
        if (!M('return_goods')->where('order_id', $order_id)->where('status', 'neq', -2)->find()) {
            M('order')->where('order_id', $order_id)->update(['order_status' => 2]);
        }

        $return_info = M('return_goods')->where(['id' => $id, 'user_id' => $this->user_id])->find();
        // 解冻分成
        if (5 != $return_info['status']) {
            // $goods_commission = M('Goods')->where('goods_id', $return_info['goods_id'])->getField('commission');
            // $dec_money = $return_info['refund_money'] * $goods_commission / 100;
            // M('rebate_log')->where("order_sn",$return_info['order_sn'])->update([
            //     'money' => ['exp',"money + {$dec_money}"],
            //     'freeze_money' => ['exp',"freeze_money - {$dec_money}"]
            // ]);

            $rebate_list = M('rebate_log')->where('order_sn', $return_info['order_sn'])->select();

            if ($rebate_list) {
                $OrderLogic = new OrderLogic();
                foreach ($rebate_list as $rk => $rv) {
                    $money = $OrderLogic->getDecMoney($rv['order_id'], $rv['level']);

                    $dec_money = $money[$return_info['rec_id']]['money'];
                    $dec_point = $money[$return_info['rec_id']]['point'];

                    M('rebate_log')->where('id', $rv['id'])->update([
                        'money' => ['exp', "money + {$dec_money}"],
                        'point' => ['exp', "point + {$dec_point}"],
                        'freeze_money' => ['exp', "freeze_money - {$dec_money}"],
                    ]);
                }
            }
        }
        if ($res) {
            return json(['status' => 1, 'msg' => '成功取消服务单']);
        }

        return json(['status' => 0, 'msg' => '服务单不存在']);
    }

    /**
     * 换货商品确认收货.
     *
     * @author lxl
     * @time  17-4-25
     * */
    public function receiveConfirm()
    {
        $return_id = I('return_id/d');
        $return_info = M('return_goods')->field('order_id,order_sn,goods_id,spec_key')->where('id', $return_id)->find(); //查找退换货商品信息
        $update = M('return_goods')->where('id', $return_id)->save(['status' => 3]);  //要更新状态为已完成
        if ($update) {
            M('order_goods')->where([
                'order_id' => $return_info['order_id'],
                'goods_id' => $return_info['goods_id'],
                'spec_key' => $return_info['spec_key'],])->save(['is_send' => 2]);  //订单商品改为已换货
            return json(['status' => 1, 'msg' => '操作成功', 'result' => null]);
        }

        return json(['status' => 0, 'msg' => '操作失败', 'result' => null]);
    }

    /**
     * 获取我的粉丝数据.
     *
     * @author J
     * @time  18-08-03
     * */
    public function lower()
    {
        $first_fans = M('users')->where('invite_uid', $this->user_id)->count();
        $first_fans_id = M('users')->field('user_id')->where('invite_uid', $this->user_id)->select();

        $first_fans_id_arr = [];

        if ($first_fans_id) {
            foreach ($first_fans_id as $fk => $fv) {
                $first_fans_id_arr[] = $fv['user_id'];
            }
        }
        $second_fans = 0;
        if ($first_fans_id_arr) {
            $second_fans = M('users')->where('invite_uid', 'IN', $first_fans_id_arr)->count();
        }

        $return['first_fans'] = $first_fans; // 总数
        $return['second_fans'] = $second_fans;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 获取粉丝列表接口
     * */
    public function lower_list()
    {
        $level = I('get.level', 1);

        if (1 == $level) {
            $count = M('users')->where('first_leader', $this->user_id)->count();

            $page = new Page($count, 35);

            $list = M('users')
                ->field('user_id,head_pic,nickname,distribut_level,CASE(distribut_level) WHEN "1" THEN "粉丝" WHEN "2" THEN "VIP会员" WHEN "3" THEN "乐活优选店主" ELSE "未知" END AS distribut_level_name')
                ->where('first_leader', $this->user_id)
                ->limit("{$page->firstRow},{$page->listRows}")
                ->order('user_id desc')
                ->select();
        } else {
            $count = M('users')->where('second_leader', $this->user_id)->count();

            $page = new Page($count, 35);

            $list = M('users')
                ->field('user_id,head_pic,first_leader,nickname,distribut_level,CASE(distribut_level) WHEN "1" THEN "粉丝" WHEN "2" THEN "VIP会员" WHEN "3" THEN "乐活优选店主" ELSE "未知" END AS distribut_level_name')
                ->where('second_leader', $this->user_id)
                ->limit("{$page->firstRow},{$page->listRows}")
                ->order('user_id desc')
                ->select();
        }

        $return['count'] = $count; // 总数
        $return['level'] = $level;
        $return['member'] = $list; // 线
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
     * 总的分销订单
     * */
    public function income()
    {
        $result = Db::query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where user_id = {$this->user_id}");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;
        $status = I('get.status', -2);

        if ('0' == $status || $status > 0) {
            $condition['status'] = $status;
        }

        $condition['user_id'] = $this->user_id;
        $count = M('rebate_log')->where($condition)->count();
        $page = new Page($count, 10);
        $rebate_log = M('rebate_log')->where($condition)->limit("{$page->firstRow},{$page->listRows}")->order('id desc')->select();
        $OrderCommonLogc = new \app\common\logic\OrderLogic();
        foreach ($rebate_log as $rk => $rv) {
            $order_goods = M('OrderGoods')
                ->alias('a')
                ->field('a.*,o.add_time')
                ->join('__ORDER__ o', 'o.order_id = a.order_id', 'LEFT')
                ->where('a.order_id', $rv['order_id'])
                ->select();

            foreach ($order_goods as $ok => $ov) {
                $goodsInfo = M('Goods')->field('exchange_integral,shop_price,shop_price - exchange_integral as integral_price, original_img as imgSrc')->where('goods_id', $ov['goods_id'])->find();
                $order_goods[$ok]['add_time'] = date('Y-m-d H:i:s', $ov['add_time']);
                $order_goods[$ok]['imgSrc'] = $goodsInfo['imgSrc'];
                if ($ov['use_integral'] > 0) {
                    $order_goods[$ok]['price'] = $goodsInfo['integral_price'];
                    $order_goods[$ok]['integral'] = $ov['use_integral'];
                } else {
                    $order_goods[$ok]['price'] = $goodsInfo['shop_price'];
                    $order_goods[$ok]['integral'] = 0;
                }

                $order_goods[$ok]['get_price'] = $OrderCommonLogc->getRongMoney(($ov['final_price'] * $ov['goods_num']) * $ov['commission'] / 100, $rv['level'], $ov['add_time'], $ov['goods_id']);

                //$order_goods[$ok]['get_price'] = round(($ov['final_price'] * $ov['goods_num']) * $ov['commission'] / 100 * $distribut_rate, 2);
                $order_goods[$ok]['is_freeze'] = M('return_goods')->where('rec_id', $ov['rec_id'])->where('status', 'gt', -1)->where(['status' => ['neq', 4]])->find() ? 1 : 0;
            }

            $rebate_log[$rk]['status_desc'] = rebate_status($rv['status']);
            $rebate_log[$rk]['order_goods'] = $order_goods;
            $rebate_log[$rk]['imgTx'] = M('Users')->where('user_id', $rv['buy_user_id'])->getField('head_pic');
            $rebate_log[$rk]['showContent'] = false;
        }

        $return['page'] = $page->show(); // 赋值分页出
        $return['rebate_log'] = $rebate_log;
        $return['status'] = $status;
        $return['result'] = $result;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    // 获取他人的分销订单列表
    public function userIncome()
    {
        $user_id = I('id/d', 0);
        if (!$user_id) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        $user = M('users')
            ->field('user_id,is_distribut,distribut_level,nickname,mobile,head_pic,first_leader,second_leader,third_leader')
            ->where('user_id', $user_id)
            ->find();
        if (!$user) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }

        $result = Db::query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where buy_user_id = {$user_id} and user_id = {$this->user_id} and status = 5");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;
        $status = I('get.status', -2);

        if ('0' == $status || $status > 0) {
            $condition['status'] = $status;
        }

        $condition['buy_user_id'] = $user_id;
        $condition['user_id'] = $this->user_id;
        $count = M('rebate_log')->where($condition)->count();
        $page = new Page($count, 10);
        $rebate_log = M('rebate_log')->where($condition)->limit("{$page->firstRow},{$page->listRows}")->order('id desc')->select();
        $OrderCommonLogc = new \app\common\logic\OrderLogic();
        foreach ($rebate_log as $rk => $rv) {
            $order_goods = M('OrderGoods')
                ->alias('a')
                ->field('a.*,o.add_time')
                ->join('__ORDER__ o', 'o.order_id = a.order_id', 'LEFT')
                ->where('a.order_id', $rv['order_id'])
                ->select();

            foreach ($order_goods as $ok => $ov) {
                $goodsInfo = M('Goods')->field('exchange_integral,shop_price,shop_price - exchange_integral as integral_price, original_img as imgSrc')->where('goods_id', $ov['goods_id'])->find();
                $order_goods[$ok]['add_time'] = date('Y-m-d H:i:s', $ov['add_time']);
                $order_goods[$ok]['imgSrc'] = $goodsInfo['imgSrc'];
                if ($ov['use_integral'] > 0) {
                    $order_goods[$ok]['price'] = $goodsInfo['integral_price'];
                    $order_goods[$ok]['integral'] = $ov['use_integral'];
                } else {
                    $order_goods[$ok]['price'] = $goodsInfo['shop_price'];
                    $order_goods[$ok]['integral'] = 0;
                }
                //$order_goods[$ok]['get_price'] = round(($ov['final_price'] * $ov['goods_num']) * $ov['commission'] / 100 * $distribut_rate, 2);

                $order_goods[$ok]['get_price'] = $OrderCommonLogc->getRongMoney(($ov['final_price'] * $ov['goods_num']) * $ov['commission'] / 100, $rv['level'], $ov['add_time'], $ov['goods_id']);

                $order_goods[$ok]['is_freeze'] = M('return_goods')->where('rec_id', $ov['rec_id'])->where('status', 'gt', -1)->where(['status' => ['neq', 4]])->find() ? 1 : 0;
            }

            $rebate_log[$rk]['status_desc'] = rebate_status($rv['status']);
            $rebate_log[$rk]['order_goods'] = $order_goods;
            $rebate_log[$rk]['imgTx'] = M('Users')->where('user_id', $rv['buy_user_id'])->getField('head_pic');
            $rebate_log[$rk]['showContent'] = false;
        }

        $return['rebate_log'] = $rebate_log;
        $return['user'] = $user;
        $return['status'] = $status;
        $return['result'] = $result;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 订单商品评价列表.
     */
    public function comment_list()
    {
        $order_id = I('order_id/d');
        $rec_id = I('rec_id/d');
        if (empty($order_id) || empty($rec_id)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        //查找订单
        $order_comment_where['order_id'] = $order_id;
        $order_info = M('order')->field('order_sn,order_id,add_time,prom_type')->where($order_comment_where)->find();
        //查找评价商品
        $order_comment_where['rec_id'] = $rec_id;
        $order_goods = M('order_goods')
            ->field('rec_id,goods_id,is_comment,goods_name,goods_num,goods_price,spec_key_name')
            ->where($order_comment_where)
            ->find();
        $order_info = array_merge($order_info, $order_goods);
        $return['order_info'] = $order_info;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /*
    *添加评论
    */
    public function add_comment()
    {
        $user_info = session('user');
        $comment_img = serialize(I('comment_img/a')); // 上传的图片文件
        $add['rec_id'] = I('rec_id/d');
        $add['goods_id'] = I('goods_id/d');
        $add['email'] = $user_info['email'];
        $hide_username = I('hide_username');
        if (empty($hide_username)) {
            $add['username'] = $user_info['nickname'];
        }
        $add['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
        $add['order_id'] = I('order_id/d');
        $add['service_rank'] = I('service_rank');
        $add['deliver_rank'] = I('deliver_rank');
        $add['goods_rank'] = I('goods_rank');
        $add['is_show'] = 1; //默认显示
        $add['content'] = I('content');
        $add['img'] = $comment_img;
        $add['add_time'] = time();
        $add['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $add['user_id'] = $this->user_id;
        $logic = new UsersLogic();
        //添加评论
        $row = $logic->add_comment($add);

        return json($row);
    }

    /**
     * 获取提交订单前的信息
     * @return \think\response\Json
     * @throws \app\common\util\TpshopException
     */
    public function orderBeforeInfo()
    {
        // 用户默认地址
        $userAddress = get_user_address_list_new($this->user_id, true);
        if (!empty($userAddress)) {
            unset($userAddress[0]['zipcode']);
            unset($userAddress[0]['is_pickup']);
            unset($userAddress[0]['tabs']);
        }

        $goodsId = I('goods_id', '');           // 商品ID
        $itemId = I('item_id', '');             // 商品规格ID
        $goodsNum = I('goods_num', '');         // 商品数量
        $payType = input('pay_type', 1);        // 结算类型
        $couponId = I('coupon_id', '');         // 优惠券ID
        $cartIds = I('cart_ids', '');           // 购物车ID组合

        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        if (!empty($goodsId) && empty($cartIds)) {
            /*
             * 单个商品下单
             */
            $cartLogic->setGoodsModel($goodsId);
            $cartLogic->setSpecGoodsPriceModel($itemId);
            $cartLogic->setGoodsBuyNum($goodsNum);
            $cartLogic->setType($payType);
            $cartLogic->setCartType(0);
            try {
                $buyGoods = $cartLogic->buyNow();
            } catch (TpshopException $t) {
                $error = $t->getErrorArr();
                return json(['status' => 0, 'msg' => $error['msg']]);
            }
            $cartList['cartList'] = [$buyGoods];
        } elseif (empty($goodsId) && !empty($cartIds)) {
            /*
             * 购物车下单
             */
            $cartIds = explode(',', $cartIds);
            foreach ($cartIds as $k => $v) {
                $data = [];
                $data['id'] = $v;
                $data['selected'] = 1;
                $cartIds[$k] = $data;
            }
            $result = $cartLogic->AsyncUpdateCarts($cartIds);
            if (1 != $result['status']) {
                return json(['status' => 0, 'msg' => $result['msg'], 'result' => null]);
            }
            if (0 == $cartLogic->getUserCartOrderCount()) {
                return json(['status' => 0, 'msg' => '你的购物车没有选中商品', 'result' => null]);
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
        } else {
            /*
             * 单个商品 + 购物车 下单
             */
            $cartIds = explode(',', $cartIds);
            $goodsInfo = $cartLogic->getCartGoods($cartIds, 'c.goods_id, sgp.item_id, c.goods_num, c.type pay_type');
            $goodsInfo[] = [
                'goods_id' => $goodsId,
                'item_id' => $itemId,
                'goods_num' => $goodsNum,
                'pay_type' => $payType
            ];
            $buyGoods = [];
            foreach ($goodsInfo as $goods) {
                $cartLogic->setGoodsModel($goods['goods_id']);
                $cartLogic->setSpecGoodsPriceModel($goods['item_id']);
                $cartLogic->setGoodsBuyNum($goods['goods_num']);
                $cartLogic->setType($goods['pay_type']);
                $cartLogic->setCartType(0);
                try {
                    $buyGoods[] = $cartLogic->buyNow();
                } catch (TpshopException $t) {
                    $error = $t->getErrorArr();
                    return json(['status' => 0, 'msg' => $error['msg']]);
                }
            }
            $cartList['cartList'] = $buyGoods;
        }
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据 商品总额/节约金额/商品总共数量/商品使用积分
        $cartList = array_merge($cartList, $cartPriceInfo);

        $cartGoodsList = get_arr_column($cartList['cartList'], 'goods');
        $cartGoodsId = get_arr_column($cartGoodsList, 'goods_id');
        $cartGoodsCatId = get_arr_column($cartGoodsList, 'cat_id');
        $couponLogic = new CouponLogic();
        // 用户可用的优惠券列表
        $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId);
//        $userCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        $couponList = [];
        foreach ($userCouponList as $k => $coupon) {
            $couponList[$k] = [
                'coupon_id' => $coupon['coupon']['id'],
                'name' => $coupon['coupon']['name'],
                'money' => $coupon['coupon']['money'],
                'use_start_time' => date('Y-m-d', $coupon['coupon']['use_start_time']),
                'use_end_time' => date('Y-m-d', $coupon['coupon']['use_end_time']),
                'is_selected' => 0
            ];
            if ($coupon['coupon']['id'] == $couponId) {
                $couponList[$k]['is_selected'] = 1;
            }
        }
        $couponSelected = get_arr_column($couponList, 'is_selected');
        if (!in_array(1, $couponSelected)) {
            $couponId = $couponList[0]['coupon_id'];    // 默认选中第一张
        }
        // 用户可用的兑换券列表
        $userExchangeList = $couponLogic->getUserAbleCouponListRe($this->user_id, $cartGoodsId, $cartGoodsCatId);
//        $userExchangeList = $cartLogic->getCouponCartList($cartList, $userExchangeList);
        $exchangeList = [];
        foreach ($userExchangeList as $coupon) {
            $exchangeList[] = [
                'exchange_id' => $coupon['coupon']['id'],
                'name' => $coupon['coupon']['name'],
                'money' => $coupon['coupon']['money'],
                'use_start_time' => date('Y-m-d', $coupon['coupon']['use_start_time']),
                'use_end_time' => date('Y-m-d', $coupon['coupon']['use_end_time']),
                'is_selected' => $coupon['coupon']['id'] == $couponId ? 1 : 0
            ];
        }
        $payLogic = new Pay();
        $payLogic->setUserId($this->user_id);   // 设置支付用户ID
        // 计算购物车价格
        $payLogic->payCart($cartList['cartList']);
        // 检测支付商品购买限制
        $payLogic->check();
        // 参与活动促销 加价购活动
//        $payLogic->activityPayBefore();
        $payLogic->goodsPromotion();

        // 配送物流
        if (empty($userAddress)) {
            $payLogic->delivery(0);
        } else {
            $payLogic->delivery($userAddress[0]['district']);
        }
        $pay_points = $payLogic->getUsePoint();     // 使用积分
        if ($this->user['pay_points'] < $pay_points) {
            return json(['status' => 0, 'msg' => '用户消费积分只有' . $this->user['pay_points']]);
        }

        $payLogic->usePayPoints($pay_points);
        $give_integral = 0;             // 赠送积分
        $weight = 0;                    // 产品重量
        $order_prom_fee = 0;            // 订单优惠促销总价
        foreach ($cartList['cartList'] as $v) {
            $goodsInfo = M('Goods')->field('give_integral, weight')->where('goods_id', $v['goods_id'])->find();
            $give_integral += $goodsInfo['give_integral'];
            $weight += $goodsInfo['weight'];
            if (isset($v['is_order_prom']) && $v['is_order_prom'] == 1) {
                $order_prom_fee += ($v['use_integral'] + $v['member_goods_price']) * $v['goods_num'];
            }
        }

        $payLogic->activity();      // 满单赠品
        $payLogic->activity2New($order_prom_fee);     // 指定商品赠品 / 订单优惠赠品
        $payLogic->activity3();     // 订单优惠促销

        // 使用优惠券
        if (isset($couponId) && $couponId > 0) {
            $payLogic->useCouponById($couponId, $payLogic->getPayList());
        }
        // 支付数据
        $payReturn = $payLogic->toArray();
        // 商品列表 赠品列表
        $payList = collection($payLogic->getPayList())->toArray();
        $goodsList = [];
        $giftList = $payLogic->getPromGiftList();
        foreach ($payList as $k => $list) {
            // 商品列表
            $goods = $list['goods'];
            $goodsList[$k] = [
                'goods_id' => $goods['goods_id'],
                'goods_sn' => $goods['goods_sn'],
                'goods_name' => $goods['goods_name'],
                'goods_remark' => $goods['goods_remark'],
                'spec_key_name' => $goods['spec_key_name'],
                'original_img' => $goods['original_img'],
                'goods_num' => $list['goods_num'],
                'shop_price' => $goods['shop_price'],
                'exchange_integral' => $list['use_integral'],
            ];
            // 处理显示金额
            if ($list['use_integral'] != 0) {
                $goodsList[$k]['exchange_price'] = bcdiv(bcsub(bcmul($list['goods']['shop_price'], 100), bcmul($list['use_integral'], 100)), 100, 2);
            } else {
                $goodsList[$k]['exchange_price'] = $list['goods']['shop_price'];
            }
            if (isset($list['gift_goods'])) {
                $goodsList[$k]['gift_goods'] = $list['gift_goods'];
            }
        }
        // 组合数据
        $return = [
            // 用户地址
            'user_address' => $userAddress,
            // 提货
            'delivery' => [
                'way' => [
                    ['id' => 1, 'name' => '邮寄']
                ],
                'self_pick' => []
            ],
            // 商品列表 赠品列表
            'order_goods' => [
                'type' => 1,
                'type_value' => '圃美多乐活',
                'goods_list' => $goodsList,
                'gift_list' => $giftList
            ],
            // 优惠券 兑换券
            'coupon_list' => $couponList,
            'exchange_list' => $exchangeList,
            // 支付
            'user_electronic' => $this->user['user_electronic'],
            'weight' => $weight,
            'goods_fee' => $payReturn['goods_price'],
            'shipping_price' => $payReturn['shipping_price'],
            'coupon_price' => $payReturn['coupon_price'],
            'prom_price' => $payReturn['order_prom_amount'],
            'electronic_price' => $payReturn['user_electronic'],
            'pay_points' => $payReturn['pay_points'],
            'order_amount' => $payReturn['order_amount'],
            'spare_pay_points' => bcsub($this->user['pay_points'], $payReturn['pay_points'], 2),
            'give_integral' => $give_integral,
            'free_shipping_price' => tpCache('shopping.freight_free') <= $payReturn['order_amount'] ? 0 : bcsub(tpCache('shopping.freight_free'), $payReturn['order_amount'], 2)
        ];
        return json(['status' => 1, 'result' => $return]);
    }
}
