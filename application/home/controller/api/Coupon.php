<?php

namespace app\home\controller\api;


use app\common\logic\UsersLogic;
use think\Db;

class Coupon extends Base
{
    /**
     * 领券中心列表
     * @return \think\response\Json
     */
    function couponList()
    {
        // 优惠券信息
        $where = [
            'send_start_time' => array('elt', NOW_TIME),
            'send_end_time' => array('egt', NOW_TIME),
            'status' => 1
        ];
        $field = 'id, name, use_type, type_value, condition, money, createnum create_num, send_num, use_start_time, use_end_time';
        $couponData = M('coupon')->field($field)->where($where)->order('id desc')->select();
        $couponIds = [];
        foreach ($couponData as $coupon) {
            $couponIds[] = $coupon['id'];
        }
        // 优惠券商品
        $couponGoods = Db::name('goods_coupon gc')->join('goods g', 'g.goods_id = gc.goods_id')->where(['gc.coupon_id' => ['in', $couponIds]])->field('gc.coupon_id, g.goods_id, g.goods_name, g.original_img')->select();
        // 优惠券分类
        $couponCate = Db::name('goods_coupon gc1')->join('goods_category gc2', 'gc1.goods_category_id = gc2.id')->where(['gc1.coupon_id' => ['in', $couponIds]])->getField('gc1.coupon_id, gc2.id cate_id, gc2.name cate_name', true);
        // 组合数据
        $couponList = [];
        foreach ($couponData as $k => $coupon) {
            // 检查是否已经领取
            $isReceived = M('coupon_list')->where(array('cid' => $coupon['id'], 'uid' => $this->user_id))->field('id')->find();
            switch ($coupon['use_type']) {
                case 1:
                    // 指定商品可用
                    foreach ($couponGoods as $goods) {
                        if ($coupon['id'] == $goods['coupon_id']) {
                            $couponList[] = [
                                'coupon_id' => $coupon['id'],
                                'type_value' => $coupon['type_value'],
                                'use_type' => $coupon['use_type'],
                                'name' => $coupon['name'],
                                'condition' => $coupon['condition'],
                                'money' => $coupon['money'],
                                'use_start_time' => $coupon['use_start_time'],
                                'use_end_time' => $coupon['use_end_time'],
                                'create_num' => $coupon['create_num'],
                                'is_received' => $isReceived ? 1 : 0,
                                'percent' => $coupon['create_num'] > 0 ? bcdiv($coupon['send_num'], $coupon['create_num'], 2) : 0,
                                'cate_id' => '',
                                'cate_name' => '',
                                'goods_id' => $goods['goods_id'],
                                'goods_name' => $goods['goods_name'],
                                'original_img' => $goods['original_img']
                            ];
                        }
                    }
                    break;
                case 0:
                    // 全店通用
                case 2:
                    // 指定分类可用
                case 4:
                    // 指定商品折扣券
                case 5:
                    // 兑换商品券
                    $couponList[$k] = [
                        'coupon_id' => $coupon['id'],
                        'type_value' => $coupon['type_value'],
                        'use_type' => $coupon['use_type'],
                        'name' => $coupon['name'],
                        'condition' => $coupon['condition'],
                        'money' => $coupon['money'],
                        'use_start_time' => $coupon['use_start_time'],
                        'use_end_time' => $coupon['use_end_time'],
                        'create_num' => $coupon['create_num'],
                        'is_received' => $isReceived ? 1 : 0,
                        'percent' => $coupon['create_num'] > 0 ? bcdiv($coupon['send_num'], $coupon['create_num'], 2) : 0,
                        'cate_id' => isset($couponCate[$coupon['id']]) ? $couponCate[$coupon['id']]['cate_id'] : '',
                        'cate_name' => isset($couponCate[$coupon['id']]) ? $couponCate[$coupon['id']]['cate_name'] : '',
                        'goods_id' => '',
                        'goods_name' => '',
                        'original_img' => ''
                    ];
                    break;
            }
        }
        return json(['status' => 1, 'result' => array_values($couponList)]);
    }

    /**
     * 用户优惠券列表
     * @return \think\response\Json
     */
    public function userCoupon()
    {
        $type = I('type', 0);
        // 用户优惠券信息
        $couponData = (new UsersLogic())->get_coupon($this->user_id, $type)['result'];
        $couponIds = [];
        foreach ($couponData as $coupon) {
            $couponIds[] = $coupon['cid'];
        }
        // 优惠券商品
        $couponGoods = Db::name('goods_coupon gc')->join('goods g', 'g.goods_id = gc.goods_id')->where(['gc.coupon_id' => ['in', $couponIds]])->field('gc.coupon_id, g.goods_id, g.goods_name, g.original_img')->select();
        // 优惠券分类
        $couponCate = Db::name('goods_coupon gc1')->join('goods_category gc2', 'gc1.goods_category_id = gc2.id')->where(['gc1.coupon_id' => ['in', $couponIds]])->getField('gc1.coupon_id, gc2.id cate_id, gc2.name cate_name', true);
        // 组合数据
        $couponList = [];
        foreach ($couponData as $coupon) {
            switch ($coupon['use_type']) {
                case 0:
                    // 全店通用
                case 2:
                    // 指定分类可用
                    $couponList[] = [
                        'id' => $coupon['id'],
                        'coupon_id' => $coupon['cid'],
                        'type_value' => $coupon['type_value'],
                        'use_type' => $coupon['use_type'],
                        'name' => $coupon['name'],
                        'condition' => $coupon['condition'],
                        'money' => $coupon['money'],
                        'use_start_time' => $coupon['use_start_time'],
                        'use_end_time' => $coupon['use_end_time'],
                        'cate_id' => isset($couponCate[$coupon['cid']]) ? $couponCate[$coupon['cid']]['cate_id'] : '',
                        'cate_name' => isset($couponCate[$coupon['cid']]) ? $couponCate[$coupon['cid']]['cate_name'] : '',
                        'goods_id' => '',
                        'goods_name' => '',
                        'original_img' => '',
                        'goods_list' => []
                    ];
                    break;
            }
        }
        foreach ($couponData as $k => $coupon) {
            switch ($coupon['use_type']) {
                case 1:
                    // 指定商品可用
                    foreach ($couponGoods as $goods) {
                        if ($coupon['cid'] == $goods['coupon_id']) {
                            $couponList[] = [
                                'id' => $coupon['id'],
                                'coupon_id' => $coupon['cid'],
                                'type_value' => $coupon['type_value'],
                                'use_type' => $coupon['use_type'],
                                'name' => $coupon['name'],
                                'condition' => $coupon['condition'],
                                'money' => $coupon['money'],
                                'use_start_time' => $coupon['use_start_time'],
                                'use_end_time' => $coupon['use_end_time'],
                                'cate_id' => '',
                                'cate_name' => '',
                                'goods_id' => $goods['goods_id'],
                                'goods_name' => $goods['goods_name'],
                                'original_img' => $goods['original_img'],
                                'goods_list' => []
                            ];
                        }
                    }
                    break;
                case 4:
                    // 指定商品折扣券
                case 5:
                    // 兑换商品券
                    $couponList[$k . '_5'] = [
                        'id' => $coupon['id'],
                        'coupon_id' => $coupon['cid'],
                        'type_value' => $coupon['type_value'],
                        'use_type' => $coupon['use_type'],
                        'name' => $coupon['name'],
                        'condition' => $coupon['condition'],
                        'money' => $coupon['money'],
                        'use_start_time' => $coupon['use_start_time'],
                        'use_end_time' => $coupon['use_end_time'],
                        'cate_id' => isset($couponCate[$coupon['cid']]) ? $couponCate[$coupon['cid']]['cate_id'] : '',
                        'cate_name' => isset($couponCate[$coupon['cid']]) ? $couponCate[$coupon['cid']]['cate_name'] : '',
                        'goods_id' => '',
                        'goods_name' => '',
                        'original_img' => '',
                        'goods_list' => []
                    ];
                    foreach ($couponGoods as $goods) {
                        if ($coupon['cid'] == $goods['coupon_id']) {
                            $couponList[$k . '_5']['goods_list'][] = [
                                'goods_id' => $goods['coupon_id'],
                                'original_img' => $goods['original_img']
                            ];
                        }
                    }
                    break;
            }
        }
        return json(['status' => 1, 'result' => array_values($couponList)]);
    }

    /**
     * 领取优惠券
     * @return \think\response\Json
     */
    public function couponReceive()
    {
        $couponId = I('coupon_id', 0);
        if (!$couponId) {
            return json(['status' => 0, 'msg' => '操作有误']);
        }
        $where = array(
            'send_start_time' => array('elt', NOW_TIME),
            'send_end_time' => array('egt', NOW_TIME),
            'id' => $couponId
        );
        $coupon = M('coupon')->where($where)->find();
        if (!$coupon) {
            return json(['status' => 0, 'msg' => '该券不可领取']);
        }
        if ($coupon['createnum'] > 0) {
            if ($coupon['send_num'] >= $coupon['createnum']) {
                return json(['status' => 0, 'msg' => '该券已领完']);
            }
        }
        // 检查用户是否已经领取
        $is_has_coupon = M('coupon_list')->where(array('cid' => $couponId, 'uid' => $this->user_id))->field('id')->find();
        if ($is_has_coupon) {
            return json(['status' => 0, 'msg' => '您已经领取过了']);
        }
        $add = [
            'cid' => $coupon['id'],
            'type' => $coupon['type'],
            'uid' => $this->user_id,
            'send_time' => time(),
        ];
        Db::startTrans();
        do {
            $code = get_rand_str(8, 0, 1); // 获取随机8位字符串
            $check_exist = M('coupon_list')->where(['code' => $code])->find();
        } while ($check_exist);

        $add['code'] = $code;
        $res1 = M('coupon_list')->add($add);
        $res2 = M('coupon')->where(array('id' => $couponId))->setInc('send_num', 1);
        if ($res1 && $res2) {
            Db::commit();
            return json(['status' => 1, 'msg' => '领取成功']);
        } else {
            Db::rollback();
            return json(['status' => 0, 'msg' => '领取失败']);
        }
    }
}