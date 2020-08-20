<?php

namespace app\home\controller\api;


class Community extends Base
{
    /**
     * 是否开启社区
     * @return \think\response\Json
     */
    public function communityStatus()
    {
        if (tpCache('basic.community_open') == 1) {
            return json(['status' => 1, 'result' => ['state' => 1, 'title' => '']]);
        } else {
            return json(['status' => 1, 'result' => ['state' => 0, 'title' => '功能尚未开启']]);
        }
    }

    /**
     * 获取全部分类
     * @return \think\response\Json
     */
    public function allCategory()
    {
        $canPublish = I('can_publish', '');
        $where = [];
        if ($canPublish !== '') {
            $where['user_can_publish'] = $canPublish;
        }
        $cateList = [
            'category' => []
        ];
        // 一级分类
        $tCategoryList = M('community_category')->where(['level' => 0, 'status' => 1])->where($where)->order('sort DESC')->field('id, cate_name, user_can_publish')->select();
        if (!empty($tCategoryList)) {
            // 二级分类
            $dCategoryList = M('community_category')->where(['level' => 1, 'status' => 1])->order('sort DESC')->field('id, cate_name, parent_id')->select();
            foreach ($tCategoryList as $k => $cate1) {
                $cateList['category'][$k] = [
                    'id' => $cate1['id'],
                    'name' => $cate1['cate_name'],
                    'can_publish' => $cate1['user_can_publish'] == 1 ? 1 : 0,
                    'list' => []
                ];
                // 下级分类
                foreach ($dCategoryList as $cate2) {
                    if ($cate1['id'] == $cate2['parent_id']) {
                        $cateList['category'][$k]['list'][] = [
                            'id' => $cate2['id'],
                            'name' => $cate2['cate_name'],
                        ];
                    }
                }
                array_unshift($cateList['category'][$k]['list'], ['id' => '0', 'name' => '全部']);
            }
            $cateList['category'] = array_values($cateList['category']);
        }
        return json(['status' => 1, 'result' => $cateList]);
    }

    /**
     * 获取文章列表
     * @return \think\response\Json
     */
    public function article()
    {
        // 获取文章数据
        $list = (new \app\common\logic\Community())->getArticleList(I('get.'))['list'];
        $goodsIds = array_column($list, 'goods_id');
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
        // 文章列表
        $articleList = [];
        foreach ($list as $key => $value) {
            // 发布时间处理
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
            // 商品价格处理
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
            // 图片处理
            !empty($value['image']) && $value['image'] = explode(',', $value['image']);
            $image = [];
            if (!empty($value['image'])) {
                foreach ($value['image'] as $item) {
                    $image[] = getFullPath($item);
                }
            }
            // 视频处理
            if (empty($value['image']) && !empty($value['video']) && empty($value['video_cover'])) {
                $videoCover = getVideoCoverImages($value['video']);
                if ($videoCover) {
                    $value['video_cover'] = $videoCover;
                    M('community_article')->where(['id' => $value['id']])->update(['video_cover' => $videoCover]);
                }
            }
            // 组合数据
            $articleList[$key] = [
                'article_id' => $value['id'],
                'content' => $value['content'],
                'share' => $value['share'],
                'publish_time' => $publishTime,
                'image' => $image,
                'video' => [
                    'url' => !empty($value['video']) ? \plugins\Oss::url($value['video']) : '',
                    'cover' => getFullPath($value['video_cover'])
                ],
                'user' => [
                    'user_id' => $value['user_id'],
                    'user_name' => !empty($value['user_name']) ? $value['user_name'] : !empty($value['nickname']) ? $value['nickname'] : '',
                    'head_pic' => getFullPath($value['head_pic']),
                ],
                'goods' => [
                    'goods_type' => $goodsType,
                    'goods_id' => $value['goods_id'],
                    'item_id' => $value['item_id'],
                    'goods_name' => $value['goods_name'],
                    'original_img_new' => getFullPath($value['original_img']),
                    'shop_price' => $shopPrice,
                    'exchange_integral' => $exchangeIntegral,
                    'exchange_price' => $exchangePrice,
                ],
            ];
        }
        return json(['status' => 1, 'result' => ['list' => $articleList]]);
    }

    /**
     * 保存更新文章
     * @return \think\response\Json
     */
    public function saveArticle()
    {
        $articleId = I('article_id', 0);
        $post = input('post.');
        // 验证参数
        $validate = validate('Community');
        if (!$validate->scene('article_add')->check($post)) {
            return json(['status' => 0, 'msg' => $validate->getError()]);
        }
        // 保存更新数据
        $post['user_id'] = $this->user_id;
        $post['source'] = 1;
        if ($articleId) {
            $post['up_time'] = NOW_TIME;
            $post['status'] = 0;
            $post['publish_time'] = 0;
            M('community_article')->where(['id' => $articleId])->update($post);
        } else {
            $post['add_time'] = NOW_TIME;
            $articleId = M('community_article')->add($post);
        }
        return json(['status' => 1, 'result' => ['article_id' => $articleId]]);
    }

    /**
     * 点击文章
     * @return \think\response\Json
     */
    public function clickArticle()
    {
        $articleId = I('article_id', 0);
        if (!$articleId) return json(['status' => 0, 'msg' => '请传入文章ID']);
        // 点击数+1
        M('community_article')->where(['id' => $articleId])->setInc('click', 1);
        return json(['status' => 1, 'msg' => '']);
    }

    /**
     * 分享文章
     * @return \think\response\Json
     */
    public function shareArticle()
    {
        $articleId = I('article_id', 0);
        if (!$articleId) return json(['status' => 0, 'msg' => '请传入文章ID']);
        // 分享数+1
        M('community_article')->where(['id' => $articleId])->setInc('share', 1);
        $share = M('community_article')->where(['id' => $articleId])->value('share');
        return json(['status' => 1, 'result' => ['share' => $share]]);
    }
}