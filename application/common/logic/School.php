<?php

namespace app\common\logic;


use think\Cache;
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

    /**
     * 校验用户-模块分类限制
     * @param $moduleClass
     * @param $user
     * @return array
     */
    private function checkUserClassLimit($moduleClass, $user)
    {
        if (empty($moduleClass)) {
            return ['status' => 0, 'msg' => '模块分类不存在'];
        }
        if ($moduleClass['is_open'] == 0) {
            return ['status' => 0, 'msg' => '模块分类已关闭'];
        }
        if ($moduleClass['is_allow'] == 0) {
            return ['status' => 0, 'msg' => '功能尚未开放'];
        }
        // 等级权限
        if ($moduleClass['distribute_level'] != 0) {
            $level = explode(',', $moduleClass['distribute_level']);
            if (!in_array($user['distribut_level'], $level)) {
                $levelName = '';
                foreach ($level as $lv) {
                    $levelName .= M('distribut_level')->where(['level_id' => $lv])->value('level_name') . '或';
                }
                $levelName = rtrim($levelName, '或');
                return ['status' => 0, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
            }
        }
        // 是否是学习课程
        if ($moduleClass['is_learn'] == 1) {
            $preClass = M('school_class')->where([
                'module_id' => $moduleClass['module_id'], 'sort' => ['>', $moduleClass['sort']],
                'is_open' => 1, 'is_allow' => 1
            ])->order('sort ASC')->find();    // 上一个课程
            if (!empty($preClass) && $preClass['is_learn'] == 1) {
                // 查看是否将上一个课程都学习完
                $articleIds = M('school_article')->where(['class_id' => $preClass['id'], 'learn_type' => 1])->getField('id', true);
                $userArticleCount = M('user_school_article')->where(['user_id' => $user['user_id'], 'article_id' => ['IN', $articleIds], 'status' => 1])->count();
                if ($userArticleCount != count($articleIds)) {
                    return ['status' => 0, 'msg' => '请先学习完' . $preClass['name'] . '的课程'];
                }
            }
        }
        return ['status' => 1];
    }

    /**
     * 检验用户-文章限制
     * @param $article
     * @param $user
     * @return array
     */
    private function checkUserArticleLimit($article, $user)
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
                return ['status' => 0, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
            }
        }
        // 是否已购买课程
        if (!M('user_school_article')->where(['user_id' => $user['user_id'], 'article_id' => $article['id']])->find()) {
            $preArticleIds = M('school_article')->where([
                'class_id' => $article['class_id'], 'sort' => ['>', $article['sort']],
                'learn_type' => 1, 'status' => 1
            ])->getField('id', true);   // 前面必修的课程文章
            if (!empty($preArticleIds)) {
                $userArticleCount = M('user_school_article')->where(['user_id' => $user['user_id'], 'article_id' => ['IN', $preArticleIds], 'status' => 1])->count();
                if ($userArticleCount != count($preArticleIds)) {
                    return ['status' => 0, 'msg' => '请按顺序学习课程'];
                }
            }
            // 课程消费积分
            if ($article['integral'] > 0) {
                if ($article['integral'] > $user['pay_points']) {
                    return ['status' => 0, 'msg' => '课程需要消费积分' . $article['integral'] . '，您的积分不足'];
                }
                accountLog($user['user_id'], 0, $article['integral'], '购买课程消费积分', 0, 0, '', 0, 22);
            }
            // 用户课程记录
            M('user_school_article')->add([
                'user_id' => $user['user_id'],
                'article_id' => $article['id'],
                'integral' => $article['integral'],
                'add_time' => NOW_TIME
            ]);
        }
        return ['status' => 1];
    }

    /**
     * 查看用户是否已购买学习课程
     * @param $articleList
     * @param $articleIds
     * @param $user
     * @return mixed
     */
    private function checkUserArticle($articleList, $articleIds, $user)
    {
        // 用户购买课程记录
        $userArticle = M('user_school_article')->where(['user_id' => $user['user_id'], 'article_id' => ['IN', $articleIds]])->getField('article_id, status', true);
        // 更新数据
        foreach ($articleList as &$article) {
            if (isset($userArticle[$article['article_id']])) {
                $article['is_buy'] = 1;
                $article['is_learn'] = $userArticle[$article['article_id']] == 1 ? 1 : 0;
            } else {
                $article['is_buy'] = 0;
                $article['is_learn'] = 0;
            }
        }
        return $articleList;
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
     * 获取模块列表
     * @return array
     */
    public function getModule()
    {
        $module = M('school')->where(['is_open' => 1])->order('sort DESC')->select();
        $list = [];
        foreach ($module as $item) {
            $img = explode(',', $item['img']);
            $list[] = [
                'module_id' => $item['id'],
                'icon' => [
                    'url' => $this->ossClient::url(substr($img[0], strrpos($img[0], 'url:') + 4)),
                    'width' => substr($img[1], strrpos($img[1], 'width:') + 6),
                    'height' => substr($img[2], strrpos($img[2], 'height:') + 7),
                ],
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
     * 获取模块分类列表
     * @param $moduleId
     * @return array
     */
    public function getModuleClass($moduleId)
    {
        $moduleClass = M('school_class')->where(['module_id' => $moduleId, 'is_open' => 1])->order('sort DESC')->select();
        $list = [];
        foreach ($moduleClass as $item) {
            $list[] = [
                'class_id' => $item['id'],
                'name' => $item['name'],
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
     * @param $user
     * @return array
     */
    public function getArticleList($limit, $param, $user)
    {
        // 查看校验限制
        if (!empty($param['class_id'])) {
            $moduleClass = M('school_class')->where(['id' => $param['class_id']])->find();
            $res = $this->checkUserClassLimit($moduleClass, $user);
            if ($res['status'] == 0) {
                return $res;
            }
        }
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
        $article = M('school_article sa')->where($where)->order($sort)->limit($page->firstRow . ',' . $page->listRows)->field('content', true)->select();
        $list = [];
        $articleIds = [];
        foreach ($article as $item) {
            $articleIds[] = $item['id'];
            $cover = explode(',', $item['cover']);  // 封面图
            $list[] = [
                'article_id' => $item['id'],
                'class_id' => $item['class_id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
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
        // 查看用户是否已购买学习课程
        $list = $this->checkUserArticle($list, $articleIds, $user);
        return ['total' => $count, 'list' => $list, 'sort_set' => $sortSet];
    }

    /**
     * 获取文章详情
     * @param $param
     * @param $user
     * @return array
     */
    public function getArticleInfo($param, $user)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 文章数据
        $articleInfo = M('school_article sa')->where($where)->find();
        // 查看阅览权限
        $res = $this->checkUserArticleLimit($articleInfo, $user);
        if ($res['status'] == 0) {
            return $res;
        }
        Cache::set('school_article_content_' . $param['article_id'], $articleInfo['content'], 60);  // 文章内容
        $cover = explode(',', $articleInfo['cover']);  // 封面图
        $info = [
            'article_id' => $articleInfo['id'],
            'class_id' => $articleInfo['class_id'],
            'title' => $articleInfo['title'],
            'subtitle' => $articleInfo['subtitle'],
            'cover' => [
                'url' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'url:') + 4)),
                'width' => substr($cover[1], strrpos($cover[1], 'width:') + 6),
                'height' => substr($cover[2], strrpos($cover[2], 'height:') + 7),
            ],
            'learn' => $articleInfo['learn'],
            'share' => $articleInfo['share'],
            'integral' => $articleInfo['integral'],
            'distribute_level' => $articleInfo['distribute_level'],
        ];
        return $info;
    }

    /**
     * 获取文章内容
     * @param $articleId
     * @return array
     */
    public function getArticleContent($articleId)
    {
        if (Cache::has('school_article_content_' . $articleId)) {
            $content = htmlspecialchars_decode(Cache::get('school_article_content_' . $articleId));
        } else {
            $content = M('school_article')->where(['id' => $articleId])->value('content');
            $content = htmlspecialchars_decode($content);
        }
        return ['content' => $content];
    }
}
