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

use app\common\model\Cart;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Db;
use think\Model;

/**
 * 购物车 逻辑定义
 * Class CatsLogic.
 */
class CartLogic extends Model
{
    protected $goods; //商品模型
    protected $specGoodsPrice; //商品规格模型
    protected $goodsBuyNum; //购买的商品数量
    protected $type = 1; //结算类型
    protected $cartType = 1; //加入购物车类型
    protected $session_id; //session_id
    protected $user_token; //用户token
    protected $user_id = 0; //user_id
    protected $userGoodsTypeCount = 0; //用户购物车的全部商品种类
    protected $userCouponNumArr; //用户符合购物车店铺可用优惠券数量
    protected $cart_id; // 购物车记录id

    public function __construct()
    {
        parent::__construct();
        if (session_id()) {
            $this->session_id = session_id();
        }
    }

    public function setCartId($cart_id)
    {
        $this->cart_id = $cart_id;
    }

    /**
     * 将session_id改成unique_id.
     *
     * @param $uniqueId |api唯一id 类似于 pc端的session id
     */
    public function setUniqueId($uniqueId)
    {
        $this->session_id = $uniqueId;
    }

    /**
     * 设置结算类型.
     *
     * @param $type |设置的结算类型
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * 设置加入购物车类型.
     *
     * @param $cartType |设置的购物车类型
     */
    public function setCartType($cartType)
    {
        $this->cartType = $cartType;
    }

    /**
     * 包含一个商品模型.
     *
     * @param $goods_id
     */
    public function setGoodsModel($goods_id)
    {
        if ($goods_id > 0) {
            $goodsModel = new Goods();
            $this->goods = $goodsModel::get($goods_id);
        }
    }

    /**
     * 包含一个商品规格模型.
     *
     * @param $item_id
     */
    public function setSpecGoodsPriceModel($item_id)
    {
        if ($item_id > 0) {
            $specGoodsPriceModel = new SpecGoodsPrice();
            $this->specGoodsPrice = $specGoodsPriceModel::get($item_id);
        } else {
            $this->specGoodsPrice = null;
        }
    }

    /**
     * 设置用户ID.
     *
     * @param $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * 设置用户Token.
     *
     * @param $user_token
     */
    public function setUserToken($user_token)
    {
        $this->user_token = $user_token;
    }

    /**
     * 设置购买的商品数量.
     *
     * @param $goodsBuyNum
     */
    public function setGoodsBuyNum($goodsBuyNum)
    {
        $this->goodsBuyNum = $goodsBuyNum;
    }

    /**
     * 立即购买.
     *
     * @return mixed
     *
     * @throws TpshopException
     */
    public function buyNow()
    {
        if (empty($this->goods)) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '购买商品不存在', 'result' => '']);
        }
        if (empty($this->goodsBuyNum)) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '购买商品数量不能为0', 'result' => '']);
        }

        $buyGoods = [
            'user_id' => $this->user_id,
            'session_id' => $this->session_id,
            'type' => $this->type,
            'cart_type' => $this->cartType,
            'goods_id' => $this->goods['goods_id'],
            'goods_sn' => $this->goods['goods_sn'],
            'goods_name' => $this->goods['goods_name'],
            'market_price' => $this->goods['market_price'],
            'goods_price' => $this->goods['shop_price'],
            'member_goods_price' => $this->goods['shop_price'],
            'goods_num' => $this->goodsBuyNum, // 购买数量
            'add_time' => time(), // 加入购物车时间
            'prom_type' => 0,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
            'prom_id' => 0,   // 活动id
            'weight' => $this->goods['weight'],   // 商品重量
            'goods' => $this->goods,
            'item_id' => empty($this->specGoodsPrice) ? 0 : $this->specGoodsPrice->item_id,
            'zone' => $this->goods['zone']
        ];
//        // 订单优惠促销（查看是否有赠送商品）
//        $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
//            ->where(['opg.type' => 1, 'goods_id' => $this->goods['goods_id'], 'item_id' => $buyGoods['item_id']])
//            ->where(['op.type' => ['in', '0, 2'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
//            ->field('order_prom_id, order_price')->find();
//        if ($orderProm) {
//            $buyGoods['is_order_prom'] = 1;
//        } else {
//            // 订单优惠促销（查看是否有优惠价格）
//            $orderProm = Db::name('order_prom_goods opg')->join('order_prom op', 'op.id = opg.order_prom_id')
//                ->where(['opg.type' => 1, 'goods_id' => $this->goods['goods_id'], 'item_id' => $buyGoods['item_id']])
//                ->where(['op.type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
//                ->field('order_prom_id, order_price, discount_price')->find();
//            if ($orderProm) {
//                $buyGoods['is_order_prom'] = 1;
//            }
//        }
        if (empty($this->specGoodsPrice)) {
            $buyGoods['goods']['spec_key'] = '';
            $buyGoods['goods']['spec_key_name'] = '';
            $specGoodsPriceCount = Db::name('SpecGoodsPrice')->where('goods_id', $this->goods['goods_id'])->count('item_id');
            if ($specGoodsPriceCount > 0) {
                throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '必须传递商品规格', 'result' => '']);
            }
            $prom_type = $this->goods['prom_type'];
            $store_count = $this->goods['store_count'];
        } else {
            $buyGoods['goods']['spec_key'] = $this->specGoodsPrice['key'];
            $buyGoods['goods']['spec_key_name'] = $this->specGoodsPrice['key_name'];
            $buyGoods['member_goods_price'] = $this->specGoodsPrice['price'];
            $buyGoods['goods_price'] = $this->specGoodsPrice['price'];
            $buyGoods['spec_key'] = $this->specGoodsPrice['key'];
            $buyGoods['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
            $buyGoods['sku'] = $this->specGoodsPrice['sku']; //商品条形码
            $prom_type = $this->specGoodsPrice['prom_type'];
            $this->goods['prom_type'] = $this->specGoodsPrice['prom_type'];
            $store_count = $this->specGoodsPrice['store_count'];
        }

        if ($this->goodsBuyNum > $store_count) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => $this->goods['goods_name'] . '，商品库存不足，剩余' . $this->goods['store_count'], 'result' => '']);
        }

        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($prom_type)) {
            $goodsPromLogic = $goodsPromFactory->makeModule($this->goods, $this->specGoodsPrice);
            if (!empty($goodsPromLogic)) {
                if ($goodsPromLogic->checkActivityIsAble()) {
                    $buyGoods = $goodsPromLogic->buyNow($buyGoods);
                    if ($prom_type == 3 && 1 == $this->type) {
                        // 商品促销优惠
                        $member_goods_price = $buyGoods['member_goods_price'] - $this->goods['exchange_integral'];
                        $use_integral = $this->goods['exchange_integral'];
                        $buyGoods['member_goods_price'] = $member_goods_price;
                        $buyGoods['use_integral'] = $use_integral;
                    }
                } else {
                    throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '活动已经结束，无法购买', 'result' => '']);
                }
            }
        } else {
            if (0 == $this->goods['prom_type']) {
                if (!empty($this->goods['price_ladder'])) {
                    //如果有阶梯价格,就是用阶梯价格
                    $goodsLogic = new GoodsLogic();
                    $price_ladder = unserialize($this->goods['price_ladder']);
                    $buyGoods['goods_price'] = $buyGoods['member_goods_price'] = $goodsLogic->getGoodsPriceByLadder($this->goodsBuyNum, $buyGoods['shop_price'], $price_ladder);
                } elseif ($this->user_id) {
//                    $user = Users::get(['user_id' => $this->user_id]);
                    // $buyGoods['goods_price'] = $buyGoods['member_goods_price'] = round($buyGoods['goods_price'] * $user['discount'], 2);
                }
                $member_goods_price = $buyGoods['member_goods_price'];
                $use_integral = 0;
                if (1 == $this->type) {
                    $member_goods_price = bcsub($member_goods_price, $this->goods['exchange_integral'], 2);
                    $use_integral = $this->goods['exchange_integral'];
                }
                $buyGoods['member_goods_price'] = $member_goods_price;
                $buyGoods['use_integral'] = $use_integral;
            }
        }

        $cart = new Cart();
        $buyGoods['cut_fee'] = $cart->getCutFeeAttr(0, $buyGoods);
        $buyGoods['goods_fee'] = $cart->getGoodsFeeAttr(0, $buyGoods);
        $buyGoods['total_fee'] = $cart->getTotalFeeAttr(0, $buyGoods);

        return $buyGoods;
    }

    /**
     * modify ：addCart.
     *
     * @return array
     */
    public function addGoodsToCart()
    {
        if (empty($this->goods)) {
            return ['status' => -3, 'msg' => '购买商品不存在', 'result' => ''];
        }
        // if($this->goods['exchange_integral'] > 0){
        //     return ['status'=>0,'msg'=>'积分商品跳转','result'=>['url'=>U('Goods/goodsInfo',['id'=>$this->goods['goods_id'],'item_id'=>$this->specGoodsPrice['item_id']],'',true)]];
        // }
        $userCartCount = Db::name('cart')->where(['user_id' => $this->user_id, 'session_id' => $this->session_id ? $this->session_id : $this->user_token])->count(); //获取用户购物车的商品有多少种
        if ($userCartCount >= 50) {
            return ['status' => -9, 'msg' => '购物车最多只能放50种商品', 'result' => ''];
        }
        $itemId = Db::name('SpecGoodsPrice')->where('goods_id', $this->goods['goods_id'])->field('item_id')->find();
        if (empty($this->specGoodsPrice) && !empty($itemId)) {
//            return ['status' => -1, 'msg' => '必须传递商品规格', 'result' => ''];
            // 默认第一个商品规格
            $this->setSpecGoodsPriceModel($itemId);
        }
        //有商品规格，和没有商品规格
        if ($this->specGoodsPrice) {
            if (1 == $this->specGoodsPrice['prom_type']) {
                $result = $this->addFlashSaleCart();
            } elseif (2 == $this->specGoodsPrice['prom_type']) {
                $result = $this->addGroupBuyCart();
            } elseif (3 == $this->specGoodsPrice['prom_type']) {
                $result = $this->addPromGoodsCart();
            } else {
                $result = $this->addNormalCart();
            }
        } else {
            if (1 == $this->goods['prom_type']) {
                $result = $this->addFlashSaleCart();
            } elseif (2 == $this->goods['prom_type']) {
                $result = $this->addGroupBuyCart();
            } elseif (3 == $this->goods['prom_type']) {
                $result = $this->addPromGoodsCart();
            } else {
                $result = $this->addNormalCart();
            }
        }
        $result['result'] = ['cart_num' => $UserCartGoodsNum = $this->getUserCartGoodsNum()]; // 查找购物车数量
        setcookie('cn', $UserCartGoodsNum, null, '/');

        return $result;
    }

    /**
     * 购物车添加普通商品
     *
     * @return array
     */
    private function addNormalCart()
    {
        if (empty($this->specGoodsPrice)) {
            $price = $this->goods['shop_price'];
            $store_count = $this->goods['store_count'];
        } else {
            //如果有规格价格，就使用规格价格，否则使用本店价。
            $price = $this->specGoodsPrice['price'];
            $store_count = $this->specGoodsPrice['store_count'];
        }
        // 查询购物车是否已经存在这商品
        if (!$this->user_id) {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        } else {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        }
        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            $userWantGoodsNum = $this->goodsBuyNum + $userCartGoods['goods_num']; //本次要购买的数量加上购物车的本身存在的数量
            //如果有阶梯价格,就是用阶梯价格
            if (!empty($this->goods['price_ladder'])) {
                $goodsLogic = new GoodsLogic();
                $price_ladder = unserialize($this->goods['price_ladder']);
                $price = $goodsLogic->getGoodsPriceByLadder($userWantGoodsNum, $this->goods['shop_price'], $price_ladder);
            }
            if ($userWantGoodsNum > 200) {
                $userWantGoodsNum = 200;
            }
            if ($userWantGoodsNum > $store_count) {
                $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num']; ///获取用户购物车的抢购商品数量
                return ['status' => -4, 'msg' => $this->goods['goods_name'] . '，商品库存不足，剩余' . $store_count . ',当前购物车已有' . $userCartGoodsNum . '件', 'result' => ''];
            }
            $member_goods_price = $price;
            $use_integral = 0;
            if (1 == $this->type) {
                $member_goods_price = bcsub($price, $this->goods['exchange_integral'], 2);
                $use_integral = $this->goods['exchange_integral'];
            }

            if (!$member_goods_price) {
                return ['status' => -4, 'msg' => $this->goods['goods_name'] . '，价格出现异常', 'result' => ''];
            }

            $cartResult = $userCartGoods->save([
                'goods_num' => $userWantGoodsNum,
                'goods_price' => $price,
                'member_goods_price' => $member_goods_price,
                'use_integral' => $use_integral,
                'type' => $this->type,
            ]);
        } else {
            //如果该商品没有存在购物车
            if ($this->goodsBuyNum > $store_count) {
                return ['status' => -4, 'msg' => $this->goods['goods_name'] . '，商品库存不足，剩余' . $this->goods['store_count'], 'result' => ''];
            }
            //如果有阶梯价格,就是用阶梯价格
            if (!empty($this->goods['price_ladder'])) {
                $goodsLogic = new GoodsLogic();
                $price_ladder = unserialize($this->goods['price_ladder']);
                $price = $goodsLogic->getGoodsPriceByLadder($this->goodsBuyNum, $this->goods['shop_price'], $price_ladder);
            }
            $member_goods_price = $price;
            $use_integral = 0;
            if (1 == $this->type) {
                $member_goods_price = bcsub($price, $this->goods['exchange_integral'], 2);
                $use_integral = $this->goods['exchange_integral'];
            }
            if (!$member_goods_price) {
                return ['status' => -4, 'msg' => $this->goods['goods_name'] . '，价格出现异常', 'result' => ''];
            }

            $cartAddData = [
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'type' => $this->type,   // type
                'cart_type' => $this->cartType,
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'goods_price' => $price,  // 原价
                'member_goods_price' => $member_goods_price,  // 会员折扣价 默认为 购买价
                'goods_num' => $this->goodsBuyNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 0,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                'prom_id' => 0,   // 活动id
                'use_integral' => $use_integral,
            ];
            if ($this->specGoodsPrice) {
                $cartAddData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
            }

            if ($this->cart_id) {
                $cartResult = Db::name('Cart')->where('id', $this->cart_id)->save($cartAddData);
            } else {
                $cartResult = Db::name('Cart')->insertGetId($cartAddData);
            }

        }
        if (false !== $cartResult) {
            return ['status' => 1, 'msg' => '成功加入购物车', 'result' => ''];
        }

        return ['status' => -1, 'msg' => '加入购物车失败', 'result' => ''];
    }

    /**
     * 购物车添加秒杀商品
     *
     * @return array
     */
    private function addFlashSaleCart()
    {
        $flashSaleLogic = new FlashSaleLogic($this->goods, $this->specGoodsPrice);
        $flashSale = $flashSaleLogic->getPromModel();

        $flashSaleIsEnd = $flashSaleLogic->checkActivityIsEnd();
        if ($flashSaleIsEnd) {
            return ['status' => -1, 'msg' => '秒杀活动已结束', 'result' => ''];
        }
        $flashSaleIsAble = $flashSaleLogic->checkActivityIsAble();
        if (!$flashSaleIsAble) {
            //活动没有进行中，走普通商品下单流程
            return $this->addNormalCart();
        }
        //活动进行中
        if (0 == $this->user_id) {
            return ['status' => -101, 'msg' => '购买活动商品必须先登录', 'result' => ''];
        }

        //获取用户购物车的抢购商品
        if (!$this->user_id) {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        } else {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        }
        $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num']; //获取用户购物车的抢购商品数量
        $userFlashOrderGoodsNum = $flashSaleLogic->getUserFlashOrderGoodsNum($this->user_id); //获取用户抢购已购商品数量
        $flashSalePurchase = $flashSale['goods_num'] - $flashSale['buy_num']; //抢购剩余库存
        $userBuyGoodsNum = $this->goodsBuyNum + $userFlashOrderGoodsNum + $userCartGoodsNum;
        if ($flashSale['buy_limit'] != 0 && $userBuyGoodsNum > $flashSale['buy_limit']) {
            return ['status' => -4, 'msg' => '每人限购' . $flashSale['buy_limit'] . '件，您已下单' . $userFlashOrderGoodsNum . '件，购物车已有' . $userCartGoodsNum . '件', 'result' => ''];
        }
        $userWantGoodsNum = $userCartGoodsNum + $this->goodsBuyNum; //本次要购买的数量加上购物车的本身存在的数量
        if ($userWantGoodsNum > 200) {
            $userWantGoodsNum = 200;
        }
        if ($userWantGoodsNum > $flashSalePurchase) {
            return ['status' => -4, 'msg' => $this->goods['goods_name'] . '，商品库存不足，剩余' . $flashSalePurchase . '件，当前购物车已有' . $userCartGoodsNum . '件', 'result' => ''];
        }
        $use_integral = 0;
        if (1 == $flashSale['can_integral']) {
            if ($this->type == 1) {
                $use_integral = $this->goods['exchange_integral'];
            }
        }
        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            $cartResult = $userCartGoods->save([
                'goods_num' => $userWantGoodsNum,
                'use_integral' => $use_integral,
                'member_goods_price' => bcsub($flashSale['price'], $use_integral, 2),
            ]);
        } else {
            $cartAddFlashSaleData = [
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'type' => $this->type,   // type
                'cart_type' => $this->cartType,
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'member_goods_price' => bcsub($flashSale['price'], $use_integral, 2),  // 会员折扣价 默认为 购买价
                'goods_num' => $userWantGoodsNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 1,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                'use_integral' => $use_integral,
            ];
            //商品有规格
            if ($this->specGoodsPrice) {
                $cartAddFlashSaleData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddFlashSaleData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddFlashSaleData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
                $cartAddFlashSaleData['goods_price'] = $this->specGoodsPrice['price'];   // 规格价
                $cartAddFlashSaleData['prom_id'] = $this->specGoodsPrice['prom_id']; // 活动id
            } else {
                $cartAddFlashSaleData['goods_price'] = $this->goods['shop_price'];   // 原价
                $cartAddFlashSaleData['prom_id'] = $this->goods['prom_id']; // 活动id
            }
            $cartResult = Db::name('Cart')->insert($cartAddFlashSaleData);
        }
        if (false !== $cartResult) {
            return ['status' => 1, 'msg' => '成功加入购物车', 'result' => ''];
        }

        return ['status' => -1, 'msg' => '加入购物车失败', 'result' => ''];
    }

    /**
     *  购物车添加团购商品
     *
     * @return array
     */
    private function addGroupBuyCart()
    {
        $groupBuyLogic = new GroupBuyLogic($this->goods, $this->specGoodsPrice);
        $groupBuy = $groupBuyLogic->getPromModel();
        //活动是否已经结束
        if (1 == $groupBuy['is_end'] || empty($groupBuy)) {
            return ['status' => -1, 'msg' => '团购活动已结束', 'result' => ''];
        }
        $groupBuyIsAble = $groupBuyLogic->checkActivityIsAble();
        if (!$groupBuyIsAble) {
            //活动没有进行中，走普通商品下单流程
            return $this->addNormalCart();
        }
        //活动进行中
        if (!$this->user_id) {
            return ['status' => -999, 'msg' => '购买活动商品必须先登录', 'result' => ''];
        }
        //获取用户购物车的团购商品
        if (!$this->user_id) {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        } else {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        }
        $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num']; ///获取用户购物车的团购商品数量
        $userWantGoodsNum = $userCartGoodsNum + $this->goodsBuyNum; //购物车加上要加入购物车的商品数量
        if ($groupBuy['buy_limit'] != 0 && $userWantGoodsNum > $groupBuy['buy_limit']) {
            return ['status' => -4, 'msg' => '每人限购' . $groupBuy['buy_limit'] . '件，您已下单' . $this->goodsBuyNum . '件，' . '购物车已有' . $userCartGoodsNum . '件', 'result' => ''];
        }
        $groupBuyPurchase = $groupBuy['goods_num'] - $groupBuy['buy_num']; //团购剩余库存
        if ($userWantGoodsNum > 200) {
            $userWantGoodsNum = 200;
        }
        if ($userWantGoodsNum > $groupBuyPurchase) {
            return ['status' => -4, 'msg' => '商品库存不足，剩余' . $groupBuyPurchase . ',当前购物车已有' . $userCartGoodsNum . '件', 'result' => ''];
        }

        $use_integral = 0;
        if (1 == $groupBuy['can_integral']) {
            if ($this->type == 1) {
                $use_integral = $this->goods['exchange_integral'];
            }
        }
        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            $cartResult = $userCartGoods->save([
                'goods_num' => $userWantGoodsNum,
                'use_integral' => $use_integral,
                'member_goods_price' => bcsub($groupBuy['price'], $use_integral, 2),
            ]);
        } else {
            $cartAddFlashSaleData = [
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'type' => $this->type,   // type
                'cart_type' => $this->cartType,
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'member_goods_price' => bcsub($groupBuy['price'], $use_integral, 2),  // 会员折扣价 默认为 购买价
                'goods_num' => $userWantGoodsNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 2,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                'use_integral' => $use_integral,
            ];
            //商品有规格
            if ($this->specGoodsPrice) {
                $cartAddFlashSaleData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddFlashSaleData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddFlashSaleData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
                $cartAddFlashSaleData['goods_price'] = $this->specGoodsPrice['price'];   // 规格价
                $cartAddFlashSaleData['prom_id'] = $this->specGoodsPrice['prom_id']; // 活动id
            } else {
                $cartAddFlashSaleData['goods_price'] = $this->goods['shop_price'];   // 原价
                $cartAddFlashSaleData['prom_id'] = $this->goods['prom_id']; // 活动id
            }
            $cartResult = Db::name('Cart')->insert($cartAddFlashSaleData);
        }
        if (false !== $cartResult) {
            return ['status' => 1, 'msg' => '成功加入购物车', 'result' => ''];
        }

        return ['status' => -1, 'msg' => '加入购物车失败', 'result' => ''];
    }

    /**
     *  购物车添加优惠促销商品
     *
     * @return array
     */
    private function addPromGoodsCart()
    {
        $promGoodsLogic = new PromGoodsLogic($this->goods, $this->specGoodsPrice);
        $promGoods = $promGoodsLogic->getPromModel();
        //活动是否存在，是否关闭，是否处于有效期，活动所属的用户范围
        $userLevel = M('users')->where(['user_id' => $this->user_id])->value('distribut_level');
        if ($promGoodsLogic->checkActivityIsEnd() || !$promGoodsLogic->checkActivityIsAble() || !in_array($userLevel, explode(',', $promGoods['group']))) {
            //活动不存在，已关闭，不处于有效期,走添加普通商品流程
            return $this->addNormalCart();
        }
        //活动进行中
        if (0 == $this->user_id) {
            return ['status' => -101, 'msg' => '购买活动商品必须先登录', 'result' => ''];
        }

        //如果有规格价格，就使用规格价格，否则使用本店价。
        if ($this->specGoodsPrice) {
            $priceBefore1 = $this->specGoodsPrice['price'];
            $storeCount = $this->specGoodsPrice['store_count'];
        } else {
            $priceBefore1 = $this->goods['shop_price'];
            $storeCount = $this->goods['store_count'];
        }

        // 查询购物车是否已经存在这商品
        if (!$this->user_id) {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'session_id' => $this->session_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        } else {
            $userCartGoods = Cart::get(['user_id' => $this->user_id, 'goods_id' => $this->goods['goods_id'], 'spec_key' => ($this->specGoodsPrice['key'] ?: '')]);
        }

        $userCartGoodsNum = empty($userCartGoods['goods_num']) ? 0 : $userCartGoods['goods_num']; ///获取用户购物车的促销商品数量
        $userWantGoodsNum = $this->goodsBuyNum + $userCartGoods['goods_num']; //本次要购买的数量加上购物车的本身存在的数量
        $UserPromOrderGoodsNum = $promGoodsLogic->getUserPromOrderGoodsNum($this->user_id); //获取用户促销已购商品数量
        $userBuyGoodsNum = $userWantGoodsNum + $UserPromOrderGoodsNum; //本次要购买的数量+购物车本身数量+已经买
        if ($promGoods['buy_limit'] != 0 && $userBuyGoodsNum > $promGoods['buy_limit']) {
            return ['status' => -4, 'msg' => '每人限购' . $promGoods['buy_limit'] . '件，您已下单' . $UserPromOrderGoodsNum . '件，' . '购物车已有' . $userCartGoodsNum . '件', 'result' => ''];
        }
        $userWantGoodsNum = $this->goodsBuyNum + $userCartGoodsNum; //本次要购买的数量加上购物车的本身存在的数量
        if ($userWantGoodsNum > 200) {
            $userWantGoodsNum = 200;
        }
        if ($userWantGoodsNum > $storeCount) {   //用户购买量不得超过库存
            return ['status' => -4, 'msg' => '商品活动库存不足，剩余' . $storeCount . ',当前购物车已有' . $userCartGoodsNum . '件', 'result' => ''];
        }

        //计算优惠价格
        $use_integral = 0;
        if ($this->type == 1) {
            $use_integral = $this->goods['exchange_integral'];
        }
        $priceBefore2 = bcsub($priceBefore1, $use_integral, 2);
        $priceAfter = $promGoodsLogic->getPromotionPrice($priceBefore2, $userWantGoodsNum);

        // 如果该商品已经存在购物车
        if ($userCartGoods) {
            /* $userWantGoodsNum = $this->goodsBuyNum + $userCartGoods['goods_num'];//本次要购买的数量加上购物车的本身存在的数量
             if($userWantGoodsNum > 200){
                 $userWantGoodsNum = 200;
             }*/
            $cartResult = $userCartGoods->save([
                'goods_num' => $userWantGoodsNum,
                'goods_price' => $priceBefore1,
                'member_goods_price' => $priceAfter,
                'use_integral' => $use_integral
            ]);
        } else {
            $cartAddData = [
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'type' => $this->type,   // sessionid
                'cart_type' => $this->cartType,
                'goods_id' => $this->goods['goods_id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'market_price' => $this->goods['market_price'],   // 市场价
                'goods_price' => $priceBefore1,  // 原价
                'member_goods_price' => $priceAfter,  // 会员折扣价 默认为 购买价
                'goods_num' => $this->goodsBuyNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 3,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                'use_integral' => $use_integral
            ];
            //商品有规格
            if ($this->specGoodsPrice) {
                $cartAddData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
                $cartAddData['sku'] = $this->specGoodsPrice['sku']; //商品条形码
                $cartAddData['prom_id'] = $this->specGoodsPrice['prom_id']; // 活动id
            } else {
                $cartAddData['prom_id'] = $this->goods['prom_id']; // 活动id
            }
            $cartResult = Db::name('Cart')->insert($cartAddData);
        }
        if (false !== $cartResult) {
            return ['status' => 1, 'msg' => '成功加入购物车', 'result' => ''];
        }

        return ['status' => -1, 'msg' => '加入购物车失败', 'result' => ''];
    }

    /**
     * 获取用户购物车商品总数.
     *
     * @return float|int
     */
    public function getUserCartGoodsNum()
    {
        if ($this->user_id) {
            $goods_num = Db::name('cart')->where(['user_id' => $this->user_id])->sum('goods_num');
        } else {
            $goods_num = Db::name('cart')->where(['session_id' => $this->session_id])->sum('goods_num');
        }

        return empty($goods_num) ? 0 : $goods_num;
    }

    /**
     * 获取用户购物车商品总数.
     *
     * @return float|int
     */
    public function getUserCartGoodsTypeNum()
    {
        if ($this->user_id) {
            $goods_num = Db::name('cart')->where(['user_id' => $this->user_id])->count();
        } else {
            $goods_num = Db::name('cart')->where(['session_id' => $this->session_id])->count();
        }

        return empty($goods_num) ? 0 : $goods_num;
    }

    /**
     * @param int $selected |是否被用户勾选中的 0 为全部 1为选中  一般没有查询不选中的商品情况
     *                                                  获取用户的购物车列表
     * @param bool $noSale 是否显示失效的商品，true显示 false不显示
     * @param bool $returnNum 是否输出购物车全部商品数量（包括赠品）
     *
     * @return array
     */
    public function getCartList($selected = 0, $noSale = false, $returnNum = false)
    {
        $cart = new Cart();
        // 如果用户已经登录则按照用户id查询
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
        }
        if (0 != $selected) {
            $cartWhere['selected'] = 1;
        }
        $cartWhere['goods_num'] = ['neq', 0];
        $cartList = $cart
            ->field("
                *,CASE type WHEN 1 THEN goods_price - member_goods_price ELSE '0' END AS use_point,
                CASE use_integral > 0 WHEN 1 THEN 1 ELSE 0 END AS can_integral
                ")
            ->with('promGoods,goods')
            ->where($cartWhere)
            ->order('id desc')
            ->select();  // 获取购物车商品

        // foreach ($exchange_integral as $k => $v) {
        //     if($v['can_integral'] == 0)
        //     {
        //         $exchange_integral[$k]['goods']['exchange_integral'] = 0;
        //     }
        // }

        $cartCheckAfterList = $this->checkCartList($cartList, $noSale);
//        $cartCheckAfterList = $cartList;
        $cartGoodsTotalNum = array_sum(array_map(function ($val) {
            return $val['goods_num'];
        }, $cartCheckAfterList)); //购物车购买的商品总数
        setcookie('cn', $cartGoodsTotalNum, null, '/');

        if ($returnNum) {
            return ['cart_list' => $cartCheckAfterList, 'cart_num' => $cartGoodsTotalNum];
        } else {
            return $cartCheckAfterList;
        }
    }

    /**
     * 过滤掉无效的购物车商品
     *
     * @param $cartList
     * @param bool $noSale 是否显示失效的商品，true显示 false不显示
     */
    public function checkCartList($cartList, $noSale = false)
    {
        $goodsPromFactory = new GoodsPromFactory();
        foreach ($cartList as $cartKey => $cart) {
            //商品不存在或者已经下架
            if (!$noSale) {
                if (empty($cart['goods']) || 1 != $cart['goods']['is_on_sale'] || 0 == $cart['goods_num']) {
                    $cart->delete();
                    unset($cartList[$cartKey]);
                    continue;
                }
            }

            $goods_sn = M('goods')->where(array('goods_id' => $cart['goods_id']))->getField('goods_sn');
            if ($goods_sn !== $cart['goods_sn']) {
                M('cart')->where(array('id' => $cart['id']))->data(array('goods_sn' => $goods_sn))->save();
                $cartList[$cartKey]['goods_sn'] = $goods_sn;
            }
            //规格不存在 则删除
            if (!empty($cart['spec_key'])) {
                $specGoodsPrice = SpecGoodsPrice::get(['goods_id' => $cart['goods_id'], 'key' => $cart['spec_key']], '', false);
                if (!$specGoodsPrice) {
                    $cart->delete();
                    unset($cartList[$cartKey]);
                    continue;
                }
                $cartList[$cartKey]['item_id'] = $specGoodsPrice['item_id'];
            } else {
                $specGoodsPrice = SpecGoodsPrice::get(['goods_id' => $cart['goods_id']], '', false);
                if ($specGoodsPrice) {
                    $cart->delete();
                    unset($cartList[$cartKey]);
                    continue;
                }
            }
            //活动商品的活动是否失效
            if ($goodsPromFactory->checkPromType($cart['prom_type'])) {
                if (!empty($cart['spec_key'])) {
                    $specGoodsPrice = SpecGoodsPrice::get(['goods_id' => $cart['goods_id'], 'key' => $cart['spec_key']], '', true);
                    if ($specGoodsPrice['prom_id'] != $cart['prom_id']) {
                        $cart->delete();
                        unset($cartList[$cartKey]);
                        continue;
                    }
                } else {
                    if ($cart['goods']['prom_id'] != $cart['prom_id']) {
                        $cart->delete();
                        unset($cartList[$cartKey]);
                        continue;
                    }
                    $specGoodsPrice = null;
                }
                $goodsPromLogic = $goodsPromFactory->makeModule($cart['goods'], $specGoodsPrice);
                if ($goodsPromLogic && !$goodsPromLogic->isAble()) {
                    $cart->delete();
                    unset($cartList[$cartKey]);
                    continue;
                }
            }
        }
        return $cartList;
    }

    /**
     *  modify ：cart_count
     *  获取用户购物车欲购买的商品有多少种.
     *
     * @return int|string
     */
    public function getUserCartOrderCount()
    {
        $count = Db::name('Cart')->where(['user_id' => $this->user_id, 'selected' => 1])->count();

        return $count;
    }

    /**
     * 用户登录后 对购物车操作
     * modify：login_cart_handle.
     */
    public function doUserLoginHandle()
    {
        if (empty($this->user_id) || empty($this->user_token)) {
            return;
        }
        //登录后将购物车的商品的 user_id 改为当前登录的id
        $cart = new Cart();
        $cart->save(['user_id' => $this->user_id], ['session_id' => $this->user_token, 'user_id' => 0]);
        // 查找购物车两件完全相同的商品
        $cart_id_arr = $cart->field('id')->where(['user_id' => $this->user_id])->group('goods_id,spec_key')->having('count(goods_id) > 1')->select();
        if (!empty($cart_id_arr)) {
            $cart_id_arr = get_arr_column($cart_id_arr, 'id');
            M('cart')->delete($cart_id_arr); // 删除购物车完全相同的商品
        }
    }

    /**
     * 更改购物车的商品数量.
     *
     * @param $cart_id |购物车id
     * @param $goods_num |商品数量
     *
     * @return array
     */
    public function changeNum($cart_id, $goods_num)
    {
        $Cart = new Cart();
        $cart = $Cart::get($cart_id);
        if ($goods_num > $cart->limit_num) {
            return ['status' => 0, 'msg' => '商品数量不能大于' . $cart->limit_num, 'result' => ['limit_num' => $cart->limit_num]];
        }
        if ($goods_num > 200) {
            $goods_num = 200;
        }
        $cartGoods = Goods::get($cart['goods_id']);
        $cart->goods_num = $goods_num;
        // if($cart->prom_type == 0 && !empty($cartGoods['price_ladder'])){
        //     //如果有阶梯价格,就是用阶梯价格
        //     $goodsLogic = new GoodsLogic();
        //     $price_ladder = unserialize($cartGoods['price_ladder']);
        //     $cart->goods_price = $cart->member_goods_pric[e = $goodsLogic->getGoodsPriceByLadder($goods_num, $cartGoods['shop_price'], $price_ladder);
        // }
        $cart->save();

        return ['status' => 1, 'msg' => '修改商品数量成功', 'result' => ''];
    }

    /**
     * 更新购物车数量（新）
     * @param $cartId
     * @param $cartNum
     * @return array
     */
    public function changeNumNew($cartId, $cartNum)
    {
        // 验证库存
        $cartModel = new Cart();
        $cart = $cartModel::get($cartId);
        if (empty($cart)) {
            return ['status' => 0, 'msg' => '购物车商品已删除'];
        }
        if ($cartNum == 0) {
            // 删除购物车
            Db::name('cart')->where(['id' => $cartId])->delete();
            return ['status' => 1, 'msg' => '更新购物车数量成功'];
        }
        if ($cartNum > $cart->limit_num) {
            return ['status' => 0, 'msg' => '商品数量不能大于' . $cart->limit_num, 'result' => ['limit_num' => $cart->limit_num]];
        }
        if ($cartNum > 200) {
            $cartNum = 200;
        }
        // 更新购物车
        Db::name('cart')->where(['id' => $cartId])->update(['goods_num' => $cartNum]);
        return ['status' => 1, 'msg' => '更新购物车数量成功'];
    }

    /**
     * 更改购物车的商品类型.
     *
     * @param $cart_id |购物车id
     * @param $type |类型
     *
     * @return array
     */
    public function changeType($cart_id, $type)
    {
        $Cart = new Cart();
        $cart = $Cart::get($cart_id);

        if (!$cart) {
            return ['status' => 0, 'msg' => '非法传参', 'result' => ''];
        }
        $cart->type = $type;
        $cartGoods = Goods::get($cart['goods_id']);
        if (1 == $type) {
            $cart->member_goods_price = $cartGoods->shop_price - $cartGoods->exchange_integral;
            $cart->use_integral = $cartGoods->exchange_integral;
        } elseif (2 == $type) {
            $cart->member_goods_price = $cartGoods->shop_price;
            $cart->use_integral = 0;
        } else {
            return ['status' => 0, 'msg' => '类型传参错误', 'result' => ''];
        }
        $cart->save();

        return ['status' => 1, 'msg' => '更改购物车的商品类型成功', 'result' => ''];
    }

    /**
     * 删除购物车商品
     *
     * @param array $cart_ids
     *
     * @return int
     *
     * @throws \think\Exception
     */
    public function delete($cart_ids = [])
    {
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
            $user['user_id'] = 0;
        }
        $delete = Db::name('cart')->where($cartWhere)->where('id', 'IN', $cart_ids)->delete();

        return $delete;
    }

    /**
     * 删除购物车商品
     *
     * @param array $cart_ids
     *
     * @return int
     *
     * @throws \think\Exception
     */
    public function deleteByGoodsId($goods_id = [])
    {
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
            $user['user_id'] = 0;
        }

        $delete = Db::name('cart')->where($cartWhere)->where('goods_id', 'IN', $goods_id)->delete();

        return $delete;
    }

    /**
     *  更新购物车，并返回计算结果.
     *
     * @param array $cart
     *
     * @return array
     */
    public function AsyncUpdateCart($cart = [])
    {
        $cartList = $cartSelectedId = $cartNoSelectedId = [];
        if (empty($cart)) {
            return ['status' => 0, 'msg' => '购物车没商品', 'result' => compact('total_fee', 'goods_fee', 'goods_num', 'cartList')];
        }
        foreach ($cart as $key => $val) {
            if (1 == $cart[$key]['selected']) {
                $cartSelectedId[] = $cart[$key]['id'];
            } else {
                $cartNoSelectedId[] = $cart[$key]['id'];
            }
        }
        $Cart = new Cart();
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
        }
        if (!empty($cartNoSelectedId)) {
            $Cart->where('id', 'IN', $cartNoSelectedId)->where($cartWhere)->update(['selected' => 0]);
        }
        if (empty($cartSelectedId)) {
            $cartPriceInfo = $this->getCartPriceInfo();
            $cartPriceInfo['cartList'] = $cartList;

            return ['status' => 1, 'msg' => '购物车没选中商品', 'result' => $cartPriceInfo];
        }
        $cartList = $Cart->where('id', 'IN', $cartSelectedId)->where($cartWhere)->select();
        /*$users = M('users')->where(array('user_id'=>$this->user_id))->field('distribut_level')->find();*/
        $goodsPromAmount = 0;
        foreach ($cartList as $cartKey => $cartVal) {
            if (0 == $cartList[$cartKey]['selected']) {
                $Cart->where('id', 'IN', $cartSelectedId)->where($cartWhere)->update(['selected' => 1]);
                break;
            }

            /*$goods_tao_grade = M('goods_tao_grade')
                ->alias('g')
                ->field('pg.type,pg.buy_limit,pg.type,pg.expression,pg.id')
                ->where(array('g.goods_id'=>$cartVal['goods_id']))
                ->join('prom_goods pg','g.promo_id = pg.id and pg.group in (0,'.$users['distribut_level'].') and pg.start_time <= '.NOW_TIME.' and pg.end_time >= '.NOW_TIME.' and pg.is_end = 0 and pg.min_num <= '.$cartVal['goods_num'])
                ->select();

            if($goods_tao_grade){
                foreach($goods_tao_grade as $key=>$group_activity){
                    if (0 == $group_activity['type']) {
                        $member_goods_price = bcdiv(bcmul($cartVal['member_goods_price'] , $group_activity['expression'],2),100,2);
                        $goodsPromAmount1 = bcmul(bcsub($cartVal['member_goods_price'],$member_goods_price,2),$cartVal['goods_num'],2);
                        $cartList[$cartKey]['member_goods_price'] = $member_goods_price;
                        $this->orderPromId = $group_activity['id'];
                    } elseif (1 == $group_activity['type']) {
                        $member_goods_price = bcsub($cartVal['member_goods_price'] , $group_activity['expression'],2);
                        $goodsPromAmount1 = bcmul(bcsub($cartVal['member_goods_price'],$member_goods_price,2),$cartVal['goods_num'],2);

                        $cartList[$cartKey]['member_goods_price'] = $member_goods_price;

                    }
                    $goodsPromAmount = bcadd($goodsPromAmount,$goodsPromAmount1,2);
                }
            }*/


        }
        if ($cartList) {
            $cartList = collection($cartList)->append(['cut_fee', 'total_fee', 'goods_fee'])->toArray();
            $cartPriceInfo = $this->getCartPriceInfo($cartList);
            $cartPriceInfo['cartList'] = $cartList;

            return ['status' => 1, 'msg' => '计算成功', 'result' => $cartPriceInfo];
        }
        $cartPriceInfo = $this->getCartPriceInfo();
        $cartPriceInfo['cartList'] = $cartList;
        $cartPriceInfo['goodsPromAmount'] = $goodsPromAmount;

        return ['status' => 1, 'msg' => '购物车没选中商品', 'result' => $cartPriceInfo];
    }

    /**
     *  更新购物车，并返回计算结果.
     *
     * @param array $cart
     *
     * @return array
     */
    public function AsyncUpdateCarts($cart = [])
    {
        $cartList = $cartSelectedId = [];
        if (empty($cart)) {
            return ['status' => 0, 'msg' => '购物车没商品', 'result' => compact('total_fee', 'goods_fee', 'goods_num', 'cartList')];
        }
        foreach ($cart as $key => $val) {
            if (1 == $cart[$key]['selected']) {
                $cartSelectedId[] = $cart[$key]['id'];
            }
        }
        $Cart = new Cart();
        if ($this->user_id) {
            $cartWhere['user_id'] = $this->user_id;
        } else {
            $cartWhere['session_id'] = $this->session_id;
        }
        if (!empty($cartSelectedId)) {
            $Cart->where('id', 'NOT IN', $cartSelectedId)->where($cartWhere)->update(['selected' => 0]);
        }
        if (empty($cartSelectedId)) {
            $cartPriceInfo = $this->getCartPriceInfo();
            $cartPriceInfo['cartList'] = $cartList;
            return ['status' => 1, 'msg' => '购物车没选中商品', 'result' => $cartPriceInfo];
        }

        $cartList = $Cart->where('id', 'IN', $cartSelectedId)->where($cartWhere)->select();
        foreach ($cartList as $cartKey => $cartVal) {
            if (0 == $cartList[$cartKey]['selected']) {
                $Cart->where('id', 'IN', $cartSelectedId)->where($cartWhere)->update(['selected' => 1]);
                break;
            }
        }
        if ($cartList) {
            $cartList = collection($cartList)->append(['cut_fee', 'total_fee', 'goods_fee'])->toArray();
            $cartPriceInfo = $this->getCartPriceInfo($cartList);
            $cartPriceInfo['cartList'] = $cartList;
            return ['status' => 1, 'msg' => '计算成功', 'result' => $cartPriceInfo];
        }
        $cartPriceInfo = $this->getCartPriceInfo();
        $cartPriceInfo['cartList'] = $cartList;

        return ['status' => 1, 'msg' => '购物车没选中商品', 'result' => $cartPriceInfo];
    }

    /**
     * 更新并获取购物车选中的商品
     * @param $cartIdArr
     * @return array
     */
    public function calcUpdateCart($cartIdArr)
    {
        if ($this->user_id) {
            $where['user_id'] = $this->user_id;
        } else {
            $where['session_id'] = $this->session_id;
        }
        $cartIds = [];
        $cartModel = new Cart();
        foreach ($cartIdArr as $cart) {
            $cartIds[] = $cart['cart_id'];
            // 更新购物车
            $cartModel->where($where)->where(['id' => $cart['cart_id']])->update(['goods_num' => $cart['cart_num'], 'selected' => 1]);
        }
        // 更新购物车
        $cartModel->where($where)->where(['id' => ['not in', $cartIds]])->update(['selected' => 0]);
        // 购物车数据
        $cartList = $cartModel->where($where)->where(['id' => ['in', $cartIds]])->select();
        if ($cartList) {
            $cartList = collection($cartList)->append(['cut_fee', 'total_fee', 'goods_fee', 'integral'])->toArray();
            $cartPriceInfo = $this->getCartPriceInfo($cartList);
            $cartPriceInfo['cart_list'] = $cartList;
            return ['status' => 1, 'data' => $cartPriceInfo];
        } else {
            return ['status' => 0, 'data' => [
                'total_fee' => 0.00,
                'goods_num' => 0,
                'use_integral' => 0,
                'discount_price' => 0,
                'can_integral' => 1
            ]];
        }
    }

    /**
     * 获取购物车的价格详情.
     *
     * @param $cartList |购物车列表
     *
     * @return array
     */
    public function getCartPriceInfo($cartList = null)
    {
        $total_fee = $goods_fee = $cut_fee = $goods_num = $use_integral = '0'; // 初始化数据
        if ($cartList) {
            foreach ($cartList as $cartKey => $cartItem) {
                if (in_array($cartItem['prom_type'], [1, 2])) {
                    // 秒杀 团购
                    $total_fee = bcadd($total_fee, $cartItem['goods_fee'], 2);
                } else {
                    $total_fee = bcadd($total_fee, $cartItem['total_fee'], 2);
                }
                $goods_fee = bcadd($goods_fee, $cartItem['goods_fee'], 2);
                $cut_fee = bcadd($cut_fee, $cartItem['cut_fee'], 2);
                $goods_num += $cartItem['goods_num'];
                $use_integral = bcadd($use_integral, $cartItem['integral'], 2);
            }
        }
        return compact('total_fee', 'goods_fee', 'cut_fee', 'goods_num', 'use_integral');
    }

    /**
     * 转换购物车的优惠券数据.
     *
     * @param $cartList |购物车商品
     * @param $userCouponList |用户优惠券列表
     *
     * @return mixedable
     */
    public function getCouponCartList($cartList, $userCouponList)
    {
        $userCouponArray = collection($userCouponList)->toArray();  //用户的优惠券
        $couponNewList = [];
        $coupon_num = 0;
        foreach ($userCouponArray as $couponKey => $couponItem) {
            if (0 == $userCouponArray[$couponKey]['coupon']['use_type'] || 5 == $userCouponArray[$couponKey]['coupon']['use_type']) { //全店使用优惠券
                if ($cartList['total_fee'] >= $userCouponArray[$couponKey]['coupon']['condition']) {  //订单商品总价是否符合优惠券购买价格
                    $userCouponArray[$couponKey]['coupon']['able'] = 1;
                    ++$coupon_num;
                } else {
                    $userCouponArray[$couponKey]['coupon']['able'] = 0;
                }
            } elseif (1 == $userCouponArray[$couponKey]['coupon']['use_type']) { //指定商品优惠券
                $pointGoodsPrice = 0; //指定商品的购买总价
                $couponGoodsId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods_id'], $couponGoodsId)) {
                        $pointGoodsPrice += $Item['member_goods_price'] * $Item['goods_num'];  //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $userCouponArray[$couponKey]['coupon']['able'] = 1;
                    ++$coupon_num;
                } else {
                    $userCouponArray[$couponKey]['coupon']['able'] = 0;
                }
            } elseif (2 == $userCouponArray[$couponKey]['coupon']['use_type']) { //指定商品分类优惠券
                $pointGoodsCatPrice = 0; //指定商品分类的购买总价
                $couponGoodsCatId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_category_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods']['cat_id'], $couponGoodsCatId)) {
                        $pointGoodsCatPrice += $Item['member_goods_price'] * $Item['goods_num']; //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsCatPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $userCouponArray[$couponKey]['coupon']['able'] = 1;
                    ++$coupon_num;
                } else {
                    $userCouponArray[$couponKey]['coupon']['able'] = 0;
                }
            } elseif (4 == $userCouponArray[$couponKey]['coupon']['use_type']) { //指定商品优惠券
                $pointGoodsPrice = 0; //指定商品的购买总价
                $couponGoodsId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods_id'], $couponGoodsId)) {
                        $pointGoodsPrice += $Item['member_goods_price'] * $Item['goods_num'];  //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $userCouponArray[$couponKey]['coupon']['able'] = 1;
                    ++$coupon_num;
                } else {
                    $userCouponArray[$couponKey]['coupon']['able'] = 0;
                }
            } else {
                $userCouponList[$couponKey]['coupon']['able'] = 1;
            }
            $couponNewList[] = $userCouponArray[$couponKey];
        }
        $this->userCouponNumArr['usable_num'] = $coupon_num;

        return $couponNewList;
    }

    /**
     * 获取可用的购物车优惠券返回。不可用的过滤掉。
     *
     * @param $cartList |购物车商品
     * @param $userCouponList |用户优惠券列表
     *
     * @return mixedable
     */
    public function getCouponAbleCartList($cartList, $userCouponList)
    {
        $userCouponArray = collection($userCouponList)->toArray();  //用户的优惠券
        $couponNewList = [];
        foreach ($userCouponArray as $couponKey => $couponItem) {
            if (0 == $userCouponArray[$couponKey]['coupon']['use_type']) { //全店使用优惠券
                if ($cartList['total_fee'] >= $userCouponArray[$couponKey]['coupon']['condition']) {  //订单商品总价是否符合优惠券购买价格
                    $coupon = $this->getApiCoupon($userCouponArray[$couponKey]);
                    array_push($couponNewList, $coupon);
                }
            } elseif (1 == $userCouponArray[$couponKey]['coupon']['use_type']) { //指定商品优惠券
                $pointGoodsPrice = 0; //指定商品的购买总价
                $couponGoodsId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods_id'], $couponGoodsId)) {
                        $pointGoodsPrice += $Item['member_goods_price'] * $Item['goods_num'];  //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $coupon = $this->getApiCoupon($userCouponArray[$couponKey]);
                    array_push($couponNewList, $coupon);
                }
            } elseif (2 == $userCouponArray[$couponKey]['coupon']['use_type']) { //指定商品分类优惠券
                $pointGoodsCatPrice = 0; //指定商品分类的购买总价
                $couponGoodsCatId = get_arr_column($userCouponArray[$couponKey]['coupon']['goods_coupon'], 'goods_category_id');
                foreach ($cartList['cartList'] as $tKey => $Item) {
                    if (in_array($Item['goods']['cat_id'], $couponGoodsCatId)) {
                        $pointGoodsCatPrice += $Item['member_goods_price'] * $Item['goods_num']; //用会员折扣价统计每个商品的总价
                    }
                }
                if ($pointGoodsCatPrice >= $userCouponArray[$couponKey]['coupon']['condition']) {
                    $coupon = $this->getApiCoupon($userCouponArray[$couponKey]);
                    array_push($couponNewList, $coupon);
                }
            } else {
                array_push($couponNewList, $userCouponArray[$couponKey]);
            }
        }

        return $couponNewList;
    }

    private function getApiCoupon($userCoupon)
    {
        $coupon['id'] = $userCoupon['id'];
        $coupon['cid'] = $userCoupon['cid'];
        $coupon['name'] = $userCoupon['coupon']['name'];
        $coupon['money'] = $userCoupon['coupon']['money'];
        $coupon['condition'] = $userCoupon['coupon']['condition'];
        $coupon['use_type_title'] = $userCoupon['coupon']['use_type_title'];

        return $coupon;
    }

    public function getUserCouponNumArr()
    {
        return $this->userCouponNumArr;
    }

    /**
     * 检查购物车数据是否满足库存购买.
     *
     * @param $cartList
     *
     * @throws TpshopException
     */
    public function checkStockCartList($cartList)
    {
        foreach ($cartList as $cartKey => $cartVal) {
            if ($cartVal->goods_num > $cartVal->limit_num) {
                throw new TpshopException('计算订单价格', 0, ['status' => 0, 'msg' => $cartVal->goods_name . '购买数量不能大于' . $cartVal->limit_num, 'result' => ['limit_num' => $cartVal->limit_num]]);
            }
        }
    }

    /**
     * 清除用户购物车选中.
     *
     * @throws \think\Exception
     */
    public function clear()
    {
        Db::name('cart')->where(['user_id' => $this->user_id, 'selected' => 1])->delete();
    }

    /**
     * 获取购物车商品信息
     * @param array $cartIds
     * @param string $field
     * @param bool $getField
     * @return false|mixed|\PDOStatement|string|\think\Collection
     */
    public function getCartGoods($cartIds = [], $field = '', $getField = false)
    {
        if (!empty($cartIds)) {
            $where['c.id'] = ['in', $cartIds];
        } else {
            $where['c.user_id'] = $this->user_id;
        }
        $data = Db::name('cart c')->join('goods g', 'g.goods_id = c.goods_id')
            ->join('spec_goods_price sgp', 'sgp.goods_id = c.goods_id and sgp.`key` = c.spec_key', 'LEFT')
            ->where($where)->field($field);
        if ($getField) {
            return $data->getField($field, true);
        } else {
            return $data->select();
        }
    }

    /**
     * 检查优惠促销（同一优惠促销）
     * @param $promInfo
     * @param $cartList
     * @return array
     */
    public function checkCardPromotion($promInfo, $cartList)
    {
        $goodsNum = 0;          // 商品总数量
        $goodsPrice = 0.00;     // 商品总价格
        foreach ($cartList as $k => $v) {
            $goodsNum += $v['goods_num'];
            $goodsPrice = bcadd($goodsPrice, $v['member_goods_price'], 2);
        }
        switch ($promInfo['type']) {
            case 0:
            case 1:
            case 2:
            case 3:
                return ['status' => 1, 'type_value' => $promInfo['title']];
            case 4:
                // 满打折
                $differ = (int)$promInfo['goods_num'] - $goodsNum;
                if ($differ <= 0) {
                    return ['status' => 1, 'type_value' => '已满' . $promInfo['goods_num'] . '件，已打' . $promInfo['expression'] . '折'];
                } else {
                    return ['status' => 0, 'type_value' => '已满' . $goodsNum . '件', 'other_value' => '再购' . $differ . '件可打' . $promInfo['expression'] . '折，去凑单'];
                }
            case 5:
                // 满减价
                $differ = bcsub($promInfo['goods_price'], $goodsPrice, 2);
                if ($differ <= 0) {
                    return ['status' => 1, 'type_value' => '已满' . $promInfo['goods_price'] . '元，已减' . $promInfo['expression'] . '元'];
                } else {
                    return ['status' => 0, 'type_value' => '已满' . $goodsPrice . '元', 'other_value' => '再购' . $differ . '元可减' . $promInfo['expression'] . '元，去凑单'];
                }
            default:
                return ['status' => 1, 'type_value' => $promInfo['title']];
        }
    }
}
