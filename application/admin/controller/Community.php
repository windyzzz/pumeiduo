<?php

namespace app\admin\controller;

use app\admin\model\CommunityArticle;
use think\Page;

class Community extends Base
{
    /**
     * 社区分类
     * @return mixed
     */
    public function category()
    {
        $act = I('act', 'list');
        $this->assign('act', $act);
        switch ($act) {
            case 'list':
                $cateList = [];
                // 一级分类数据
                $tCategory = M('community_category')->where(['level' => 0])->select();
                foreach ($tCategory as $cate1) {
                    $cateList[$cate1['id']] = $cate1;
                    // 下级分类数据
                    $dCategory = M('community_category')->where(['parent_id' => $cate1['id'], 'level' => 1])->select();
                    foreach ($dCategory as $cate2) {
                        $cateList[$cate2['id']] = $cate2;
                    }
                }
                $this->assign('cate_list', $cateList);
                return $this->fetch('category_list');
                break;
            case 'add':
                if (IS_POST) {
                    $postData = input('post.');
                    // 查看同级是否重复
                    $where = [
                        'parent_id' => $postData['parent_id'],
                        'cate_name' => $postData['cate_name']
                    ];
                    if (M('community_category')->where($where)->find()) {
                        $this->ajaxReturn(['status' => 0, 'msg' => '同级已有相同分类存在']);
                    }
                    if ($postData['parent_id'] != 0) {
                        // 查看上级
                        $tCategory = M('community_category')->where(['id' => $postData['parent_id']])->find();
                        $postData['level'] = $tCategory['level'] + 1;
                        $postData['user_can_publish'] = $tCategory['user_can_publish'] == 1 ? 1 : 0;
                    }
                    M('community_category')->add($postData);
                    $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'data' => ['url' => U('Admin/Community/category')]]);
                } else {
                    $parentId = I('parent_id', 0);
                    // 一级分类数据
                    $tCategory = M('community_category')->where(['level' => 0])->field('id, cate_name')->select();
                    $this->assign('act', $act);
                    $this->assign('parent_id', $parentId);
                    $this->assign('t_category', $tCategory);
                    return $this->fetch('category_info');
                }
                break;
            case 'edit':
                $id = I('id', '');
                if (IS_POST) {
                    $postData = input('post.');
                    // 查看同级是否重复
                    $where = [
                        'parent_id' => $postData['parent_id'],
                        'cate_name' => $postData['cate_name']
                    ];
                    $categoryList = M('community_category')->where($where)->select();
                    foreach ($categoryList as $cate) {
                        if ($id != $cate['id']) {
                            $this->ajaxReturn(['status' => 0, 'msg' => '同级已有相同分类存在']);
                        }
                    }
                    if ($postData['parent_id'] != 0) {
                        unset($postData['user_can_publish']);
                        // 查看上级
                        $tCategory = M('community_category')->where(['id' => $postData['parent_id']])->find();
                        $postData['level'] = !empty($tCategory) ? $tCategory['level'] + 1 : 0;
                    } else {
                        // 下级一并修改
                        M('community_category')->where(['parent_id' => $id])->update(['user_can_publish' => $postData['user_can_publish']]);
                    }
                    M('community_category')->where(['id' => $id])->update($postData);
                    $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'data' => ['url' => U('Admin/Community/category')]]);
                } else {
                    $category = M('community_category')->where(['id' => $id])->find();
                    $parentId = $category['parent_id'];
                    // 一级分类数据
                    $tCategory = M('community_category')->where(['level' => 0])->field('id, cate_name')->select();
                    $this->assign('parent_id', $parentId);
                    $this->assign('t_category', $tCategory);
                    $this->assign('category', $category);
                    return $this->fetch('category_info');
                }
                break;
            case 'del':
                $ids = I('post.ids', '');
                empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！', 'data' => '']);
                // 判断子分类
                $count = M('community_category')->where(['parent_id' => ['IN', $ids]])->count('id');
                $count > 0 && $this->ajaxReturn(['status' => -1, 'msg' => '该分类下还有分类不得删除!']);
                // 删除
                M('community_category')->where(['id' => ['IN', $ids]])->delete();
                $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/Community/category')]);
                break;
            case 'low_level':
                $parentId = I('cate_id', 0);
                if (!$parentId) {
                    $category = M('community_category')->where(['level' => 0])->select();
                } else {
                    $category = M('community_category')->where(['parent_id' => $parentId])->select();
                }
                $this->ajaxReturn(['status' => 1, 'result' => ['list' => $category]]);
        }
    }

    /**
     * 社区文章
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article()
    {
        $act = I('act', 'list');
        $this->assign('act', $act);
        // 一级分类数据
        $tCategory = M('community_category')->where(['level' => 0])->getField('id, cate_name');
        $this->assign('t_category', $tCategory);
        // 二级分类数据
        $dCategory = M('community_category')->where(['level' => 1])->getField('id, cate_name');
        switch ($act) {
            case 'list':
                // 文章列表数据
                $source = I('source', '');
                $cateId1 = I('cate_id1', 0);
                $cateId2 = I('cate_id2', 0);
                $status = I('status', '');
                $where = [];
                if ($source) {
                    $where['source'] = $source;
                }
                if ($cateId1) {
                    $where['cate_id1'] = $cateId1;
                }
                if ($cateId2) {
                    $where['cate_id2'] = $cateId2;
                }
                if ($status !== '') {
                    $where['status'] = $status;
                }
                $count = M('community_article')->where($where)->count();
                $page = new Page($count, 10);
                $articleModel = new CommunityArticle();
                $articleList = $articleModel->where($where)->limit($page->firstRow . ',' . $page->listRows)->order(['add_time DESC'])->select();
                foreach ($articleList as &$article) {
                    $article['cate_id1_desc'] = $tCategory[$article['cate_id1']];
                    $article['cate_id2_desc'] = $dCategory[$article['cate_id2']];
                }

                $this->assign('source', $source);
                $this->assign('cate_id1', $cateId1);
                $this->assign('cate_id2', $cateId2);
                $this->assign('status', $status);
                $this->assign('page', $page);
                $this->assign('article_list', $articleList);
                return $this->fetch('article_list');
                break;
            case 'edit':
                // 审核文章详情
                $articleId = I('article_id', 0);
                if (IS_POST) {
                    $status = I('status', 0);
                    $updata = ['status' => $status];
                    switch ($status) {
                        case 1:
                            $updata['publish_time'] = NOW_TIME;
                            break;
                    }
                    M('community_article')->where(['id' => $articleId])->update($updata);
                    // 审核记录
                    $logData = [
                        'article_id' => $articleId,
                        'status' => $status,
                        'reason' => I('reason'),
                        'admin_id' => session('admin_id'),
                        'add_time' => NOW_TIME
                    ];
                    M('community_article_verify_log')->add($logData);
                    $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
                }
                $articleModel = new CommunityArticle();
                $articleInfo = $articleModel->where(['id' => $articleId])->find();
                switch ($articleInfo['source']) {
                    case 1:
                        $userInfo = M('users')->where(['user_id' => $articleInfo['user_id']])->field('user_id, nickname, user_name')->find();
                        break;
                    case 2:
                        $userInfo = M('admin')->where(['admin_id' => $articleInfo['user_id']])->field('user_name')->find();
                        break;
                }
                $articleInfo['image'] = !empty($articleInfo['image']) ? explode(',', $articleInfo['image']) : [];
                $articleInfo['video'] = !empty($articleInfo['video']) ? \plugins\Oss::url($articleInfo['video']) : '';
                $articleInfo['cate_id1_desc'] = $tCategory[$articleInfo['cate_id1']];
                $articleInfo['cate_id2_desc'] = $dCategory[$articleInfo['cate_id2']];
                $articleInfo['goods_name'] = M('goods')->where(['goods_id' => $articleInfo['goods_id']])->value('goods_name');
                // 文章审核记录
                $articleLog = M('community_article_verify_log')->where(['article_id' => $articleId])->order('add_time DESC')->select();
                $this->assign('info', $articleInfo);
                $this->assign('user_info', $userInfo);
                $this->assign('log', $articleLog);
                return $this->fetch('article_info');
                break;
            case 'log':
                // 文章审核记录
                $articleId = I('article_id', 0);
                $count = M('community_article_verify_log')->where(['article_id' => $articleId])->count();
                $page = new Page($count, 10);
                $articleLog = M('community_article_verify_log')->where(['article_id' => $articleId])->limit($page->firstRow . ',' . $page->listRows)->order('add_time DESC')->select();
                $this->assign('page', $page);
                $this->assign('log', $articleLog);
                return $this->fetch('article_log');
                break;
            case 'add':
                // 添加文章
                if (IS_POST) {
                    $postData = I('post.');
                    // 验证参数
                    $validate = validate('Community');
                    if (!$validate->scene('article_add')->check($postData)) {
                        return $this->ajaxReturn(['status' => 0, 'msg' => $validate->getError()]);
                    }
                    unset($postData['image'][count($postData['image']) - 1]);
                    switch ($postData['upload_content']) {
                        case 1:
                            if (empty($postData['image'])) {
                                $this->ajaxReturn(['status' => 0, 'msg' => '请上传图片']);
                            }
                            $postData['image'] = implode(',', $postData['image']);
                            $postData['video'] = '';
                            break;
                        case 2:
                            if (empty($postData['video'])) {
                                $this->ajaxReturn(['status' => 0, 'msg' => '请上传视频']);
                            }
                            $postData['image'] = '';
                            break;
                    }
                    unset($postData['upload_content']);
                    $postData['user_id'] = session('admin_id');
                    $postData['add_time'] = NOW_TIME;
                    $postData['publish_time'] = strtotime($postData['publish_time']);
                    $postData['status'] = 2;
                    $postData['source'] = 2;
                    M('community_article')->add($postData);
                    $this->ajaxReturn(['status' => 1, 'msg' => '添加成功']);
                }
                return $this->fetch('article_add');
                break;
        }
    }
}