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
        $params['user_token'] = $this->userToken;
        Hook::exec('app\\home\\behavior\\CheckAuth', 'run', $params);

        $user = $this->user;
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
        // 判断商品性质
        $flashSale = Db::name('flash_sale fs')
            ->join('goods g', 'g.goods_id = fs.goods_id')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => $goods_id, 'fs.start_time' => ['<=', time()], 'fs.end_time' => ['>=', time()]])
            ->where(['fs.source' => ['LIKE', $this->isApp ? '%' . 3 . '%' : '%' . 1 . '%']])
            ->field('fs.goods_id, sgp.key spec_key, fs.price, fs.goods_num, fs.buy_num, fs.order_num, fs.buy_limit, fs.start_time, fs.end_time, fs.can_integral, g.exchange_integral')->select();
        if (!empty($flashSale)) {
            // 秒杀商品
            // 商品参加活动数限制
            if ($flashSale[0]['goods_num'] <= $flashSale[0]['buy_num'] || $flashSale[0]['goods_num'] <= $flashSale[0]['order_num']) {
                $goods['nature'] = [];
            } else {
                $goods['nature'] = [
                    'price' => bcsub($flashSale[0]['price'], $flashSale[0]['can_integral'] == 0 ? 0 : $flashSale[0]['exchange_integral'], 2),
                    'exchange_integral' => $flashSale[0]['can_integral'] == 0 ? '0' : $flashSale[0]['exchange_integral']
                ];
            }
        } else {
            $goods['nature'] = [];
        }
        if (empty($goods['nature'])) {
            $groupBuy = Db::name('group_buy gb')
                ->join('goods g', 'g.goods_id = gb.goods_id')
                ->join('spec_goods_price sgp', 'sgp.item_id = gb.item_id', 'LEFT')
                ->where(['gb.goods_id' => $goods_id, 'gb.start_time' => ['<=', time()], 'gb.end_time' => ['>=', time()]])
                ->field('gb.goods_id, gb.price, sgp.key spec_key, gb.price, gb.group_goods_num, gb.goods_num, gb.buy_num, gb.order_num, gb.buy_limit, gb.start_time, gb.end_time, gb.can_integral, g.exchange_integral')->select();
            if (!empty($groupBuy)) {
                // 团购商品
                // 商品参加活动数限制
                if ($groupBuy[0]['goods_num'] <= $groupBuy[0]['buy_num'] || $groupBuy[0]['goods_num'] <= $groupBuy[0]['order_num']) {
                    $goods['nature'] = [];
                } else {
                    $goods['nature'] = [
                        'price' => bcsub($groupBuy[0]['price'], $groupBuy[0]['can_integral'] == 0 ? '0' : $groupBuy[0]['exchange_integral'], 2),
                        'exchange_integral' => $groupBuy[0]['can_integral'] == 0 ? '0' : $groupBuy[0]['exchange_integral']
                    ];
                }
            }
        }
        if (!empty($goods['nature'])) {
            $gData['price'] = $goods['nature']['price'];
            $gData['point'] = $goods['nature']['exchange_integral'];
        }

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

//        $Goods = new \app\common\model\Goods();
        $goods = Db::name('goods')->where('goods_id', $goods_id)->field('goods_id, cat_id, extend_cat_id, goods_sn, goods_name, goods_type, goods_remark, goods_content, 
            brand_id, store_count, comment_count, market_price, shop_price, cost_price, give_integral, exchange_integral, original_img, limit_buy_num, least_buy_num,
            is_on_sale, is_free_shipping, is_recommend, is_new, is_hot, is_virtual, virtual_indate, click_count, zone, prom_type, prom_id, commission, integral_pv')->find();
        if (empty($goods) || (0 == $goods['is_on_sale']) || (1 == $goods['is_virtual'] && $goods['virtual_indate'] <= time())) {
            return json(['status' => 0, 'msg' => '该商品已经下架', 'result' => null]);
        }
        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }
        if ($goods['brand_id']) {
            $goods['brand_name'] = M('brand')->where('id', $goods['brand_id'])->getField('name');
        }
        $goods_images_list = M('GoodsImages')->where('goods_id', $goods_id)->select(); // 商品 图册

        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where('goods_id', $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);
        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费
        $spec_goods_price = M('spec_goods_price')->where('goods_id', $goods_id)->getField('key,item_id,price,store_count'); // 规格 对应 价格 库存表
        M('Goods')->where('goods_id', $goods_id)->save(['click_count' => $goods['click_count'] + 1]); //统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id); // 获取某个商品的评论统计
        $point_rate = tpCache('shopping.point_rate');

        $look_see = $goodsLogic->get_look_see($goods);
        if ($this->user) {
            // 商品pv
            if ($this->user['distribut_level'] < 3) {
                $goods['integral_pv'] = '';
            } elseif ($goods['integral_pv'] == 0) {
                $goods['integral_pv'] = '';
            }
            // 商品佣金
            if ($this->user['distribut_level'] < 2) {
                $goods['commission'] = '';
            } elseif ($goods['commission'] == 0) {
                $goods['commission'] = '';
            } else {
                $goods['commission'] = bcdiv(bcmul(bcsub($goods['shop_price'], $goods['exchange_integral'], 2), $goods['commission'], 2), 100, 2);
            }
        } else {
            $goods['integral_pv'] = '';
            $goods['commission'] = '';
        }

        $data = [];
        $data['freight_free'] = $freight_free; // 全场满多少免运费
        $data['spec_goods_price'] = $spec_goods_price; // 规格 对应 价格 库存表
        $data['navigate_goods'] = navigate_goods($goods_id, 1); // 面包屑导航
        $data['commentStatistics'] = $commentStatistics; //评论概览
        $data['goods_attribute'] = $goods_attribute; //属性值
        $data['goods_attr_list'] = $goods_attr_list; //属性列表
        $data['filter_spec'] = $filter_spec; //规格参数
        $data['goods_images_list'] = $goods_images_list; //商品缩略图
        $data['siblings_cate'] = $goodsLogic->get_siblings_cate($goods['cat_id']); //相关分类
        $data['look_see'] = $look_see; //看了又看
        $data['goods'] = $goods;
        // 添加 is_enshrine  是否收藏字段 && 添加 tabs  商品标签字段 BY J

        $data['is_enshrine'] = 0;
        $data['tabs'] = M('GoodsTab')->where('goods_id', $goods_id)->select();
        if (session('?user')) {
            if (1 == $goodsLogic->isCollectGoods(session('user')['user_id'], $goods_id)) {
                $data['is_enshrine'] = 1;
            }
        }

        //输出到图片
//        createSharePng($gData,'code_png/php_code.png','share.png');

        //构建手机端URL
//         $ShareLink = urlencode("http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Goods&a=goodsInfo&id={$goods['goods_id']}");
//         $data['ShareLink'] = $ShareLink;
        $data['point_rate'] = $point_rate;

        //是否弹窗
        //弹窗内容
        //弹窗推荐人
        $data['is_alert'] = 0;
        $data['is_alert_title'] = '';
        $data['is_alert_content'] = '';
        $data['is_alert_referee'] = '';

        if ($data['goods']['zone'] == 3) {
            $data['is_alert'] = 1;
            $article = M('article')->where(array('article_id' => 104))->field('title,content')->find();
            $data['is_alert_title'] = $article['title'];
            $data['is_alert_content'] = $article['content'];
            $user = session('user');
            $invite_uid = M('users')->where(array('user_id' => $user['user_id']))->getField('invite_uid');
            if ($invite_uid) {
                $data['is_alert_referee'] = '推荐人会员号：' . $invite_uid;
            }
        }

        $goods_tao_grade = M('goods_tao_grade')
            ->alias('g')
            ->field('pg.type,pg.title,pg.id')
            ->where(array('g.goods_id' => $goods_id))
            ->join('prom_goods pg', 'g.promo_id = pg.id and pg.start_time <= ' . NOW_TIME . ' and pg.end_time >= ' . NOW_TIME . ' and pg.is_end = 0 and  pg.is_open =1 ')
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
        $data['prom_goods'] = $goods_tao_grade;
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
            brand_id, store_count, comment_count, market_price, shop_price, cost_price, give_integral, exchange_integral, original_img, limit_buy_num, least_buy_num,
            is_on_sale, is_free_shipping, is_recommend, is_new, is_hot, is_virtual, virtual_indate, click_count, zone, commission, integral_pv, is_abroad')->find();
        if (empty($goods) || (0 == $goods['is_on_sale']) || (1 == $goods['is_virtual'] && $goods['virtual_indate'] <= time())) {
            return json(['status' => 0, 'msg' => '该商品已经下架', 'result' => null]);
        }
        $goods['buy_limit'] = $goods['limit_buy_num'];  // 商品最大购买数量
        $goods['buy_least'] = $goods['least_buy_num'];  // 商品最低购买数量
        $zone = $goods['zone'];
        $goodsLogic = new GoodsLogic();
        // 判断商品性质
        $flashSale = Db::name('flash_sale fs')
            ->join('goods g', 'g.goods_id = fs.goods_id')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => $goods_id, 'fs.start_time' => ['<=', time()], 'fs.end_time' => ['>=', time()]])
            ->where(['fs.source' => ['LIKE', $this->isApp ? '%' . 3 . '%' : '%' . 1 . '%']])
            ->field('fs.goods_id, sgp.key spec_key, fs.price, fs.goods_num, fs.buy_num, fs.order_num, fs.buy_limit, fs.start_time, fs.end_time, fs.can_integral, g.exchange_integral')->select();
        if (!empty($flashSale)) {
            // 秒杀商品
            // 商品参加活动数限制
            if ($flashSale[0]['goods_num'] <= $flashSale[0]['buy_num'] || $flashSale[0]['goods_num'] <= $flashSale[0]['order_num']) {
                $goods['nature'] = [];
                $extendGoodsSpec = [];
            } else {
                $goods['nature'] = [
                    'type' => 'flash_sale',
                    'price' => $flashSale[0]['price'],
                    'limit_num' => $flashSale[0]['goods_num'],
                    'buy_limit' => $flashSale[0]['buy_limit'],
                    'start_time' => $flashSale[0]['start_time'],
                    'end_time' => $flashSale[0]['end_time'],
                    'now_time' => time() . '',
                    'exchange_integral' => $flashSale[0]['can_integral'] == 0 ? '0' : $flashSale[0]['exchange_integral']
                ];
                $extendGoodsSpec = ['type' => 'flash_sale', 'data' => $flashSale];
            }
        } else {
            $goods['nature'] = [];
        }
        if (empty($goods['nature'])) {
            $groupBuy = Db::name('group_buy gb')
                ->join('goods g', 'g.goods_id = gb.goods_id')
                ->join('spec_goods_price sgp', 'sgp.item_id = gb.item_id', 'LEFT')
                ->where(['gb.goods_id' => $goods_id, 'gb.start_time' => ['<=', time()], 'gb.end_time' => ['>=', time()]])
                ->field('gb.goods_id, gb.price, sgp.key spec_key, gb.price, gb.group_goods_num, gb.goods_num, gb.buy_num, gb.order_num, gb.buy_limit, gb.start_time, gb.end_time, gb.can_integral, g.exchange_integral')->select();
            if (!empty($groupBuy)) {
                // 团购商品
                // 商品参加活动数限制
                if ($groupBuy[0]['goods_num'] <= $groupBuy[0]['buy_num'] || $groupBuy[0]['goods_num'] <= $groupBuy[0]['order_num']) {
                    $goods['nature'] = [];
                    $extendGoodsSpec = [];
                } else {
                    $goods['nature'] = [
                        'type' => 'group_buy',
                        'price' => $groupBuy[0]['price'],
                        'group_goods_num' => $groupBuy[0]['group_goods_num'],
                        'limit_num' => bcdiv($groupBuy[0]['goods_num'], $groupBuy[0]['group_goods_num']),
                        'buy_limit' => $groupBuy[0]['buy_limit'],
                        'start_time' => $groupBuy[0]['start_time'],
                        'end_time' => $groupBuy[0]['end_time'],
                        'now_time' => time() . '',
                        'exchange_integral' => $groupBuy[0]['can_integral'] == 0 ? '0' : $groupBuy[0]['exchange_integral']
                    ];
                    $extendGoodsSpec = ['type' => 'group_buy', 'data' => $groupBuy];
                }
            }
        }
        if (!empty($goods['nature']) && in_array($goods['nature']['type'], ['group_buy', 'flash_sale'])) {
            $goods['buy_least'] = '0';
        }
        // 处理显示金额
        if ($goods['exchange_integral'] != 0) {
            $goods['exchange_price'] = bcsub($goods['shop_price'], $goods['exchange_integral'], 2);
        } else {
            $goods['exchange_price'] = $goods['shop_price'];
        }
        if ($this->user) {
            // 商品pv
            if ($this->user['distribut_level'] < 3) {
                $goods['integral_pv'] = '';
            } elseif ($goods['integral_pv'] == 0) {
                $goods['integral_pv'] = '';
            }
            // 商品佣金
            if ($this->user['distribut_level'] < 2) {
                $goods['commission'] = '';
            } elseif ($goods['commission'] == 0) {
                $goods['commission'] = '';
            } else {
                $goods['commission'] = bcdiv(bcmul(bcsub($goods['shop_price'], $goods['exchange_integral'], 2), $goods['commission'], 2), 100, 2);
            }
        } else {
            $goods['integral_pv'] = '';
            $goods['commission'] = '';
        }
        // 处理商品详情（抽取图片）
        $contentArr = explode('public', $goods['goods_content']);
        $contentImgArr = [];
        Url::root('/');
        $baseUrl = url('/', '', '', true);
        foreach ($contentArr as $key => $value) {
            if ($key == 0) {
                continue;
            }
            $imageUrl = 'public' . explode('&quot;', $value)[0];
            $imageIdentify = md5($imageUrl);
            $contentImage = M('goods_content_images')->where(['goods_id' => $goods_id, 'image_identify' => $imageIdentify])->find();
            if (!empty($contentImage)) {
                $contentImgArr[] = [
                    'url' => $imageUrl,
                    'width' => $contentImage['width'],
                    'height' => $contentImage['height']
                ];
            } else {
                $imageSize = getimagesize($baseUrl . $imageUrl);
                $contentImgArr[] = [
                    'url' => $imageUrl,
                    'width' => $imageSize[0] . '',
                    'height' => $imageSize[1] . ''
                ];
                M('goods_content_images')->add([
                    'goods_id' => $goods_id,
                    'image_identify' => $imageIdentify,
                    'width' => $imageSize[0],
                    'height' => $imageSize[1],
                    'type' => $imageSize['mime']
                ]);
            }
        }
        $goods['content_images_list'] = $contentImgArr;
        if ($goods['brand_id']) {
            // 品牌名称
            $goods['brand_name'] = M('brand')->where('id', $goods['brand_id'])->getField('name');
        }
        // 商品标签
        $goods['tabs'] = [];
        $goodsTab = Db::name('goods_tab')->where(['goods_id' => $goods_id, 'status' => 1])->limit(0, 3)->field('tab_id, title')->select();
        if ($goodsTab) {
            $goods['tabs'] = $goodsTab;
        }
        $goods['goods_images_list'] = M('GoodsImages')->where('goods_id', $goods_id)->select(); // 商品图片列表
        $goods['share_goods_image'] = !empty($goods['original_img']) ? $goods['original_img'] : ''; // 商品分享图
        // 规格参数
        $specData = $goodsLogic->get_spec_new($goods_id, $itemId);
        $goods['spec'] = $specData['spec'];
        $defaultKey = $specData['default_key'];     // 默认显示规格
        // 规格参数价格
        $goods['spec_price'] = $goodsLogic->get_spec_price($goods_id);
        foreach ($goods['spec_price'] as $key => $spec) {
            $goods['spec_price'][$key]['buy_limit'] = $goods['limit_buy_num'];
            $goods['spec_price'][$key]['exchange_integral'] = $goods['exchange_integral'];     // 是否能使用积分
            $goods['spec_price'][$key]['activity'] = [
                'type' => '',
                'price' => '',
                'group_goods_num' => '',
                'limit_num' => '',
                'buy_limit' => '',
                'start_time' => '',
                'end_time' => '',
                'now_time' => time() . '',
                'exchange_integral' => ''
            ];
        }
        if (empty($goods['spec']) || empty($goods['spec_price'])) {
            $goods['spec'] = ['0_0' => 1];
            $goods['spec_price'] = ['0_0' => 1];
        } elseif (!empty($extendGoodsSpec) && !empty($goods['spec_price'])) {
            $type = $extendGoodsSpec['type'];
            foreach ($extendGoodsSpec['data'] as $spec) {
                if (isset($goods['spec_price'][$spec['spec_key']])) {
                    // 是否能使用积分
                    if ($spec['can_integral'] == 0) {
                        $goods['spec_price'][$spec['spec_key']]['exchange_integral'] = '0';
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
                                'end_time' => $spec['end_time'],
                                'now_time' => time() . '',
                                'exchange_integral' => $spec['can_integral'] == 0 ? '0' : $goods['spec_price'][$spec['spec_key']]['exchange_integral']
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
                                'end_time' => $spec['end_time'],
                                'now_time' => time() . '',
                                'exchange_integral' => $spec['can_integral'] == 0 ? '0' : $goods['spec_price'][$spec['spec_key']]['exchange_integral']
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
        if ($zone == 3) {
            $goods['promotion'] = [];
            $goods['coupon'] = [];
        } else {
            // 促销
            $goods['promotion'] = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
                ->where(['gtg.goods_id' => $goods_id, 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
                ->field('pg.id prom_id, pg.type, pg.title')->select();
            // 优惠券
            $ext['not_type_value'] = [4, 5];
            $couponLogic = new CouponLogic();
            $couponCurrency = $couponLogic->getCoupon(0, null, null, $ext);    // 通用优惠券
            foreach ($couponCurrency as $item) {
                $ext['not_coupon_id'][] = $item['coupon_id'];
            }
            $couponGoods = $couponLogic->getCoupon(null, $goods_id, '', $ext);    // 指定商品优惠券
            foreach ($couponGoods as $k => $item) {
                $ext['not_coupon_id'][] = $item['coupon_id'];
            }
            if ($goods['cat_id'] == 0 && $goods['extend_cat_id'] == 0) {
                $couponCate = [];
            } else {
                $cateIds = [];
                if ($goods['cat_id'] != 0) {
                    $cateIds[] = $goods['cat_id'];
                }
                if ($goods['extend_cat_id'] != 0) {
                    $cateIds[] = $goods['extend_cat_id'];
                }
                $couponCate = $couponLogic->getCoupon(null, '', $cateIds, $ext);    // 指定分类优惠券
            }
            $goods['coupon'] = array_merge_recursive($couponCurrency, $couponGoods, $couponCate);
            if (!empty($goods['coupon'])) {
                // 查看用户是否拥有优惠券
                if ($this->user) {
                    foreach ($goods['coupon'] as $k => $coupon) {
                        if ($coupon['nature'] != 1) {
                            unset($goods['coupon'][$k]);
                            continue;
                        }
                        $userCoupon = Db::name('coupon_list')->where(['cid' => $coupon['coupon_id'], 'uid' => $this->user_id])->find();
                        if ($userCoupon) {
                            if ($userCoupon['status'] == 1 || $userCoupon['status'] == 2) {
                                // 已使用 或 已过期
                                unset($goods['coupon'][$k]);
                            } else {
                                $goods['coupon'][$k]['is_received'] = 1;
                            }
                        } else {
                            $goods['coupon'][$k]['is_received'] = 0;
                        }
                    }
                } else {
                    foreach ($goods['coupon'] as $k => $coupon) {
                        $goods['coupon'][$k]['is_received'] = 0;
                    }
                }
            }
            foreach ($goods['coupon'] as $k => $coupon) {
                if ($coupon['nature'] != 1) {
                    unset($goods['coupon'][$k]);
                    continue;
                }
                $res = $couponLogic->couponTitleDesc($coupon);
                if (empty($res)) {
                    unset($goods['coupon'][$k]);
                    continue;
                }
                $goods['coupon'][$k]['title'] = $res['title'];
                $goods['coupon'][$k]['desc'] = $res['desc'];
            }
            $goods['coupon'] = array_values($goods['coupon']);
        }
        unset($goods['zone']);

        $goods['freight_free'] = tpCache('shopping.freight_free'); // 全场满多少免运费
        $goods['qr_code'] = ''; // 分享二维码
        // 海外购物流流程图
        $goods['abroad_freight_process'] = [
            'url' => '',
            'width' => '',
            'height' => ''
        ];
        if ($goods['is_abroad'] == 1) {
            $process = M('abroad_config')->where(['type' => 'freight_process'])->value('content');
            if (!empty($process)) {
                $imageSize = getimagesize(SITE_URL . $process);
                $goods['abroad_freight_process'] = [
                    'url' => SITE_URL . $process,
                    'width' => $imageSize[0] . '',
                    'height' => $imageSize[1] . ''
                ];
            }
        }

        // 组装数据
        $result['goods'] = $goods;
        $result['can_cart'] = $zone == 3 ? 0 : 1;   // 能否加入购物车
        $result['is_enshrine'] = 0; // 是否收藏
        if ($this->user_id) {
            // 用户浏览记录
            $goodsLogic->add_visit_log($this->user_id, $goods);
            // 用户收藏
            $goodsCollect = $goodsLogic->getCollectGoods($this->user_id);
            if (!empty($goodsCollect)) {
                foreach ($goodsCollect as $value) {
                    if ($goods_id == $value['goods_id']) {
                        $result['is_enshrine'] = 1;
                        break;
                    }
                }
            }
            // 分享二维码
            $filename = 'public/images/qrcode/goods/goods_' . $this->user_id . '_' . $goods_id . '.png';
            if (!file_exists($baseUrl . $filename)) {
                $this->scerweima($this->user_id, $goods['goods_id']);
            }
            $result['goods']['qr_code'] = $baseUrl . $filename;
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $result]);
    }

    /**
     * 获取规格组合价格
     * @return \think\response\Json
     */
    public function calcSpecPrice()
    {
        $itemId = I('item_id', 0);
        $goodsPrice = M('spec_goods_price sgp')->join('goods g', 'g.goods_id = sgp.goods_id')
            ->field('sgp.goods_id, sgp.item_id, sgp.price shop_price, sgp.store_count, g.exchange_integral, sgp.prom_type, sgp.prom_id')
            ->where(['sgp.item_id' => $itemId])
            ->find();
        if (empty($goodsPrice)) return json(['status' => 0, 'msg' => '请传入正确的规格组合ID']);
        $isNormal = false;
        switch ($goodsPrice['prom_type']) {
            case 1:
                // 秒杀商品
                $flashSale = M('flash_sale')->where([
                    'goods_id' => $goodsPrice['goods_id'],
                    'item_id' => $goodsPrice['item_id'],
                    'start_time' => ['<', time()],
                    'end_time' => ['>', time()],
                    'source' => ['LIKE', $this->isApp ? '%' . 3 . '%' : '%' . 1 . '%']
                ])->field('price, buy_limit, can_integral')->find();
                if (empty($flashSale)) {
                    $isNormal = true;
                } else {
                    $goodsPrice['activity_type'] = 'flash_sale';
                    $goodsPrice['shop_price'] = $flashSale['price'];
                    $goodsPrice['buy_limit'] = $flashSale['buy_limit'];
                    if ($flashSale['can_integral'] == 0) {
                        $goodsPrice['exchange_integral'] = '0';
                    }
                }
                break;
            case 2:
                // 团购商品
                $groupBuy = M('group_buy')->where([
                    'goods_id' => $goodsPrice['goods_id'],
                    'item_id' => $goodsPrice['item_id'],
                    'start_time' => ['<', time()],
                    'end_time' => ['>', time()]
                ])->field('price, buy_limit, can_integral')->find();
                if (empty($groupBuy)) {
                    $isNormal = true;
                } else {
                    $goodsPrice['activity_type'] = 'group_buy';
                    $goodsPrice['shop_price'] = $groupBuy['price'];
                    $goodsPrice['buy_limit'] = $groupBuy['buy_limit'];
                    if ($groupBuy['can_integral'] == 0) {
                        $goodsPrice['exchange_integral'] = '0';
                    }
                }
                break;
            default:
                // 普通商品
                $isNormal = true;
        }
        if ($isNormal) {
            $goodsPrice['activity_type'] = 'normal';
            $goodsPrice['buy_limit'] = '0';
        }
        $goodsPrice['exchange_price'] = bcsub($goodsPrice['shop_price'], $goodsPrice['exchange_integral'], 2);
        unset($goodsPrice['prom_type']);
        unset($goodsPrice['prom_id']);
        return json(['status' => 1, 'result' => $goodsPrice]);
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

                if (isset($goods['can_integral']) && 1 == $goods['can_integral']) {
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
        $sort_asc = I('get.sort_asc', 'desc'); // 排序
        $sortArr = [];
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sortArr = [$sort => $sort_asc];
                break;
            case 'shop_price':
                // 价格
                $sortArr = ['shop_price - exchange_integral' => $sort_asc];
                break;
            case 'goods_id':
                // 新品
                $sortArr = [
                    'is_new' => $sort_asc,
                    'goods_id' => $sort_asc
                ];
                break;
        }
        $filter_param = []; // 筛选数组
        $id = I('get.id/d', 0); // 当前分类id
        $couponId = I('get.coupon_id', 0); // 优惠券ID
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
        if ($couponId != 0) {
            $filter_goods_id = Db::name('goods_coupon')->where(['coupon_id' => $couponId])->getField('goods_id', true);
        } else {
            $filter_goods_id = Db::name('goods')->where($goods_where)->getField('goods_id', true);
        }

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
        $page = new Page($count, 10);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sortArr, $page, $this->user_id);
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
     * 商品列表页（新）（包含搜索）
     */
    public function goodsListNew()
    {
        $sort = I('get.sort', 'sort'); // 排序
        $sort_asc = I('get.sort_asc', 'desc'); // 排序
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sort = 'sales_sum';
                break;
            case 'shop_price':
                // 价格
                $sort = 'shop_price - exchange_integral';
                break;
            case 'goods_id':
                // 新品
                $sort = 'is_new';
                break;
        }
        $sortArr = [$sort => $sort_asc];
        $id = I('get.id/d', 0); // 当前分类id
        $couponId = I('get.coupon_id', 0); // 优惠券ID
        $search = urldecode(trim(I('search', ''))); // 关键字搜索
        if (!empty($search)) {
            $SearchWordLogic = new SearchWordLogic();
            $where = $SearchWordLogic->getSearchWordWhere($search);
            $where['is_on_sale'] = 1;
            // 搜索词被搜索数量+1
            Db::name('search_word')->where('keywords', $search)->setInc('search_num');
            // 搜索的商品数量
            $goodsHaveSearchWord = Db::name('goods')->where($where)->count();
            if ($goodsHaveSearchWord) {
                $SearchWordIsHave = Db::name('search_word')->where('keywords', $search)->find();
                if ($SearchWordIsHave) {
                    Db::name('search_word')->where('id', $SearchWordIsHave['id'])->update(['goods_num' => $goodsHaveSearchWord]);
                } else {
                    $SearchWordData = [
                        'keywords' => $search,
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
                $where['cat_id|extend_cat_id'] = ['in', implode(',', $cat_id_arr)];
            }
            $search_goods = M('goods')->where($where)->getField('goods_id, cat_id');
            $filter_goods_id = array_keys($search_goods);
        } else {
            // 筛选
            $cat_id_arr = getCatGrandson($id);
            $goods_where = ['is_on_sale' => 1, 'cat_id|extend_cat_id' => ['in', $cat_id_arr]];
            if ($couponId != 0) {
                $filter_goods_id = Db::name('goods_coupon')->where(['coupon_id' => $couponId])->getField('goods_id', true);
            } else {
                $filter_goods_id = Db::name('goods')->where($goods_where)->getField('goods_id', true);
            }
        }
        // 数据
        $count = count($filter_goods_id);
        $page = new Page($count, 10);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sortArr, $page, $this->user_id);
        }
        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 获取超值套组商品列表
     * @param integer $num 获取数量
     * @param string $output 输出格式，默认是json
     */
    public function getSeriesGoodsList($num = 10, $output = 'json')
    {
        $key = md5($_SERVER['REQUEST_URI'] . I('start_price') . '_' . I('end_price'));
        $html = S($key);
        if (!empty($html)) {
            json(['status' => 1, 'msg' => 'success', 'result' => $html]);
        }
        $sort = I('get.sort', 'sort'); // 排序
        $sort_asc = I('get.sort_asc', 'desc'); // 排序
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sort = 'sales_sum';
                break;
            case 'shop_price':
                // 价格
                $sort = 'shop_price - exchange_integral';
                break;
            case 'goods_id':
                // 新品
                $sort = 'is_new';
                break;
        }
        $sortArr = [$sort => $sort_asc];
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
        $goods_where = ['is_on_sale' => 1, 'sale_type' => 2, 'cat_id' => ['in', $cat_id_arr], 'zone' => ['neq', 3], 'distribut_id' => 0];
        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField('goods_id', true);

        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sortArr, $page, $this->user_id);
        }

        $navigate_cat = navigate_goods($id); // 面包屑导航
        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
        $return['navigate_cat'] = $navigate_cat;
        $return['goodsCate'] = $goodsCate ?? '';
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
     * 获取秒杀商品列表
     * @param string $output 输出格式，默认是json
     * @return array|\think\response\Json
     */
    public function getFlashSalesGoodsList($output = 'json')
    {
        $sort = I('get.sort'); // 排序
        $sort_asc = I('get.sort_asc'); // 排序
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sort = 'fs.buy_num';
                break;
            case 'shop_price':
                // 价格
                $sort = 'fs.price';
                break;
            case 'goods_id':
                // 新品
                $sort = 'fs.start_time';
                break;
            default:
                $sort = 'fs.end_time';
                $sort_asc = 'asc';
        }
        $sortArr = [$sort => $sort_asc];
        $where = [
            'fs.start_time' => ['<=', time()],
            'fs.end_time' => ['>=', time()],
//            'fs.is_end' => 0,
            'source' => ['LIKE', $this->isApp ? '%' . 3 . '%' : '%' . 1 . '%']
        ];
        // 秒杀商品ID
        $filter_goods_id = Db::name('flash_sale fs')->join('goods g', 'g.goods_id = fs.goods_id')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where($where)->where(['g.is_on_sale' => 1])->getField('fs.goods_id', true);
        $count = count($filter_goods_id);
        $page = new Page($count, 10);
        // 秒杀商品
        $flashSaleGoods = Db::name('flash_sale fs')->join('goods g', 'g.goods_id = fs.goods_id')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where($where)
            ->where(['fs.goods_id' => ['in', $filter_goods_id]])->field('fs.id prom_id, g.goods_id, fs.item_id, g.goods_sn, g.goods_name, g.original_img, g.exchange_integral, fs.price flash_sale_price, fs.title, fs.goods_num, fs.buy_num, sgp.key_name, fs.end_time, fs.can_integral')
            ->limit($page->firstRow . ',' . $page->listRows)->order($sortArr)->select();
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filter_goods_id], 'status' => 1])->select();
        $endTime = 0;
        foreach ($flashSaleGoods as $k => $v) {
            // 商品参加活动数限制
            if ($v['goods_num'] <= $v['buy_num'] || $v['goods_num'] <= $v['order_num']) {
                unset($flashSaleGoods[$k]);
                continue;
            }
            $flashSaleGoods[$k]['key_name'] = $v['key_name'] ?? '';
            // 最近的结束时间
            if ($k == 0) {
                $endTime = $v['end_time'];
            }
            if ($endTime >= $v['end_time']) {
                $endTime = $v['end_time'];
            }
            unset($flashSaleGoods[$k]['end_time']);
            // 是否已售完
            if ($v['goods_num'] <= $v['buy_num']) {
                $flashSaleGoods[$k]['sold_out'] = 1;
            } else {
                $flashSaleGoods[$k]['sold_out'] = 0;
            }
            unset($flashSaleGoods[$k]['goods_num']);
            unset($flashSaleGoods[$k]['buy_num']);
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
            // 价格判断
            if ($v['can_integral'] == 0) {
                $flashSaleGoods[$k]['exchange_integral'] = 0;
                $flashSaleGoods[$k]['shop_price'] = $v['flash_sale_price'];
                $flashSaleGoods[$k]['exchange_price'] = $v['flash_sale_price'];
            } else {
                $flashSaleGoods[$k]['shop_price'] = bcsub($v['flash_sale_price'], $v['exchange_integral'], 2);
                $flashSaleGoods[$k]['exchange_price'] = bcsub($v['flash_sale_price'], $v['exchange_integral'], 2);
            }
            unset($flashSaleGoods[$k]['flash_sale_price']);
            unset($flashSaleGoods[$k]['can_integral']);
        }
        switch ($output) {
            case 'json':
                return json(['status' => 1, 'msg' => 'success', 'result' => ['now_time' => time() . '', 'end_time' => $endTime, 'goods_list' => $flashSaleGoods]]);
            default:
                return ['now_time' => time() . '', 'end_time' => $endTime, 'goods_list' => $flashSaleGoods];
        }
    }

    /**
     * 获取团购商品列表
     * @param integer $num 获取数量
     * @param string $output 输出格式，默认是json
     * @return array|false|\PDOStatement|string|\think\Collection|\think\response\Json
     */
    public function getGroupBuyGoodsList($num = 10, $output = 'json')
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
//            'gb.is_end' => 0,
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
            $goodsItem = Db::name('spec_goods_price')->where(['goods_id' => ['in', $filter_goods_id]])->group('goods_id')->getField('goods_id, item_id', true);

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
                    $goods_list[$k]['exchange_price'] = bcsub($v['shop_price'], $v['exchange_integral'], 2);
                } else {
                    $goods_list[$k]['exchange_price'] = $v['shop_price'];
                }

                // 价格判断
                if ($v['group_buy']['can_integral'] == 0) {
                    $goods_list[$k]['exchange_integral'] = '0.00';
                    $goods_list[$k]['shop_price'] = $v['group_buy_detail']['price'];
                    $goods_list[$k]['exchange_price'] = $v['group_buy_detail']['price'];
                } else {
                    $goods_list[$k]['shop_price'] = bcsub($v['group_buy_detail']['price'], $v['exchange_integral'], 2);
                    $goods_list[$k]['exchange_price'] = bcsub($v['group_buy_detail']['price'], $v['exchange_integral'], 2);
                }
                unset($goods_list[$k]['group_buy']['groupBuy_price']);
                unset($goods_list[$k]['group_buy']['can_integral']);
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
    public function getGroupBuyGoodsListNew($num = 10, $output = 'json')
    {
        // 筛选 品牌 规格 属性 价格
        $GroupBuy = new GroupBuy();
        $where = [
            'gb.start_time' => ['elt', time()],
            'gb.end_time' => ['egt', time()],
//            'gb.is_end' => 0,
            'g.is_on_sale' => 1,
        ];
        // 查询满足要求的总记录数
        $filter_groupBy_id = $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->where($where)->order('gb.sort_order')->getField('gb.id', true);
        $count = count($filter_groupBy_id);
        $page = new Page($count, $num);
        // 获取数据
        $groupBuyData = $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->where(['gb.id' => ['in', implode(',', $filter_groupBy_id)]])
            ->field('gb.*, FROM_UNIXTIME(gb.start_time,"%Y-%m-%d") as start_time, FROM_UNIXTIME(gb.end_time,"%Y-%m-%d") as end_time,
                (FORMAT((gb.goods_num - gb.buy_num) / gb.goods_num, 2)) as num_percent, gb.goods_num - gb.buy_num as store_count, 
                CASE gb.buy_num >= gb.goods_num WHEN 1 THEN 1 ELSE 0 END AS is_sale_out, gb.group_goods_num - gb.buy_num % gb.group_goods_num as people_num,
                g.original_img, g.goods_remark, g.shop_price, g.exchange_integral')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $groupBuyData = collection($groupBuyData)->toArray();
        foreach ($groupBuyData as $k => $groupBuy) {
            $groupBuyData[$k]['now_time'] = time() . '';
            // 价格判断
            if ($groupBuy['can_integral'] == 0) {
                $groupBuyData[$k]['exchange_integral'] = '0';
                $groupBuyData[$k]['shop_price'] = $groupBuy['price'];
                $groupBuyData[$k]['exchange_price'] = $groupBuy['price'];
            } else {
                $groupBuyData[$k]['shop_price'] = bcsub($groupBuy['price'], $groupBuy['exchange_integral']);
                $groupBuyData[$k]['exchange_price'] = bcsub($groupBuy['price'], $groupBuy['exchange_integral']);
            }
            unset($groupBuyData[$k]['can_integral']);
        }
        $return['goods_list'] = $groupBuyData;

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
     * @param int $taskId
     * @return array|\think\response\Json
     */
    public function getRecommendGoodsList($num = 20, $output = 'json', $taskId = 0)
    {
        $key = md5($_SERVER['REQUEST_URI'] . I('start_price') . '_' . I('end_price'));
        $html = S($key);
        if (!empty($html)) {
            json(['status' => 1, 'msg' => 'success', 'result' => $html]);
        }
        $sort = I('get.sort', 'sort'); // 排序
        $sort_asc = I('get.sort_asc', 'desc'); // 排序
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sort = 'sales_sum';
                break;
            case 'shop_price':
                // 价格
                $sort = 'shop_price - exchange_integral';
                break;
            case 'goods_id':
                // 新品
                $sort = 'is_new';
                break;
        }
        $sortArr = [$sort => $sort_asc];
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
        $cat_id_arr = getCatGrandson($id);
//        $goods_where = ['is_on_sale' => 1, 'is_recommend' => 1, 'cat_id' => ['in', $cat_id_arr]];
//        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField('goods_id', true);

        $promId = I('get.prom_id', null);
        if ($promId) {
            $return['prom_title'] = Db::name('prom_goods')->where(['id' => $promId])->value('title');
            $where = [
                'pg.id' => $promId,
                'pg.is_open' => 1,
//                'pg.is_end' => 0,
                'pg.end_time' => ['>=', time()],
                'g.is_on_sale' => 1,
                'g.cat_id' => ['in', $cat_id_arr]
            ];
            $filter_goods_id = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
                ->join('goods g', 'g.goods_id = gtg.goods_id')
                ->where($where)->getField('gtg.goods_id', true);
            $filter_goods_id = array_unique($filter_goods_id);
        } else {
            $return['prom_title'] = '';
            if ($taskId > 0) {
                $taskLogic = new TaskLogic($taskId);
                $task_info = $taskLogic->getTaskInfo();
                if ($task_info) {
                    $filter_goods_id = explode(',', $task_info['goods_id_list']);
                } else {
                    $where = [
                        'is_on_sale' => 1,
                        'is_recommend' => 1,
                        'zone' => ['neq', 3],
                        'distribut_id' => 0
                    ];
                    $filter_goods_id = Db::name('goods')->where($where)->getField('goods_id', true);
                    $filter_goods_id = array_unique($filter_goods_id);
                }
            } else {
                $where = [
                    'is_on_sale' => 1,
                    'is_recommend' => 1,
                    'zone' => ['neq', 3],
                    'distribut_id' => 0
                ];
                $filter_goods_id = Db::name('goods')->where($where)->getField('goods_id', true);
                $filter_goods_id = array_unique($filter_goods_id);
            }
        }
        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sortArr, $page, $this->user_id);
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
        $sort = I('get.sort', 'sort'); // 排序
        $sort_asc = I('get.sort_asc', 'desc'); // 排序
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sort = 'sales_sum';
                break;
            case 'shop_price':
                // 价格
                $sort = 'shop_price - exchange_integral';
                break;
            case 'goods_id':
                // 新品
                $sort = 'is_new';
                break;
        }
        $sortArr = [$sort => $sort_asc];
        $filter_goods_id = M('goods')->where('is_new', 1)->where('is_on_sale', 1)->getField('goods_id', true);
        $filter_goods_id = array_unique($filter_goods_id);
        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sortArr, $page, $this->user_id);
        }
        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
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
        $sort = I('get.sort', 'sort'); // 排序
        $sort_asc = I('get.sort_asc', 'desc'); // 排序
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sort = 'sales_sum';
                break;
            case 'shop_price':
                // 价格
                $sort = 'shop_price - exchange_integral';
                break;
            case 'goods_id':
                // 新品
                $sort = 'is_new';
                break;
        }
        $sortArr = [$sort => $sort_asc];
        $filter_goods_id = M('goods')->where('is_hot', 1)->where('is_on_sale', 1)->getField('goods_id', true);
        $filter_goods_id = array_unique($filter_goods_id);
        $count = count($filter_goods_id);
        $page = new Page($count, $num);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sortArr, $page, $this->user_id);
        }
        $return['goods_list'] = isset($goodsData) ? $goodsData['goods_list'] : [];
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
        $sortArr = [];
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sortArr = [$sort => $sort_asc];
                break;
            case 'shop_price':
                // 价格
                $sortArr = ['shop_price - exchange_integral' => $sort_asc];
                break;
            case 'goods_id':
                // 新品
                $sortArr = [
                    'is_new' => $sort_asc,
                    'goods_id' => $sort_asc
                ];
                break;
        }
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
        $page = new Page($count, 10);
        if ($count > 0) {
            // 获取商品数据
            $goodsLogic = new GoodsLogic();
            $goodsData = $goodsLogic->getGoodsList($filter_goods_id, $sortArr, $page, $this->user_id);
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
     * 模糊搜索列表
     * @return \think\response\Json
     */
    public function searchList()
    {
        $keyword = I('search', '');
        $keyword = trim($keyword);
        if (!$keyword) {
            return json(['status' => 1, 'result' => ['search_list' => []]]);
        }
        $keyword = explode(' ', $keyword);
        $searchList = [];
        $searchWordLogic = new SearchWordLogic();
        foreach ($keyword as $key) {
            $where = $searchWordLogic->getSearchWordWhere($key, 3);
            $where['is_on_sale'] = 1;
            $searchGoods = M('goods')->where($where)->getField('goods_id, goods_name', true);
            foreach ($searchGoods as $goodsId => $goodsName) {
                $searchList[] = [
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'mark_field' => $key
                ];
            }
        }
        $return = [
            'search_list' => $searchList
        ];
        return json(['status' => 1, 'result' => $return]);
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

    /**
     * 猜你喜欢
     * @return \think\response\Json
     */
    public function look_see()
    {
        $lookSee = (new GoodsLogic())->get_look_see([], $this->user_id);
        $filterGoodsIds = [];
        foreach ($lookSee as $item) {
            $filterGoodsIds[] = $item['goods_id'];
        }
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filterGoodsIds], 'status' => 1])->select();
        // 秒杀商品
        $flashSale = Db::name('flash_sale')->where([
            'goods_id' => ['in', $filterGoodsIds],
            'source' => ['LIKE', $this->isApp ? '%' . 3 . '%' : '%' . 1 . '%']
        ])->where(['start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->field('goods_id')->select();
        // 团购商品
        $groupBuy = Db::name('group_buy')->where(['goods_id' => ['in', $filterGoodsIds]])
            ->where(['start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->field('goods_id')->select();
        // 促销商品
        $promGoods = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['in', $filterGoodsIds], 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.title, gtg.goods_id')->select();    // 促销活动
        $couponLogic = new CouponLogic();
        $couponCurrency = $couponLogic->getCoupon(0);    // 通用优惠券
        $couponGoods = [];
        $couponCate = [];
        if (empty($coupon)) {
            $couponGoods = $couponLogic->getCoupon(null, $filterGoodsIds);    // 指定商品优惠券
            $filter_cat_id = Db::name('goods')->where(['goods_id' => ['in', $filterGoodsIds]])->getField('cat_id', true);
            $couponCate = $couponLogic->getCoupon(null, '', $filter_cat_id, null);    // 指定分类优惠券
        }
        $promGoods = array_merge_recursive($promGoods, $couponCurrency, $couponGoods, $couponCate);
        // 循环处理数据
        foreach ($lookSee as $k => $v) {
            // 商品标签
            $lookSee[$k]['tabs'] = [];
            if (!empty($goodsTab)) {
                foreach ($goodsTab as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $lookSee[$k]['tabs'][] = [
                            'tab_id' => $value['tab_id'],
                            'title' => $value['title'],
                            'status' => $value['status']
                        ];
                    }
                }
            }
            // 商品标识
            $lookSee[$k]['tags'] = [];
            // 第一类，活动类（优先级：秒杀” > ”团购“ > ”套组“ > “自营”）
//            $lookSee[$k]['tags'][0] = ['type' => 'activity', 'title' => '自营'];
            if ($v['sale_type'] == 2) {
                $lookSee[$k]['tags'][0] = ['type' => 'activity', 'title' => '套组'];
            }
            if (!empty($groupBuy)) {
                foreach ($groupBuy as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $lookSee[$k]['tags'][0]['title'] = '团购';
                        break;
                    }
                }
            }
            if (!empty($flashSale)) {
                foreach ($flashSale as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $lookSee[$k]['tags'][0]['title'] = '秒杀';
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
                            $lookSee[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['title']];
                            break;
                        }
                    } else {
                        // 优惠券类
                        if ($value['use_type'] == 0) {
                            // 通用券
                            $lookSee[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        } elseif ($v['goods_id'] == $value['goods_id']) {
                            // 指定商品
                            $lookSee[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        } elseif ($v['cat_id'] == $value['cat_id']) {
                            // 指定分类
                            $lookSee[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['name']];
                            break;
                        }
                    }
                }
            }
            // 第三类，默认
            $lookSee[$k]['tags'][] = ['type' => 'default', 'title' => '品牌直营'];
        }
        return json(['status' => 1, 'result' => $lookSee]);
    }

    /**
     * 升级套餐列表
     * @return \think\response\Json
     */
    public function levelGoodsList()
    {
        $type = I('type', 2); // 2为普卡会员 3为网点会员
        $count = M('goods')->where(['zone' => 3, 'distribut_id' => $type, 'is_on_sale' => 1])->count('goods_id');
        $page = new Page($count, 10);
        $list = M('goods')
            ->where(['zone' => 3, 'distribut_id' => $type, 'is_on_sale' => 1])
            ->field('goods_id, goods_name, shop_price, exchange_integral, original_img')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('sort')
            ->select();
        foreach ($list as $k => $v) {
            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $list[$k]['exchange_price'] = bcsub($v['shop_price'], $v['exchange_integral'], 2);
            } else {
                $list[$k]['exchange_price'] = $v['shop_price'];
            }
        }
        return json(['status' => 1, 'result' => $list]);
    }

    /**
     * 是否开启海外购
     * @return \think\response\Json
     */
    public function abroadStatus()
    {
        if (tpCache('basic.abroad_open') == 1) {
            return json(['status' => 1, 'result' => ['state' => 1, 'title' => '']]);
        } else {
            return json(['status' => 1, 'result' => ['state' => 0, 'title' => '功能尚未开启']]);
        }
    }

    /**
     * 海外购商品分类
     * @return \think\response\Json
     */
    public function abroadCate()
    {
        $cateId = I('cate_id', 0);
        $cateList = M('goods_category gc1')
            ->join('goods_category gc2', 'gc1.id = gc2.parent_id')
            ->join('goods_category gc3', 'gc2.id = gc3.parent_id')
            ->where([
                'gc1.name' => ['LIKE', '%海外购%'],
                'gc1.parent_id' => 0,
                'gc1.is_show' => 1,
                'gc2.is_show' => 1,
                'gc3.is_show' => 1,
            ])
            ->order('gc3.sort_order DESC')->field('gc3.id cate_id, gc3.name')->select();
        $cateItem = [
            'cate_id' => '-1',
            'name' => '精选'
        ];
        array_unshift($cateList, $cateItem);
        foreach ($cateList as $k => $v) {
            if ($cateId == 0 && $k == 0) {
                $cateList[$k]['is_selected'] = 1;
            } elseif ($cateId == $v['cate_id']) {
                $cateList[$k]['is_selected'] = 1;
            } else {
                $cateList[$k]['is_selected'] = 0;
            }
        }
        return json(['status' => 1, 'result' => ['cate_list' => $cateList]]);
    }

    /**
     * 海外购商品列表
     * @return \think\response\Json
     */
    public function abroadGoods()
    {
        $cateId = I('cate_id', 0);
        $sort = I('sort', 'goods_id');
        $sortAsc = I('sort_asc', 'desc');
        $sortArr = [];
        switch ($sort) {
            case 'sales_sum':
                // 销量
                $sortArr = [$sort => $sortAsc];
                break;
            case 'shop_price':
                // 价格
                $sortArr = ['shop_price - exchange_integral' => $sortAsc];
                break;
            case 'goods_id':
                // 新品
                $sortArr = [
                    'is_new' => $sortAsc,
                    'goods_id' => $sortAsc
                ];
                break;
        }
        $where = [
            'is_on_sale' => 1,
            'is_abroad' => 1
        ];
        if ($cateId != 0) {
            switch ($cateId) {
                case -1:
                    $where['abroad_recommend'] = 1;
                    break;
                default:
                    $where['cat_id'] = $cateId;
            }
        }
        $goodsIds = M('goods')->where($where)->getField('goods_id', true);
        $count = count($goodsIds);
        $page = new Page($count, 10);
        // 获取商品数据
        $goodsLogic = new GoodsLogic();
        $goodsList = $goodsLogic->getGoodsList($goodsIds, $sortArr, $page, null, ['is_abroad' => 1])['goods_list'];

        return json(['status' => 1, 'result' => ['goods_list' => $goodsList]]);
    }

    /**
     * 主页展示不同类型商品
     * @return \think\response\Json
     */
    public function indexGoods()
    {
        $type = I('type', '');

        $seriesGoods = [];
        $groupBuyGoods = [];
        $newGoods = [];
        $recommendGoods = [];
        $hotGoods = [];
        $typeArr = explode(',', $type);
        foreach ($typeArr as $type) {
            switch ($type) {
                case 'series':
                    // 超值套装列表
                    $seriesGoods = $this->getSeriesGoodsList(10, 'array');
                    break;
                case 'groupBuy':
                    // 团购商品列表
                    $groupBuyGoods = $this->getGroupBuyGoodsListNew(10, 'array');
                    break;
                case 'new':
                    // 新品列表
                    $newGoods = $this->getNewGoodsList(10, 'array');
                    break;
                case 'recommend':
                    // 促销商品
                    $recommendGoods = $this->getRecommendGoodsList(10, 'array', 1);
                    break;
                case 'hot':
                    // 热销商品
                    $hotGoods = $this->getHotGoodsList(10, 'array');
                    break;
                default:
                    return json(['status' => 0, 'msg' => 'fail']);
            }
        }
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