<?php

namespace app\common\logic;


use think\Page;

class Community
{
    private $ossClient = null;
    public function __construct()
    {
        $this->ossClient = new OssLogic();
    }

    /**
     * 文章搜索条件
     * @param $param
     * @return array
     */
    private function articleWhere($param)
    {
        $where = [];
        if (!empty($param['article_id'])) {
            return ['ca.id' => $param['article_id']];
        }
        if (isset($param['status'])) {
            if ($param['status'] != '') {
                $where['ca.status'] = $param['status'];
            }
        } else {
            $where['ca.status'] = 1;
        }
        if (isset($param['is_browse'])) {
            $where['ca.is_browse'] = $param['is_browse'];
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
        $articleSort = M('community_config')->where(['type' => 'article_sort'])->value('content');
        switch ($articleSort) {
            case 'publish_time':
                $sort .= $articleSort . ' DESC, share DESC,';
                break;
            case 'share':
                $sort .= $articleSort . ' DESC, publish_time DESC,';
                break;
        }
        $sort .= ' add_time DESC';
        return $sort;
    }

    /**
     * 文章列表
     * @param $param
     * @param int $num
     * @return array
     */
    public function getArticleList($param, $num = 10)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 排序
        $sort = $this->articleSort($param);
        // 查询数据
        $count = M('community_article ca')->where($where)->count();
        $page = new Page($count, $num);
        $articleList = M('community_article ca')
            ->join('users u', 'u.user_id = ca.user_id', 'LEFT')
            ->join('goods g', 'g.goods_id = ca.goods_id', 'LEFT')
            ->field('ca.*, u.nickname, u.user_name, u.head_pic, g.goods_name, g.original_img, g.shop_price, g.exchange_integral, g.is_on_sale')
            ->where($where)->order($sort)->limit($page->firstRow . ',' . $page->listRows)->select();
        return ['total' => $count, 'list' => $articleList];
    }

    /**
     * 文章内容
     * @param $articleId
     * @return mixed
     */
    public function getArticleInfo($articleId)
    {
        $articleInfo = M('community_article ca')
            ->join('users u', 'u.user_id = ca.user_id')
            ->join('goods g', 'g.goods_id = ca.goods_id', 'LEFT')
            ->field('ca.*, u.nickname, u.user_name, u.head_pic, g.goods_name, g.original_img, g.shop_price, g.exchange_integral, g.is_on_sale')
            ->where(['ca.id' => $articleId])->find();
        return $articleInfo;
    }

    /**
     * 处理文章数据
     * @param $articleList
     * @param $goodsIds
     * @return mixed
     */
    public function handleArticleData($articleList, $goodsIds)
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
            // 次数处理
            if ($value['share'] > 999) {
                $articleList[$key]['share'] = '999+';
            }
            if ($value['click'] > 999) {
                $articleList[$key]['click'] = '999+';
            }
            // 发布时间处理
            $publishTime = '';
            if ($value['publish_time'] != 0) {
                $publishTime = date('Y-m-d', $value['publish_time']);
                if ($publishTime == date('Y-m-d', time())) {
                    $publishTime = '今天 ' . date('H:i', $value['publish_time']);
                } elseif ($publishTime == date('Y-m-d', time() - (86400))) {
                    $publishTime = '昨天 ' . date('H:i', $value['publish_time']);
                } elseif ($publishTime == date('Y-m-d', time() - (86400 * 2))) {
                    $publishTime = '前天 ' . date('H:i', $value['publish_time']);
                } else {
                    $publishTime = date('Y-m-d H:i', $value['publish_time']);
                }
            }
            // 图片处理
            !empty($value['image']) && $value['image'] = explode(';', $value['image']);
            $image = [];
            $imageSize = [];
            if (!empty($value['image'])) {
                foreach ($value['image'] as $item) {
                    $item = explode(',', $item);
                    $image[] = $this->ossClient::url(substr($item[0], strrpos($item[0], 'url:') + 4));
                    $imageSize[] = [
                        'width' => substr($item[1], strrpos($item[1], 'width:') + 6),
                        'height' => substr($item[2], strrpos($item[2], 'height:') + 7),
                    ];
                }
            }
            // 视频处理
            $video = [];
            if (!empty($value['video'])) {
                $video = [
                    'url' => !empty($value['video']) ? $this->ossClient::url($value['video']) : '',
                    'cover' => !empty($value['video']) ? $this->ossClient::url($value['video_cover']) : '',
                    'axis' => $value['video_axis']
                ];
            }
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
                    'is_on_sale' => $value['is_on_sale'],
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
            $articleList[$key]['publish_time'] = $publishTime;
            $articleList[$key]['image'] = $image;
            $articleList[$key]['image_size'] = $imageSize;
            $articleList[$key]['video'] = (object)$video;
            unset($articleList[$key]['video_cover']);
            unset($articleList[$key]['video_axis']);
            unset($articleList[$key]['goods_id']);
            unset($articleList[$key]['item_id']);
            unset($articleList[$key]['shop_price']);
            unset($articleList[$key]['exchange_integral']);
            unset($articleList[$key]['goods_name']);
            unset($articleList[$key]['original_img']);
            unset($articleList[$key]['is_on_sale']);
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
            '-3' => '已删除',
            '-2' => '已取消',
            '-1' => '不通过审核',
            '0' => '未审核',
            '1' => '审核通过',
            '2' => '预发布状态'
        ];
        return $statusDesc[$status];
    }
}