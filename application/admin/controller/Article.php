<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

use app\admin\logic\ArticleCatLogic;
use think\Page;

class Article extends Base
{
    public function categoryList()
    {
        $ArticleCat = new ArticleCatLogic();
        $cat_list = $ArticleCat->article_cat_list(0, 0, false);

        $this->assign('cat_list', $cat_list);

        return $this->fetch('categoryList');
    }

    public function category()
    {
        $ArticleCat = new ArticleCatLogic();
        $act = I('get.act', 'add');
        $cat_id = I('get.cat_id/d');
        $parent_id = I('get.parent_id/d');
        if ($cat_id) {
            $cat_info = M('article_cat')->where('cat_id=' . $cat_id)->find();
            $parent_id = $cat_info['parent_id'];
            $this->assign('cat_info', $cat_info);
        }
        $cats = $ArticleCat->article_cat_list(0, $parent_id, true);
        switch ($parent_id) {
            case 2:
                // 帮助中心分类
                $helpCate = M('help_center_cate')->select();
                $this->assign('help_cate', $helpCate);
                break;
            case 81:
                // 常见问题分类
                $helpCate = M('question_cate')->select();
                $this->assign('question_cate', $helpCate);
                break;
        }
        $this->assign('act', $act);
        $this->assign('cat_select', $cats);

        return $this->fetch();
    }

    public function articleList()
    {
        $Article = M('Article');
        $res = $list = [];
        $p = empty($_REQUEST['p']) ? 1 : $_REQUEST['p'];
        $size = empty($_REQUEST['size']) ? 20 : $_REQUEST['size'];

        $where = ' 1 = 1 ';
        $keywords = trim(I('keywords'));
        $keywords && $where .= " and title like '%$keywords%' ";
        $cat_id = I('cat_id', 0);
        $cat_id && $where .= " and cat_id = $cat_id ";
        $res = $Article->where($where)->order('article_id desc')->page("$p,$size")->select();
        $count = $Article->where($where)->count(); // 查询满足要求的总记录数
        $pager = new Page($count, $size); // 实例化分页类 传入总记录数和每页显示的记录数
        //$page = $pager->show();//分页显示输出

        $ArticleCat = new ArticleCatLogic();
        $cats = $ArticleCat->article_cat_list(0, 0, false);
        if ($res) {
            foreach ($res as $val) {
                $val['category'] = $cats[$val['cat_id']]['cat_name'];
                $val['add_time'] = date('Y-m-d H:i:s', $val['add_time']);
                $val['publish_time'] = date('Y-m-d H:i:s', $val['publish_time']);
                $list[] = $val;
            }
        }
        $this->assign('cats', $cats);
        $this->assign('cat_id', $cat_id);
        $this->assign('list', $list); // 赋值数据集
        $this->assign('pager', $pager); // 赋值分页输出
        return $this->fetch('articleList');
    }

    public function article()
    {
        $ArticleCat = new ArticleCatLogic();
        $act = I('GET.act', 'add');
        $info = [];
        $info['publish_time'] = time();
        if (I('GET.article_id')) {
            $article_id = I('GET.article_id');
            $info = M('article')->where('article_id=' . $article_id)->find();
        }
        $cats = $ArticleCat->article_cat_list(0, $info['cat_id']);
        $this->assign('cat_select', $cats);
        $this->assign('act', $act);
        $this->assign('info', $info);

        return $this->fetch();
    }

    public function categoryHandle()
    {
        $data = I('post.');

        $result = $this->validate($data, 'ArticleCategory.' . $data['act'], [], true);
        if (true !== $result) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'result' => $result]);
        }

        if ('add' == $data['act']) {
            $r = M('article_cat')->add($data);
        } elseif ('edit' == $data['act']) {
            $cat_info = M('article_cat')->where('cat_id', $data['cat_id'])->find();
            if (1 == $cat_info['cat_type'] && $data['parent_id'] > 1) {
                $this->ajaxReturn(['status' => -1, 'msg' => '可更改系统预定义分类的上级分类']);
            }
            $r = M('article_cat')->where('cat_id', $data['cat_id'])->save($data);
        } elseif ('del' == $data['act']) {
            if ($data['cat_id'] < 9) {
                $this->ajaxReturn(['status' => -1, 'msg' => '系统默认分类不得删除']);
            }
            if (M('article_cat')->where('parent_id', $data['cat_id'])->count() > 0) {
                $this->ajaxReturn(['status' => -1, 'msg' => '还有子分类，不能删除']);
            }
            if (M('article')->where('cat_id', $data['cat_id'])->count() > 0) {
                $this->ajaxReturn(['status' => -1, 'msg' => '该分类下有文章，不允许删除，请先删除该分类下的文章']);
            }
            $r = M('article_cat')->where('cat_id', $data['cat_id'])->delete();
        }

        if (!$r) {
            $this->ajaxReturn(['status' => -1, 'msg' => '操作失败']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
    }

    public function articleHandle()
    {
        $data = I('post.');
        $data['publish_time'] = strtotime($data['publish_time']);
        $data['finish_time'] = !empty($data['finish_time']) ? strtotime($data['finish_time']) : '';
        //$referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U('Admin/Article/articleList');

        $result = $this->validate($data, 'Article.' . $data['act'], [], true);
        if (true !== $result) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'result' => $result]);
        }

        if ('add' == $data['act']) {
            $data['click'] = mt_rand(1000, 1300);
            $data['add_time'] = time();
            $r = M('article')->add($data);
        } elseif ('edit' == $data['act']) {
            $r = M('article')->where('article_id=' . $data['article_id'])->save($data);
        } elseif ('del' == $data['act']) {
            $r = M('article')->where('article_id=' . $data['article_id'])->delete();
        }

        if (!$r) {
            $this->ajaxReturn(['status' => -1, 'msg' => '操作失败']);
        }

        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
    }

    public function link()
    {
        $act = I('GET.act', 'add');
        $this->assign('act', $act);
        $link_id = I('GET.link_id');
        $link_info = [];
        if ($link_id) {
            $link_info = M('friend_link')->where('link_id=' . $link_id)->find();
            $this->assign('info', $link_info);
        }

        return $this->fetch();
    }

    public function linkList()
    {
        $Ad = M('friend_link');
        $p = $this->request->param('p');
        $res = $Ad->order('orderby')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $val['target'] = $val['target'] > 0 ? '开启' : '关闭';
                $list[] = $val;
            }
        }
        $this->assign('list', $list); // 赋值数据集
        $count = $Ad->count(); // 查询满足要求的总记录数
        $Page = new Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        $this->assign('pager', $Page);
        $this->assign('page', $show); // 赋值分页输出
        return $this->fetch();
    }

    public function linkHandle()
    {
        $data = I('post.');
        if ('del' == $data['act']) {
            $r = M('friend_link')->where(['link_id' => $data['link_id']])->delete();
            if ($r) {
                exit(json_encode(1));
            }
        }
        $result = $this->validate($data, 'FriendLink.' . $data['act'], [], true);
        if (true !== $result) {
            // 验证失败 输出错误信息
            $validate_error = '';
            foreach ($result as $key => $value) {
                $validate_error .= $value . ',';
            }
            $this->error($validate_error);
        }
        if ('add' == $data['act']) {
            $r = M('friend_link')->insert($data);
        }
        if ('edit' == $data['act']) {
            $r = M('friend_link')->where('link_id=' . $data['link_id'])->save($data);
        }
        if ($r) {
            $this->success('操作成功', U('Admin/Article/linkList'));
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 帮助中心分类
     * @return mixed
     */
    public function helpCenterCate()
    {
        // 未分类的帮助中心数据
        $cateList1[] = [
            'id' => 0,
            'parent_id' => -1,
            'level' => 0,
            'name' => '未分类',
            'desc' => '未分类的不会显示',
            'sort' => 0,
        ];
        $articleCate = M('article_cat')->where(['parent_id' => 2, 'extend_cate_id' => null])
            ->field('cat_id, cat_name, extend_sort, cat_desc')->order('sort_order')->select();
        foreach ($articleCate as $article) {
            $cateList1[] = [
                'id' => $article['cat_id'],
                'parent_id' => 0,
                'level' => 1,
                'name' => $article['cat_name'],
                'desc' => $article['cat_desc'],
                'sort' => $article['extend_sort'],
            ];
        }
        // 帮助中心上级分类
        $centerCate = M('help_center_cate')->field('id, name, `desc`, `sort`')->order('sort')->select();
        // 已分类的帮助中心数据
        $cateList2 = [];
        foreach ($centerCate as $center) {
            $cateList2[] = [
                'id' => $center['id'],
                'parent_id' => -1,
                'level' => 0,
                'name' => $center['name'],
                'desc' => $center['desc'],
                'sort' => $center['sort'],
            ];
            // 帮助中心分类数据
            $articleCate = M('article_cat')->where(['parent_id' => 2, 'extend_cate_id' => $center['id']])
                ->field('cat_id, cat_name, extend_sort, cat_desc')->order('sort_order')->select();
            foreach ($articleCate as $article) {
                $cateList2[] = [
                    'id' => $article['cat_id'],
                    'parent_id' => $center['id'],
                    'level' => 1,
                    'name' => $article['cat_name'],
                    'desc' => $article['cat_desc'],
                    'sort' => $article['extend_sort'],
                ];
            }
        }
        $this->assign('cate_list', array_merge($cateList1, $cateList2));
        return $this->fetch();
    }

    /**
     * 帮助中心分类信息
     * @return mixed
     */
    public function helpCenterCateInfo()
    {
        if ($this->request->isPost()) {
            $data = I('post.', []);
            if (!empty($data['cate_id'])) {
                $cateId = $data['cate_id'];
                unset($data['cate_id']);
                M('help_center_cate')->where(['id' => $cateId])->update($data);
            } else {
                $cateId = M('help_center_cate')->add($data);
            }
            $this->success('更新成功', U('Admin/Article/helpCenterCate'));
        }
        $cateId = I('cate_id', '');
        $cateInfo = M('help_center_cate')->where(['id' => $cateId])->find();
        $this->assign('cate_info', $cateInfo);
        return $this->fetch();
    }

    /**
     * 常见问题分类
     * @return mixed
     */
    public function questionCate()
    {
        $questionCate = M('question_cate')->field('id, name, sort, is_show')->order('sort')->select();
        // 已分类的常见问题数据
        $cateList = [];
        foreach ($questionCate as $question) {
            $cateList[] = [
                'id' => $question['id'],
                'parent_id' => -1,
                'level' => 0,
                'cate_name' => $question['name'],
                'sort' => $question['sort'],
                'is_show' => $question['is_show'],
            ];
            // 文章列表
            $article = M('article')->where(['cat_id' => 81, 'extend_cate_id' => $question['id']])
                ->field('article_id, title, extend_sort, is_open')->order('extend_sort')->select();
            foreach ($article as $value) {
                $cateList[] = [
                    'id' => $value['article_id'],
                    'parent_id' => $question['id'],
                    'level' => 1,
                    'article_title' => $value['title'],
                    'sort' => $value['extend_sort'],
                    'is_open' => $value['is_open'],
                ];
            }
        }
        $this->assign('cate_list', $cateList);
        return $this->fetch();
    }

    /**
     * 常见问题分类信息
     * @return mixed
     */
    public function questionCateInfo()
    {
        if ($this->request->isPost()) {
            $data = I('post.', []);
            if (!empty($data['cate_id'])) {
                $cateId = $data['cate_id'];
                unset($data['cate_id']);
                M('question_cate')->where(['id' => $cateId])->update($data);
            } else {
                $cateId = M('question_cate')->add($data);
            }
            $this->success('更新成功', U('Admin/Article/questionCate'));
        }
        $cateId = I('cate_id', '');
        $cateInfo = M('question_cate')->where(['id' => $cateId])->find();
        $this->assign('cate_info', $cateInfo);
        return $this->fetch();
    }

    /**
     * 常见问题文章
     * @return mixed
     */
    public function questionArticle()
    {
        $articleId = I('article_id', '');
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['extend_cate_id']) || $data['extend_cate_id'] == 0) {
                $this->error('分类ID不能为空');
            }
            unset($data['article_id']);
            if ($articleId) {
                M('article')->where(['article_id' => $articleId])->update($data);
            } else {
                $data['cat_id'] = 81;
                $data['add_time'] = time();
                M('article')->add($data);
            }
            $this->success('更新成功', U('Admin/Article/questionCate'));
        } else {
            if ($articleId) {
                $article = M('article')->where(['article_id' => $articleId])
                    ->field('article_id id, extend_cate_id, extend_sort, title, content')->find();
                $this->assign('article', $article);
            }
            $questionCate = M('question_cate')->field('id, name')->order('sort')->select();
            $this->assign('question_cate', $questionCate);
            return $this->fetch();
        }
    }

    /**
     * 处理常见问题文章
     */
    public function handleQuestionArticle()
    {
        $act = I('act');
        $articleId = I('article_id');
        switch ($act) {
            case 'del':
                M('article')->where(['article_id' => $articleId])->delete();
                $this->ajaxReturn(['status' => 1]);
            default:
                $this->ajaxReturn(['status' => 1]);
        }
    }
}
