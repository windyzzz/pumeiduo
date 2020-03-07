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

use app\common\logic\TaskLogic;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use app\common\logic\UsersLogic;
use app\common\logic\MessageLogic;
use app\common\logic\ArticleLogic;
use app\home\controller\api\Goods as GoodsController;
use think\Page;

class Index
{
    public $user_id = 0;
    public $user = [];

    public function __construct()
    {
        $user = session('user');
        $this->user = $user;
        $this->user_id = $user['user_id'];
    }

    public function index()
    {
        //获取广告
        $position_id = '1,2,49,60';
        $position_id_arr = explode(',', $position_id);
        $ad = array();
        foreach ($position_id_arr as $k => $position_id) {
            $ad[$position_id] = M('ad')
                ->field('ad_code,ad_link,ad_name')
                ->where('pid', $position_id)
                ->where('enabled', 1)
                ->where('start_time', 'elt', NOW_TIME)
                ->where('end_time', 'egt', NOW_TIME)
                ->order('orderby')
                ->select();
        }

        //购物车数量
        $getCartNum = M('Cart')->where('user_id', $this->user_id)->sum('goods_num');

        //新品
        $getNewGoods = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
            ->where('is_new', 1)
            ->where('is_on_sale', 1)
            ->order('sort')
            ->select();

        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        foreach ($getNewGoods as $k => $v) {
            $getNewGoods[$k]['is_enshrine'] = 0;
            $getNewGoods[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
        }

        //抢购活动
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
        $flashSaleList = array();
        $flashSaleList['flash_sale_goods'] = $flash_sale_goods;
        $flashSaleList['now'] = $now;

        //热门
        $getHotGoods = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
            ->where('is_hot', 1)
            ->where('is_on_sale', 1)
            ->limit(9)
            ->order('sort')
            ->select();

        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        foreach ($getHotGoods as $k => $v) {
            $getHotGoods[$k]['is_enshrine'] = 0;
            $getHotGoods[$k]['tabs'] = M('GoodsTab')->where(array('goods_id' => $v['goods_id'], 'title' => array('neq', '')))->select();
        }


        $taskLogic = new TaskLogic();
        $task_info = $taskLogic->getTaskInfo();

        if ($task_info) {
            $goods_id = explode(',', $task_info['goods_id_list']);

            $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
                ->where('is_on_sale', 1)
                ->where('goods_id', 'in', $goods_id)
                ->order('sort')
                ->select();
        } else {
            $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
                ->where('is_recommend', 1)
                ->where('is_on_sale', 1)
                ->order('sort')
                ->select();
        }

        foreach ($list as $k => $v) {
            $list[$k]['is_enshrine'] = 0;
            $list[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
        }
        $task = [];
        $task['title'] = $task_info['title'] ?: '';
        $task['icon'] = $task_info['icon'] ?: '';
        $getRecommendGoods = array();
        $getRecommendGoods['list'] = $list;
        $getRecommendGoods['task_info'] = $task;


        $count = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img,goods_remark')
            ->where('sale_type', 2)
            ->where('is_on_sale', 1)
            ->count();

        $page = new Page($count, 2);

        $getSeriesGoods = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img,goods_remark')
            ->where('sale_type', 2)
            ->where('is_on_sale', 1)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('sort')
            ->select();

        foreach ($getSeriesGoods as $k => $v) {
            $getSeriesGoods[$k]['is_enshrine'] = 0;
            $getSeriesGoods[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
        }


        //团购
        $GroupBuy = new GroupBuy();
        $where = [
            'gb.start_time' => ['elt', time()],
            'gb.end_time' => ['egt', time()],
            'g.is_on_sale' => 1,
        ];
        $count = $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->alias('gb')->where($where)->count('gb.goods_id'); // 查询满足要求的总记录数
        $Page = new Page($count, 2); // 实例化分页类 传入总记录数和每页显示的记录数
        $groupBuyList = $GroupBuy
            ->alias('gb')
            ->field('*,FROM_UNIXTIME(start_time,"%Y-%m-%d") as start_time,FROM_UNIXTIME(end_time,"%Y-%m-%d") as end_time,(FORMAT(buy_num%group_goods_num/group_goods_num,2)) as percent, CASE can_integral > 0  WHEN 1 THEN gb.price - g.exchange_integral ELSE gb.price END AS price , CASE buy_num >= goods_num  WHEN 1 THEN 1 ELSE 0 END AS is_sale_out, group_goods_num - buy_num%group_goods_num as people_num')
            ->with(['goods', 'specGoodsPrice'])
            ->join('__GOODS__ g', 'g.goods_id = gb.goods_id')
            ->where($where)
            ->order('gb.sort_order')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        foreach ($groupBuyList as $k => $v) {
            if (1 == $v['is_sale_out']) {
                $groupBuyList[$k]['percent'] = 1;
                $groupBuyList[$k]['people_num'] = 0;
            }
        }


        $time = NOW_TIME;
        $field = 'top1,top2,top3,top4,top5,top6,top7,top8,bg1';
        $icon = M('icon')->field($field)->where(array('from_time' => array('elt', $time), 'to_time' => array('egt', $time)))->find();
        if (!$icon) {
            $icon = M('icon')->field($field)->where(array('id' => 1))->find();
        }

        //是否弹出金卡提示框
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id);
        $user_info = $user_info['result'];
        $is_show_apply_jk = 0;
        if ($user_info['is_not_show_jk'] == 0) {
            $logic = new UsersLogic();
            $check_apply_customs = $logic->check_apply_customs($user_info['user_id']);
            if ($check_apply_customs) {
                $is_show_apply_jk = 1;
            }
        }


        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();


        //获取用户活动信息的数量
        $articleLogic = new ArticleLogic();
        $user_article_count = $articleLogic->getUserArticleCount();

        $data = array(
            'ad' => $ad,
            'getCartNum' => $getCartNum,//购物车
            'getNewGoods' => $getNewGoods,//新品
            'flashSaleList' => $flashSaleList,//抢购活动
            'getHotGoods' => $getHotGoods,//热门
            'getRecommendGoods' => $getRecommendGoods,//推荐
            'getSeriesGoods' => $getSeriesGoods,//热销
            'groupBuyList' => $groupBuyList,//团购
            'icon' => $icon,//logo
            'is_show_apply_jk' => $is_show_apply_jk,//是否弹出金卡
            'user_message_count' => $user_message_count,//用户信息的数量
            'user_article_count' => $user_article_count//用户活动信息的数量
        );

        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }

    /**
     * 主页（新）
     * @return \think\response\Json
     */
    public function indexNew()
    {
        $goodsController = new GoodsController();
        // 超值套装列表
        $seriesGoods = $goodsController->getSeriesGoodsList(11, 'array');
        // 团购商品列表
        $groupBuyGoods = $goodsController->getGroupBuyGoodsListNew(11, 'array');
        // 新品列表
        $newGoods = $goodsController->getNewGoodsList(20, 'array');
        // 促销商品
        $recommendGoods = $goodsController->getRecommendGoodsList(20, 'array', 1);
        // 热销商品
        $hotGoods = $goodsController->getHotGoodsList(20, 'array');

        $return = [
            'series_goods' => $seriesGoods,
            'groupBuy_goods' => $groupBuyGoods,
            'new_goods' => $newGoods,
            'recommend_goods' => $recommendGoods,
            'hot_goods' => $hotGoods
        ];
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }
}
