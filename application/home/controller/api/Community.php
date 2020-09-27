<?php

namespace app\home\controller\api;

use app\common\logic\Community as CommunityLogic;

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
                if ($canPublish == '') {
                    array_unshift($cateList['category'][$k]['list'], ['id' => '0', 'name' => '全部']);
                }
            }
            $cateList['category'] = array_values($cateList['category']);
        }
        return json(['status' => 1, 'result' => $cateList]);
    }

    /**
     * 所有文章列表
     * @return \think\response\Json
     */
    public function article()
    {
        $communityLogic = new CommunityLogic();
        // 获取文章数据
        $list = $communityLogic->getArticleList(I('get.'))['list'];
        // 文章列表
        $articleList = [];
        foreach ($list as $key => $value) {
            // 组合数据
            $articleList[$key] = [
                'article_id' => $value['id'],
                'content' => $value['content'],
                'share' => $value['share'],
                'publish_time' => $value['publish_time'],
                'image' => $value['image'],
                'video' => $value['video'],
                'video_cover' => $value['video_cover'],
                'video_axis' => $value['video_axis'],
                'user' => [
                    'user_id' => !empty($value['user_id']) ? $value['user_id'] : '',
                    'user_name' => !empty($value['user_name']) ? $value['user_name'] : !empty($value['nickname']) ? $value['nickname'] : '',
                    'head_pic' => !empty($value['head_pic']) ? getFullPath($value['head_pic']) : getFullPath('/public/images/default_head.png'),
                ],
                'goods' => [],
                'goods_id' => $value['goods_id'],
                'item_id' => $value['item_id'],
                'shop_price' => $value['shop_price'],
                'exchange_integral' => $value['exchange_integral'],
                'goods_name' => $value['goods_name'],
                'original_img' => $value['original_img'],
                'is_on_sale' => $value['is_on_sale']
            ];
            // 发布者处理
            if ($value['source'] == 2) {
                $official = M('community_config')->where(['type' => 'official'])->find();
                $articleList[$key]['user'] = [
                    'user_id' => '0',
                    'user_name' => $official ? $official['name'] : '圃美多官方',
                    'head_pic' => $official ? getFullPath($official['url']) : getFullPath('/public/images/default_head.png')
                ];
            }
        }
        // 数据处理
        $goodsIds = array_column($list, 'goods_id');
        $articleList = $communityLogic->handleArticleData($articleList, $goodsIds);
        return json(['status' => 1, 'result' => ['list' => $articleList]]);
    }

    /**
     * 用户文章列表
     * @return \think\response\Json
     */
    public function articleList()
    {
        $communityLogic = new CommunityLogic();
        // 获取用户文章数据
        $param = I('get.');
        $param['user_id'] = $this->user_id;
        $list = $communityLogic->getArticleList($param)['list'];
        // 社区文章分类数据
        $category = M('community_category')->getField('id, cate_name', true);
        // 文章列表
        $articleList = [];
        foreach ($list as $key => $value) {
            // 时间处理
            switch ($value['status']) {
                case -1:
                    $publishTime = M('community_article_verify_log')->where(['article_id' => $value['id']])->order('add_time DESC')->value('add_time');
                    break;
                case 0:
                    $publishTime = $value['add_time'];
                    break;
                case 1:
                    $publishTime = $value['publish_time'];
                    break;
                default:
                    continue 2;
            }
            // 组合数据
            $articleList[] = [
                'article_id' => $value['id'],
                'content' => trim_replace($value['content'], ["\n", "\r"], [" ", " "]),
                'publish_time' => $publishTime,
                'goods' => [],
                'goods_id' => $value['goods_id'],
                'item_id' => $value['item_id'],
                'shop_price' => $value['shop_price'],
                'exchange_integral' => $value['exchange_integral'],
                'goods_name' => $value['goods_name'],
                'original_img' => $value['original_img'],
                'is_on_sale' => $value['is_on_sale'],
                'status' => $value['status'],
                'status_desc' => $communityLogic->articleStatus($value['status']),
                'cate_id1' => $value['cate_id1'],
                'cate_id1_desc' => $category[$value['cate_id1']],
                'cate_id2' => $value['cate_id2'],
                'cate_id2_desc' => $category[$value['cate_id2']],
                'reason' => $value['status'] == -1 ? M('community_article_verify_log')->where(['article_id' => $value['id'], 'status' => -1])->order('add_time DESC')->value('reason') : ''
            ];
        }
        // 数据处理
        $goodsIds = array_column($list, 'goods_id');
        $articleList = $communityLogic->handleArticleData($articleList, $goodsIds);
        return json(['status' => 1, 'result' => ['list' => $articleList]]);
    }

    /**
     * 用户文章信息
     * @return \think\response\Json
     */
    public function articleInfo()
    {
        $articleId = I('article_id', 0);
        if (!$articleId) return json(['status' => 0, 'msg' => '请上传文章ID']);
        $communityLogic = new CommunityLogic();
        // 获取用户文章数据
        $info = $communityLogic->getArticleInfo($articleId);
        if (!$info) return json(['status' => 0, 'msg' => '文章内容不存在']);
        // 更新点击量
        $upData = ['click' => $info['click'] + 1];
        // 用户自己查看
        if ($this->user_id && $info['is_browse'] == 0 && $this->user_id == $info['user_id']) {
            $upData['is_browse'] = 1;
        }
        M('community_article')->where(['id' => $info['id']])->update($upData);
        // 社区文章分类数据
        $category = M('community_category')->getField('id, cate_name', true);
        $articleInfo = [
            'article_id' => $info['id'],
            'content' => $info['content'],
            'share' => $info['share'],
            'publish_time' => $info['publish_time'],
            'image' => $info['image'],
            'video' => $info['video'],
            'video_cover' => $info['video_cover'],
            'video_axis' => $info['video_axis'],
            'user' => [
                'user_id' => !empty($info['user_id']) ? $info['user_id'] : '',
                'user_name' => !empty($info['user_name']) ? $info['user_name'] : !empty($info['nickname']) ? $info['nickname'] : '',
                'head_pic' => !empty($info['head_pic']) ? getFullPath($info['head_pic']) : getFullPath('public/images/default_head.png'),
            ],
            'goods' => [],
            'goods_id' => $info['goods_id'],
            'item_id' => $info['item_id'],
            'shop_price' => $info['shop_price'],
            'exchange_integral' => $info['exchange_integral'],
            'goods_name' => $info['goods_name'],
            'original_img' => $info['original_img'],
            'is_on_sale' => $info['is_on_sale'],
            'cate_id1' => $info['cate_id1'],
            'cate_id1_desc' => $category[$info['cate_id1']],
            'cate_id2' => $info['cate_id2'],
            'cate_id2_desc' => $category[$info['cate_id2']],
            'reason' => $info['status'] == -1 ? M('community_article_verify_log')->where(['article_id' => $info['id'], 'status' => -1])->order('add_time DESC')->value('reason') : ''
        ];
        $articleInfo = $communityLogic->handleArticleData([$articleInfo], [$info['goods_id']]);
        $return = [
            'status' => $info['status'],
            'status_desc' => $communityLogic->articleStatus($info['status']),
            'info' => $articleInfo[0]
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 用户文章数量统计
     * @return \think\response\Json
     */
    public function articleNum()
    {
        $return = [
            'WAIT' => 0,
            'SUCCESS' => 0,
            'FAIL' => 0,
        ];
        $param['is_browse'] = I('is_browse', 0);
        $param['status'] = '';
        $param['user_id'] = $this->user_id;
        $communityLogic = new CommunityLogic();
        // 获取用户文章数据
        $list = $communityLogic->getArticleList($param)['list'];
        foreach ($list as $value) {
            switch ($value['status']) {
                case 0:
                    $return['WAIT'] += 1;
                    break;
                case 1:
                    $return['SUCCESS'] += 1;
                    break;
                case -1:
                    $return['FAIL'] += 1;
                    break;
            }
        }
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 保存更新文章
     * @return \think\response\Json
     */
    public function saveArticle()
    {
        $post = input('post.');
        $articleId = $post['article_id'];
        unset($post['article_id']);
        // 验证参数
        $post['content'] = trim($post['content']);
        $validate = validate('Community');
        if (!$validate->scene('article_add')->check($post)) {
            return json(['status' => 0, 'msg' => $validate->getError()]);
        }
        if ($post['goods_id'] && !M('goods')->where(['goods_id' => $post['goods_id'], 'is_on_sale' => 1])->value('goods_id')) {
            return json(['status' => 0, 'msg' => '商品已下架，请重新选择']);
        }
        if (empty($post['image']) && empty($post['video'])) {
            return json(['status' => 0, 'msg' => '请上传图片或视频']);
        }
        $post['user_id'] = $this->user_id;
        $post['source'] = 1;
        // 图片地址处理
        if (!empty($post['image'])) {
            $imageArr = explode(',', $post['image']);
            $post['image'] = '';
            foreach ($imageArr as $image) {
                $post['image'] .= 'url:' . substr($image, strpos($image, 'image')) . ',width:300,height:300;';
            }
            $post['image'] = rtrim($post['image'], ';');
        }
        // 视频地址处理
        if (!empty($post['video'])) {
            $post['video'] = substr($post['video'], strpos($post['video'], 'video'));
//            // 处理视频封面图
//            $videoCover = getVideoCoverImages_v2($post['video']);
//            $post['video_cover'] = $videoCover['path'];
//            $post['video_axis'] = $videoCover['axis'];
        }
        // 保存更新数据
        if ($articleId) {
            $articleData = M('community_article')->where(['id' => $articleId])->find();
            if ($articleData['status'] != -1) {
                return json(['status' => 0, 'msg' => '文章不是审核不通过的状态不能编辑']);
            }
            $post['video_cover'] = '';
            $post['up_time'] = NOW_TIME;
            $post['status'] = 0;
            $post['publish_time'] = 0;
            // 更新记录
            M('community_article_edit_log')->add([
                'type' => 1,
                'user_id' => $this->user_id,
                'article_id' => $articleId,
                'data' => json_encode($articleData),
                'add_time' => NOW_TIME
            ]);
            // 更新数据
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
        $article = M('community_article')->where(['id' => $articleId])->field('user_id, share')->find();
        if (!$this->user_id) {
            $share = $article['share'];
        } else {
            if ($article['user_id'] == $this->user_id) {
                $share = $article['share'];
            } else {
                if (!M('community_article_share_log')->where(['article_id' => $articleId, 'user_id' => $this->user_id])->find()) {
                    // 分享数+1
                    M('community_article')->where(['id' => $articleId])->setInc('share', 1);
                    $share = M('community_article')->where(['id' => $articleId])->value('share');
                } else {
                    $share = $article['share'];
                }
                // 分享记录
                M('community_article_share_log')->add([
                    'article_id' => $articleId,
                    'user_id' => $this->user_id,
                    'add_time' => NOW_TIME
                ]);
            }
        }
        return json(['status' => 1, 'result' => ['share' => $share]]);
    }

    /**
     * 取消/删除文章
     * @return \think\response\Json
     */
    public function cancelArticle()
    {
        $articleId = I('article_id', 0);
        if (!$articleId) return json(['status' => 0, 'msg' => '请传入文章ID']);
        $articleData = M('community_article')->where(['id' => $articleId])->find();
        // 更新文章信息
        switch ($articleData['status']) {
            case 0:
                $upData = [
                    'status' => -2,
                    'cancel_time' => NOW_TIME
                ];
                break;
            case 1:
                $upData = [
                    'status' => -3,
                    'delete_time' => NOW_TIME
                ];
                break;
            default:
                return json(['status' => 0, 'msg' => '文章不能被删除']);
        }
        M('community_article')->where(['id' => $articleId])->update($upData);
        return json(['status' => 1, 'msg' => '操作成功']);
    }
}