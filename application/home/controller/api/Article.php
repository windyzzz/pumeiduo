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

use app\common\logic\ArticleLogic;
use think\Db;
use think\Page;

class Article
{
    public function index()
    {
        $article_id = I('article_id/d', 38);
        $article = Db::name('article')->where('article_id', $article_id)->find();
        $return['article'] = $article;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 文章内列表页.
     */
    public function articleList()
    {
        $article_cat = M('ArticleCat')->where('parent_id  = 0')->select();
        $return['article_cat'] = $article_cat;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 通过文章分类id获取文章列表.
     */
    public function getArticleList()
    {
        $cat_id = I('cat_id', 0);
        if (!$cat_id) {
            return json(['status' => -1, 'msg' => '传参不正确', 'result' => null]);
        }
        $ArticleLogic = new ArticleLogic();
        $articleList = $ArticleLogic->getArticleListByCatId($cat_id);
        $return['article_list'] = $articleList;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 通过文章分类id获取下级分类列表.
     */
    public function getArticleCatList()
    {
        $cat_id = I('cat_id', 0);
        if (!$cat_id) {
            return json(['status' => -1, 'msg' => '传参不正确', 'result' => null]);
        }
        $ArticleLogic = new ArticleLogic();
        $catList = $ArticleLogic->getCatListById($cat_id);
        $return['cat_list'] = $catList;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 文章内容页.
     */
    public function detail()
    {
        $article_id = I('article_id/d', 1);
        $article = Db::name('article')->where('article_id', $article_id)->where('publish_time', 'elt', time())
            ->where('is_open', 1)->find();
        if ($article) {
            $parent = Db::name('article_cat')->where('cat_id', $article['cat_id'])->find();
            $return['cat_name'] = $parent['cat_name'];
            $article['publish_time'] = date('Y-m-d H:i:s', $article['publish_time']);
            $article['add_time'] = date('Y-m-d H:i:s', $article['add_time']);
            $return['article'] = $article;
        }

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    // 搜索功能（目前只是搜索帮助中心里面的文章）
    public function searchArticle()
    {
        $keyword = I('keyword');

        if (!$keyword) {
            return json(['status' => -1, 'msg' => '传参不正确', 'result' => null]);
        }

        $cat_id = 2;
        $child = M('article_cat')->where('parent_id', $cat_id)->select();
        $child_cat_ids = get_arr_column($child, 'cat_id');
        array_push($child_cat_ids, $cat_id);

        $count = M('article')
            ->where('title|description|keywords', 'LIKE', "%{$keyword}%")
            ->where('cat_id', 'in', $child_cat_ids)
            ->where('publish_time', 'elt', time())
            ->where('is_open', 1)
            ->count();
        $Page = new Page($count, 15);

        $list = M('article')
            ->where('title|description|keywords', 'LIKE', "%{$keyword}%")
            ->where('cat_id', 'in', $child_cat_ids)
            ->where('publish_time', 'elt', time())
            ->where('is_open', 1)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        foreach ($list as $k => $v) {
            $list[$k]['title'] = str_replace($keyword, "<span style='color:red'>{$keyword}</span>", $list[$k]['title']);
            $list[$k]['description'] = str_replace($keyword, "<span style='color:red'>{$keyword}</span>", $list[$k]['description']);
            $list[$k]['keywords'] = str_replace($keyword, "<span style='color:red'>{$keyword}</span>", $list[$k]['keywords']);
        }

        $return['list'] = $list;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    // 获取文章分类信息
    public function getArticleCatInfo()
    {
        $cat_id = I('cat_id', 0);
        if (!$cat_id) {
            return json(['status' => -1, 'msg' => '传参不正确', 'result' => null]);
        }
        $ArticleLogic = new ArticleLogic();
        $info = $ArticleLogic->getCatInfo($cat_id);
        $return['info'] = $info;

        return json(['status' => 1, 'msg' => 'success', 'result' => $return]);
    }

    /**
     * 文章分类列表
     * @return \think\response\Json
     */
    public function articleCateList()
    {
        $cateId = I('cate_id', 2);
        // 帮助中心
        $cateList = (new ArticleLogic())->getCateListById($cateId);
        return json(['status' => 1, 'result' => $cateList]);
    }

    /**
     * 文章列表
     * @return \think\response\Json
     */
    public function articleListNew()
    {
        $cateId = I('cate_id', '');
        $keyword = I('keyword', '');
        $articleList = (new ArticleLogic())->getArticleListByCateId($cateId, $keyword);
        // 由于APP不能解析文章content的html标签，所以直接返回H5的地址给APP
        foreach ($articleList as $key => $item) {
            $articleList[$key]['article_url'] = SITE_URL . '/#/member/app_help_particulars?article_id=' . $item['article_id'];
        }
        return json(['status' => 1, 'result' => ['list' => $articleList]]);
    }

    /**
     * 文章内容
     * @return \think\response\Json
     */
    public function articleDetail()
    {
        $articleId = I('article_id', '');
        if (!$articleId) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        $article = M('article')->where(['article_id' => $articleId, 'is_open' => 1])->field('article_id, title, app_content')->find();
        $return = $article;
        return json(['status' => 1, 'result' => $return]);
    }
}
