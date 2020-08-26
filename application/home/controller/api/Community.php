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
        $communityLogic = new CommunityLogic();
        // 获取文章数据
        $list = $communityLogic->getArticleList(I('get.'))['list'];
        // 文章列表
        $articleList = [];
        foreach ($list as $key => $value) {
            // 组合数据
            $articleList[] = [
                'article_id' => $value['id'],
                'content' => $value['content'],
                'share' => $value['share'],
                'publish_time' => $value['publish_time'],
                'image' => $value['image'],
                'video' => $value['video'],
                'user' => [
                    'user_id' => $value['user_id'],
                    'user_name' => !empty($value['user_name']) ? $value['user_name'] : !empty($value['nickname']) ? $value['nickname'] : '',
                    'head_pic' => getFullPath($value['head_pic']),
                ],
                'goods' => [],
                'goods_id' => $value['goods_id'],
                'item_id' => $value['item_id'],
                'shop_price' => $value['shop_price'],
                'exchange_integral' => $value['exchange_integral'],
                'goods_name' => $value['goods_name'],
                'original_img' => $value['original_img'],
            ];
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
            // 组合数据
            $articleList[] = [
                'article_id' => $value['id'],
                'content' => $value['content'],
                'share' => $value['share'],
                'publish_time' => $value['publish_time'],
                'image' => $value['image'],
                'video' => $value['video'],
                'goods' => [],
                'goods_id' => $value['goods_id'],
                'item_id' => $value['item_id'],
                'shop_price' => $value['shop_price'],
                'exchange_integral' => $value['exchange_integral'],
                'goods_name' => $value['goods_name'],
                'original_img' => $value['original_img'],
                'status' => $value['status'],
                'status_desc' => $communityLogic->articleStatus($value['status']),
                'cate_id1' => $value['cate_id1'],
                'cate_id1_desc' => $category[$value['cate_id1']],
                'cate_id2' => $value['cate_id2'],
                'cate_id2_desc' => $category[$value['cate_id2']],
            ];
        }
        // 数据处理
        $goodsIds = array_column($list, 'goods_id');
        $articleList = $communityLogic->handleArticleData($articleList, $goodsIds);
        return json(['status' => 1, 'result' => ['list' => $articleList]]);
    }


    public function articleInfo()
    {
        $articleId = I('article_id', 0);
        if (!$articleId) return json(['status' => 0, 'msg' => '请上传文章ID']);
        $communityLogic = new CommunityLogic();
        // 获取用户文章数据
        $info = $communityLogic->getArticleInfo($articleId);
        $articleInfo = [];
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
        if (empty($post['image']) && empty($post['video'])) {
            return json(['status' => 0, 'msg' => '请上传图片或视频']);
        }
        $post['user_id'] = $this->user_id;
        $post['source'] = 1;
        if (!empty($post['video'])) {
            $post['video'] = substr($post['video'], strpos($post['video'], 'video'));
            // 处理视频封面图
            $videoCover = getVideoCoverImages($post['video']);
            $post['video_cover'] = $videoCover['path'];
            $post['video_axis'] = $videoCover['axis'];
        }
        // 保存更新数据
        if ($articleId) {
            $post['up_time'] = NOW_TIME;
            $post['status'] = 0;
            $post['publish_time'] = 0;
            $articleData = M('community_article')->where(['id' => $articleId])->find();
            // 更新记录
            M('community_article_edit_log')->add([
                'type' => 1,
                'user_id' => $this->user_id,
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
    public
    function clickArticle()
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
    public
    function shareArticle()
    {
        $articleId = I('article_id', 0);
        if (!$articleId) return json(['status' => 0, 'msg' => '请传入文章ID']);
        // 分享数+1
        M('community_article')->where(['id' => $articleId])->setInc('share', 1);
        $share = M('community_article')->where(['id' => $articleId])->value('share');
        return json(['status' => 1, 'result' => ['share' => $share]]);
    }
}