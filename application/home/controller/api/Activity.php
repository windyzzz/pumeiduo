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

use app\common\logic\ActivityLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\GoodsLogic;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use think\Db;
use think\Page;

class Activity
{
    /**
     * 团购活动列表.
     */
    public function groupBuyList()
    {
        $GroupBuy = new GroupBuy();
        $where = [
            'gb.start_time' => ['elt', time()],
            'gb.end_time' => ['egt', time()],
            // 'gb.is_end'            =>0,
            'g.is_on_sale' => 1,
        ];
        $count = $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->alias('gb')->where($where)->count('gb.goods_id'); // 查询满足要求的总记录数
        $Page = new Page($count, 2); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        // $return['page'] = $show;// 赋值分页输出
        $list = $GroupBuy
            ->alias('gb')
            ->field('*,FROM_UNIXTIME(start_time,"%Y-%m-%d") as start_time,FROM_UNIXTIME(end_time,"%Y-%m-%d") as end_time,(FORMAT(buy_num%group_goods_num/group_goods_num,2)) as percent, CASE can_integral > 0  WHEN 1 THEN gb.price - g.exchange_integral ELSE gb.price END AS price , CASE buy_num >= goods_num  WHEN 1 THEN 1 ELSE 0 END AS is_sale_out, group_goods_num - buy_num%group_goods_num as people_num')
            ->with(['goods', 'specGoodsPrice'])
            ->join('__GOODS__ g', 'g.goods_id = gb.goods_id')
            ->where($where)
            ->order('gb.sort_order')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        foreach ($list as $k => $v) {
            if (1 == $v['is_sale_out']) {
                $list[$k]['percent'] = 1;
                $list[$k]['people_num'] = 0;
            }
        }
        $return['list'] = $list;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
        // return json(['status'=>0, 'msg'=>'success', 'result'=>$return]);
    }

    /**
     * 预售列表页.
     */
    public function pre_sell_list()
    {
        $goodsActivityLogic = new GoodsActivityLogic();
        $pre_sell_list = Db::name('goods_activity')->where(['act_type' => 1, 'is_finished' => 0])->select();
        foreach ($pre_sell_list as $key => $val) {
            $pre_sell_list[$key] = array_merge($pre_sell_list[$key], unserialize($pre_sell_list[$key]['ext_info']));
            $pre_sell_list[$key]['act_status'] = $goodsActivityLogic->getPreStatusAttr($pre_sell_list[$key]);
            $pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_list[$key]['act_id'], $pre_sell_list[$key]['goods_id']);
            $pre_sell_list[$key] = array_merge($pre_sell_list[$key], $pre_count_info);
            $pre_sell_list[$key]['price'] = $goodsActivityLogic->getPrePrice($pre_sell_list[$key]['total_goods'], $pre_sell_list[$key]['price_ladder']);
        }
        $return['pre_sell_list'] = $pre_sell_list;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     *   预售详情页.
     */
    public function pre_sell()
    {
        $id = I('id/d', 0);
        $pre_sell_info = M('goods_activity')->where(['act_id' => $id, 'act_type' => 1])->find();
        if (empty($pre_sell_info)) {
            return json(['status' => 0, 'msg' => '对不起，该预售商品不存在或者已经下架了', 'result' => null]);
        }
        $goods = M('goods')->where(['goods_id' => $pre_sell_info['goods_id']])->find();
        if (empty($goods)) {
            return json(['status' => 0, 'msg' => '对不起，该预售商品不存在或者已经下架了', 'result' => null]);
        }
        $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
        $goodsActivityLogic = new GoodsActivityLogic();
        $pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']); //预售商品的订购数量和订单数量
        $pre_sell_info['price'] = $goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']); //预售商品价格
        $pre_sell_info['amount'] = $goodsActivityLogic->getPreAmount($pre_count_info['total_goods'], $pre_sell_info['price_ladder']); //预售商品数额ing
        if ($goods['brand_id']) {
            $brand = M('brand')->where(['id' => $goods['brand_id']])->find();
            $goods['brand_name'] = $brand['name'];
        }
        $goods_images_list = M('GoodsImages')->where(['goods_id' => $goods['goods_id']])->select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where(['goods_id' => $goods['goods_id']])->select(); // 查询商品属性表
        $goodsLogic = new GoodsLogic();
        $commentStatistics = $goodsLogic->commentStatistics($goods['goods_id']); // 获取某个商品的评论统计
        $return['pre_count_info'] = $pre_count_info; //预售商品的订购数量和订单数量
        $return['commentStatistics'] = $commentStatistics; //评论概览
        $return['goods_attribute'] = $goods_attribute; //属性值
        $return['goods_attr_list'] = $goods_attr_list; //属性列表
        $return['goods_images_list'] = $goods_images_list; //商品缩略图
        $return['pre_sell_info'] = $pre_sell_info;
        $return['look_see'] = $goodsLogic->get_look_see($goods); //看了又看
        $return['goods'] = $goods;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    // 促销活动页面
    public function promoteList()
    {
        $goods_where['p.start_time'] = ['lt', time()];
        $goods_where['p.end_time'] = ['gt', time()];
        $goods_where['p.is_end'] = 0;
        $goods_where['g.prom_type'] = 3;
        $goods_where['g.is_on_sale'] = 1;
        $goodsList = Db::name('goods')
            ->field('g.*,p.end_time,s.item_id')
            ->alias('g')
            ->join('__PROM_GOODS__ p', 'g.prom_id = p.id')
            ->join('__SPEC_GOODS_PRICE__ s', 'g.prom_id = s.prom_id AND s.goods_id = g.goods_id', 'LEFT')
            ->group('g.goods_id')
            ->where($goods_where)
            ->cache(true, 5)
            ->select();
        $brandList = M('brand')->cache(true)->getField('id,name,logo');
        $return['brandList'] = $brandList;
        $return['goodsList'] = $goodsList;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 抢购活动列表.
     */
    public function flash_sale_list()
    {
        $time_space = flash_sale_time_space();
        $return['time_space'] = $time_space;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 抢购活动列表ajax.
     */
    public function ajax_flash_sale()
    {
        $p = I('p', 1);
        $start_time = I('start_time');
        $end_time = I('end_time');
        $where = [
            'fl.start_time' => ['egt', $start_time],
            'fl.end_time' => ['elt', $end_time],
            'g.is_on_sale' => 1,
        ];
        $FlashSale = new FlashSale();
        $flash_sale_goods = $FlashSale->alias('fl')->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice', 'goods'])
            ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
            ->where($where)
            ->page($p, 10)
            ->select();
        $return['flash_sale_goods'] = $flash_sale_goods;
        $return['now'] = time();

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 抢购活动列表ajax.
     */
    public function flashSaleList()
    {
        $p = I('p', 1);
        $now = time();
        $where = [
            'fl.start_time' => ['elt', $now],
            'fl.end_time' => ['egt', $now],
            'g.is_on_sale' => 1,
        ];
        $FlashSale = new FlashSale();
        $flash_sale_goods = $FlashSale->alias('fl')->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice', 'goods'])
            ->field('*,FROM_UNIXTIME(start_time,"%Y-%m-%d %H:%i:%s") as start_time,FROM_UNIXTIME(end_time,"%Y-%m-%d %H:%i:%s") as end_time,100*(FORMAT(buy_num/goods_num,2)) as percent, CASE can_integral > 0  WHEN 1 THEN fl.price - g.exchange_integral ELSE fl.price END AS price')
            ->where($where)
            ->page($p, 10)
            ->select();
        $return['flash_sale_goods'] = $flash_sale_goods;
        $return['now'] = $now;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function coupon_list()
    {
        $atype = I('atype', 1);
        $use_type = I('use_type', '');
        $user = session('user');
        $p = I('p', '');

        $activityLogic = new ActivityLogic();
        $result = $activityLogic->getCouponList($atype, $use_type, $user['user_id'], $p);
        $return['coupon_list'] = $result;
        $return['coupon_type'] = get_coupon_type();

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 领券.
     */
    public function get_coupon()
    {
        $id = I('coupon_id/d');
        if (empty($id)) {
            return json(['status' => 0, 'msg' => '参数错误', 'result' => null]);
        }
        $user = session('user');
        if ($user) {
            $activityLogic = new ActivityLogic();
            $result = $activityLogic->get_coupon($id, $user['user_id']);
        }

        $return['res'] = $result;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 分类主题活动列表
     * @return \think\response\Json
     */
    public function cateActList()
    {
        $activityLogic = new ActivityLogic();
        $res = $activityLogic->getCateActList();
        return json(['status' => 1, 'msg' => 'success', 'result' => $res]);
    }

    /**
     * 分类主题活动商品列表
     * @return \think\response\Json
     */
    public function cateActGoodsList()
    {
        $activityId = I('activity_id', 0);
        if (!$activityId) {
            return json(['status' => 0, 'msg' => '请传入正确的活动ID']);
        }
        $activityLogic = new ActivityLogic();
        $res = $activityLogic->getCateActGoodsList($activityId);
        if (empty($res)) {
            return json(['status' => 0, 'msg' => '活动已取消']);
        }
        return json(['status' => 1, 'msg' => 'success', 'result' => $res]);
    }
}
