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

use app\common\logic\OssLogic;
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
                ->field('ad_code,ad_link,ad_name,need_login')
                ->where('pid', $position_id)
                ->where('enabled', 1)
                ->where('start_time', 'elt', NOW_TIME)
                ->where('end_time', 'egt', NOW_TIME)
                ->order('orderby DESC')
                ->select();
        }

        //购物车数量
        $getCartNum = M('Cart')->where('user_id', $this->user_id)->count('id');

        //新品
        $getNewGoods = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
            ->where('is_new', 1)
            ->where('is_on_sale', 1)
            ->where('is_abroad', 0)
            ->where('is_supply', 0)
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
            'fl.source' => ['LIKE', '%' . 1 . '%'],
            'g.is_on_sale' => 1,
            'g.is_abroad' => 0,
            'g.is_supply' => 0,
        ];
        $FlashSale = new FlashSale();
        $flash_sale_goods = $FlashSale->alias('fl')->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice', 'goods'])
            ->field('*,FROM_UNIXTIME(start_time,"%Y-%m-%d %H:%i:%s") as start_time,FROM_UNIXTIME(end_time,"%Y-%m-%d %H:%i:%s") as end_time,100*(FORMAT(buy_num/goods_num,2)) as percent, CASE can_integral > 0  WHEN 1 THEN fl.price - g.exchange_integral ELSE fl.price END AS price')
            ->where($where)
            ->page($p, 10)
            ->order(['fl.end_time' => 'asc'])
            ->select();
        $flashSaleList = array();
        $flashSaleList['flash_sale_goods'] = $flash_sale_goods;
        $flashSaleList['now'] = $now;

        //热门
        $getHotGoods = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
            ->where('is_hot', 1)
            ->where('is_on_sale', 1)
            ->where('is_abroad', 0)
            ->where('is_supply', 0)
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
                ->where('is_abroad', 0)
                ->where('is_supply', 0)
                ->where('goods_id', 'in', $goods_id)
                ->order('sort')
                ->select();
        } else {
            $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
                ->where('is_recommend', 1)
                ->where('is_on_sale', 1)
                ->where('is_abroad', 0)
                ->where('is_supply', 0)
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
            ->where('is_abroad', 0)
            ->where('is_supply', 0)
            ->count();

        $page = new Page($count, 2);

        $getSeriesGoods = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img,goods_remark')
            ->where('sale_type', 2)
            ->where('is_on_sale', 1)
            ->where('is_abroad', 0)
            ->where('is_supply', 0)
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
            'g.is_abroad' => 0,
            'g.is_supply' => 0,
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
        $seriesGoods = $goodsController->getSeriesGoodsList(10, 'array');
        // 团购商品列表
        $groupBuyGoods = $goodsController->getGroupBuyGoodsListNew(10, 'array');
        // 新品列表
        $newGoods = $goodsController->getNewGoodsList(10, 'array');
        // 促销商品
        $recommendGoods = $goodsController->getRecommendGoodsList(10, 'array', 1);
        // 热销商品
        $hotGoods = $goodsController->getHotGoodsList(10, 'array');

        $return = [
            'series_goods' => $seriesGoods,
            'groupBuy_goods' => $groupBuyGoods,
            'new_goods' => $newGoods,
            'recommend_goods' => $recommendGoods,
            'hot_goods' => $hotGoods
        ];
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 金刚区icon列表
     * @return \think\response\Json
     */
    public function icon()
    {
        // 一行icon个数
        $iconConfig = M('app_icon_config')->where(['type' => 'index'])->field('row_num, not_allow_tips')->find();
        // icon列表
        $appIcon = M('app_icon')->where(['type' => 'index', 'is_open' => 1])->order('sort DESC')->select();
        $iconList = [];
        foreach ($appIcon as $key => $icon) {
            $imgInfo = json_decode($icon['img'], true);
            $imgInfo['img'] = getFullPath($imgInfo['img']);
            $iconList[$key] = [
                'code' => $icon['code'],
                'name' => $icon['name'],
                'img' => $imgInfo,
                'is_allow' => (int)$icon['is_allow'],
                'tips' => $iconConfig['not_allow_tips'] ?? '功能尚未开放',
                'need_login' => 0,
                'target_param' => [
                    'applet_type' => '',
                    'applet_id' => '',
                    'applet_path' => '',
                    'cate_id' => $icon['cate_id']
                ]
            ];
            switch ($icon['code']) {
                case 'icon5':
                    // 韩国购
                    $iconList[$key]['is_allow'] = tpCache('basic.abroad_open') == 1 ? 1 : 0;
                    break;
                case 'icon8':
                    // SVIP专享
                    $iconList[$key]['need_login'] = 1;
                    break;
                case 'icon9':
                    // 小程序
                    $iconList[$key]['target_param']['applet_type'] = C('APPLET_TYPE');
                    $iconList[$key]['target_param']['applet_id'] = C('APPLET_ID');
                    $iconList[$key]['target_param']['applet_path'] = C('APPLET_PATH');
                    break;
                case 'icon14':
                    // 优选品牌
                    $iconList[$key]['target_param']['cate_id'] = '-1';
                    break;
            }
        }
        $return = [
            'config' => [
                'row_num' => $iconConfig['row_num'] ?? 4
            ],
            'list' => $iconList
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 首页数据（新）
     * @return \think\response\Json
     */
    public function indexData()
    {
        $ossLogic = new OssLogic();
        /*
         * part1：小程序宣传 商学院宣传
         */
        $appletPublicize = M('applet_config')->where(['type' => 'publicize'])->value('url');
        if (!empty($appletPublicize)) {
            $appletPublicize = explode(',', $appletPublicize);
            $appletPublicize = $ossLogic::url(substr($appletPublicize[0], strrpos($appletPublicize[0], 'img:') + 4));
        }
        $schoolPublicize = M('school_config')->where(['type' => 'publicize'])->value('url');
        if (!empty($schoolPublicize)) {
            $schoolPublicize = explode(',', $schoolPublicize);
            $schoolPublicize = $ossLogic::url(substr($schoolPublicize[0], strrpos($schoolPublicize[0], 'img:') + 4));
        }
        /*
         * part2：韩国购 SVIP宣传 促销商品 新品
         */
        $abroadGoods = M('goods')->where(['is_on_sale' => 1, 'is_abroad' => 1, 'is_agent' => 0, 'applet_on_sale' => 0])->order('sort DESC, goods_id DESC')->field('goods_id, original_img, shop_price exchange_price')->limit(0, 2)->select();
        foreach ($abroadGoods as &$goods) {
            $goods['original_img_new'] = getFullPath($goods['original_img']);
        }
        $recommendGoods = M('goods')->where(['is_on_sale' => 1, 'is_recommend' => 1, 'is_agent' => 0, 'applet_on_sale' => 0])->order('sort DESC, goods_id DESC')->field('goods_id, original_img, shop_price exchange_price')->limit(0, 2)->select();
        foreach ($recommendGoods as &$goods) {
            $goods['original_img_new'] = getFullPath($goods['original_img']);
        }
        $newGoods = M('goods')->where(['is_on_sale' => 1, 'is_new' => 1, 'is_agent' => 0, 'applet_on_sale' => 0])->order('sort DESC, goods_id DESC')->field('goods_id, original_img, shop_price exchange_price')->limit(0, 2)->select();
        foreach ($newGoods as &$goods) {
            $goods['original_img_new'] = getFullPath($goods['original_img']);
        }
        $svipPublicize = M('distribute_config')->where(['type' => 'svip_publicize'])->value('url');
        if (!empty($svipPublicize)) {
            $svipPublicize = explode(',', $svipPublicize);
            $svipPublicize = $ossLogic::url(substr($svipPublicize[0], strrpos($svipPublicize[0], 'img:') + 4));
        }
        /*
         * part3：VIP商品 主推商品
         */
        $vipPublicize = M('distribute_config')->where(['type' => 'vip_publicize'])->value('url');
        if (!empty($vipPublicize)) {
            $vipPublicize = explode(',', $vipPublicize);
            $vipPublicize = $ossLogic::url(substr($vipPublicize[0], strrpos($vipPublicize[0], 'img:') + 4));
        }
        $vipGoods = M('goods')->where(['is_on_sale' => 1, 'zone' => 3, 'distribut_id' => 2, 'is_agent' => 0, 'applet_on_sale' => 0])->order('sort DESC, goods_id DESC')->field('goods_id, original_img, shop_price exchange_price')->limit(0, 10)->select();
        foreach ($vipGoods as &$goods) {
            $goods['original_img_new'] = getFullPath($goods['original_img']);
        }
        $mainGoods = M('goods_recommend gr')->join('goods g', 'g.goods_id = gr.goods_id')
            ->where(['gr.is_open' => 1, 'g.is_on_sale' => 1, 'g.is_agent' => 0, 'g.applet_on_sale' => 0])
            ->order('gr.sort DESC, g.sort DESC, g.goods_id DESC')
            ->field('gr.goods_id, gr.image, gr.video, gr.video_cover, gr.video_axis, g.goods_name, g.shop_price, g.exchange_integral, g.sale_type')
            ->limit(0, 5)
            ->select();
        $mainGoodsIds = [];
        foreach ($mainGoods as &$goods) {
            $mainGoodsIds[] = $goods['goods_id'];
        }
        // 秒杀
        $flashSale = M('flash_sale fs')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => ['IN', $mainGoodsIds], 'fs.start_time' => ['<=', time()], 'fs.end_time' => ['>=', time()], 'fs.is_end' => 0])
            ->where(['fs.source' => ['LIKE', '%' . 3 . '%']])
            ->field('fs.goods_id, sgp.key spec_key, fs.price, fs.goods_num, fs.buy_limit, fs.start_time, fs.end_time, fs.can_integral')->select();
        // 团购
        $groupBuy = M('group_buy gb')
            ->join('spec_goods_price sgp', 'sgp.item_id = gb.item_id', 'LEFT')
            ->where(['gb.goods_id' => ['IN', $mainGoodsIds], 'gb.start_time' => ['<=', time()], 'gb.end_time' => ['>=', time()], 'gb.is_end' => 0])
            ->field('gb.goods_id, gb.price, sgp.key spec_key, gb.price, gb.group_goods_num, gb.goods_num, gb.buy_limit, gb.start_time, gb.end_time, gb.can_integral')->select();
        // 促销
        $promGoods = M('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['IN', $mainGoodsIds], 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.id prom_id, pg.title, pg.type, pg.expression, gtg.goods_id')->order('expression desc');
        if ($this->user) {
            $promGoods = $promGoods->where(['pg.group' => ['LIKE', '%' . $this->user['distribut_level'] . '%']]);
        }
        $promGoods = $promGoods->select();
        // 订单满减优惠
        $orderPromTitle = M('order_prom')
            ->where(['type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
            ->order('discount_price desc')->value('title');
        $mainGoodsTabs = M('goods_tab')->where(['goods_id' => ['IN', $mainGoodsIds], 'status' => 1])->field('goods_id, title')->select();
        foreach ($mainGoods as &$goods) {
            $goods['exchange_price'] = bcsub($goods['shop_price'], $goods['exchange_integral'], 2);
            $goods['tabs'] = [];
            if (!empty($goods['image'])) {
                $image = explode(',', $goods['image']);
                $goods['image'] = $ossLogic::url(substr($image[0], strrpos($image[0], 'url:') + 4));
            }
            if (!empty($goods['video'])) {
                $goods['video'] = $ossLogic::url($goods['video']);
                $goods['video_cover'] = $ossLogic::url($goods['video_cover']);
            }
            foreach ($mainGoodsTabs as $value) {
                if ($goods['goods_id'] == $value['goods_id']) {
                    $goods['tabs'][] = [
                        'title' => $value['title']
                    ];
                }
            }
            $goods['tags'] = [];
            if ($goods['sale_type'] == 2) {
                $goods['tags'][0] = ['type' => 'activity', 'title' => '套组'];
            }
            if (!empty($groupBuy)) {
                foreach ($groupBuy as $value) {
                    if ($goods['goods_id'] == $value['goods_id']) {
                        if ($value['can_integral'] == 0) {
                            $goods['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $goods['exchange_price'] = bcsub($value['price'], $goods['exchange_integral'], 2);
                        $goods['tags'][0]['type'] = 'activity';
                        $goods['tags'][0]['title'] = '团购';
                        break;
                    }
                }
            }
            if (!empty($flashSale)) {
                foreach ($flashSale as $value) {
                    if ($goods['goods_id'] == $value['goods_id']) {
                        if ($value['can_integral'] == 0) {
                            $goods['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $goods['exchange_price'] = bcsub($value['price'], $goods['exchange_integral'], 2);
                        $goods['tags'][0]['type'] = 'activity';
                        $goods['tags'][0]['title'] = '秒杀';
                        break;
                    }
                }
            }
            // 第二类，促销类
            if (!empty($promGoods)) {
                foreach ($promGoods as $value) {
                    if ($goods['goods_id'] == $value['goods_id']) {
                        $goods['goods_type'] = 'promotion';
                        switch ($value['type']) {
                            case 0:
                                // 打折
                                $goods['exchange_price'] = bcdiv(bcmul($goods['exchange_price'], $value['expression'], 2), 100, 2);
                                break;
                            case 1:
                                // 减价
                                $goods['exchange_price'] = bcsub($goods['exchange_price'], $value['expression'], 2);
                                break;
                        }
                        $goods['tags'][] = ['type' => 'promotion', 'title' => $value['title']];
                        break;
                    }
                }
                if (!isset($goods['tags'][1]) && !empty($orderPromTitle)) {
                    $goods['tags'][] = ['type' => 'promotion', 'title' => $orderPromTitle];
                }
            }
        }
        $return = [
            'applet' => [
                'image' => $appletPublicize ?? '',
                'applet_type' => C('APPLET_TYPE'),
                'applet_id' => C('APPLET_ID'),
                'applet_path' => C('APPLET_PATH')
            ],
            'school' => [
                'image' => $schoolPublicize ?? ''
            ],
            'abroad' => ['goods_list' => $abroadGoods ?? []],
            'recommend' => ['goods_list' => $recommendGoods ?? []],
            'new_' => ['goods_list' => $newGoods ?? []],
            'svip' => ['image' => $svipPublicize ?? ''],
            'vip' => [
                'image' => $vipPublicize ?? '',
                'goods_list' => $vipGoods ?? []
            ],
            'main' => ['goods_list' => $mainGoods ?? []]
        ];
        return json(['status' => 1, 'result' => $return, 'msg' => '']);
    }
}
