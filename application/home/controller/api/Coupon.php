<?php

namespace app\home\controller\api;


use app\common\logic\CouponLogic;
use app\common\logic\UsersLogic;
use think\Db;

class Coupon extends Base
{
    public function __construct()
    {
        parent::__construct();
        if ($this->passAuth) {
            die(json_encode(['status' => -999, 'msg' => '请先登录']));
        }
    }

    /**
     * 领券中心列表
     * @return \think\response\Json
     */
    function couponList()
    {
        if ($this->passAuth) {
            return json(['status' => -999, 'msg' => '请先登录']);
        }
        // 优惠券信息
        $where = [
            'send_start_time' => array('elt', NOW_TIME),
            'send_end_time' => array('egt', NOW_TIME),
            'use_end_time' => array('egt', NOW_TIME),
            'status' => 1,
            'nature' => 1,
            'type_value' => ['not in', [4, 5]]  // 新用户、新VIP，通过其他方式获取
        ];
        $couponData = M('coupon')->where($where)->order('id desc')->select();
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
        $couponLogic = new CouponLogic();
        foreach ($couponData as $k => $coupon) {
            // 检查是否已经领取
            $isReceived = M('coupon_list')->where(array('cid' => $coupon['id'], 'uid' => $this->user_id))->field('id')->find();
            // 使用对象
            if ($coupon['type_value'] == 0) {
                $target = '全员通用';
            } else {
                $target = '';
                $typeValue = explode(',', $coupon['type_value']);
                if (in_array('1', $typeValue)) $target .= '注册会员、';
                if (in_array('2', $typeValue)) $target .= 'VIP、';
                if (in_array('3', $typeValue)) $target .= 'SVIP、';
                if (in_array('4', $typeValue)) $target .= '新注册会员、';
                if (in_array('5', $typeValue)) continue;
                $target = rtrim($target, '、');
                $target .= '可用';
            }
            if ($coupon['use_type'] == 1) {
                // 指定商品可用
                foreach ($couponGoods as $goods) {
                    if ($coupon['id'] == $goods['coupon_id']) {
                        $couponList[] = [
                            'coupon_id' => $coupon['id'],
                            'type_value' => $coupon['type_value'],
                            'use_type' => $coupon['use_type'],
                            'name' => $coupon['name'],
                            'condition' => floatval($coupon['condition']) . '',
                            'money' => floatval($coupon['money']) . '',
                            'use_start_time' => date('Y.m.d', $coupon['use_start_time']),
                            'use_end_time' => date('Y.m.d', $coupon['use_end_time']),
                            'create_num' => $coupon['createnum'],
                            'is_received' => $isReceived ? 1 : 0,
                            'percent' => $coupon['createnum'] > 0 ? bcdiv($coupon['send_num'], $coupon['createnum'], 2) : 0,
                            'cate_id' => '',
                            'cate_name' => '',
                            'goods_id' => $goods['goods_id'],
                            'goods_name' => $goods['goods_name'],
                            'original_img' => $goods['original_img'],
                            'original_img_new' => getFullPath($goods['original_img']),
                            'goods_list' => [],
                            'title' => '￥' . floatval($coupon['money']) . '仅限' . $goods['goods_name'] . '可用',
                            'desc' => '￥' . floatval($coupon['money']) . '仅限' . $goods['goods_name'] . '可用',
                            'target' => $target,
                            'content' => $coupon['content'] ?? ''
                        ];
                    }
                }
            } else {
                $couponList[$k . '-1'] = [
                    'coupon_id' => $coupon['id'],
                    'type_value' => $coupon['type_value'],
                    'use_type' => $coupon['use_type'],
                    'name' => $coupon['name'],
                    'condition' => floatval($coupon['condition']) . '',
                    'money' => floatval($coupon['money']) . '',
                    'use_start_time' => date('Y.m.d', $coupon['use_start_time']),
                    'use_end_time' => date('Y.m.d', $coupon['use_end_time']),
                    'create_num' => $coupon['createnum'],
                    'is_received' => $isReceived ? 1 : 0,
                    'percent' => $coupon['createnum'] > 0 ? bcdiv($coupon['send_num'], $coupon['createnum'], 2) : 0,
                    'cate_id' => isset($couponCate[$coupon['id']]) ? $couponCate[$coupon['id']]['cate_id'] : '',
                    'cate_name' => isset($couponCate[$coupon['id']]) ? $couponCate[$coupon['id']]['cate_name'] : '',
                    'goods_id' => '',
                    'goods_name' => '',
                    'original_img' => '',
                    'original_img_new' => '',
                    'goods_list' => [],
                    'title' => '',
                    'desc' => '',
                    'target' => '',
                    'content' => $coupon['content'] ?? ''
                ];
                // 优惠券展示描述
                $res = $couponLogic->couponTitleDesc($coupon, $couponList[$k . '-1']['goods_name'], $couponList[$k . '-1']['cate_name']);
                if (empty($res)) {
                    continue;
                }
                $couponList[$k . '-1']['title'] = $res['title'];
                $couponList[$k . '-1']['desc'] = $res['desc'];
                $couponList[$k . '-1']['target'] = $target;
                // 优惠券下的商品
                switch ($coupon['use_type']) {
                    case 4:
                        // 指定商品折扣券
                        foreach ($couponGoods as $goods) {
                            if ($coupon['id'] == $goods['coupon_id']) {
                                $couponList[$k . '-1']['goods_list'][] = [
                                    'goods_id' => $goods['goods_id'],
                                    'original_img' => $goods['original_img'],
                                    'original_img_new' => getFullPath($goods['original_img']),
                                ];
                            }
                        }
                        break;
                    case 5:
                        // 兑换商品券
                        foreach ($couponGoods as $goods) {
                            if ($coupon['id'] == $goods['coupon_id']) {
                                $couponList[$k . '-1']['goods_list'][] = [
                                    'goods_id' => $goods['goods_id'],
                                    'original_img' => $goods['original_img'],
                                    'original_img_new' => getFullPath($goods['original_img']),
                                ];
                            }
                        }
                        break;
                    default:
                        continue 2;
                        break;
                }
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
        $couponLogic = new CouponLogic();
        foreach ($couponData as $k => $coupon) {
            if ($coupon['use_type'] == 1) {
                // 指定商品可用
                foreach ($couponGoods as $goods) {
                    if ($coupon['cid'] == $goods['coupon_id']) {
                        $couponList[] = [
                            'id' => $coupon['id'],
                            'coupon_id' => $coupon['cid'],
                            'type_value' => $coupon['type_value'],
                            'use_type' => $coupon['use_type'],
                            'name' => $coupon['name'],
                            'condition' => floatval($coupon['condition']) . '',
                            'money' => floatval($coupon['money']) . '',
                            'use_start_time' => date('Y.m.d', $coupon['use_start_time']),
                            'use_end_time' => date('Y.m.d', $coupon['use_end_time']),
                            'cate_id' => '',
                            'cate_name' => '',
                            'goods_id' => $goods['goods_id'],
                            'goods_name' => $goods['goods_name'],
                            'original_img' => $goods['original_img'],
                            'original_img_new' => getFullPath($goods['original_img']),
                            'goods_list' => [],
                            'title' => '￥' . floatval($coupon['money']) . '仅限' . $goods['goods_name'] . '可用',
                            'desc' => '￥' . floatval($coupon['money']) . '仅限' . $goods['goods_name'] . '可用',
                            'content' => $coupon['content'] ?? ''
                        ];
                    }
                }
            } else {
                $couponList[$k . '-1'] = [
                    'id' => $coupon['id'],
                    'coupon_id' => $coupon['cid'],
                    'type_value' => $coupon['type_value'],
                    'use_type' => $coupon['use_type'],
                    'name' => $coupon['name'],
                    'condition' => floatval($coupon['condition']) . '',
                    'money' => floatval($coupon['money']) . '',
                    'use_start_time' => date('Y.m.d', $coupon['use_start_time']),
                    'use_end_time' => date('Y.m.d', $coupon['use_end_time']),
                    'cate_id' => isset($couponCate[$coupon['cid']]) ? $couponCate[$coupon['cid']]['cate_id'] : '',
                    'cate_name' => isset($couponCate[$coupon['cid']]) ? $couponCate[$coupon['cid']]['cate_name'] : '',
                    'goods_id' => '',
                    'goods_name' => '',
                    'original_img' => '',
                    'original_img_new' => '',
                    'goods_list' => [],
                    'title' => '',
                    'desc' => '',
                    'content' => $coupon['content'] ?? ''
                ];
                // 优惠券展示描述
                $res = $couponLogic->couponTitleDesc($coupon, $couponList[$k . '-1']['goods_name'], $couponList[$k . '-1']['cate_name']);
                if (empty($res)) {
                    continue;
                }
                $couponList[$k . '-1']['title'] = $res['title'];
                $couponList[$k . '-1']['desc'] = $res['desc'];
                // 优惠券下的商品
                switch ($coupon['use_type']) {
                    case 4:
                        // 指定商品折扣券
                        foreach ($couponGoods as $goods) {
                            if ($coupon['id'] == $goods['coupon_id']) {
                                $couponList[$k . '-1']['goods_list'][] = [
                                    'goods_id' => $goods['goods_id'],
                                    'original_img' => $goods['original_img'],
                                    'original_img_new' => getFullPath($goods['original_img']),
                                ];
                            }
                        }
                        break;
                    case 5:
                        // 兑换商品券
                        foreach ($couponGoods as $goods) {
                            if ($coupon['id'] == $goods['coupon_id']) {
                                $couponList[$k . '-1']['goods_list'][] = [
                                    'goods_id' => $goods['goods_id'],
                                    'original_img' => $goods['original_img'],
                                    'original_img_new' => getFullPath($goods['original_img']),
                                ];
                            }
                        }
                        break;
                    default:
                        continue 2;
                        break;
                }
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
        if ($coupon['nature'] == 1) {
            // 检查用户是否已经领取
            $is_has_coupon = M('coupon_list')->where(array('cid' => $couponId, 'uid' => $this->user_id))->field('id')->find();
            if ($is_has_coupon) {
                return json(['status' => 0, 'msg' => '您已经领取过了']);
            }
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