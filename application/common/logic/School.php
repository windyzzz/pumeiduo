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
        // 文章ID
        if (!empty($param['article_id'])) {
            return ['sa.id' => $param['article_id']];
        }
        // 模块分类
        if (!empty($param['class_id'])) {
            $where['sa.class_id'] = $param['class_id'];
        }
        // 状态
        if (!empty($param['status'])) {
            $where['sa.status'] = $param['status'];
        } else {
            $where['sa.status'] = 1;
        }
        // 是否是推荐
        if (!empty($param['is_recommend'])) {
            $where['sa.is_recommend'] = 1;
        }
        // 是否需要积分购买
        if (!empty($param['is_integral'])) {
            $where['sa.integral'] = ['>', 0];
        }
        // 等级限制
        if (!empty($param['distribute_level']) || $param['distribute_level'] !== '') {
            $level = explode(',', $param['distribute_level']);
            sort($level);
            $level = implode(',', $level);
            $where['sa.distribute_level'] = $level;
        }
        return $where;
    }

    /**
     * 用户文章记录条件
     * @param $param
     * @return array
     */
    private function userArticleWhere($param)
    {
        $where = [];
        // 状态
        if (!empty($param['status'])) {
            $where['usa.status'] = $param['status'];
        }
        $where['usa.type'] = 1;
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
                return ['status' => -1, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
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
     * @param $isResource
     * @return array
     */
    private function checkUserArticleLimit($article, $user, $isResource = false)
    {
        if (empty($article)) {
            return ['status' => 0, 'msg' => '文章数据不存在'];
        }
        if ($article['status'] != 1) {
            return ['status' => 0, 'msg' => '文章已失效'];
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
                return ['status' => -1, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
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
                if ($isResource) {
                    $tips = '下载素材';
                } else {
                    $tips = '购买课程';
                }
                if ($article['integral'] > $user['pay_points']) {
                    return ['status' => 0, 'msg' => $tips . '需要消费积分' . $article['integral'] . '，您的积分不足'];
                }
                accountLog($user['user_id'], 0, -$article['integral'], $tips . '消费积分', 0, 0, '', 0, 22);
            }
            $logData = [
                'user_id' => $user['user_id'],
                'article_id' => $article['id'],
                'integral' => $article['integral'],
                'add_time' => NOW_TIME
            ];
            if ($isResource) {
                $logData['type'] = 2;
                $logData['status'] = 1;
                $logData['finish_time'] = NOW_TIME;
            }
            // 用户课程记录
            M('user_school_article')->add($logData);
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
            $item['distribute_level'] = explode(',', $item['distribute_level']);
            rsort($item['distribute_level']);   // 等级限制
            $list[] = [
                'article_id' => $item['id'],
                'class_id' => $item['class_id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'cover' => [
                    'url' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'url:') + 4)),
                    'width' => $cover[1] ? substr($cover[1], strrpos($cover[1], 'width:') + 6) : 1,
                    'height' => $cover[2] ? substr($cover[2], strrpos($cover[2], 'height:') + 7) : 1,
                ],
                'learn' => $item['learn'],
                'share' => $item['share'],
                'integral' => $item['integral'],
                'distribute_level' => $item['distribute_level'][0],
                'publish_time' => format_time($item['publish_time']),
                'image' => [],
                'video' => [
                    'url' => '',
                    'cover' => '',
                    'axis' => '1',
                ],
                'user' => [
                    'user_name' => '',
                    'head_pic' => '',
                ]
            ];
        }
        // 素材专区数据处理
        if (!empty($param['module_type']) && $param['module_type'] == 'module6') {
            $resource = M('school_article_resource')->where(['article_id' => ['IN', $articleIds]])->select();
            $official = M('school_config')->where(['type' => 'official'])->find();
            $headUrl = explode(',', $official['url']);
            $official['url'] = $this->ossClient::url(substr($headUrl[0], strrpos($headUrl[0], 'url:') + 4));
            foreach ($list as $k => $l) {
                $list[$k]['user']['user_name'] = $official['name'];
                $list[$k]['user']['head_pic'] = $official['url'];
                foreach ($resource as $r) {
                    if ($l['article_id'] == $r['article_id']) {
                        if (!empty($r['image'])) {
                            $image = explode(',', $r['image']);
                            $list[$k]['image'][] = [
                                'url' => $this->ossClient::url(substr($image[0], strrpos($image[0], 'url:') + 4)),
                                'width' => $image[1],
                                'height' => $image[2],
                            ];
                        } elseif (!empty($r['video'])) {
                            $list[$k]['video']['url'] = $this->ossClient::url($r['video']);
                            $list[$k]['video']['cover'] = $this->ossClient::url($r['video_cover']);
                            $list[$k]['video']['axis'] = $r['video_axis'];
                            continue 1;
                        }
                    }
                }
            }
        }
        // 查看用户是否已购买学习课程
        $list = $this->checkUserArticle($list, $articleIds, $user);
        return ['sort_set' => (object)$sortSet, 'total' => $count, 'list' => $list];
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
        // 查看用户是否已购买学习课程
        $info = $this->checkUserArticle([$info], [$articleInfo['id']], $user)[0];
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

    /**
     * 获取用户文章列表
     * @param $limit
     * @param $param
     * @param $user
     * @return array
     */
    public function getUserArticle($limit, $param, $user)
    {
        // 搜索条件
        $where = $this->userArticleWhere($param);
        $where['usa.user_id'] = $user['user_id'];
        // 数据数量
        $count = M('user_school_article usa')->join('school_article sa', 'sa.id = usa.article_id')->where($where)->count();
        // 查询数据
        $page = new Page($count, $limit);
        $userArticle = M('user_school_article usa')->join('school_article sa', 'sa.id = usa.article_id')->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)->field('sa.*, usa.integral, usa.credit')->select();
        $list = [];
        foreach ($userArticle as $item) {
            $cover = explode(',', $item['cover']);  // 封面图
            $list[] = [
                'article_id' => $item['id'],
                'class_id' => $item['class_id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'cover' => [
                    'url' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'url:') + 4)),
                    'width' => $cover[1] ? substr($cover[1], strrpos($cover[1], 'width:') + 6) : 1,
                    'height' => $cover[2] ? substr($cover[2], strrpos($cover[2], 'height:') + 7) : 1,
                ],
                'learn' => $item['learn'],
                'share' => $item['share'],
                'integral' => $item['integral'],
                'distribute_level' => $item['distribute_level'],
                'publish_time' => format_time($item['publish_time']),
            ];
        }
        return ['total' => $count, 'list' => $list];
    }

    /**
     * 学习课程文章（完成）
     * @param $articleId
     * @param $user
     * @return array
     */
    public function learnArticle($articleId, $user)
    {
        $articleInfo = M('school_article')->where(['id' => $articleId])->find();
        $userSchoolArticle = M('user_school_article')->where(['user_id' => $user['user_id'], 'article_id' => $articleId])->find();
        if (empty($userSchoolArticle)) {
            return ['status' => 0, 'msg' => '请先购买课程'];
        }
        if ($userSchoolArticle['status'] == 0 || $userSchoolArticle['finish_time'] == 0) {
            M('user_school_article')->where(['id' => $userSchoolArticle['id']])->update([
                'status' => 1,
                'credit' => $articleInfo['credit'],
                'finish_time' => NOW_TIME
            ]);
            if ($articleInfo['credit'] > 0) {
                accountLog($user['user_id'], 0, 0, '课程学习完毕奖励学分', 0, 0, '', 0, 23, true, 0, $articleInfo['credit']);
            }
        }
        return ['credit' => $articleInfo['credit']];
    }

    /**
     * 下载文章素材
     * @param $param
     * @param $user
     * @return array
     */
    public function downloadArticleResource($param, $user)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 文章数据
        $articleInfo = M('school_article sa')->where($where)->find();
        // 查看阅览权限
        return $this->checkUserArticleLimit($articleInfo, $user, true);
    }
}
