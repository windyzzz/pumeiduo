<?php

namespace app\home\controller\api;


class Community extends Base
{
    /**
     * 获取全部分类
     * @return \think\response\Json
     */
    public function allCategory()
    {
        $cateList = [
            'category' => []
        ];
        // 一级分类
        $tCategoryList = M('community_category')->where(['level' => 0, 'status' => 1])->order('sort DESC')->field('id, cate_name')->select();
        if (!empty($tCategoryList)) {
            // 二级分类
            $dCategoryList = M('community_category')->where(['level' => 1, 'status' => 1])->order('sort DESC')->field('id, cate_name, parent_id')->select();
            foreach ($tCategoryList as $k => $cate1) {
                $cateList['category'][$k] = [
                    'id' => $cate1['id'],
                    'name' => $cate1['cate_name'],
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


    public function article()
    {
        $cateId = I('cate_id', 0);

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
        if (!$validate->scene('add')->check($post)) {
            return json(['status' => 0, 'msg' => $validate->getError()]);
        }
        // 保存更新数据
        $post['user_id'] = $this->user_id;
        if ($articleId) {
            $post['update_time'] = NOW_TIME;
            $post['status'] = 0;
            $post['publish_time'] = 0;
            M('community_article')->where(['id' => $articleId])->update($post);
        } else {
            $post['add_time'] = NOW_TIME;
            $articleId = M('community_article')->add($post);
        }
        return json(['status' => 1, 'result' => ['article_id' => $articleId]]);
    }
}