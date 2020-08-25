<?php

namespace app\common\logic;


use think\Page;

class Community
{
    /**
     * 文章搜索条件
     * @param $param
     * @return array
     */
    private function articleWhere($param)
    {
        $where = [];
        if (isset($param['status'])) {
            if ($param['status'] != -1) {
                $where['ca.status'] = $param['status'];
            }
        } else {
            $where['ca.status'] = 1;
        }
        if (!empty($param['cate_id1'])) {
            $where['ca.cate_id1'] = $param['cate_id1'];
        }
        if (!empty($param['cate_id2'])) {
            $where['ca.cate_id2'] = $param['cate_id2'];
        }
        if (!empty($param['user_id'])) {
            $where['ca.user_id'] = $param['user_id'];
        }
        return $where;
    }

    /**
     * 文章搜索排序
     * @param $param
     * @return string
     */
    private function articleSort($param)
    {
        $sort = '';
        if (!empty($param['sort']) && !empty($param['order'])) {

        }
        $sort .= ' publish_time desc';
        return $sort;
    }

    /**
     * 文章列表
     * @param $param
     * @param int $num
     * @return array
     */
    public function getArticleList($param, $num = 20)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 排序
        $sort = $this->articleSort($param);
        // 查询数据
        $count = M('community_article ca')->where($where)->count();
        $page = new Page($count, $num);
        $articleList = M('community_article ca')
            ->join('users u', 'u.user_id = ca.user_id')
            ->join('goods g', 'g.goods_id = ca.goods_id', 'LEFT')
            ->field('ca.*, u.nickname, u.user_name, u.head_pic, g.goods_name, g.original_img, g.shop_price, g.exchange_integral')
            ->where($where)->order($sort)->limit($page->firstRow . ',' . $page->listRows)->select();
        return ['total' => $count, 'list' => $articleList];
    }

    /**
     * 处理文章商品数据
     * @param $articleList
     * @param $goodsIds
     * @return mixed
     */
    public function handleArticleGoodsData($articleList, $goodsIds)
    {
        // 秒杀商品
        $flashSale = M('flash_sale')->where(['goods_id' => ['in', $goodsIds]])
            ->where(['is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->field('goods_id, price, can_integral')->select();
        // 团购商品
        $groupBuy = M('group_buy')->where(['goods_id' => ['in', $goodsIds]])
            ->where(['is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])->field('goods_id, price, can_integral')->select();
        // 促销商品
        $promGoods = M('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['in', $goodsIds], 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.type, pg.expression, gtg.goods_id')->select();
        foreach ($articleList as $key => $value) {
            // 商品处理
            if ($value['goods_id'] != 0) {
                $goodsType = 'normal';
                $shopPrice = $value['shop_price'];
                $exchangeIntegral = $value['exchange_integral'];
                $exchangePrice = bcsub($shopPrice, $exchangeIntegral, 2);
                if (!empty($flashSale)) {
                    foreach ($flashSale as $v) {
                        if ($value['goods_id'] == $v['goods_id']) {
                            $goodsType = 'flash_sale';
                            if ($v['can_integral'] == 0) {
                                $exchangeIntegral = '0';    // 不能使用积分兑换
                            }
                            $shopPrice = $v['price'];
                            $exchangePrice = bcsub($shopPrice, $exchangeIntegral, 2);
                            break;
                        }
                    }
                }
                if (!empty($groupBuy)) {
                    foreach ($groupBuy as $v) {
                        if ($value['goods_id'] == $v['goods_id']) {
                            $goodsType = 'group_buy';
                            if ($v['can_integral'] == 0) {
                                $exchangeIntegral = '0';    // 不能使用积分兑换
                            }
                            $shopPrice = $v['price'];
                            $exchangePrice = bcsub($shopPrice, $exchangeIntegral, 2);
                            break;
                        }
                    }
                }
                if (!empty($promGoods)) {
                    foreach ($promGoods as $v) {
                        if ($value['goods_id'] == $v['goods_id']) {
                            $goodsType = 'promotion';
                            switch ($v['type']) {
                                case 0:
                                    // 打折
                                    $shopPrice = bcdiv(bcmul($shopPrice, $v['expression'], 2), 100, 2);
                                    $exchangePrice = bcdiv(bcmul($exchangePrice, $v['expression'], 2), 100, 2);
                                    break 2;
                                case 1:
                                    // 减价
                                    $shopPrice = bcsub($shopPrice, $v['expression'], 2);
                                    $exchangePrice = bcsub($exchangePrice, $v['expression'], 2);
                                    break 2;
                            }
                        }
                    }
                }
                $articleList[$key]['goods'] = [
                    'goods_type' => $goodsType,
                    'goods_id' => $value['goods_id'],
                    'item_id' => $value['item_id'],
                    'goods_name' => $value['goods_name'],
                    'original_img_new' => getFullPath($value['original_img']),
                    'shop_price' => $shopPrice,
                    'exchange_integral' => $exchangeIntegral,
                    'exchange_price' => $exchangePrice,
                ];
            } else {
                $articleList[$key]['goods'] = (object)[];
            }
            unset($articleList[$key]['goods_id']);
            unset($articleList[$key]['item_id']);
            unset($articleList[$key]['shop_price']);
            unset($articleList[$key]['exchange_integral']);
            unset($articleList[$key]['goods_name']);
            unset($articleList[$key]['original_img']);
        }
        return $articleList;
    }

    /**
     * 获取文章状态描述
     * @param $status
     * @return mixed
     */
    public function articleStatus($status)
    {
        $statusDesc = [
            '0' => '未审核',
            '1' => '审核通过',
            '-1' => '不通过审核',
            '2' => '预发布状态'
        ];
        return $statusDesc[$status];
    }
}