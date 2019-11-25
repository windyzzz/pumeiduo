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
use app\common\logic\CouponLogic;
use app\common\logic\FreightLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsPromFactory;
use app\common\logic\SearchWordLogic;
use app\common\logic\TaskLogic;
use app\common\model\Goods as GoodsModel;
use app\common\model\GroupBuy;
use app\common\model\SpecGoodsPrice;
use think\AjaxPage;
use think\Db;
use think\Hook;
use think\Page;
use think\Verify;
use think\Loader;
use think\Url;

class Goods extends Base
{
    public $user_id = 0;
    public $user = [];

    public function __construct()
    {
        parent::__construct();
        // header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Method:POST,GET');
        $user = session('user');
        if ($user) {
            $this->user = $user;
            $this->user_id = $user['user_id'];
        }
    }

    public function getShareImage()
    {
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);

        $user = session('user');
        $user_id = $user['user_id'];

        $goods_id = I('goods_id/d');
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id);
        //使用方法-------------------------------------------------
        //数据格式，如没有优惠券coupon_price值为0。
        $gData = [
            'pic' => './' . $goods['original_img'],
            'title' => $goods['goods_name'],
            'price' => $goods['shop_price'] - $goods['exchange_integral'],
            'point' => $goods['exchange_integral'],
            'original_price' => $goods['market_price'] == 0 ? $goods['shop_price'] : $goods['market_price'],
            'coupon_price' => 100.00,
            'user_name' => $user_id,
        ];

        $filename = 'public/images/qrcode/goods/goods_' . $user_id . '_' . $goods_id . '.png';

        if (!file_exists($filename)) {
            $this->scerweima($user_id, $goods['goods_id']);
        }

        //直接输出
        createSharePng($gData, $filename);
        exit;

    }

    private function scerweima($user_id, $goods_id)
    {
        Loader::import('phpqrcode', EXTEND_PATH);

        Url::root('/');
        $baseUrl = url('/', '', '', true);

        $url = $baseUrl . '/#/goods/goods_details?goods_id=' . $goods_id . '&cart_type=0&invite=' . $user_id;

        $value = $url;                  //二维码内容

        $errorCorrectionLevel = 'L';    //容错级别
        $matrixPointSize = 10;           //生成图片大小

        //生成二维码图片
        $filename = 'public/images/qrcode/goods/goods_' . $user_id . '_' . $goods_id . '.png';
        \QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 2);

    }

    /**
     * 商品详情
     * @return \think\response\Json
     */
    public function goodsInfo()
    {
        $goodsLogic = new GoodsLogic();
        $goods_id = I('goods_id/d');

        $goods = Db::name('goods')->where('goods_id', $goods_id)->field('goods_id, cat_id, extend_cat_id, goods_sn, goods_name, goods_type, goods_remark, goods_content, 
            brand_id, store_count, comment_count, market_price, shop_price, cost_price, give_integral, exchange_integral, original_img, limit_buy_num,
            is_on_sale, is_free_shipping, is_recommend, is_new, is_hot, is_virtual, virtual_indate, click_count, zone')->find();
        if (empty($goods) || (0 == $goods['is_on_sale']) || (1 == $goods['is_virtual'] && $goods['virtual_indate'] <= time())) {
            return json(['status' => 0, 'msg' => '该商品已经下架', 'result' => null]);
        }
        if ($this->user) {
            $goodsLogic->add_visit_log($this->user_id, $goods);
        }
        if ($goods['brand_id']) {
            $goods['brand_name'] = M('brand')->where('id', $goods['brand_id'])->getField('name');
        }
        // 处理显示金额
        if ($goods['exchange_integral'] != 0) {
            $goods['exchange_price'] = bcdiv(bcsub(bcmul($goods['shop_price'], 100), bcmul($goods['exchange_integral'], 100)), 100, 2);
        } else {
            $goods['exchange_price'] = $goods['shop_price'];
        }
        M('Goods')->where('goods_id', $goods_id)->save(['click_count' => $goods['click_count'] + 1]); //统计点击数

        $data = [];
        $data['goods'] = $goods;
        $data['point_rate'] = tpCache('shopping.point_rate');
        $data['freight_free'] = tpCache('shopping.freight_free'); // 全场满多少免运费
        $data['filter_spec'] = $goodsLogic->get_spec($goods_id); //规格参数
        $data['spec_goods_price'] = $goodsLogic->get_spec_price($goods_id); //规格参数价格
//        $data['navigate_goods'] = navigate_goods($goods_id, 1); // 面包屑导航
//        $data['commentStatistics'] = $goodsLogic->commentStatistics($goods_id); // 获取某个商品的评论统计
//        $data['goods_attribute'] = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
//        $data['goods_attr_list'] = M('GoodsAttr')->where('goods_id', $goods_id)->select(); // 查询商品属性表
        $data['goods_images_list'] = M('GoodsImages')->where('goods_id', $goods_id)->select(); //商品缩略图
        $data['siblings_cate'] = $goodsLogic->get_siblings_cate($goods['cat_id']); //相关分类
        $data['look_see'] = $goodsLogic->get_look_see($goods); //看了又看

        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        $data['is_enshrine'] = 0;
        $data['tabs'] = M('GoodsTab')->where('goods_id', $goods_id)->select();
        if ($this->user) {
            if (1 == $goodsLogic->isCollectGoods($this->user_id, $goods_id)) {
                $data['is_enshrine'] = 1;
            }
        }

        //输出到图片
//        createSharePng($gData,'code_png/php_code.png','share.png');

        //构建手机端URL
        // $ShareLink = urlencode("http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Goods&a=goodsInfo&id={$goods['goods_id']}");
        // $data['ShareLink'] = $ShareLink;


        //是否弹窗
        //弹窗内容
        //弹窗推荐人
        $data['is_alert'] = 0;
        $data['is_alert_title'] = '';
        $data['is_alert_content'] = '';
        $data['is_alert_referee'] = '';

//        if ($data['goods']['zone'] == 3) {
//            $data['is_alert'] = 1;
//            $article = M('article')->where(array('article_id' => 104))->field('title,content')->find();
//            $data['is_alert_title'] = $article['title'];
//            $data['is_alert_content'] = $article['content'];
//            $invite_uid = M('users')->where(array('user_id' => $this->user_id))->getField('invite_uid');
//            if ($invite_uid) {
//                $data['is_alert_referee'] = '推荐人会员号：' . $invite_uid;
//            }
//        }

        $goods_tao_grade = M('goods_tao_grade')
            ->alias('g')
            ->field('pg.type,pg.title,pg.id')
            ->where(array('g.goods_id' => $goods_id))
            ->join('prom_goods pg', 'g.promo_id = pg.id and pg.start_time <= ' . NOW_TIME . ' and pg.end_time >= ' . NOW_TIME . ' and pg.is_end = 0 and  pg.is_open = 1')
            ->select();
        if ($goods_tao_grade) {
            $type_arr = array(
                0 => '折扣',
                1 => '立减'
            );
            foreach ($goods_tao_grade as $k => $v) {
                $goods_tao_grade[$k]['type_name'] = $type_arr[$v['type']];
            }
        }
        // 活动商品
        $data['prom_goods'] = $goods_tao_grade;
        // 赠品
        $Pay = new \app\common\logic\Pay();
        $data['gift_goods'] = $Pay->gift2_goods($goods_id);

        return json(['status' => 1, 'msg' => 'success', 'result' => $data]);
    }

    /**
     * 商品详情（新）
     * @return \think\response\Json
     */
    public function goodsInfoNew()
    {
        $goods_id = I('goods_id/d', null);
        $itemId = I('item_id/d', null);
        if (!$goods_id) {
            return json(['status' => 0, 'msg' => '该商品已经下架', 'result' => null]);
        }
        $goods = Db::name('goods')->where('goods_id', $goods_id)->field('goods_id, cat_id, extend_cat_id, goods_sn, goods_name, goods_type, goods_remark, goods_content, 
            brand_id, store_count, comment_count, market_price, shop_price, cost_price, give_integral, exchange_integral, original_img, limit_buy_num,
            is_on_sale, is_free_shipping, is_recommend, is_new, is_hot, is_virtual, virtual_indate, click_count')->find();
        if (empty($goods) || (0 == $goods['is_on_sale']) || (1 == $goods['is_virtual'] && $goods['virtual_indate'] <= time())) {
            return json(['status' => 0, 'msg' => '该商品已经下架', 'result' => null]);
        }
        $goodsLogic = new GoodsLogic();
        if ($this->user) {
            // 用户浏览记录
            $goodsLogic->add_visit_log($this->user_id, $goods);
        }
        // 判断商品性质
        $flashSale = Db::name('flash_sale fs')->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => $goods_id, 'fs.start_time' => ['<=', time()], 'fs.end_time' => ['>=', time()], 'fs.is_end' => 0])
            ->field('fs.goods_id, sgp.key spec_key, fs.price, fs.goods_num, fs.buy_limit, fs.start_time, fs.end_time, fs.can_integral')->select();
        if (!empty($flashSale)) {
            // 秒杀商品
            $goods['nature'] = [
                'type' => 'flash_sale',
                'price' => $flashSale[0]['price'],
                'limit_num' => $flashSale[0]['goods_num'],
                'start_time' => $flashSale[0]['start_time'],
                'end_time' => $flashSale[0]['end_time']
            ];
            $extendGoodsSpec = ['type' => 'flash_sale', 'data' => $flashSale];
        } else {
            $groupBuy = Db::name('group_buy gb')->join('spec_goods_price sgp', 'sgp.item_id = gb.item_id', 'LEFT')
                ->where(['gb.goods_id' => $goods_id, 'gb.is_end' => 0, 'gb.start_time' => ['<=', time()], 'gb.end_time' => ['>=', time()]])
                ->field('gb.goods_id, gb.price, sgp.key spec_key, gb.price, gb.group_goods_num, gb.goods_num, gb.buy_limit, gb.start_time, gb.end_time, gb.can_integral')->select();
            if (!empty($groupBuy)) {
                // 团购商品
                $goods['nature'] = [
                    'type' => 'group_buy',
                    'price' => $groupBuy[0]['price'],
                    'group_goods_num' => $groupBuy[0]['group_goods_num'],
                    'limit_num' => bcdiv($groupBuy[0]['goods_num'], $groupBuy[0]['group_goods_num']),
                    'start_time' => $groupBuy[0]['start_time'],
                    'end_time' => $groupBuy[0]['end_time']
                ];
                $extendGoodsSpec = ['type' => 'group_buy', 'data' => $groupBuy];
            } else {
                $goods['nature'] = [];
            }
        }
        // 处理商品详情（抽取图片）
        $contentArr = explode('public', $goods['goods_content']);
        $contentImgArr = [];
        foreach ($contentArr as $key => $value) {
            if ($key == 0) {
                continue;
            }
            $contentImgArr[] = 'public' . explode('&quot;', $value)[0];
        }
        $goods['content_images_list'] = $contentImgArr;
        if ($goods['brand_id']) {
            // 品牌名称
            $goods['brand_name'] = M('brand')->where('id', $goods['brand_id'])->getField('name');
        }
        // 处理显示金额
        if ($goods['exchange_integral'] != 0) {
            $goods['exchange_price'] = bcdiv(bcsub(bcmul($goods['shop_price'], 100), bcmul($goods['exchange_integral'], 100)), 100, 2);
        } else {
            $goods['exchange_price'] = $goods['shop_price'];
        }
        // 商品标签
        $goodsTab = Db::name('goods_tab')->where(['goods_id' => $goods_id, 'status' => 1])->limit(0, 3)->field('tab_id, title')->select();
        if ($goodsTab) {
            $goods['tabs'] = $goodsTab;
        }
        $goods['goods_images_list'] = M('GoodsImages')->where('goods_id', $goods_id)->select(); //商品缩略图
        // 规格参数
        $specData = $goodsLogic->get_spec_new($goods_id, $itemId);
        $goods['spec'] = $specData['spec'];
        $defaultKey = $specData['default_key'];     // 默认显示规格
        // 规格参数价格
        $goods['spec_price'] = $goodsLogic->get_spec_price($goods_id);
        foreach ($goods['spec_price'] as $key => $spec) {
            $goods['spec_price'][$key]['exchange_integral'] = $goods['exchange_integral'];     // 是否能使用积分
            $goods['spec_price'][$key]['activity'] = [
                'type' => '',
                'price' => '',
                'group_goods_num' => '',
                'limit_num' => '',
                'buy_limit' => '',
                'start_time' => '',
                'end_time' => ''
            ];
        }
        if (!empty($extendGoodsSpec) && !empty($goods['spec_price'])) {
            $type = $extendGoodsSpec['type'];
            foreach ($extendGoodsSpec['data'] as $spec) {
                if (isset($goods['spec_price'][$spec['spec_key']])) {
                    // 是否能使用积分
                    if ($spec['can_integral'] == 0) {
                        $goods['spec_price'][$spec['spec_key']]['exchange_integral'] = 0;
                    }
                    // 替换价格
                    $goods['spec_price'][$spec['spec_key']]['price'] = $spec['price'];
                    $goods['spec_price'][$spec['spec_key']]['store_count'] = $spec['goods_num'];
                    switch ($type) {
                        case 'flash_sale':
                            $activity = [
                                'type' => 'flash_sale',
                                'price' => $spec['price'],
                                'limit_num' => $spec['goods_num'],
                                'buy_limit' => $spec['buy_limit'],
                                'start_time' => $spec['start_time'],
                                'end_time' => $spec['end_time']
                            ];
                            break;
                        case 'group_buy':
                            $activity = [
                                'type' => 'group_buy',
                                'price' => $spec['price'],
                                'group_goods_num' => $spec['group_goods_num'],
                                'limit_num' => bcdiv($spec['goods_num'], $spec['group_goods_num']),
                                'buy_limit' => $spec['buy_limit'],
                                'start_time' => $spec['start_time'],
                                'end_time' => $spec['end_time']
                            ];
                            break;
                        default:
                            $activity = [];
                    }
                    // 添加活动信息
                    $goods['spec_price'][$spec['spec_key']]['activity'] = $activity;
                    if (!empty($defaultKey) && $defaultKey == $spec['spec_key']) {
                        $goods['nature'] = $activity;
                    }
                }
            }
        }
        // 促销
        $goods['promotion'] = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => $goods_id, 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.id prom_id, pg.type, pg.title')->select();
        // 优惠券
        $couponLogic = new CouponLogic();
        $couponCurrency = $couponLogic->getCoupon(0);    // 通用优惠券
        $couponIds = [];
        foreach ($couponCurrency as $item) {
            $couponIds['not_coupon_id'][] = $item['coupon_id'];
        }
        $couponGoods = $couponLogic->getCoupon(null, $goods_id, '', $couponIds);    // 指定商品优惠券
        foreach ($couponGoods as $item) {
            $couponIds['not_coupon_id'][] = $item['coupon_id'];
        }
        $couponCate = $couponLogic->getCoupon(null, '', $goods['cat_id'], $couponIds);    // 指定分类优惠券
        $goods['coupon'] = array_merge_recursive($couponCurrency, $couponGoods, $couponCate);
        $goods['freight_free'] = tpCache('shopping.freight_free'); // 全场满多少免运费
        // 组装数据
        $result['goods'] = $goods;
        // 猜你喜欢
        $result['look_see'] = $goodsLogic->get_look_see($goods, $this->user_id); // 猜你喜欢
        // 用户收藏
        if ($this->user_id) {
            $goodsCollect = $goodsLogic->getCollectGoods($this->user_id);
        }
        $result['is_enshrine'] = 0;
        if (!empty($goodsCollect)) {
            foreach ($goodsCollect as $value) {
                if ($goods_id == $value['goods_id']) {
                    $result['is_enshrine'] = 1;
                    break;
                }
            }
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $result]);
    }

    public function getNewGoods()
    {
        // Hook::exec('app\\home\\behavior\\CheckAuth','run',$params);
        // $page = new Page($count,3);

        $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
            ->where('is_new', 1)
            ->where('is_on_sale', 1)
            // ->limit($page->firstRow.','.$page->listRows)
            ->order('sort')
            ->select();
        $goodsLogic = new GoodsLogic();
        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        foreach ($list as $k => $v) {
            $list[$k]['is_enshrine'] = 0;
            $list[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
            if (session('?user')) {
                if (1 == $goodsLogic->isCollectGoods(session('user')['user_id'], $v['goods_id'])) {
                    $list[$k]['is_enshrine'] = 1;
                }
            }
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $list]);
    }

    public function getRecommendGoods()
    {
        // Hook::exec('app\\home\\behavior\\CheckAuth','run',$params);
        // $page = new Page($count,3);

        $taskLogic = new TaskLogic();
        $task_info = $taskLogic->getTaskInfo();

        if ($task_info) {
            $goods_id = explode(',', $task_info['goods_id_list']);

            $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
                // ->where("is_recommend",1)
                ->where('is_on_sale', 1)
                ->where('goods_id', 'in', $goods_id)
                // ->limit($page->firstRow.','.$page->listRows)
                ->order('sort')
                ->select();
        } else {
            $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
                ->where('is_recommend', 1)
                ->where('is_on_sale', 1)
                // ->where("goods_id", 'in', $goods_id)
                // ->limit($page->firstRow.','.$page->listRows)
                ->order('sort')
                ->select();
        }

        $goodsLogic = new GoodsLogic();
        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        foreach ($list as $k => $v) {
            $list[$k]['is_enshrine'] = 0;
            $list[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
            if (session('?user')) {
                if (1 == $goodsLogic->isCollectGoods(session('user')['user_id'], $v['goods_id'])) {
                    $goods_list[$k]['is_enshrine'] = 1;
                }
            }
        }
        $task = [];
        $task['title'] = $task_info['title'] ?: '';
        $task['icon'] = $task_info['icon'] ?: '';
        $return['list'] = $list;
        $return['task_info'] = $task;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    public function getHotGoods()
    {
        // Hook::exec('app\\home\\behavior\\CheckAuth','run',$params);
        // $page = new Page($count,15);

        $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
            ->where('is_hot', 1)
            ->where('is_on_sale', 1)
            // ->limit($page->firstRow.','.$page->listRows)
            ->limit(9)
            ->order('sort')
            ->select();
        $goodsLogic = new GoodsLogic();
        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        foreach ($list as $k => $v) {
            $list[$k]['is_enshrine'] = 0;
            $list[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
            if (session('?user')) {
                if (1 == $goodsLogic->isCollectGoods(session('user')['user_id'], $v['goods_id'])) {
                    $goods_list[$k]['is_enshrine'] = 1;
                }
            }
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $list]);
    }

    public function getSeriesGoods()
    {
        // Hook::exec('app\\home\\behavior\\CheckAuth','run',$params);
        $count = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img,goods_remark')
            ->where('sale_type', 2)
            ->where('is_on_sale', 1)
            ->count();

        $page = new Page($count, 2);

        $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img,goods_remark')
            ->where('sale_type', 2)
            ->where('is_on_sale', 1)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('sort')
            ->select();
        $goodsLogic = new GoodsLogic();
        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        foreach ($list as $k => $v) {
            $list[$k]['is_enshrine'] = 0;
            $list[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
            if (session('?user')) {
                if (1 == $goodsLogic->isCollectGoods(session('user')['user_id'], $v['goods_id'])) {
                    $goods_list[$k]['is_enshrine'] = 1;
                }
            }
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $list]);
    }

    public function getLevelZone()
    {
        // Hook::exec('app\\home\\behavior\\CheckAuth','run',$params);
        $page = new Page($count, 10);

        $type = I('type', 2); // 2为普卡会员 3为网点会员

        $list = M('goods')->field('goods_name,goods_id,shop_price,exchange_integral,shop_price - exchange_integral as member_price,original_img')
            ->where('zone', 3)
            ->where('distribut_id', $type)
            ->where('is_on_sale', 1)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('sort')
            ->select();
        $goodsLogic = new GoodsLogic();
        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J
        foreach ($list as $k => $v) {
            $list[$k]['is_enshrine'] = 0;
            $list[$k]['tabs'] = M('GoodsTab')->where('goods_id', $v['goods_id'])->select();
            if (session('?user')) {
                if (1 == $goodsLogic->isCollectGoods(session('user')['user_id'], $v['goods_id'])) {
                    $goods_list[$k]['is_enshrine'] = 1;
                }
            }
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $list]);
    }

    /**
     * 用户收藏商品
     */
    public function collect_goods()
    {
        $goods_ids = I('goods_ids/a', []);
        if (empty($goods_ids)) {
            return json(['status' => 0, 'msg' => '请至少选择一个商品', 'result' => '']);
        }
        $goodsLogic = new GoodsLogic();
        $result = [];
        foreach ($goods_ids as $key => $val) {
            $result[] = $goodsLogic->collect_goods($this->user_id, $val);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->deleteByGoodsId($goods_ids);

        return json(['status' => 1, 'msg' => '已添加至我的收藏', 'result' => $result]);
    }

    /**
     * 用户批量收藏商品（新）
     * @return array|\think\response\Json
     */
    public function collect_goods_new()
    {
        $goodsIds = I('goods_ids', null);
        if (!$goodsIds) {
            return json(['status' => 0, 'msg' => '请至少选择一个商品', 'result' => '']);
        }
        // 收藏商品
        $goodsLogic = new GoodsLogic();
        $res = $goodsLogic->collect_goods_arr($this->user_id, $goodsIds);
        if ($res['status'] !== 1) {
            return json($res);
        }
        // 移除购物车
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->deleteByGoodsId($goodsIds);

        return json($res);
    }

    //查询商品是否参与活动
    public function activity()
    {
        $goods_id = input('goods_id/d'); //商品id
        $item_id = input('item_id/d'); //规格id
        $goods_num = input('goods_num/d'); //欲购买的商品数量
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id);
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            //这里会自动更新商品活动状态，所以商品需要重新查询
            if ($item_id) {
                $specGoodsPrice = SpecGoodsPrice::get($item_id);
                $goodsPromLogic = $goodsPromFactory->makeModule($goods, $specGoodsPrice);
            } else {
                $goodsPromLogic = $goodsPromFactory->makeModule($goods, null);
            }
            if ($goodsPromLogic->checkActivityIsAble()) {
                $goods = $goodsPromLogic->getActivityGoodsInfo();
                $goods['over_time'] = $goods['end_time'] - $goods['start_time'];
                $goods['end_timestamp'] = $goods['end_time'];
                $goods['end_time'] = date('m-d H:i:s', $goods['end_time']);
                $goods['start_time'] = date('m-d H:i:s', $goods['start_time']);
                $goods['activity_is_on'] = 1;
                $goods['use_integral'] = 0;
                if (1 == $goods['can_integral']) {
                    $goods['use_integral'] = $goods['exchange_integral'];
                    $goods['shop_price'] = $goods['shop_price'] - $goods['exchange_integral'];
                }

                return json(['status' => 1, 'msg' => '该商品参与活动', 'result' => ['goods' => $goods]]);
            }
            if (!empty($goods['price_ladder'])) {
                $goodsLogic = new GoodsLogic();
                $price_ladder = unserialize($goods['price_ladder']);
                $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
            }
            $goods['activity_is_on'] = 0;

            return json(['status' => 0, 'msg' => '该商品没有参与活动', 'result' => ['goods' => $goods]]);
        }
        if (!empty($goods['price_ladder'])) {
            $goodsLogic = new GoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }

        return json(['status' => 0, 'msg' => '该商品没有参与活动', 'result' => ['goods' => $goods]]);
    }

    /**
     * 获取可发货地址
     */
    public function getRegion()
    {
        $goodsLogic = new GoodsLogic();
        $region_list = $goodsLogic->getRegionList(); //获取配送地址列表
        $region_list['status'] = 1;

        return json($region_list);
    }

    /**
     * 商品列表页.
     */
    public function goodsList()
    {
        $key = md5($_SERVER['REQUEST_URI'] . I('start_price') . '_' . I('end_price'));
        $html = S($key);
        if (!empty($html)) {
            json(['status' => 1, 'msg' => 'success', 'result' => $html]);
        }
        $sort = I('get.sort', 'goods_id'); // 排序
        $sort_asc = I('get.sort_asc', 'asc'); // 排序
        $filter_param = []; // 筛选数组
        $id = I('get.id/d', 0); // 当前分类id
//        $brand_id = I('get.brand_id', 0);
//        $spec = I('get.spec', 0); // 规格
//        $attr = I('get.attr', ''); // 属性
//        $price = I('get.price', ''); // 价钱
//        $start_price = trim(I('post.start_price', '0')); // 输入框价钱
//        $end_price = trim(I('post.end_price', '0')); // 输入框价钱
//        if ($start_price && $end_price) {
//            // 如果输入框有价钱 则使用输入框的价钱
//            $price = $start_price . '-' . $end_price;
//        }
//        $coupon_id = I('coupon_id', 0, 'int');

        $filter_param['id'] = $id; //加入筛选条件中
//        $filter_param['coupon_id'] = $coupon_id;

        $prom_id = I('prom_id', 0, 'int');
        $prom_id && ($filter_param['prom_id'] = $prom_id); //加入筛选条件中

//        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
//        $spec && ($filter_param['spec'] = $spec); //加入筛选条件中
//        $attr && ($filter_param['attr'] = $attr); //加入筛选条件中
//        $price && ($filter_param['price'] = $price); //加入筛选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类

        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where('id', $id)->find(); // 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 筛选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson($id);
        $goods_where = ['is_on_sale' => 1, 'cat_id|extend_cat_id' => ['in', $cat_id_arr]];
        $filter_goods_id = Db::name('goods')
            ->where($goods_where)
            ->getField('goods_id', true);

//        // 过滤筛选的结果集里面找商品
//        if ($brand_id || $price) {// 品牌或者价格
//            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
//            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个筛选条件的结果 的交集
//        }
//        if ($spec) {// 规格
//            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
//            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_2); // 获取多个筛选条件的结果 的交集
//        }
//        if ($attr) {// 属性
//            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
//            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_3); // 获取多个筛选条件的结果 的交集
//        }
//
//        if ($coupon_id) {
//            $coupon_ids_list = M('goods_coupon')->field('goods_id')->where(['coupon_id' => $coupon_id])->getField('goods_id', true);
//            $filter_goods_id = array_intersect($filter_goods_id, $coupon_ids_list); // 获取多个筛选条件的结果 的交集
//        }
//
//        //活动优惠
//        if ($prom_id) {
//            $prom_ids_list = M('goods_tao_grade')->field('goods_id')->where(['promo_id' => $prom_id])->getField('goods_id', true);
//            $filter_goods_id = array_intersect($filter_goods_id, $prom_ids_list); // 获取多个筛选条件的结果 的交集
//        }

//        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'goodsList'); // 获取显示的筛选菜单
//        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'goodsList'); // 筛选的价格期间
//        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'goodsList'); // 获取指定分类下的筛选品牌
//        $filter_spec = $goodsLogic->get_filter_spec($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选规格
//        $filter_attr = $goodsLogic->get_filter_attr($filter_goods_id, $filter_param, 'goodsList', 1); // 获取指定分类下的筛选属性

        $count = count($filter_goods_id);
        $page = new Page($count, 20);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sort, $sort_asc, $page, $this->user_id);
        }

//        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $navigate_cat = navigate_goods($id); // 面包屑导航
        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
        $return['navigate_cat'] = $navigate_cat;
//        $return['goods_category'] = $goods_category;
//        $return['goods_images'] = $goods_images;  // 相册图片
//        $return['filter_menu'] = $filter_menu;  // 筛选菜单
//        $return['filter_spec'] = $filter_spec;  // 筛选规格
//        $return['filter_attr'] = $filter_attr;  // 筛选属性
//        $return['filter_brand'] = $filter_brand;  // 列表页筛选属性 - 商品品牌
//        $return['filter_price'] = $filter_price; // 筛选的价格期间
        $return['goodsCate'] = $goodsCate;
        $return['cateArr'] = $cateArr;
        $return['filter_param'] = $filter_param; // 筛选条件
        $return['cat_id'] = $id;
        $return['page'] = $page; // 赋值分页输出
        S($key, $return);

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
        // $html = $this->fetch();
        // S($key,$html);
        // return $html;
    }

    /**
     * 获取超值套组商品列表
     * @param integer $num 获取数量
     * @param string $output 输出格式，默认是json
     */
    public function getSeriesGoodsList($num = 20, $output = 'json')
    {
        $key = md5($_SERVER['REQUEST_URI'] . I('start_price') . '_' . I('end_price'));
        $html = S($key);
        if (!empty($html)) {
            json(['status' => 1, 'msg' => 'success', 'result' => $html]);
        }
        $sort = I('get.sort', 'goods_id'); // 排序
        $sort_asc = I('get.sort_asc', 'asc'); // 排序
        $filter_param = []; // 筛选数组
        $id = I('get.id/d', 0); // 当前分类id
        if ($id != 0) {
            $filter_param['id'] = $id; //加入筛选条件中
            $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
            // 分类菜单显示
            $goodsCate = M('GoodsCategory')->where('id', $id)->find(); // 当前分类
            //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
            $cateArr = $goodsLogic->get_goods_cate($goodsCate);
        }
        // 筛选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson($id);
        $goods_where = ['is_on_sale' => 1, 'sale_type' => 2, 'cat_id' => ['in', $cat_id_arr]];
        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField('goods_id', true);

        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sort, $sort_asc, $page, $this->user_id);
        }

        $navigate_cat = navigate_goods($id); // 面包屑导航
        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
        $return['navigate_cat'] = $navigate_cat;
        $return['goodsCate'] = $goodsCate;
        $return['cateArr'] = $cateArr;
        $return['filter_param'] = $filter_param; // 筛选条件
        $return['cat_id'] = $id;
        $return['page'] = $page; // 赋值分页输出
        S($key, $return);

        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            default:
                return $return['goods_list'];
        }
        // $html = $this->fetch();
        // S($key,$html);
        // return $html;
    }

    /**
     * 获取秒杀商品列表
     * @param string $output 输出格式，默认是json
     * @return array|\think\response\Json
     */
    public function getFlashSalesGoodsList($output = 'json')
    {
        $where = [
            'fs.start_time' => ['<=', time()],
            'fs.end_time' => ['>=', time()],
            'fs.is_end' => 0
        ];
        // 秒杀商品ID
        $filter_goods_id = Db::name('flash_sale fs')->join('goods g', 'g.goods_id = fs.goods_id')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where($where)->where(['g.is_on_sale' => 1])->getField('fs.goods_id', true);
        $count = count($filter_goods_id);
        $page = new Page($count, 20);
        // 秒杀商品
        $flashSaleGoods = Db::name('flash_sale fs')->join('goods g', 'g.goods_id = fs.goods_id')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => ['in', $filter_goods_id]])->field('fs.id prom_id, g.goods_id, fs.item_id, g.goods_sn, g.goods_name, g.original_img, g.exchange_integral, fs.price flash_sale_price, fs.title, sgp.key_name, fs.end_time, fs.can_integral')
            ->limit($page->firstRow . ',' . $page->listRows)->select();
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filter_goods_id], 'status' => 1])->limit($page->firstRow . ',' . $page->listRows)->select();
        $endTime = 0;
        foreach ($flashSaleGoods as $k => $v) {
            // 最近的结束时间
            if ($k == 0) {
                $endTime = $v['end_time'];
            }
            if ($endTime >= $v['end_time']) {
                $endTime = $v['end_time'];
            }
            unset($flashSaleGoods[$k]['end_time']);
            // 商品标签
            $flashSaleGoods[$k]['tabs'] = [];
            if (!empty($goodsTab)) {
                $tabCount = 0;
                foreach ($goodsTab as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $flashSaleGoods[$k]['tabs'][] = [
                            'tab_id' => $value['tab_id'],
                            'title' => $value['title'],
                            'status' => $value['status']
                        ];
                        $tabCount++;
                    }
                    if ($tabCount == 3) {
                        break;
                    }
                }
            }
        }
        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => ['now_time' => time(), 'end_time' => $endTime, 'goods_list' => $flashSaleGoods]]);
            default:
                return ['now_time' => time(), 'end_time' => $endTime, 'goods_list' => $flashSaleGoods];
        }
    }

    /**
     * 获取团购商品列表
     * @param integer $num 获取数量
     * @param string $output 输出格式，默认是json
     * @return array|false|\PDOStatement|string|\think\Collection|\think\response\Json
     */
    public function getGroupBuyGoodsList($num = 20, $output = 'json')
    {
        $key = md5($_SERVER['REQUEST_URI'] . I('start_price') . '_' . I('end_price'));
        $html = S($key);
        if (!empty($html)) {
            json(['status' => 1, 'msg' => 'success', 'result' => $html]);
        }
        $sort = I('get.sort', 'goods_id'); // 排序
        $sort_asc = I('get.sort_asc', 'asc'); // 排序
        $filter_param = []; // 筛选数组
        $id = I('get.id/d', 0); // 当前分类id
        if ($id != 0) {
            $filter_param['id'] = $id; //加入筛选条件中
            $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
            // 分类菜单显示
            $goodsCate = M('GoodsCategory')->where('id', $id)->find(); // 当前分类
            //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
            $cateArr = $goodsLogic->get_goods_cate($goodsCate);
        }
        // 筛选 品牌 规格 属性 价格
        $GroupBuy = new GroupBuy();
        $where = [
            'gb.start_time' => ['elt', time()],
            'gb.end_time' => ['egt', time()],
            'gb.is_end' => 0,
            'g.is_on_sale' => 1,
        ];
        // 查询满足要求的总记录数
        $filter_goods_id = $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->where($where)->order('gb.sort_order')->getField('g.goods_id', true);
        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        $goods_list = [];
        if ($count > 0) {
            $Goods = new GoodsModel();
            $goods_list = $Goods->with(['GroupBuyDetail' => function ($query) use ($filter_goods_id) {
                $query->alias('gb')->field('gb.*, FROM_UNIXTIME(start_time,"%Y-%m-%d") as start_time, FROM_UNIXTIME(end_time,"%Y-%m-%d") as end_time,
                (FORMAT(buy_num%group_goods_num/group_goods_num,2)) as percent, (FORMAT((goods_num - buy_num) / goods_num,2)) as num_percent, goods_num - buy_num as store_count, 
                CASE buy_num >= goods_num WHEN 1 THEN 1 ELSE 0 END AS is_sale_out, group_goods_num - buy_num%group_goods_num as people_num');
            }])
                ->where('goods_id', 'in', implode(',', $filter_goods_id))
                ->field('goods_id, cat_id, extend_cat_id, goods_sn, goods_name, goods_remark, goods_type, brand_id, store_count, comment_count, goods_remark,
                market_price, shop_price, cost_price, give_integral, exchange_integral, original_img, limit_buy_num, trade_type,
                is_on_sale, is_free_shipping, is_recommend, is_new, is_hot')
                ->order([$sort => $sort_asc])
                ->limit($page->firstRow . ',' . $page->listRows)
                ->select();
            $goods_list = collection($goods_list)->toArray();
            // 商品规格属性
            $goodsItem = Db::name('spec_goods_price')->where(['goods_id' => ['in', $filter_goods_id]])->limit($page->firstRow . ',' . $page->listRows)->group('goods_id')->getField('goods_id, item_id', true);

            foreach ($goods_list as $k => $v) {
                // 商品规格属性
                if (isset($goodsItem[$v['goods_id']])) {
                    $goods_list[$k]['item_id'] = $goodsItem[$v['goods_id']];
                } else {
                    $goods_list[$k]['item_id'] = 0;
                }
                $goods_list[$k]['group_buy'] = $v['group_buy'] = $v['group_buy_detail'];
                if ($goods_list[$k]['group_buy']['can_integral'] > 0) {
                    $integral = M('Goods')->where('goods_id', $goods_list[$k]['group_buy']['goods_id'])->getField('exchange_integral');
                    $goods_list[$k]['group_buy']['price'] = $goods_list[$k]['group_buy']['price'] - $integral;
                }
                if (!$v['group_buy']) {
                    unset($goods_list[$k]);
                }
                if (1 == $v['group_buy']['is_sale_out']) {
                    $goods_list[$k]['group_buy']['percent'] = 1;
                    $goods_list[$k]['group_buy']['people_num'] = 0;
                }
                // 处理显示金额
                if ($v['exchange_integral'] != 0) {
                    $goods_list[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
                } else {
                    $goods_list[$k]['exchange_price'] = $v['shop_price'];
                }
                unset($goods_list[$k]['group_buy_detail']);
            }
            $new_goods_list = [];
            if ('goods_id' == $sort) {
                foreach ($filter_goods_id as $fk => $fv) {
                    foreach ($goods_list as $gk => $gv) {
                        if ($gv['goods_id'] == $fv) {
                            $new_goods_list[] = $gv;
                            break;
                        }
                    }
                }
            }
            if ($new_goods_list) {
                $goods_list = $new_goods_list;
            }
        }
        $navigate_cat = navigate_goods($id); // 面包屑导航
        $return['goods_list'] = $goods_list;
        $return['navigate_cat'] = $navigate_cat;
        $return['goodsCate'] = $goodsCate ?? [];
        $return['cateArr'] = $cateArr ?? [];
        $return['filter_param'] = $filter_param; // 筛选条件
        $return['cat_id'] = $id;
        $return['page'] = $page; // 赋值分页输出
        S($key, $return);

        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            default:
                return $return['goods_list'];
        }
        // $html = $this->fetch();
        // S($key,$html);
        // return $html;
    }

    /**
     * 获取团购商品列表（新）
     * @param int $num
     * @param string $output
     * @return array|\think\response\Json
     */
    public function getGroupBuyGoodsListNew($num = 20, $output = 'json')
    {
        // 筛选 品牌 规格 属性 价格
        $GroupBuy = new GroupBuy();
        $where = [
            'gb.start_time' => ['elt', time()],
            'gb.end_time' => ['egt', time()],
            'gb.is_end' => 0,
            'g.is_on_sale' => 1,
        ];
        // 查询满足要求的总记录数
        $filter_goods_id = $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->where($where)->order('gb.sort_order')->getField('g.goods_id', true);
        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        $goods_data = [];
        if ($count > 0) {
            $Goods = new GoodsModel();
            $goods_list = $Goods->with(['GroupBuyDetail' => function ($query) use ($filter_goods_id) {
                $query->alias('gb')->field('gb.*, FROM_UNIXTIME(start_time,"%Y-%m-%d") as start_time, FROM_UNIXTIME(end_time,"%Y-%m-%d") as end_time,
                (FORMAT((goods_num - buy_num) / goods_num,2)) as num_percent, goods_num - buy_num as store_count, 
                CASE buy_num >= goods_num WHEN 1 THEN 1 ELSE 0 END AS is_sale_out, group_goods_num - buy_num%group_goods_num as people_num');
            }])
                ->where('goods_id', 'in', implode(',', $filter_goods_id))
                ->field('goods_id, cat_id, extend_cat_id, goods_sn, goods_name, goods_remark, goods_type, brand_id, store_count, comment_count, goods_remark,
                market_price, shop_price, cost_price, give_integral, exchange_integral, original_img, limit_buy_num, trade_type,
                is_on_sale, is_free_shipping, is_recommend, is_new, is_hot')
                ->limit($page->firstRow . ',' . $page->listRows)
                ->select();
            $goods_list = collection($goods_list)->toArray();

            foreach ($goods_list as $k => $v) {
                $goods_list[$k]['group_buy'] = $v['group_buy'] = $v['group_buy_detail'];
                if (!$v['group_buy']) {
                    unset($goods_list[$k]);
                }
                if (1 == $v['group_buy']['is_sale_out']) {
                    $goods_list[$k]['group_buy']['percent'] = 1;
                    $goods_list[$k]['group_buy']['people_num'] = 0;
                }
                $goods_list[$k]['group_buy']['original_img'] = $v['original_img'];
                $goods_list[$k]['group_buy']['goods_remark'] = $v['goods_remark'];
                $goods_list[$k]['group_buy']['groupBuy_price'] = $v['group_buy_detail']['price'];
                $goods_list[$k]['group_buy']['exchange_integral'] = $v['exchange_integral'];
                unset($goods_list[$k]['group_buy_detail']);
                $goods_data[] = $goods_list[$k]['group_buy'];
            }
        }
        $return['goods_list'] = $goods_data;

        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            default:
                return $return['goods_list'];
        }
    }

    /**
     * 获取推荐（促销）商品列表
     * @param $num
     * @param string $output
     * @return array|\think\response\Json
     */
    public function getRecommendGoodsList($num = '20', $output = 'json')
    {
        $key = md5($_SERVER['REQUEST_URI'] . I('start_price') . '_' . I('end_price'));
        $html = S($key);
        if (!empty($html)) {
            json(['status' => 1, 'msg' => 'success', 'result' => $html]);
        }

        $promId = I('get.prom_id', null);

        $sort = I('get.sort', 'goods_id'); // 排序
        $sort_asc = I('get.sort_asc', 'asc'); // 排序
        $filter_param = []; // 筛选数组
        $id = I('get.id/d', 0); // 当前分类id
        $filter_param['id'] = $id; //加入筛选条件中
        if ($id != 0) {
            $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
            // 分类菜单显示
            $goodsCate = M('GoodsCategory')->where('id', $id)->find(); // 当前分类
            //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
            $cateArr = $goodsLogic->get_goods_cate($goodsCate);
        }
        // 筛选 品牌 规格 属性 价格
//        $cat_id_arr = getCatGrandson($id);
//        $goods_where = ['is_on_sale' => 1, 'is_recommend' => 1, 'cat_id' => ['in', $cat_id_arr]];
//        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField('goods_id', true);
        $where = [
            'pg.is_open' => 1,
            'pg.is_end' => 0,
            'pg.end_time' => ['>=', time()]
        ];
        if ($promId) {
            $where['pg.id'] = $promId;
            $return['prom_title'] = Db::name('prom_goods')->where(['id' => $promId])->value('title');
        } else {
            $return['prom_title'] = '';
        }
        $filter_goods_id = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where($where)->getField('gtg.goods_id', true);
        $filter_goods_id = array_unique($filter_goods_id);

        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sort, $sort_asc, $page, $this->user_id);
        }

        $navigate_cat = navigate_goods($id); // 面包屑导航
        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
        $return['navigate_cat'] = $navigate_cat;
//        $return['goods_images'] = $goods_images;  // 相册图片
//        $return['filter_menu'] = $filter_menu;  // 筛选菜单
//        $return['filter_spec'] = $filter_spec;  // 筛选规格
//        $return['filter_attr'] = $filter_attr;  // 筛选属性
//        $return['filter_brand'] = $filter_brand;  // 列表页筛选属性 - 商品品牌
//        $return['filter_price'] = $filter_price; // 筛选的价格期间
        $return['goodsCate'] = $goodsCate ?? [];
        $return['cateArr'] = $cateArr ?? [];
        $return['filter_param'] = $filter_param; // 筛选条件
        $return['cat_id'] = $id;
        $return['page'] = $page; // 赋值分页输出
        S($key, $return);

        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            default:
                return $return['goods_list'];
        }
        // $html = $this->fetch();
        // S($key,$html);
        // return $html;
    }

    /**
     * 获取新商品列表
     * @param int $num
     * @param string $output
     * @return \think\response\Json
     */
    public function getNewGoodsList($num = 20, $output = 'json')
    {
        $filter_goods_id = M('goods')->where('is_new', 1)->where('is_on_sale', 1)->getField('goods_id', true);
        $filter_goods_id = array_unique($filter_goods_id);
        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        $goodsList = M('goods')->field('goods_id, goods_name, shop_price, exchange_integral, original_img')
            ->where('is_new', 1)
            ->where('is_on_sale', 1)
            ->order('sort')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        // 商品规格属性
        $goodsItem = Db::name('spec_goods_price')->where(['goods_id' => ['in', $filter_goods_id]])->limit($page->firstRow . ',' . $page->listRows)->group('goods_id')->getField('goods_id, item_id', true);
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filter_goods_id], 'status' => 1])->limit($page->firstRow . ',' . $page->listRows)->select();
        // 秒杀商品
        $flashSale = Db::name('flash_sale')->where(['goods_id' => ['in', $filter_goods_id]])
            ->where(['is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->limit($page->firstRow . ',' . $page->listRows)->field('goods_id')->select();
        // 团购商品
        $groupBuy = Db::name('group_buy')->where(['goods_id' => ['in', $filter_goods_id]])
            ->where(['is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->limit($page->firstRow . ',' . $page->listRows)->field('goods_id')->select();
        // 促销商品
        $promGoods = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['in', $filter_goods_id], 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.title, gtg.goods_id')->limit($page->firstRow . ',' . $page->listRows)->select();    // 促销活动
        $couponLogic = new CouponLogic();
        $couponCurrency = $couponLogic->getCoupon(0);    // 通用优惠券
        $couponGoods = [];
        $couponCate = [];
        if (empty($coupon)) {
            $couponGoods = $couponLogic->getCoupon(null, $filter_goods_id, null, ['limit' => ['offset' => $page->firstRow, 'length' => $page->listRows]]);    // 指定商品优惠券
            $filter_cat_id = Db::name('goods')->where(['goods_id' => ['in', $filter_goods_id]])->limit($page->firstRow . ',' . $page->listRows)->getField('cat_id', true);
            $couponCate = $couponLogic->getCoupon(null, null, $filter_cat_id, ['limit' => ['offset' => $page->firstRow, 'length' => $page->listRows]]);    // 指定分类优惠券
        }
        $promGoods = array_merge_recursive($promGoods, $couponCurrency, $couponGoods, $couponCate);
        // 循环处理数据
        foreach ($goodsList as $k => $v) {
            // 商品规格属性
            if (isset($goodsItem[$v['goods_id']])) {
                $goodsList[$k]['item_id'] = $goodsItem[$v['goods_id']];
            } else {
                $goodsList[$k]['item_id'] = 0;
            }
            // 商品标签
            $goodsList[$k]['tabs'] = [];
            if (!empty($goodsTab)) {
                $tabCount = 0;
                foreach ($goodsTab as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['tabs'][] = [
                            'tab_id' => $value['tab_id'],
                            'title' => $value['title'],
                            'status' => $value['status']
                        ];
                        $tabCount++;
                    }
                    if ($tabCount == 3) {
                        break;
                    }
                }
            }
            // 商品标识
            $goodsList[$k]['tags'] = [];
            // 第一类，活动类（优先级：秒杀” > ”团购“ > ”套组“ > “自营”）
            $goodsList[$k]['tags'][0] = ['type' => 'activity', 'title' => '自营'];
            if ($v['sale_type'] == 2) {
                $goodsList[$k]['tags'][0]['title'] = '套组';
            }
            if (!empty($groupBuy)) {
                foreach ($groupBuy as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['tags'][0]['title'] = '团购';
                        break;
                    }
                }
            }
            if (!empty($flashSale)) {
                foreach ($flashSale as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['tags'][0]['title'] = '秒杀';
                        break;
                    }
                }
            }
            // 第二类，促销类
            if (!empty($promGoods)) {
                foreach ($promGoods as $value) {
                    if (!isset($value['use_type'])) {
                        // 促销活动类
                        if ($v['goods_id'] == $value['goods_id']) {
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['title']];
                            break;
                        }
                    } else {
                        // 优惠券类
                        if ($value['use_type'] == 0) {
                            // 通用券
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        } elseif ($v['goods_id'] == $value['goods_id']) {
                            // 指定商品
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        } elseif ($v['cat_id'] == $value['cat_id']) {
                            // 指定分类
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        }
                    }
                }
            }
            // 第三类，默认
            $goodsList[$k]['tags'][] = ['type' => 'default', 'title' => '品牌直营'];

            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $goodsList[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
            } else {
                $goodsList[$k]['exchange_price'] = $v['shop_price'];
            }
        }
        $return['goods_list'] = $goodsList;
        $return['page'] = $page; // 赋值分页输出
        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            default:
                return $return['goods_list'];
        }
    }

    /**
     * 获取热销商品列表
     * @param int $num
     * @param string $output
     * @return \think\response\Json
     */
    public function getHotGoodsList($num = 20, $output = 'json')
    {
        $filter_goods_id = M('goods')->where('is_hot', 1)->where('is_on_sale', 1)->getField('goods_id', true);
        $filter_goods_id = array_unique($filter_goods_id);
        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        $goodsList = M('goods')->field('goods_id, goods_name, shop_price, exchange_integral, original_img')
            ->where('is_hot', 1)
            ->where('is_on_sale', 1)
            ->order('sort')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        // 商品规格属性
        $goodsItem = Db::name('spec_goods_price')->where(['goods_id' => ['in', $filter_goods_id]])->limit($page->firstRow . ',' . $page->listRows)->group('goods_id')->getField('goods_id, item_id', true);
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filter_goods_id], 'status' => 1])->limit($page->firstRow . ',' . $page->listRows)->select();
        // 秒杀商品
        $flashSale = Db::name('flash_sale')->where(['goods_id' => ['in', $filter_goods_id]])
            ->where(['is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->limit($page->firstRow . ',' . $page->listRows)->field('goods_id')->select();
        // 团购商品
        $groupBuy = Db::name('group_buy')->where(['goods_id' => ['in', $filter_goods_id]])
            ->where(['is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->limit($page->firstRow . ',' . $page->listRows)->field('goods_id')->select();
        // 促销商品
        $promGoods = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['in', $filter_goods_id], 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.title, gtg.goods_id')->limit($page->firstRow . ',' . $page->listRows)->select();    // 促销活动
        $couponLogic = new CouponLogic();
        $couponCurrency = $couponLogic->getCoupon(0);    // 通用优惠券
        $couponGoods = [];
        $couponCate = [];
        if (empty($coupon)) {
            $couponGoods = $couponLogic->getCoupon(null, $filter_goods_id, null, ['limit' => ['offset' => $page->firstRow, 'length' => $page->listRows]]);    // 指定商品优惠券
            $filter_cat_id = Db::name('goods')->where(['goods_id' => ['in', $filter_goods_id]])->limit($page->firstRow . ',' . $page->listRows)->getField('cat_id', true);
            $couponCate = $couponLogic->getCoupon(null, null, $filter_cat_id, ['limit' => ['offset' => $page->firstRow, 'length' => $page->listRows]]);    // 指定分类优惠券
        }
        $promGoods = array_merge_recursive($promGoods, $couponCurrency, $couponGoods, $couponCate);
        // 循环处理数据
        foreach ($goodsList as $k => $v) {
            // 商品规格属性
            if (isset($goodsItem[$v['goods_id']])) {
                $goodsList[$k]['item_id'] = $goodsItem[$v['goods_id']];
            } else {
                $goodsList[$k]['item_id'] = 0;
            }
            // 商品标签
            $goodsList[$k]['tabs'] = [];
            if (!empty($goodsTab)) {
                $tabCount = 0;
                foreach ($goodsTab as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['tabs'][] = [
                            'tab_id' => $value['tab_id'],
                            'title' => $value['title'],
                            'status' => $value['status']
                        ];
                        $tabCount++;
                    }
                    if ($tabCount == 3) {
                        break;
                    }
                }
            }
            // 商品标识
            $goodsList[$k]['tags'] = [];
            // 第一类，活动类（优先级：秒杀” > ”团购“ > ”套组“ > “自营”）
            $goodsList[$k]['tags'][0] = ['type' => 'activity', 'title' => '自营'];
            if ($v['sale_type'] == 2) {
                $goodsList[$k]['tags'][0]['title'] = '套组';
            }
            if (!empty($groupBuy)) {
                foreach ($groupBuy as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['tags'][0]['title'] = '团购';
                        break;
                    }
                }
            }
            if (!empty($flashSale)) {
                foreach ($flashSale as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['tags'][0]['title'] = '秒杀';
                        break;
                    }
                }
            }
            // 第二类，促销类
            if (!empty($promGoods)) {
                foreach ($promGoods as $value) {
                    if (!isset($value['use_type'])) {
                        // 促销活动类
                        if ($v['goods_id'] == $value['goods_id']) {
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['title']];
                            break;
                        }
                    } else {
                        // 优惠券类
                        if ($value['use_type'] == 0) {
                            // 通用券
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        } elseif ($v['goods_id'] == $value['goods_id']) {
                            // 指定商品
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        } elseif ($v['cat_id'] == $value['cat_id']) {
                            // 指定分类
                            $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        }
                    }
                }
            }
            // 第三类，默认
            $goodsList[$k]['tags'][] = ['type' => 'default', 'title' => '品牌直营'];

            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $goodsList[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
            } else {
                $goodsList[$k]['exchange_price'] = $v['shop_price'];
            }
        }
        $return['goods_list'] = $goodsList;
        $return['page'] = $page; // 赋值分页输出
        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
            default:
                return $return['goods_list'];
        }
    }

    /**
     *  查询配送地址，并执行回调函数.
     */
    public function region()
    {
        $fid = I('fid/d');
        $callback = I('callback');
        $parent_region = M('region2')->field('id,name')->where(['parent_id' => $fid])->cache(true)->select();

        echo $callback . '(' . json_encode($parent_region) . ')';
        exit;
    }

    /**
     * 商品物流配送和运费.
     */
    public function dispatching()
    {
        $goods_id = I('goods_id/d'); //143
        $region_id = I('region_id/d'); //28242
        $Goods = new \app\common\model\Goods();
        $goods = $Goods->cache(true)->where('goods_id', $goods_id)->find();
        $freightLogic = new FreightLogic();
        $freightLogic->setGoodsModel($goods);
        $freightLogic->setRegionId($region_id);
        $freightLogic->setGoodsNum(1);
        $isShipping = $freightLogic->checkShipping();
        if ($isShipping) {
            $freightLogic->doCalculation();
            $freight = $freightLogic->getFreight();
            $dispatching_data = ['status' => 1, 'msg' => '可配送', 'result' => ['freight' => $freight]];
        } else {
            $dispatching_data = ['status' => 0, 'msg' => '该地区不支持配送', 'result' => ''];
        }

        return json($dispatching_data);
    }

    /**
     * 商品搜索列表页.
     */
    public function search()
    {
        //C('URL_MODEL',0);
        $sort = I('sort', 'goods_id'); // 排序
        $sort_asc = I('sort_asc', 'asc'); // 排序
        $filter_param = []; // 筛选数组
        $id = I('get.id/d', 0); // 当前分类id
//        $brand_id = I('brand_id', 0);
//        $price = I('price', ''); // 价钱
//        $start_price = trim(I('start_price', '0')); // 输入框价钱
//        $end_price = trim(I('end_price', '0')); // 输入框价钱
//        if ($start_price && $end_price) {
//            // 如果输入框有价钱 则使用输入框的价钱
//            $price = $start_price . '-' . $end_price;
//        }
        $q = urldecode(trim(I('q', ''))); // 关键字搜索
        if (empty($q)) {
            return json(['status' => 0, 'msg' => '请输入搜索词', 'result' => null]);
        }
        $id && ($filter_param['id'] = $id); //加入筛选条件中
//        $brand_id && ($filter_param['brand_id'] = $brand_id); //加入筛选条件中
//        $price && ($filter_param['price'] = $price); //加入筛选条件中
        $q && ($_GET['q'] = $filter_param['q'] = $q); //加入筛选条件中
        $SearchWordLogic = new SearchWordLogic();
        $where = $SearchWordLogic->getSearchWordWhere($q);
        $where['is_on_sale'] = 1;
        // $where['exchange_integral'] = 0;//不检索积分商品
        // 搜索词被搜索数量+1
        Db::name('search_word')->where('keywords', $q)->setInc('search_num');
        // 搜索的商品数量
        $goodsHaveSearchWord = Db::name('goods')->where($where)->count();
        if ($goodsHaveSearchWord) {
            $SearchWordIsHave = Db::name('search_word')->where('keywords', $q)->find();
            if ($SearchWordIsHave) {
                Db::name('search_word')->where('id', $SearchWordIsHave['id'])->update(['goods_num' => $goodsHaveSearchWord]);
            } else {
                $SearchWordData = [
                    'keywords' => $q,
                    'pinyin_full' => $SearchWordLogic->getPinyinFull($q),
                    'pinyin_simple' => $SearchWordLogic->getPinyinSimple($q),
                    'search_num' => 1,
                    'goods_num' => $goodsHaveSearchWord,
                ];
                Db::name('search_word')->insert($SearchWordData);
            }
        }
        if ($id) {
            $cat_id_arr = getCatGrandson($id);
            $where['cat_id'] = ['in', implode(',', $cat_id_arr)];
        }
        $search_goods = M('goods')->where($where)->getField('goods_id,cat_id');
        $filter_goods_id = array_keys($search_goods);
        $filter_cat_id = array_unique($search_goods); // 分类需要去重
        if ($filter_cat_id) {
            $cateArr = M('goods_category')->where('id', 'in', implode(',', $filter_cat_id))->select();
            $tmp = $filter_param;
            foreach ($cateArr as $k => $v) {
                $tmp['id'] = $v['id'];
                $cateArr[$k]['href'] = U('/Home/Goods/search', $tmp);
            }
        }
//        // 过滤筛选的结果集里面找商品
//        if ($brand_id || $price) {
//            // 品牌或者价格
//            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id, $price); // 根据 品牌 或者 价格范围 查找所有商品id
//            $filter_goods_id = array_intersect($filter_goods_id, $goods_id_1); // 获取多个筛选条件的结果 的交集
//        }
//        $filter_menu = $goodsLogic->get_filter_menu($filter_param, 'search'); // 获取显示的筛选菜单
//        $filter_price = $goodsLogic->get_filter_price($filter_goods_id, $filter_param, 'search'); // 筛选的价格期间
//        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id, $filter_param, 'search'); // 获取指定分类下的筛选品牌

        $count = count($filter_goods_id);
        $page = new Page($count, 20);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sort, $sort_asc, $page, $this->user_id);
        }

        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
//        $return['goods_images'] = isset($goodsData) ? $goodsData['goods_images'] : [];  // 相册图片
//        $return['filter_menu'] = $filter_menu;  // 筛选菜单
//        $return['filter_brand'] = $filter_brand;  // 列表页筛选属性 - 商品品牌
//        $return['filter_price'] = $filter_price; // 筛选的价格期间
        $return['cateArr'] = $cateArr;
        $return['filter_param'] = $filter_param; // 筛选条件
        $return['cat_id'] = $id;
        $return['page'] = $page; // 赋值分页输出
        $return['q'] = I('q');
        C('TOKEN_ON', false);

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 商品咨询ajax分页.
     */
    public function ajax_consult()
    {
        $goods_id = I('goods_id/d', '0');
        $consult_type = I('consult_type', '0'); // 0全部咨询  1 商品咨询 2 支付咨询 3 配送 4 售后
        $where = ['parent_id' => 0, 'goods_id' => $goods_id, 'is_show' => 1];
        if ($consult_type > 0) {
            $where['consult_type'] = $consult_type;
        }
        $count = M('GoodsConsult')->where($where)->count();
        $page = new AjaxPage($count, 5);
        $show = $page->show();
        $consultList = M('GoodsConsult')->where($where)->order('id desc')->limit($page->firstRow . ',' . $page->listRows)->order('add_time desc')->select();
        foreach ($consultList as $key => $list) {
            $consultList[$key]['replyList'] = M('GoodsConsult')->where(['parent_id' => $list['id'], 'is_show' => 1])->order('add_time desc')->select();
        }
        $return['consultCount'] = $count; // 商品咨询数量
        $return['consultList'] = $consultList; // 商品咨询
        $return['page'] = $show; // 赋值分页输出
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 商品评论ajax分页.
     */
    public function ajaxComment()
    {
        $goods_id = I('goods_id/d', '0');
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        $where = ['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => 0];
        if (5 == $commentType) {
            $where['img'] = ['<>', ''];
        } else {
            $typeArr = ['1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2'];
            $where['ceil((deliver_rank + goods_rank + service_rank) / 3)'] = ['in', $typeArr[$commentType]];
        }
        $count = M('Comment')->where($where)->count();

        $page = new AjaxPage($count, 10);
        $show = $page->show();

        $list = M('Comment')->alias('c')->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')->where($where)->order('add_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

//        $replyList = M('Comment')->where(['is_show'=>1,'goods_id'=>$goods_id,'parent_id'=>['>',0]])->order("add_time desc")->select();

        foreach ($list as $k => $v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $replyList[$v['comment_id']] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order('add_time desc')->select();
        }
        $return['commentlist'] = $list; // 商品评论
        $return['replyList'] = $replyList; // 管理员回复
        $return['page'] = $show; // 赋值分页输出
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     *  商品咨询.
     */
    public function goodsConsult()
    {
        C('TOKEN_ON', true);
        $goods_id = I('goods_id/d', '0'); // 商品id
        $store_id = I('store_id/d', '0'); // 商品id
        $consult_type = I('consult_type', '1'); // 商品咨询类型
        $username = I('username', 'TPshop用户'); // 网友咨询
        $content = trim(I('content', '')); // 咨询内容
        if (strlen($content) > 500) {
            return json(['status' => 0, 'msg' => '咨询内容不得超过500字符！！', 'result' => null]);
        }
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), 'consult')) {
            return json(['status' => 0, 'msg' => '验证码错误', 'result' => null]);
        }
        $data = [
            'goods_id' => $goods_id,
            'consult_type' => $consult_type,
            'username' => $username,
            'content' => $content,
            'store_id' => $store_id,
            'is_show' => 1,
            'add_time' => time(),
        ];
        Db::name('goodsConsult')->add($data);

        return json(['status' => 1, 'msg' => '咨询已提交!', 'result' => null]);
    }

    /**
     * 加入购物车弹出.
     */
    public function open_add_cart()
    {
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 积分商城.
     */
    public function integralMall()
    {
        $cat_id = I('get.id/d');
        $minValue = I('get.minValue');
        $maxValue = I('get.maxValue');
        $brandType = I('get.brandType');
        $point_rate = tpCache('shopping.point_rate');
        $is_new = I('get.is_new', 0);
        $exchange = I('get.exchange', 0);
        $goods_where = [
            'is_on_sale' => 1,  //是否上架
            'is_virtual' => 0,
        ];
        //积分兑换筛选
        $exchange_integral_where_array = [['gt', 0]];
        // 分类id
        if (!empty($cat_id)) {
            $goods_where['cat_id'] = ['in', getCatGrandson($cat_id)];
        }
        //积分截止范围
        if (!empty($maxValue)) {
            array_push($exchange_integral_where_array, ['elt', $maxValue]);
        }
        //积分起始范围
        if (!empty($minValue)) {
            array_push($exchange_integral_where_array, ['egt', $minValue]);
        }
        //积分+金额
        if (1 == $brandType) {
            array_push($exchange_integral_where_array, ['exp', ' < shop_price* ' . $point_rate]);
        }
        //全部积分
        if (2 == $brandType) {
            array_push($exchange_integral_where_array, ['exp', ' = shop_price* ' . $point_rate]);
        }
        //新品
        if (1 == $is_new) {
            $goods_where['is_new'] = $is_new;
        }
        //我能兑换
        $user_id = cookie('user_id');
        if (1 == $exchange && !empty($user_id)) {
            $user_pay_points = intval(M('users')->where(['user_id' => $user_id])->getField('pay_points'));
            if (false !== $user_pay_points) {
                array_push($exchange_integral_where_array, ['lt', $user_pay_points]);
            }
        }

        $goods_where['exchange_integral'] = $exchange_integral_where_array;
        $goods_list_count = M('goods')->where($goods_where)->count();   //总页数
        $page = new Page($goods_list_count, 15);
        $goods_list = M('goods')->where($goods_where)->limit($page->firstRow . ',' . $page->listRows)->select();
        $goods_category = M('goods_category')->where(['level' => 1])->select();

        $return['goods_list'] = $goods_list;
        $return['page'] = $page->show();
        $return['goods_list_count'] = $goods_list_count;
        $return['goods_category'] = $goods_category; //商品1级分类
        $return['point_rate'] = $point_rate; //兑换率
        $return['nowPage'] = $page->nowPage; // 当前页
        $return['totalPages'] = $page->totalPages; //总页数
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 全部商品分类.
     *
     * @author lxl
     * @time17-4-18
     */
    public function all_category()
    {
        $goods_category_tree = get_goods_category_tree();
        $return['goods_category_tree'] = $goods_category_tree;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 全部品牌列表.
     *
     * @author lxl
     * @time17-4-18
     */
    public function all_brand()
    {
        $brand_list = M('brand')->cache(true)->field('id,name,parent_cat_id,logo,is_hot')->where('parent_cat_id>0')->select();
        $return['brand_list'] = $brand_list;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }
}
