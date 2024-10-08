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
use think\Db;
use think\Model;
use think\Page;

/**
 * 活动逻辑类.
 */
class ActivityLogic extends Model
{
    /**
     * 团购总数.
     *
     * @param type $sort_type
     * @param type $page_index
     * @param type $page_size
     */
    public function getGroupBuyCount()
    {
        $group_by_where = [
            'start_time' => ['lt', time()],
            'end_time' => ['gt', time()],
        ];
        $count = M('group_buy')->alias('b')
            ->field('b.goods_id,b.rebate,b.virtual_num,b.buy_num,b.title,b.goods_price,b.end_time,b.price,b.order_num,g.comment_count')
            ->join('__GOODS__ g', 'b.goods_id=g.goods_id AND g.prom_type=2 AND g.is_on_sale=1')
            ->where($group_by_where)
            ->count();

        return $count;
    }

    /**
     * 团购列表.
     *
     * @param type $sort_type
     * @param type $page_index
     * @param type $page_size
     */
    public function getGroupBuyList($sort_type = '', $page_index = 1, $page_size = 20)
    {
        if ('new' == $sort_type) {
            $type = 'start_time';
        } elseif ('comment' == $sort_type) {
            $type = 'g.comment_count';
        } else {
            $type = '';
        }

        $group_by_where = [
            'start_time' => ['lt', time()],
            'end_time' => ['gt', time()],
            'is_end' => 0,
        ];
        $list = M('group_buy')->alias('b')
            ->field('b.goods_id,b.item_id,b.rebate,b.virtual_num,b.buy_num,b.title,b.goods_price,b.end_time,b.price,b.order_num,g.comment_count')
            ->join('__GOODS__ g', 'b.goods_id=g.goods_id AND g.prom_type=2 AND g.is_on_sale=1')
            ->where($group_by_where)->page($page_index, $page_size)
            ->order($type, 'desc')
            ->select(); // 找出这个商品

        $groups = [];
        $server_time = time();
        foreach ($list as $v) {
            $v['server_time'] = $server_time;
            $groups[] = $v;
        }

        return $groups;
    }

    /**
     * 优惠券列表.
     *
     * @param type $atype 排序类型 1:默认id排序，2:即将过期，3:面值最大
     * @param type $use_type 使用范围：0全店通用1指定商品可用2指定分类商品可用
     * @param $user_id  用户ID
     * @param int $p 第几页
     *
     * @return array
     */
    public function getCouponList($atype, $use_type = '', $user_id, $p = 1)
    {
        $time = time();
        if ('' !== $use_type) {
            $where = ['type' => 2, 'use_type' => $use_type, 'status' => 1, 'send_start_time' => ['elt', time()], 'send_end_time' => ['egt', time()]];
        } else {
            $where = ['type' => 2, 'status' => 1, 'send_start_time' => ['elt', time()], 'send_end_time' => ['egt', time()]];
        }

        $order = ['id' => 'desc'];
        if (2 == $atype) {
            //即将过期
            $order = ['spacing_time' => 'asc'];
            $where["send_end_time-'$time'"] = ['egt', 0];
        } elseif (3 == $atype) {
            //面值最大
            $order = ['money' => 'desc'];
        }
        $coupon_list = M('coupon')->field("*,send_end_time-'$time' as spacing_time")
            // ->fetchSql(1)
            ->where($where)
            ->page($p, 15)
            ->order($order)
            ->select();

        if (is_array($coupon_list) && count($coupon_list) > 0) {
            if ($user_id) {
                $user_coupon = M('coupon_list')->where(['uid' => $user_id, 'type' => 2])->getField('cid', true);
            }
            if (!empty($user_coupon)) {
                foreach ($coupon_list as $k => $val) {
                    $coupon_list[$k]['isget'] = 0;
                    if (in_array($val['id'], $user_coupon)) {
                        $coupon_list[$k]['isget'] = 1;
                        unset($coupon_list[$k]);
                        continue;
                    }
                    $coupon_list[$k]['use_scope'] = C('COUPON_USER_TYPE')[$coupon_list[$k]['use_type']];
                }
            }
        }

        return $coupon_list;
    }

    /**
     * 获取优惠券查询对象
     *
     * @param int $queryType 0:count 1:select
     * @param type $user_id
     * @param int $type 查询类型 0:未使用，1:已使用，2:已过期
     * @param type $orderBy 排序类型，use_end_time、send_time,默认send_time
     * @param int $order_money
     *
     * @return Query
     */
    public function getCouponQuery($queryType, $user_id, $type = 0, $orderBy = null, $order_money = 0, $is_yhq = true)
    {
        $where['l.uid'] = $user_id;
        $where['c.status'] = 1;
        //查询条件
        if (empty($type)) {
            // 未使用
            $where['l.order_id'] = 0;
            $where['c.use_end_time'] = ['gt', time()];
            $where['l.status'] = 0;
        } elseif (1 == $type) {
            //已使用
//            $where['l.order_id'] = ['gt', 0];
            $where['l.use_time'] = ['gt', 0];
            $where['l.status'] = 1;
        } elseif (2 == $type) {
            //已过期
            $where['c.use_end_time'] = ['lt', time()];
            $where['l.status|c.status'] = ['neq', 1];
        }

        if ('use_end_time' == $orderBy) {
            //即将过期，$type = 0 AND $orderBy = 'use_end_time'
            $order['c.use_end_time'] = 'asc';
        } elseif ('send_time' == $orderBy) {
            //最近到账，$type = 0 AND $orderBy = 'send_time'
            $where['l.send_time'] = ['lt', time()];
            $order['l.send_time'] = 'desc';
        } elseif (empty($orderBy)) {
            $order = ['l.send_time' => 'DESC', 'l.use_time'];
        }
        $condition = floatval($order_money) ? ' AND c.condition <= ' . $order_money : '';

        /*if($is_yhq){
            $condition .= ' and use_type !=5 ';
        }else{
            $condition .= ' and use_type = 5 ';
        }*/

        $query = M('coupon_list')->alias('l')
            ->join('__COUPON__ c', 'l.cid = c.id' . $condition)
            ->where($where);

        if (0 != $queryType) {
            $query = $query->field('l.*,c.name,c.money,c.type_value,c.use_start_time,c.use_end_time,c.condition,c.use_type,c.content')
                ->order($order);
        }

        return $query;
    }

    /**
     * 获取优惠券数目.
     *
     * @param $user_id
     * @param int $type
     * @param null $orderBy
     * @param int $order_money
     *
     * @return mixed
     */
    public function getUserCouponNum($user_id, $type = 0, $orderBy = null, $order_money = 0)
    {
        $query = $this->getCouponQuery(0, $user_id, $type, $orderBy, $order_money);

        return $query->count();
    }

    /**
     * 获取用户优惠券列表.
     *
     * @param $firstRow
     * @param $listRows
     * @param $user_id
     * @param int $type
     * @param null $orderBy
     * @param int $order_money
     *
     * @return mixed
     */
    public function getUserCouponList($firstRow, $listRows, $user_id, $type = 0, $orderBy = null, $order_money = 0, $is_yhq = true)
    {
        $query = $this->getCouponQuery(1, $user_id, $type, $orderBy, $order_money, $is_yhq);

        return $query->limit($firstRow, $listRows)->select();
    }

    /**
     * 领券中心.
     *
     * @param type $cat_id 领券类型id
     * @param type $user_id 用户id
     * @param type $p 第几页
     *
     * @return type
     */
    public function getCouponCenterList($cat_id, $user_id, $p = 1)
    {
        /* 获取优惠券列表 */
        $cur_time = time();
        $coupon_where = ['type' => 2, 'status' => 1, 'send_start_time' => ['elt', time()], 'send_end_time' => ['egt', time()]];
        $query = M('coupon')->alias('c')
            ->field('c.use_type,c.name,c.id,c.money,c.condition,c.createnum,c.send_num,c.send_end_time-' . $cur_time . ' as spacing_time')
            ->where('((createnum-send_num>0 AND createnum>0) OR (createnum=0))')//领完的也不要显示了
            ->where($coupon_where)->page($p, 15)
            ->order('spacing_time', 'asc');
        if ($cat_id > 0) {
            $query = $query->join('__GOODS_COUPON__ gc', 'gc.coupon_id=c.id AND gc.goods_category_id=' . $cat_id);
        }
        $coupon_list = $query->select();

        if (!(is_array($coupon_list) && count($coupon_list) > 0)) {
            return [];
        }

        $user_coupon = [];
        if ($user_id) {
            $user_coupon = M('coupon_list')->where(['uid' => $user_id, 'type' => 2])->column('cid');
        }

        $types = [];
        if ($cat_id) {
            /* 优惠券类型格式转换 */
            $couponType = $this->getCouponTypes();
            foreach ($couponType as $v) {
                $types[$v['id']] = $v['mobile_name'];
            }
        }

        $store_logo = tpCache('shop_info.store_logo') ?: '';
        $Coupon = new Coupon();
        foreach ($coupon_list as $k => $coupon) {
            /* 是否已获取 */
            $coupon_list[$k]['use_type_title'] = $Coupon->getUseTypeTitleAttr(null, $coupon_list[$k]);
            $coupon_list[$k]['isget'] = 0;
            if (in_array($coupon['id'], $user_coupon)) {
                $coupon_list[$k]['isget'] = 1;
            }

            /* 构造封面和标题 */
            $coupon_list[$k]['image'] = $store_logo;
        }

        return $coupon_list;
    }

    /**
     * 优惠券类型列表.
     *
     * @param type $p 第几页
     * @param type $num 每页多少，null表示全部
     *
     * @return type
     */
    public function getCouponTypes($p = 1, $num = null)
    {
        $list = M('coupon')->alias('c')
            ->join('__GOODS_COUPON__ gc', 'gc.coupon_id=c.id AND gc.goods_category_id!=0')
            ->where(['type' => 2, 'status' => 1])
            ->column('gc.goods_category_id');

        $result = M('goods_category')->field('id, mobile_name')->where('id', 'IN', $list)->page($p, $num)->select();
        $result = $result ?: [];
        array_unshift($result, ['id' => 0, 'mobile_name' => '精选']);

        return $result;
    }

    /**
     * 领券.
     *
     * @param $id 优惠券id
     * @param $user_id
     */
    public function get_coupon($id, $user_id, $get_order_id = 0)
    {
        if (empty($id)) {
            return ['status' => 0, 'msg' => '参数错误'];
        }
        if ($user_id) {
            $_SERVER['HTTP_REFERER'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U('Home/Activity/coupon_list');
            $coupon_info = M('coupon')->where(['id' => $id, 'status' => 1])->find();
            if (empty($coupon_info)) {
                $return = ['status' => 0, 'msg' => '活动已结束或不存在，看下其他活动吧~', 'return_url' => $_SERVER['HTTP_REFERER']];
            } elseif ($coupon_info['send_end_time'] < time()) {
                //来晚了，过了领取时间
                $return = ['status' => 0, 'msg' => '抱歉，已经过了领取时间', 'return_url' => $_SERVER['HTTP_REFERER']];
            } elseif ($coupon_info['send_num'] >= $coupon_info['createnum'] && 0 != $coupon_info['createnum']) {
                //来晚了，优惠券被抢完了
                $return = ['status' => 0, 'msg' => '来晚了，优惠券被抢完了', 'return_url' => $_SERVER['HTTP_REFERER']];
            } else {
                if ($coupon_info['nature'] == 1 && M('coupon_list')->where(['cid' => $id, 'uid' => $user_id, 'status' => 0])->find()) {
                    //已经领取过
                    $return = ['status' => 2, 'msg' => '您已领取过该优惠券', 'return_url' => $_SERVER['HTTP_REFERER']];
                } else {
                    do {
                        $code = get_rand_str(8, 0, 1);  // 获取随机8位字符串
                        $check_exist = M('coupon_list')->where(['code' => $code])->value('id');
                    } while ($check_exist);
                    $data = ['uid' => $user_id, 'get_order_id' => $get_order_id, 'cid' => $id, 'type' => 1, 'send_time' => time(), 'status' => 0, 'code' => $code];
                    M('coupon_list')->add($data);
                    M('coupon')->where(['id' => $id, 'status' => 1])->setInc('send_num');
                    $return = ['status' => 1, 'msg' => '恭喜您，抢到' . $coupon_info['money'] . '元优惠券!', 'return_url' => $_SERVER['HTTP_REFERER'], 'coupon' => $coupon_info];
                }
            }
        } else {
            $return = ['status' => 0, 'msg' => '请先登录', 'return_url' => U('User/login')];
        }
        return $return;
    }

    /**
     * 获取活动简要信息.
     */
    public function getActivitySimpleInfo(&$goods, $user)
    {
        //1.商品促销
        $activity = $this->getGoodsPromSimpleInfo($user, $goods);

        //2.订单促销
        $activity_order = $this->getOrderPromSimpleInfo($user, $goods);

        if ($activity['data'] || $activity_order) {
            empty($activity['data']) && $activity['data'] = [];
            $activity['data'] = array_merge($activity['data'], $activity_order);
        }

        $activity['server_current_time'] = time(); //服务器时间

        return $activity;
    }

    /**
     * 获取商品促销简单信息.
     */
    public function getGoodsPromSimpleInfo($user, &$goods)
    {
        $goods['prom_is_able'] = 0;
        $activity['prom_type'] = 0;

        //1.商品促销
        $goodsPromFactory = new \app\common\logic\GoodsPromFactory();
        if (!$goodsPromFactory->checkPromType($goods['prom_type'])) {
            return $activity;
        }
        $goodsPromLogic = $goodsPromFactory->makeModule($goods, $goods['prom_id']);
        //上面会自动更新商品活动状态，所以商品需要重新查询
        $goods = M('Goods')->where('goods_id', $goods['goods_id'])->find();
        unset($goods['goods_content']);
        $goods['prom_is_able'] = 0;

        //prom_type:0默认 1抢购 2团购 3优惠促销 4预售(不考虑)
        if (!$goodsPromLogic->checkActivityIsAble()) {
            return $activity;
        }
        $prom = $goodsPromLogic->getPromModel()->getData();
        if (in_array($goods['prom_type'], [1, 2])) {
            $prom['virtual_num'] = $prom['virtual_num'] + $prom['buy_num']; //参与人数
            $goods['prom_is_able'] = 1;
            $activity = [
                'prom_type' => $goods['prom_type'],
                'prom_price' => $prom['price'],
                'virtual_num' => $prom['virtual_num'],
            ];
            if ($prom['start_time']) {
                $activity['prom_start_time'] = $prom['start_time'];
            }
            if ($prom['end_time']) {
                $activity['prom_end_time'] = $prom['end_time'];
            }

            return $activity;
        }

        // 3优惠促销
        $levels = explode(',', $prom['group']);
        if ($prom['group'] && (isset($user['level']) && in_array($user['level'], $levels))) {
            //type:0直接打折,1减价优惠,2固定金额出售,3买就赠优惠券
            if (0 == $prom['type']) {
                $activityData[] = ['title' => '折扣', 'content' => "指定商品立打{$prom['expression']}折"];
            } elseif (1 == $prom['type']) {
                $activityData[] = ['title' => '直减', 'content' => "指定商品立减{$prom['expression']}元"];
            } elseif (2 == $prom['type']) {
                $activityData[] = ['title' => '促销', 'content' => "促销价{$prom['expression']}元"];
            } elseif (3 == $prom['type']) {
                $couponLogic = new \app\common\logic\CouponLogic();
                $money = $couponLogic->getSendValidCouponMoney($prom['expression'], $goods['goods_id'], $goods['cat_id3']);
                if (false !== $money) {
                    $activityData[] = ['title' => '送券', 'content' => "买就送代金券{$money}元"];
                }
            }
            if ($activityData) {
                $goods['prom_is_able'] = 1;
                $activity = [
                    'prom_type' => $goods['prom_type'],
                    'data' => $activityData,
                ];
                if ($prom['start_time']) {
                    $activity['prom_start_time'] = $prom['start_time'];
                }
                if ($prom['end_time']) {
                    $activity['prom_end_time'] = $prom['end_time'];
                }
            }
        }

        return $activity;
    }

    /**
     * 获取.
     *
     * @param type $user_level
     * @param type $cur_time
     * @param type $goods
     *
     * @return string|array
     */
    public function getOrderPromSimpleInfo($user, $goods)
    {
        $cur_time = time();
        $sql = "select * from __PREFIX__prom_order where start_time <= $cur_time AND end_time > $cur_time";
        $data = [];
        $po = Db::query($sql);
        if (!empty($po)) {
            foreach ($po as $p) {
                //type:0满额打折,1满额优惠金额,2满额送积分,3满额送优惠券
                if (0 == $p['type']) {
                    $data[] = ['title' => '折扣', 'content' => "满{$p['money']}元打" . round($p['expression'] / 10, 1) . '折'];
                } elseif (1 == $p['type']) {
                    $data[] = ['title' => '优惠', 'content' => "满{$p['money']}元优惠{$p['expression']}元"];
                } elseif (2 == $p['type']) {
                    //积分暂不支持?
                } elseif (3 == $p['type']) {
                    $couponLogic = new \app\common\logic\CouponLogic();
                    $money = $couponLogic->getSendValidCouponMoney($p['expression'], $goods['goods_id'], $goods['cat_id']);
                    if (false !== $money) {
                        $data[] = ['title' => '送券', 'content' => "满{$p['money']}元送{$money}元优惠券"];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 订单支付时显示的优惠显示.
     *
     * @param type $user
     * @param type $store_id
     *
     * @return type
     */
    public function getOrderPayProm($order_amount = 0)
    {
        $cur_time = time();
        $sql = "select * from __PREFIX__prom_order where type<2 and start_time <= $cur_time "
            . "AND end_time > $cur_time AND  money<=$order_amount order by money desc limit 1"; //显示满额打折,减价优惠信息
        $data = '';
        $po = Db::query($sql);
        if (!empty($po)) {
            foreach ($po as $p) {
                //type:0满额打折,1满额优惠金额,2满额送积分,3满额送优惠券
                if (0 == $p['type']) {
                    $data = "满{$p['money']}元打" . round($p['expression'] / 10, 1) . '折';
                } elseif (1 == $p['type']) {
                    $data = "满{$p['money']}元优惠{$p['expression']}元";
                }
            }
        }

        return $data;
    }

    /**
     * 获取分类主题列表
     * @param $user
     * @param int $source
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \think\exception\DbException
     */
    public function getCateActList($user, $source = 1)
    {
        $where = [
            'start_time' => ['<=', time()],
            'end_time' => ['>=', time()],
            'is_open' => 1,
            'is_end' => 0
        ];
        // 活动列表
        $activityList = Db::name('cate_activity')->where($where)->field('id, title, slogan, banner')->order('sort DESC')->select();
        if (empty($activityList)) {
            return [];
        }
        $activityIds = [];
        foreach ($activityList as $item) {
            $activityIds[] = $item['id'];
        }
        // 活动商品
        $activityGoods = Db::name('cate_activity_goods ag')->join('goods g', 'g.goods_id = ag.goods_id')
            ->where(['g.is_on_sale' => 1, 'ag.cate_act_id' => ['IN', $activityIds]])
            ->field('ag.cate_act_id, ag.goods_id, ag.item_id, g.goods_name, goods_remark, shop_price, exchange_integral, original_img')
            ->order(['ag.cate_act_id' => 'desc', 'g.sort' => 'desc', 'g.goods_id' => 'desc'])
            ->select();
        $filter_goods_id = [];
        foreach ($activityGoods as $item) {
            $filter_goods_id[] = $item['goods_id'];
        }
        // 商品规格属性
        $goodsItem = Db::name('spec_goods_price')->where(['goods_id' => ['in', $filter_goods_id]])->getField('item_id, key_name');
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filter_goods_id], 'status' => 1])->select();
        // 秒杀商品
        $flashSale = Db::name('flash_sale fs')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => ['IN', $filter_goods_id], 'fs.start_time' => ['<=', time()], 'fs.end_time' => ['>=', time()], 'fs.is_end' => 0])
            ->where(['fs.source' => ['LIKE', '%' . $source . '%']])
            ->field('fs.goods_id, sgp.key spec_key, fs.price, fs.goods_num, fs.buy_limit, fs.start_time, fs.end_time, fs.can_integral')->select();
        // 团购商品
        $groupBuy = Db::name('group_buy gb')
            ->join('spec_goods_price sgp', 'sgp.item_id = gb.item_id', 'LEFT')
            ->where(['gb.goods_id' => ['IN', $filter_goods_id], 'gb.start_time' => ['<=', time()], 'gb.end_time' => ['>=', time()], 'gb.is_end' => 0])
            ->field('gb.goods_id, gb.price, sgp.key spec_key, gb.price, gb.group_goods_num, gb.goods_num, gb.buy_limit, gb.start_time, gb.end_time, gb.can_integral')->select();
        // 促销商品
        $promGoods = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['IN', $filter_goods_id], 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.id prom_id, pg.title, pg.type, pg.expression, gtg.goods_id')->order('expression desc');
        if ($user) {
            $promGoods = $promGoods->where(['pg.group' => ['LIKE', '%' . $user['distribut_level'] . '%']]);
        }
        $promGoods = $promGoods->select();
        // 处理活动商品数据
        foreach ($activityGoods as $k => $v) {
            // 缩略图
            $activityGoods[$k]['original_img_new'] = getFullPath($v['original_img']);
            // 商品属性
            if (isset($goodsItem[$v['item_id']])) {
                $activityGoods[$k]['key_name'] = $goodsItem[$v['item_id']];
            } else {
                $activityGoods[$k]['key_name'] = '';
            }
            // 商品标签
            $activityGoods[$k]['tabs'] = [];
            if (!empty($goodsTab)) {
                foreach ($goodsTab as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['tabs'][] = [
                            'tab_id' => $value['tab_id'],
                            'title' => $value['title'],
                            'status' => $value['status']
                        ];
                    }
                }
            }
            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $activityGoods[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
            } else {
                $activityGoods[$k]['exchange_price'] = $v['shop_price'];
            }
            if (!empty($groupBuy)) {
                foreach ($groupBuy as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['goods_type'] = 'group_buy';
                        if ($value['can_integral'] == 0) {
                            $activityGoods[$k]['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $activityGoods[$k]['exchange_price'] = bcsub($value['price'], $activityGoods[$k]['exchange_integral'], 2);
                        $activityGoods[$k]['tags'][0]['type'] = 'activity';
                        $activityGoods[$k]['tags'][0]['title'] = '团购';
                        break;
                    }
                }
            }
            if (!empty($flashSale)) {
                foreach ($flashSale as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['goods_type'] = 'flash_sale';
                        if ($value['can_integral'] == 0) {
                            $activityGoods[$k]['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $activityGoods[$k]['exchange_price'] = bcsub($value['price'], $activityGoods[$k]['exchange_integral'], 2);
                        $activityGoods[$k]['tags'][0]['type'] = 'activity';
                        $activityGoods[$k]['tags'][0]['title'] = '秒杀';
                        break;
                    }
                }
            }
            if (!empty($promGoods)) {
                foreach ($promGoods as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['goods_type'] = 'promotion';
                        switch ($value['type']) {
                            case 0:
                                // 打折
                                $activityGoods[$k]['exchange_price'] = bcdiv(bcmul($activityGoods[$k]['exchange_price'], $value['expression'], 2), 100, 2);
                                break;
                            case 1:
                                // 减价
                                $activityGoods[$k]['exchange_price'] = bcsub($activityGoods[$k]['exchange_price'], $value['expression'], 2);
                                break;
                        }
                        $activityGoods[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['title']];
                        break;
                    }
                }
                if (!isset($activityGoods[$k]['tags'][1]) && !empty($orderPromTitle)) {
                    $activityGoods[$k]['tags'][] = ['type' => 'promotion', 'title' => $orderPromTitle];
                }
            }
            // 优化价格显示
            if ($activityGoods[$k]['exchange_integral'] == 0) {
                $activityGoods[$k]['shop_price'] = $activityGoods[$k]['exchange_price'];
            }
        }
        // 组合数据
        foreach ($activityList as $key => $item) {
            $activityList[$key]['more'] = 0;
//            $activityList[$key]['more_count'] = '0';
            $count = 0;
            $moreCount = 0;
            foreach ($activityGoods as $goods) {
                if ($item['id'] == $goods['cate_act_id']) {
                    if ($count < 9) {
                        $activityList[$key]['goods'][] = $goods;
                        $count++;
                    } else {
                        $moreCount++;
                        break;
                    }
                }
            }
            if ($moreCount > 0) {
                $activityList[$key]['more'] = 1;
//                $activityList[$key]['more_count'] = $moreCount . '';
            }
            // banner处理
            $bannerInfo = json_decode($item['banner'], true);
            if ($bannerInfo) {
                $activityList[$key]['banner'] = $bannerInfo['img'];
                $bannerInfo['img'] = getFullPath($bannerInfo['img']);
                $activityList[$key]['banner_info'] = $bannerInfo;
            } else {
                $activityList[$key]['banner_info'] = [
                    'img' => getFullPath($item['banner']),
                    'width' => 750,
                    'height' => 500,
                    'type' => 'jpeg'
                ];
            }
        }
        return $activityList;
    }

    /**
     * 获取分类主题活动商品列表
     * @param $activityId
     * @param $sort
     * @param $user
     * @param $source
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\exception\DbException
     */
    public function getCateActGoodsList($activityId, $sort = [], $user = null, $source = 1)
    {
        $sort['g.sort'] = 'desc';
        if (!isset($sort['g.goods_id'])) {
            $sort['g.goods_id'] = 'desc';
        }
        $where = [
            'start_time' => ['<=', time()],
            'end_time' => ['>=', time()],
            'is_open' => 1,
            'is_end' => 0,
            'id' => $activityId
        ];
        // 活动信息
        $activityInfo = Db::name('cate_activity')->where($where)->field('id, title, slogan, banner')->find();
        if (empty($activityInfo)) {
            return [];
        }
        // banner处理
        $bannerInfo = json_decode($activityInfo['banner'], true);
        if ($bannerInfo) {
            $activityInfo['banner'] = $bannerInfo['img'];
            $bannerInfo['img'] = getFullPath($bannerInfo['img']);
            $activityInfo['banner_info'] = $bannerInfo;
        } else {
            $activityInfo['banner_info'] = [
                'img' => getFullPath($activityInfo['banner']),
                'width' => 750,
                'height' => 500,
                'type' => 'jpeg'
            ];
        }
        $activityGoods = Db::name('cate_activity_goods ag')->join('goods g', 'g.goods_id = ag.goods_id')
            ->where(['ag.cate_act_id' => $activityId])
            ->field('ag.cate_act_id, ag.goods_id, ag.item_id, g.goods_name, goods_remark, shop_price, exchange_integral, original_img')->order($sort)->select();
        $filter_goods_id = [];
        foreach ($activityGoods as $item) {
            $filter_goods_id[] = $item['goods_id'];
        }
        // 商品规格属性
        $goodsItem = Db::name('spec_goods_price')->where(['goods_id' => ['in', $filter_goods_id]])->getField('item_id, key_name');
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filter_goods_id], 'status' => 1])->select();
        // 秒杀商品
        $flashSale = Db::name('flash_sale fs')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => ['IN', $filter_goods_id], 'fs.start_time' => ['<=', time()], 'fs.end_time' => ['>=', time()], 'fs.is_end' => 0])
            ->where(['fs.source' => ['LIKE', '%' . $source . '%']])
            ->field('fs.goods_id, sgp.key spec_key, fs.price, fs.goods_num, fs.buy_limit, fs.start_time, fs.end_time, fs.can_integral')->select();
        // 团购商品
        $groupBuy = Db::name('group_buy gb')
            ->join('spec_goods_price sgp', 'sgp.item_id = gb.item_id', 'LEFT')
            ->where(['gb.goods_id' => ['IN', $filter_goods_id], 'gb.start_time' => ['<=', time()], 'gb.end_time' => ['>=', time()], 'gb.is_end' => 0])
            ->field('gb.goods_id, gb.price, sgp.key spec_key, gb.price, gb.group_goods_num, gb.goods_num, gb.buy_limit, gb.start_time, gb.end_time, gb.can_integral')->select();
        // 促销商品
        $promGoods = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['IN', $filter_goods_id], 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.id prom_id, pg.title, pg.type, pg.expression, gtg.goods_id')->order('expression desc');
        if ($user) {
            $promGoods = $promGoods->where(['pg.group' => ['LIKE', '%' . $user['distribut_level'] . '%']]);
        }
        $promGoods = $promGoods->select();
        // 处理活动商品数据
        foreach ($activityGoods as $k => $v) {
            $activityGoods[$k]['original_img_new'] = getFullPath($v['original_img']);
            // 商品属性
            if (isset($goodsItem[$v['item_id']])) {
                $activityGoods[$k]['key_name'] = $goodsItem[$v['item_id']];
            } else {
                $activityGoods[$k]['key_name'] = '';
            }
            // 商品标签
            $activityGoods[$k]['tabs'] = [];
            if (!empty($goodsTab)) {
                foreach ($goodsTab as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['tabs'][] = [
                            'tab_id' => $value['tab_id'],
                            'title' => $value['title'],
                            'status' => $value['status']
                        ];
                    }
                }
            }
            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $activityGoods[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
            } else {
                $activityGoods[$k]['exchange_price'] = $v['shop_price'];
            }
            if (!empty($groupBuy)) {
                foreach ($groupBuy as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['goods_type'] = 'group_buy';
                        if ($value['can_integral'] == 0) {
                            $activityGoods[$k]['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $activityGoods[$k]['exchange_price'] = bcsub($value['price'], $activityGoods[$k]['exchange_integral'], 2);
                        $activityGoods[$k]['tags'][0]['type'] = 'activity';
                        $activityGoods[$k]['tags'][0]['title'] = '团购';
                        break;
                    }
                }
            }
            if (!empty($flashSale)) {
                foreach ($flashSale as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['goods_type'] = 'flash_sale';
                        if ($value['can_integral'] == 0) {
                            $activityGoods[$k]['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $activityGoods[$k]['exchange_price'] = bcsub($value['price'], $activityGoods[$k]['exchange_integral'], 2);
                        $activityGoods[$k]['tags'][0]['type'] = 'activity';
                        $activityGoods[$k]['tags'][0]['title'] = '秒杀';
                        break;
                    }
                }
            }
            if (!empty($promGoods)) {
                foreach ($promGoods as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $activityGoods[$k]['goods_type'] = 'promotion';
                        switch ($value['type']) {
                            case 0:
                                // 打折
                                $activityGoods[$k]['exchange_price'] = bcdiv(bcmul($activityGoods[$k]['exchange_price'], $value['expression'], 2), 100, 2);
                                break;
                            case 1:
                                // 减价
                                $activityGoods[$k]['exchange_price'] = bcsub($activityGoods[$k]['exchange_price'], $value['expression'], 2);
                                break;
                        }
                        $activityGoods[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['title']];
                        break;
                    }
                }
                if (!isset($activityGoods[$k]['tags'][1]) && !empty($orderPromTitle)) {
                    $activityGoods[$k]['tags'][] = ['type' => 'promotion', 'title' => $orderPromTitle];
                }
            }
            // 优化价格显示
            if ($activityGoods[$k]['exchange_integral'] == 0) {
                $activityGoods[$k]['shop_price'] = $activityGoods[$k]['exchange_price'];
            }
        }
        $activityInfo['goods_list'] = $activityGoods;
        return $activityInfo;
    }
}
