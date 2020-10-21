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

use app\common\model\Coupon;
use app\common\model\CouponList;
use think\Db;
use think\Log;
use think\Model;

class CouponLogic extends Model
{
    private $order;

    /**
     * 获取优惠券展示描述
     * @param $coupon
     * @param $goodsName
     * @param $cateName
     * @return array
     */
    public function couponTitleDesc($coupon, $goodsName = '', $cateName = '')
    {
        $useTypeDesc = '';
        $title = '';
        $desc = '';
        $goodsName = !empty($goodsName) ? $goodsName : !empty($coupon['goods_name']) ? $coupon['goods_name'] : '';
        $cateName = !empty($cateName) ? $cateName : !empty($coupon['cat_name']) ? $coupon['cat_name'] : '';
        switch ($coupon['use_type']) {
            case 0:
                // 全店通用
                $useTypeDesc = '全店通用';
                $title = $coupon['name'];
                $desc = '全场商品满' . floatval($coupon['condition']) . '减' . floatval($coupon['money']);
                break;
            case 1:
                // 指定商品
                $useTypeDesc = '指定商品';
                $title = $coupon['name'];
                $desc = '仅限' . $goodsName . '可用';
                break;
            case 2:
                // 指定分类可用
                $useTypeDesc = '指定分类';
                $title = $coupon['name'];
                $desc = $cateName . '满' . floatval($coupon['condition']) . '可用';
                break;
            case 4:
                // 指定商品折扣券
                $useTypeDesc = '指定商品';
                $title = $coupon['name'];
                $desc = '指定商品满' . floatval($coupon['condition']) . '享受' . floatval($coupon['money']) . '折';
                break;
            case 5:
                // 兑换商品券
                $useTypeDesc = '兑换商品';
                $title = $coupon['name'];
                $desc = '购买任意商品可用';
                break;
        }
        if (!$title || !$desc) {
            return [];
        } else {
            return ['use_type_desc' => $useTypeDesc, 'title' => $title, 'desc' => $desc];
        }
    }

    /**
     * 获取优惠券展示描述
     * @param $coupon
     * @param $goodsName
     * @param $cateName
     * @return array
     */
    public function couponTitleDesc_v2($coupon, $goodsName = '', $cateName = '')
    {
        $title = '';
        $desc = '';
        $goodsName = !empty($goodsName) ? $goodsName : $coupon['goods_name'];
        $cateName = !empty($cateName) ? $cateName : $coupon['cat_name'];
        switch ($coupon['use_type']) {
            case 0:
                // 全店通用
                $title = '全场商品满' . floatval($coupon['condition']) . '可用';
                $desc = '全场商品满' . floatval($coupon['condition']) . '减' . floatval($coupon['money']);
                break;
            case 1:
                // 指定商品
                $title = '￥' . floatval($coupon['money']) . '仅限' . $goodsName . '可用';
                $desc = '仅限' . $goodsName . '可用';
                break;
            case 2:
                // 指定分类可用
                $title = $cateName . '满' . floatval($coupon['condition']) . '可用';
                $desc = $cateName . '满' . floatval($coupon['condition']) . '可用';
                break;
            case 4:
                // 指定商品折扣券
                $title = '指定商品满' . floatval($coupon['condition']) . '享受' . floatval($coupon['money']) . '折';
                $desc = '指定商品满' . floatval($coupon['condition']) . '享受' . floatval($coupon['money']) . '折';
                break;
            case 5:
                // 兑换商品券
                $title = $coupon['name'];
                $desc = '购买任意商品可用';
                break;
        }
        if (!$title || !$desc) {
            return [];
        } else {
            return ['title' => $title, 'desc' => $desc];
        }
    }

    /**
     * 设置order模型.
     *
     * @param $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * 获取发放有效的优惠券金额.
     *
     * @param type $coupon_id
     * @param type $goods_id
     * @param type $store_id
     * @param type $cat_id
     *
     * @return bool
     */
    public function getSendValidCouponMoney($coupon_id, $goods_id, $cat_id)
    {
        $curtime = time();
        $coupon = M('coupon')->where('id', $coupon_id)->find();
        $goods_coupon = M('goods_coupon')->where('coupon_id', $coupon_id)->where(function ($query) use ($goods_id, $cat_id) {
            $query->where('goods_id', $goods_id)->whereOr('goods_category_id', $cat_id);
        })->select();

        if ($goods_coupon && $coupon
            && $coupon['send_start_time'] <= $curtime
            && $coupon['send_end_time'] > $curtime
            && $coupon['createnum'] > $coupon['send_enum']) {
            return $coupon['money'];
        }

        return false;
    }

    public function sendNewUser($user_id)
    {
        $coupon_list = M('Coupon')->where('type_value', 'LIKE', '%4%')->select();
        if (!empty($coupon_list)) {
            $couponIds = [];
            foreach ($coupon_list as $v) {
                if ($v['type_value']) {
                    $type_value = explode(',', $v['type_value']);
                    if (in_array(4, $type_value)) {
                        if ($this->check($v)) {
                            $activityLogic = new \app\common\logic\ActivityLogic();
                            $result = $activityLogic->get_coupon($v['id'], $user_id);
                            if (1 != $result['status']) {
                                Log::record('新用户：' . $user_id . ' 获取新人优惠券：' . $v['id'] . '失败。原因：' . $result['msg'] . '优惠券ID：' . $v['id']);
                            } else {
                                $couponIds[] = $v['id'];
                            }
                        }
                    }
                }
            }
            if (!empty($couponIds)) {
                // 新用户奖励记录
                M('user_new_log')->add([
                    'user_id' => $user_id,
                    'coupon_id' => implode(',', $couponIds),
                    'add_time' => NOW_TIME
                ]);
            }
        }
    }

    public function sendNewVipUser($user_id, $order_id)
    {
        $coupon_list = M('Coupon')->where('type_value', 'LIKE', '%5%')->select();
        if (!empty($coupon_list)) {
            foreach ($coupon_list as $v) {
                if ($v['type_value']) {
                    $type_value = explode(',', $v['type_value']);
                    if (in_array(5, $type_value)) {
                        if ($this->check($v)) {
                            $activityLogic = new \app\common\logic\ActivityLogic();
                            $result = $activityLogic->get_coupon($v['id'], $user_id, $order_id);
                            if (1 != $result['status']) {
                                Log::record('新VIP：' . $user_id . ' 获取新VIP优惠券：' . $v['id'] . '失败。原因：' . $result['msg'] . '优惠券ID：' . $v['id']);
                            }
                        }
                    }
                }
            }
        }
    }

    public function check($coupon)
    {
        if (1 != $coupon['status'] || $coupon['send_start_time'] > time() || $coupon['send_end_time'] < time()) {
            return false;
        }

        return true;
    }

    /**
     * 获取用户可以使用的优惠券金额.
     *
     * @param $user_id |用户id
     * @param $coupon_id |优惠券id
     *
     * @return int|mixed
     */
    public function getCouponMoney($user_id, $coupon_id)
    {
        if (0 == $coupon_id) {
            return 0;
        }
        $couponList = M('CouponList')->where('uid', $user_id)->where('id', $coupon_id)->find(); // 获取用户的优惠券
        if (empty($couponList)) {
            return 0;
        }
        $coupon = M('Coupon')->where('id', $couponList['cid'])->find(); // 获取 优惠券类型表
        $coupon['money'] = $coupon['money'] ? $coupon['money'] : 0;

        return $coupon['money'];
    }

    /**
     * 根据优惠券代码获取优惠券金额.
     *
     * @param $couponCode |优惠券代码
     * @param $orderMoney |订单金额
     *
     * @return array
     */
    public function getCouponMoneyByCode($couponCode, $orderMoney)
    {
        $couponList = M('CouponList')->where('code', $couponCode)->find(); // 获取用户的优惠券
        if (empty($couponList)) {
            return ['status' => -9, 'msg' => '优惠券码不存在', 'result' => ''];
        }
        if ($couponList['order_id'] > 0) {
            return ['status' => -20, 'msg' => '该优惠券已被使用', 'result' => ''];
        }
        $coupon = M('Coupon')->where('id', $couponList['cid'])->find(); // 获取优惠券类型表
        if (time() < $coupon['use_start_time']) {
            return ['status' => -13, 'msg' => '该优惠券开始使用时间' . date('Y-m-d H:i:s', $coupon['use_start_time']), 'result' => ''];
        }
        if (time() > $coupon['use_end_time']) {
            return ['status' => -10, 'msg' => '优惠券已经过期' . date('Y-m-d H:i:s', $coupon['use_start_time']), 'result' => ''];
        }
        if ($orderMoney < $coupon['condition']) {
            return ['status' => -11, 'msg' => '金额没达到优惠券使用条件', 'result' => ''];
        }
        if ($couponList['order_id'] > 0) {
            return ['status' => -12, 'msg' => '优惠券已被使用', 'result' => ''];
        }

        return ['status' => 1, 'msg' => '', 'result' => $coupon['money']];
    }

    /**
     * 获取购物车中的优惠券
     * $type: 0可用，1不可用
     * $size: 每页的数量，null表示所有.
     */
    public function getCartCouponList($user_id, $type, $cartList, $p = 1, $size = null)
    {
        //商品优惠总价
        $cartTotalPrice = array_sum(array_map(function ($val) {
            return $val['total_fee'];
        }, $cartList));

        $now = time();
        $where = "c1.status=1 AND c2.uid={$user_id} AND c1.use_end_time>{$now}";
        if (!$type) {
            $where .= " AND c1.use_start_time<{$now} AND c1.condition<={$cartTotalPrice}";
        } else {
            $where .= " AND (c1.use_start_time>{$now} OR c1.condition>{$cartTotalPrice}) ";
        }

        $query = Db::name('coupon')->alias('c1')
            ->field('c1.name,c1.money,c1.condition,c1.use_end_time, c2.*')
            ->join('__COUPON_LIST__ c2', 'c2.cid=c1.id AND c2.status=0', 'LEFT')
            ->where($where);
        if ($size) {
            return $query->page($p, $size)->select();
        }

        return $query->select();
    }

    /**
     * 获取用户可用的优惠券
     * @param $user_id
     * @param array $goods_ids
     * @param array $goods_cat_id
     * @return array
     */
    public function getUserAbleCouponList($user_id, $goods_ids = [], $goods_cat_id = [], $isApp = false)
    {
        $goods_list = M('goods')->where('goods_id', 'in', $goods_ids)->field('zone, distribut_id, prom_type')->select();
        foreach ($goods_list as $key => $value) {
            if ($value['prom_type'] == 1) {
                if (M('flash_sale')->where([
                    'goods_id' => $value['goods_id'],
                    'start_time' => ['<=', time()],
                    'end_time' => ['>=', time()],
                    'source' => ['LIKE', $isApp ? '%' . 3 . '%' : '%' . 1 . '%']
                ])->value('id')) {
                    // 秒杀商品不能使用优惠券
                    return [];
                }
            }
            if (3 == $value['zone'] && $value['distribut_id'] != 0) {
                // VIP升级套餐
                return [];
            }
        }
        $CouponList = new CouponList();
        $Coupon = new Coupon();
        $userCouponArr = [];
        $userCouponList = $CouponList->where('uid', $user_id)->where('status', 0)->select(); //用户优惠券
        if (!$userCouponList) {
            return $userCouponArr;
        }
        $userCouponId = get_arr_column($userCouponList, 'cid');
        $couponList = $Coupon->with('GoodsCoupon')
            ->where('id', 'IN', $userCouponId)
            ->where('status', 1)
            ->where('use_start_time', '<', time())
            ->where('use_end_time', '>', time())
            ->select();
        //检查优惠券是否可以用
        foreach ($userCouponList as $userCoupon => $userCouponItem) {
            foreach ($couponList as $coupon => $couponItem) {
                if ($userCouponItem['cid'] == $couponItem['id']) {
                    switch ($couponItem['use_type']) {
                        case 0:
                            // 全店通用
                            $tmp = $userCouponItem;
                            $tmp['coupon'] = $couponItem->append(['use_type_title'])->toArray();
                            $userCouponArr[] = $tmp;
                            break;
                        case 1:
                            // 限定商品
                            if (!empty($couponItem['goods_coupon'])) {
                                foreach ($couponItem['goods_coupon'] as $goodsCoupon => $goodsCouponItem) {
                                    if (in_array($goodsCouponItem['goods_id'], $goods_ids)) {
                                        $tmp = $userCouponItem;
                                        $tmp['coupon'] = array_merge($couponItem->append(['use_type_title'])->toArray(), $goodsCouponItem->toArray());
                                        $userCouponArr[] = $tmp;
                                        break;
                                    }
                                }
                            }
                            break;
                        case 2:
                            // 限定商品类型
                            if (!empty($couponItem['goods_coupon'])) {
                                foreach ($couponItem['goods_coupon'] as $goodsCoupon => $goodsCouponItem) {
                                    if (in_array($goodsCouponItem['goods_category_id'], $goods_cat_id)) {
                                        $tmp = $userCouponItem;
                                        $tmp['coupon'] = array_merge($couponItem->append(['use_type_title'])->toArray(), $goodsCouponItem->toArray());
                                        $tmp['cat_name'] = M('goods_category')->where(['id' => $tmp['coupon']['goods_category_id']])->value('name');
                                        $userCouponArr[] = $tmp;
                                        break;
                                    }
                                }
                            }
                            break;
                        case 4:
                            // 折扣券
                            if (!empty($couponItem['goods_coupon'])) {
                                foreach ($couponItem['goods_coupon'] as $goodsCoupon => $goodsCouponItem) {
                                    if (in_array($goodsCouponItem['goods_id'], $goods_ids)) {
                                        $tmp = $userCouponItem;
                                        $tmp['coupon'] = array_merge($couponItem->append(['use_type_title'])->toArray(), $goodsCouponItem->toArray());
                                        $userCouponArr[] = $tmp;
                                        break;
                                    }
                                }
                            }
                            break;
                        case 5:
                            // 兑换券
                            break;
                        default:
                            return [];
                    }
                }
            }
        }

        return $userCouponArr;
    }

    /**
     * 用户可用的兑换券
     * @param $user_id
     * @param array $goods_ids
     * @param array $goods_cat_id
     * @return array
     */
    public function getUserAbleCouponListRe($user_id, $goods_ids = [], $goods_cat_id = [], $isApp = false)
    {
        $goods_list = M('goods')->where('goods_id', 'in', $goods_ids)->field('zone, distribut_id, prom_type')->select();
        foreach ($goods_list as $key => $value) {
            if ($value['prom_type'] == 1) {
                if (M('flash_sale')->where([
                    'goods_id' => $value['goods_id'],
                    'start_time' => ['<=', time()],
                    'end_time' => ['>=', time()],
                    'source' => ['LIKE', $isApp ? '%' . 3 . '%' : '%' . 1 . '%']
                ])->value('id')) {
                    // 秒杀商品不能使用优惠券
                    return [];
                }
            }
            if (3 == $value['zone'] && $value['distribut_id'] != 0) {
                // VIP升级套餐
                return [];
            }
        }
        $CouponList = new CouponList();
        $Coupon = new Coupon();
        $userCouponArr = [];
        $userCouponList = $CouponList->where('uid', $user_id)->where('status', 0)->select(); //用户优惠券
        if (!$userCouponList) {
            return $userCouponArr;
        }
        $userCouponId = get_arr_column($userCouponList, 'cid');
        $couponList = $Coupon->with('GoodsCoupon')
            ->where('id', 'IN', $userCouponId)
            ->where('status', 1)
            ->where('use_start_time', '<', time())
            ->where('use_end_time', '>', time())
            ->select(); //检查优惠券是否可以用
        foreach ($userCouponList as $userCoupon => $userCouponItem) {
            foreach ($couponList as $coupon => $couponItem) {
                if ($userCouponItem['cid'] == $couponItem['id']) {
                    if (5 == $couponItem['use_type']) {
                        // 兑换券
                        $tmp = $userCouponItem;
                        $tmp['coupon'] = $couponItem->append(['use_type_title'])->toArray();
                        $tmp['coupon_goods'] = M('goods_coupon')
                            ->alias('gc')
                            ->join('goods g', 'g.goods_id = gc.goods_id')
                            ->where('gc.coupon_id', $userCouponItem['cid'])
                            ->field('gc.goods_id,gc.number,g.goods_name,g.original_img,g.exchange_integral,shop_price - g.exchange_integral as member_price')
                            ->select();
                        $userCouponArr[] = $tmp;
                    }
                }
            }
        }

        return $userCouponArr;
    }

    /**
     * 优惠券兑换.
     *
     * @param type $user_id
     * @param type $coupon_code
     *
     * @return json
     */
    public function exchangeCoupon($user_id, $coupon_code)
    {
        if (0 == $user_id) {
            return ['status' => -100, 'msg' => '登录超时请重新登录!', 'result' => null];
        }
        if (!$coupon_code) {
            return ['status' => '0', 'msg' => '请输入优惠券券码', 'result' => ''];
        }
        $coupon_list = Db::name('coupon_list')->where('code', $coupon_code)->find();
        if (empty($coupon_list)) {
            return ['status' => 0, 'msg' => '优惠券码不存在', 'result' => ''];
        }
        if ($coupon_list['order_id'] > 0) {
            return ['status' => 0, 'msg' => '该优惠券已被使用', 'result' => ''];
        }
        if ($coupon_list['uid'] > 0) {
            return ['status' => 0, 'msg' => '该优惠券已兑换', 'result' => ''];
        }
        $coupon = Coupon::get($coupon_list['cid']); // 获取优惠券类型表
        if (time() < $coupon['use_start_time']) {
            return ['status' => 0, 'msg' => '该优惠券开始使用时间' . date('Y-m-d H:i:s', $coupon['use_start_time']), 'result' => ''];
        }
        if (time() > $coupon['use_end_time'] || 2 == $coupon['status']) {
            return ['status' => 0, 'msg' => '优惠券已失效或过期', 'result' => ''];
        }
        $do_exchange = Db::name('coupon_list')->where('id', $coupon_list['id'])->update(['uid' => $user_id]);
        if (false !== $do_exchange) {
            return ['status' => 1, 'msg' => '兑换成功',
                'result' => ['coupon' => $coupon->append(['is_expiring', 'use_start_time_format_dot', 'use_end_time_format_dot'])->toArray(), 'coupon_list' => $coupon_list],];
        }

        return ['status' => 0, 'msg' => '兑换失败', 'result' => ''];
    }

    /**
     * 获取店铺商品可领取优惠券.
     *
     * @param array $goods_ids |商品id数组
     * @param array $goods_category_ids |商品分类数组
     *
     * @return array
     */
    public function getStoreGoodsCoupon($goods_ids = [], $goods_category_ids = [])
    {
        //查询店铺下所有的优惠券
        $storeCoupon = Db::name('coupon')->select();
        $newStoreCoupon = $goodsCouponIds = []; //存放提取的优惠券|存放提取的优惠券id
        foreach ($storeCoupon as $couponKey => $couponVal) {
            //提取（免费领取，还有剩余发放数量，处于发放时间）优惠券，
            if ((($couponVal['createnum'] - $couponVal['send_num']) > 0 || 0 == $couponVal['createnum'])
                && 2 == $couponVal['type'] && $couponVal['send_start_time'] < time() && $couponVal['send_end_time'] > time()
                && 1 == $couponVal['status']
            ) {
                $newStoreCoupon[] = $couponVal; //存放提取的优惠券
                //提取（指定商品或者商品分类类型）优惠券id
                if (1 == $couponVal['use_type'] || 2 == $couponVal['use_type']) {
                    $goodsCouponIds[] = $couponVal['id']; //存放提取的优惠券id
                }
            }
        }
        if ($goodsCouponIds) {
            //查询（指定商品或者商品分类）优惠券记录
            $goodsCouponList = Db::name('goods_coupon')->where('coupon_id', 'IN', $goodsCouponIds)->select();
            if ($goodsCouponList) {
                $newGoodsCouponIds = []; //存放指定商品Id和商品分类Id的优惠券ID
                foreach ($goodsCouponList as $gcKey => $gcVal) {
                    //验证并提取（指定商品或者商品分类）优惠券id
                    if (in_array($gcVal['goods_id'], $goods_ids) || in_array($gcVal['goods_category_id'], $goods_category_ids)) {
                        if (!in_array($gcVal['coupon_id'], $newGoodsCouponIds)) {
                            array_push($newGoodsCouponIds, $gcVal['coupon_id']);
                        }
                    }
                }
                if ($newGoodsCouponIds) {
                    $tmp = [];
                    //过滤不存在的指定商品或者商品分类类型的优惠券
                    foreach ($newStoreCoupon as $newCouponKey => $newCouponVal) {
                        if ((1 == $newCouponVal['use_type'] || 2 == $newCouponVal['use_type']) && !in_array($newCouponVal['id'], $newGoodsCouponIds)) {
                            continue;
                        }
                        $tmp[] = $newCouponVal;
                    }
                    unset($newStoreCoupon);
                    $newStoreCoupon = $tmp;
                }
            }
        }

        return $newStoreCoupon;
    }

    /**
     * 获取商品优惠券
     * @param integer $useType
     * @param null $goodsId
     * @param null $catId
     * @param array $ext
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCoupon($useType = 0, $goodsId = null, $catId = null, $ext = [], $field = '')
    {
        $where = [
            'send_start_time' => ['<', time()],
            'send_end_time' => ['>', time()],
        ];
        if ($useType === 0) {
            // 全店通用
            $where['c.use_type'] = $useType;
        }
        if ($goodsId) {
            // 指定商品可用
            if (is_array($goodsId)) {
                $where['gc.goods_id'] = ['in', $goodsId];
            } else {
                $where['gc.goods_id'] = $goodsId;
            }
        }
        if ($catId) {
            // 指定分类可用
            if (is_array($catId)) {
                $where['gc.goods_category_id'] = ['in', $catId];
            } else {
                $where['gc.goods_category_id'] = $catId;
            }
        }
        if (isset($ext['nature'])) {
            // 优惠券性质（普通、任务）
            $where['c.nature'] = ['in', $ext['nature']];
        }
        if (isset($ext['not_type_value'])) {
            // 过滤的优惠券发放方式
            $where['c.type_value'] = ['not in', $ext['not_type_value']];
        }
        if (isset($ext['not_coupon_id'])) {
            // 过滤的优惠券ID
            $where['c.id'] = ['not in', $ext['not_coupon_id']];
        }
        $coupon = Db::name('coupon')->alias('c')
            ->join('goods_coupon gc', 'gc.coupon_id = c.id', 'LEFT')
            ->join('goods g', 'g.goods_id = gc.goods_id', 'LEFT')
            ->join('goods_category gcc', 'gcc.id = gc.goods_category_id', 'LEFT')
            ->where($where)->where(['c.status' => 1, 'c.use_start_time' => ['<=', time()], 'c.use_end_time' => ['>=', time()]]);
        if (!empty($field)) {
            $coupon = $coupon->field($field);
        } else {
            $coupon = $coupon->field('c.id coupon_id, c.nature, c.name, c.money, c.condition, FROM_UNIXTIME(c.use_start_time,"%Y.%m.%d") as use_start_time, FROM_UNIXTIME(c.use_end_time,"%Y.%m.%d") as use_end_time, c.use_type, gc.goods_id, g.goods_name, g.original_img, gc.goods_category_id cat_id, gcc.name cat_name');
        }
        if (isset($ext['limit'])) {
            // 限制数量
            $coupon = $coupon->limit($ext['limit']['offset'], $ext['limit']['length']);
        }

        return $coupon->select();
    }

    /**
     * 订单优惠券、兑换券处理
     */
    public function doOrderPayAfter()
    {
        $order = $this->order;
        if (!empty($order['coupon_id']) && $order['coupon_id'] != 0) {
            // 优惠券已使用
            $listId = M('coupon_list')->where(['cid' => $order['coupon_id'], 'uid' => $order['user_id']])->limit(0, 1)->value('id');
            M('coupon_list')->where(['id' => $listId])->update([
                'order_id' => $order['order_id'],
                'status' => 1,
                'use_time' => time()
            ]);
            // 优惠券使用数+1
            M('coupon')->where(['id' => $order['coupon_id']])->setInc('use_num', 1);
        }
        $orderGoods = M('order_goods')->where(['order_id' => $order['order_id'], 're_id' => ['neq', 0]])->select();
        foreach ($orderGoods as $value) {
            // 兑换券已使用
            $listId = M('coupon_list')->where(['cid' => $value['re_id'], 'uid' => $order['user_id']])->limit(0, 1)->value('id');
            M('coupon_list')->where(['id' => $listId])->update([
                'order_id' => $order['order_id'],
                'status' => 1,
                'use_time' => time()
            ]);
            // 兑换券使用数+1
            M('coupon')->where(['id' => $value['re_id']])->setInc('use_num', 1);
        }
    }
}
