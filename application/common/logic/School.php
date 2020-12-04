<?php

namespace app\common\logic;


use think\Page;

class School
{
    private $ossClient = null;

    public function __construct()
    {
        $this->ossClient = new OssLogic();
    }

    /**
     * 文章条件
     * @param $param
     * @return array
     */
    private function articleWhere($param)
    {
        $where = [];
        if (!empty($param['article_id'])) {
            return ['sa.id' => $param['article_id']];
        }
        if (!empty($param['class_id'])) {
            return ['sa.class_id' => $param['class_id']];
        }
        if (!empty($param['status'])) {
            $where['sa.status'] = $param['status'];
        } else {
            $where['sa.status'] = 1;
        }
        if (!empty($param['is_recommend'])) {
            $where['sa.is_recommend'] = 1;
        }
        if (!empty($param['is_integral'])) {
            $where['sa.integral'] = ['>', 0];
        }
        if (!empty($param['distribute_level']) || $param['distribute_level'] !== '') {
            $level = explode(',', $param['distribute_level']);
            sort($level);
            $level = implode(',', $level);
            $where['sa.distribute_level'] = $level;
        }
        return $where;
    }

    /**
     * 文章排序
     * @param $param
     * @return array
     */
    private function articleSort($param)
    {
        $sort = '';
        $sortSet = [];
//        $sortSet = [
//            'sort' => 'DESC',
//            'publish_time' => 'DESC',
//            'learn' => 'DESC',
//            'share' => 'DESC',
//        ];
//        if (!empty($param['sort']) && !empty($param['order'])) {
//            $paramSort = explode(',', $param['sort']);
//            $paramOrder = explode(',', $param['order']);
//            if (count($paramSort) == count($paramOrder)) {
//                foreach ($paramSort as $k => $v) {
//                    $sort .= $v . ' ' . $paramOrder[$k] . ',';
//                    $sortSet[$v] = $paramOrder[$k];
//                }
//            }
//        }
        $sort .= ' sort DESC, publish_time DESC';
        return ['sort' => $sort, 'sort_set' => $sortSet];
    }


    private function checkUserArticle($user, $article)
    {
        if (empty($article)) {
            return ['status' => 0, 'msg' => '文章数据不存在'];
        }
        // 等级权限
        if ($article['distribute_level'] != 0) {
            $level = explode(',', $article['distribute_level']);
            if (!in_array($user['distribut_level'], $level)) {
                $levelName = '';
                foreach ($level as $lv) {
                    $levelName .= M('distribut_level')->where(['level_id' => $lv])->value('level_name') . '或';
                }
                $levelName = rtrim($levelName, '或');
                return ['status' => 0, 'msg' => '您当前还不是' . $levelName . '，没有访问权限'];
            }
        }
        // 是否已阅览
        if (!M('user_school_article')->where(['user_id' => $user['user_id'], 'article_id' => $article['id']])->find()) {

        }
        return ['status' => 1];
    }

    /**
     * 获取轮播图
     * @param int $moduleId
     * @return array
     */
    public function getRotate($moduleId = 0)
    {
        $rotate = M('school_rotate')->where(['module_id' => $moduleId, 'is_open' => 1])->order('sort DESC')->limit(0, 3)->select();
        $list = [];
        foreach ($rotate as $item) {
            $url = explode(',', $item['url']);
            $list[] = [
                'url' => $this->ossClient::url(substr($url[0], strrpos($url[0], 'url:') + 4)),
                'width' => substr($url[1], strrpos($url[1], 'width:') + 6),
                'height' => substr($url[2], strrpos($url[2], 'height:') + 7),
                'type' => $item['module_type']
            ];
        }
        return ['list' => $list];
    }

    /**
     * 获取首页icon
     * @return array
     */
    public function getIcon()
    {
        $icon = M('school')->where(['is_open' => 1])->order('sort DESC')->select();
        $list = [];
        foreach ($icon as $item) {
            $img = explode(',', $item['img']);
            $list[] = [
                'id' => $item['id'],
                'url' => $this->ossClient::url(substr($img[0], strrpos($img[0], 'url:') + 4)),
                'width' => substr($img[1], strrpos($img[1], 'width:') + 6),
                'height' => substr($img[2], strrpos($img[2], 'height:') + 7),
                'name' => $item['name'],
                'type' => $item['type'],
                'is_allow' => (int)$item['is_allow'],
                'tips' => '功能尚未开放',
                'need_login' => 1,
            ];
        }
        return ['row_num' => 4, 'list' => $list];
    }

    /**
     * 获取文章列表
     * @param $limit
     * @param $param
     * @return array
     */
    public function getArticleList($limit, $param)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 排序
        $sortParam = $this->articleSort($param);
        $sort = $sortParam['sort'];
        $sortSet = $sortParam['sort_set'];
        // 数据数量
        $count = M('school_article sa')->where($where)->count();
        // 查询数据
        $page = new Page($count, $limit);
        $article = M('school_article sa')->where($where)->order($sort)->limit($page->firstRow . ',' . $page->listRows)->select();
        $list = [];
        foreach ($article as $item) {
            $cover = explode(',', $item['cover']);  // 封面图
            $list[] = [
                'article_id' => $item['id'],
                'class_id' => $item['class_id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'content' => htmlspecialchars_decode($item['content']),
                'cover' => [
                    'url' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'url:') + 4)),
                    'width' => substr($cover[1], strrpos($cover[1], 'width:') + 6),
                    'height' => substr($cover[2], strrpos($cover[2], 'height:') + 7),
                ],
                'learn' => $item['learn'],
                'share' => $item['share'],
                'integral' => $item['integral'],
                'distribute_level' => $item['distribute_level'],
            ];
        }
        return ['total' => $count, 'list' => $list, 'sort_set' => $sortSet];
    }



    public function getArticleInfo($param, $user)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 文章数据
        $articleInfo = M('school_article sa')->where($where)->find();
        // 查看阅览权限
        $res = $this->checkUserArticle($user, $articleInfo);
        if ($res['status'] == 0) {
            return $res;
        }
        return $res;
    }
}
