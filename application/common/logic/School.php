<?php

namespace app\common\logic;


use app\common\util\TpshopException;
use think\Cache;
use think\Db;
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
        // 搜索词
        if (!empty($param['keyword'])) {
            $resourceArticleIds = M('school_article_resource')->getField('article_id', true);
            $where['sa.id'] = ['NOT IN', $resourceArticleIds];
        }
        if (count($where) == 1) {
            // 防止前端没有传参
            $where['sa.class_id'] = 1;
        }
        return $where;
    }

    /**
     * 文章条件
     * @param $param
     * @return array
     */
    private function articleWhereOr($param)
    {
        $whereOr = [];
        // 搜索词
        if (!empty($param['keyword'])) {
            $param['keyword'] = htmlspecialchars_decode($param['keyword']);
            $whereOr['sa.title'] = ['LIKE', '%' . $param['keyword'] . '%'];
            $whereOr['sa.content'] = ['LIKE', '%' . $param['keyword'] . '%'];
            // 搜索量增加
            M('school_article_keyword')->where(['name' => $param['keyword']])->setInc('click');
        }
        return $whereOr;
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
     * 校验用户-模块限制
     * @param $module
     * @param $user
     * @return array
     */
    private function checkUserModuleLimit($module, $user)
    {
        if (empty($module)) {
            return ['status' => 0, 'msg' => '模块不存在'];
        }
        if ($module['is_open'] == 0) {
            return ['status' => 0, 'msg' => '模块已关闭'];
        }
        if ($module['is_allow'] == 0) {
            return ['status' => 0, 'msg' => '功能尚未开放'];
        }
        // 等级权限
        if ($module['distribute_level'] != 0) {
            $limitLevel = explode(',', $module['distribute_level']);
            $svipLevel = M('svip_level')->getField('app_level, name', true);
            if (!in_array($user['distribut_level'], $limitLevel)) {
                if ($user['svip_level'] == 3 && $limitLevel[0] < 4) {
                    switch ($limitLevel[0]) {
                        case 2:
                            $status = -2;
                            break;
                        case 3:
                            $status = -1;
                            break;
                        default:
                            $status = 0;
                    }
                    $levelName = M('distribut_level')->where(['level_id' => $limitLevel[0]])->value('level_name');
                    return ['status' => $status, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
                } else if (!in_array($user['svip_level'], $limitLevel)) {
                    $levelName = $svipLevel[$limitLevel[0]];
                    return ['status' => 0, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
                }
            }
        }
        return ['status' => 1, 'msg' => 'ok'];
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
            $limitLevel = explode(',', $moduleClass['distribute_level']);
            $svipLevel = M('svip_level')->getField('app_level, name', true);
            if (!in_array($user['distribut_level'], $limitLevel)) {
                if ($user['svip_level'] == 3 && $limitLevel[0] < 4) {
                    switch ($limitLevel[0]) {
                        case 2:
                            $status = -2;
                            break;
                        case 3:
                            $status = -1;
                            break;
                        default:
                            $status = 0;
                    }
                    $levelName = M('distribut_level')->where(['level_id' => $limitLevel[0]])->value('level_name');
                    return ['status' => $status, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
                } else if (!in_array($user['svip_level'], $limitLevel)) {
                    $levelName = $svipLevel[$limitLevel[0]];
                    return ['status' => 0, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
                }
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
        return ['status' => 1, 'msg' => 'ok'];
    }

    /**
     * 检验用户-文章限制
     * @param $article
     * @param $user
     * @param int $type 1普通文章 2素材文章
     * @return array
     */
    private function checkUserArticleLimit($article, $user, $type = 1)
    {
        if (empty($article)) {
            return ['status' => 0, 'msg' => '文章数据不存在'];
        }
        if ($article['status'] != 1) {
            return ['status' => 0, 'msg' => '文章已失效'];
        }
        // 点击量+1
        M('school_article')->where(['id' => $article['id']])->setInc('click', 1);
        $user = M('users')->where(['user_id' => $user['user_id']])->find();
        // 等级权限
        if ($article['distribute_level'] != 0) {
            $limitLevel = explode(',', $article['distribute_level']);
            $svipLevel = M('svip_level')->getField('app_level, name', true);
            if (!in_array($user['distribut_level'], $limitLevel)) {
                if ($user['svip_level'] == 3 && $limitLevel[0] < 4) {
                    switch ($limitLevel[0]) {
                        case 2:
                            $status = -2;
                            break;
                        case 3:
                            $status = -1;
                            break;
                        default:
                            $status = 0;
                    }
                    $levelName = M('distribut_level')->where(['level_id' => $limitLevel[0]])->value('level_name');
                    return ['status' => $status, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
                } else if (!in_array($user['svip_level'], $limitLevel)) {
                    $levelName = $svipLevel[$limitLevel[0]];
                    return ['status' => 0, 'msg' => '您当前不是' . $levelName . '，没有访问权限'];
                }
            }
        }
        // 是否已购买课程
        if (!M('user_school_article')->where(['user_id' => $user['user_id'], 'article_id' => $article['id']])->value('id')) {
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
                switch ($type) {
                    case 1:
                        $tips = '购买课程';
                        break;
                    case 2:
                        $tips = '下载素材';
                        break;
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
            if (in_array($article['learn_type'], [1, 2])) {
                $logData['is_learn'] = 1;
            }
            if ($article['learn_time'] == 0) {
                $logData['status'] = 1;
                $logData['finish_time'] = NOW_TIME;
            }
            switch ($type) {
                case 1:
                    break;
                case 2:
                    $logData['type'] = 2;
                    $logData['status'] = 1;
                    $logData['finish_time'] = NOW_TIME;
                    break;
            }
            // 用户课程记录
            M('user_school_article')->add($logData);
            // 课程学习数+1
            M('school_article')->where(['id' => $article['id']])->setInc('learn', 1);
        }
        return ['status' => 1, 'msg' => 'ok'];
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
     * 获取弹窗通知
     * @return array
     */
    public function getPopup($userId)
    {
        $return = [
            'is_open' => 0,
            'img' => [
                'img' => '',
                'width' => '',
                'height' => '',
                'type' => '',
            ],
            'article_id' => ''
        ];
        $popupConfig = M('school_config')->where(['type' => 'popup'])->find();
        if (!$popupConfig) {
            return $return;
        }
        // 查看此用户是否已经弹出过
        if (M('user_school_config')->where(['type' => 'popup', 'user_id' => $userId])->value('id')) {
            return $return;
        }
        M('user_school_config')->add([
            'type' => 'popup',
            'user_id' => $userId,
            'add_time' => NOW_TIME
        ]);
        $content = explode(',', $popupConfig['content']);
        $popupConfig['content'] = [];
        foreach ($content as $value) {
            $key = substr($value, 0, strrpos($value, ':'));
            $value = substr($value, strrpos($value, ':') + 1);
            $popupConfig['content'][$key] = $value;
        }
        if ($popupConfig['content']['is_open'] == 0) {
            return $return;
        }
        $url = explode(',', $popupConfig['url']);
        $return['img']['img'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
        $return['img']['width'] = substr($url[1], strrpos($url[1], 'width:') + 6);
        $return['img']['height'] = substr($url[2], strrpos($url[2], 'height:') + 7);
        $return['img']['type'] = substr($url[3], strrpos($url[3], 'type:') + 5);
        $return['article_id'] = $popupConfig['content']['article_id'];
        $return['is_open'] = 1;
        return $return;
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
            if ($item['module_type']) {
                $module = M('school')->where(['type' => $item['module_type']])->find();
            } elseif ($moduleId) {
                $module = M('school')->where(['id' => $moduleId])->find();
            }
            $url = explode(',', $item['url']);
            $list[] = [
                'img' => [
                    'img' => $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4)),
                    'width' => substr($url[1], strrpos($url[1], 'width:') + 6),
                    'height' => substr($url[2], strrpos($url[2], 'height:') + 7),
                    'type' => substr($url[3], strrpos($url[3], 'type:') + 5),
                ],
                'code' => $item['module_type'],
                'name' => isset($module) ? $module['name'] : '',
                'module_id' => isset($module) ? $module['id'] : '0',
                'article_id' => $item['article_id'],
                'is_allow' => isset($module) ? (int)$module['is_allow'] : 0,
                'tips' => '功能尚未开放',
                'need_login' => 1,
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
        $module1 = M('school')->where(['type' => ['IN', ['module9', 'module1', 'module2']]])->select();
        $list = [];
        foreach ($module1 as $item) {
            $img = explode(',', $item['img']);
            if ($item['type'] == 'module9') {
                $temp = [
                    'module_id' => $item['id'],
                    'img' => [
                        'img' => $this->ossClient::url(substr($img[0], strrpos($img[0], 'img:') + 4)),
                        'width' => substr($img[1], strrpos($img[1], 'width:') + 6),
                        'height' => substr($img[2], strrpos($img[2], 'height:') + 7),
                        'type' => substr($img[3], strrpos($img[3], 'type:') + 5),
                    ],
                    'name' => $item['name'],
                    'desc' => $item['desc'] ?? '',
                    'code' => $item['type'],
                    'is_allow' => (int)$item['is_allow'],
                    'tips' => '功能尚未开放',
                    'need_login' => 1,
                ];
                array_unshift($list, $temp);
            } else {
                $list[] = [
                    'module_id' => $item['id'],
                    'img' => [
                        'img' => $this->ossClient::url(substr($img[0], strrpos($img[0], 'img:') + 4)),
                        'width' => substr($img[1], strrpos($img[1], 'width:') + 6),
                        'height' => substr($img[2], strrpos($img[2], 'height:') + 7),
                        'type' => substr($img[3], strrpos($img[3], 'type:') + 5),
                    ],
                    'name' => $item['name'],
                    'desc' => $item['desc'] ?? '',
                    'code' => $item['type'],
                    'is_allow' => (int)$item['is_allow'],
                    'tips' => '功能尚未开放',
                    'need_login' => 1,
                ];
            }
        }
        $module2 = M('school')->where(['is_open' => 1, 'type' => ['NOT IN', ['module9', 'module1', 'module2']]])->order('sort DESC')->select();
        foreach ($module2 as $item) {
            $img = explode(',', $item['img']);
            $list[] = [
                'module_id' => $item['id'],
                'img' => [
                    'img' => $this->ossClient::url(substr($img[0], strrpos($img[0], 'img:') + 4)),
                    'width' => substr($img[1], strrpos($img[1], 'width:') + 6),
                    'height' => substr($img[2], strrpos($img[2], 'height:') + 7),
                    'type' => substr($img[3], strrpos($img[3], 'type:') + 5),
                ],
                'name' => $item['name'],
                'desc' => $item['desc'] ?? '',
                'code' => $item['type'],
                'is_allow' => (int)$item['is_allow'],
                'tips' => '功能尚未开放',
                'need_login' => 1,
            ];
        }
        return ['config' => ['row_num' => 2], 'list' => $list];
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
        return ['list' => $list];
    }

    /**
     * 用户模块权限检查
     * @param $moduleId
     * @param $user
     * @return array
     */
    public function checkModule($moduleId, $user)
    {
        $module = M('school')->where(['id' => $moduleId])->find();
        // 查看阅览权限
        $res = $this->checkUserModuleLimit($module, $user);
        return $res;
    }

    /**
     * 用户文章权限检查
     * @param $param
     * @param $user
     * @return array
     */
    public function checkArticle($param, $user)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 文章数据
        $articleInfo = M('school_article sa')->where($where)->find();
        // 查看阅览权限
        $res = $this->checkUserArticleLimit($articleInfo, $user, 1);
        return $res;
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
            if ($res['status'] != 1) {
                return $res;
            }
        }
        // 搜索条件
        $where = $this->articleWhere($param);
        $whereOr = $this->articleWhereOr($param);
        // 排序
        $sortParam = $this->articleSort($param);
        $sort = $sortParam['sort'];
        $sortSet = $sortParam['sort_set'];
        // 数据数量
        $count = M('school_article sa')->where($where);
        if (!empty($whereOr)) {
            $count = $count->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $count = $count->count();
        // 查询数据
        $page = new Page($count, $limit);
        $article = M('school_article sa')->where($where);
        if (!empty($whereOr)) {
            $article = $article->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $article = $article->order($sort)->limit($page->firstRow . ',' . $page->listRows)->select();
        $list = [];
        $articleIds = [];
        $fileList = [];     // 附件列表
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
                'content' => !empty($param['module_type']) && $param['module_type'] == 'module6' ? htmlspecialchars_decode($item['content']) : '',
                'cover' => [
                    'img' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'img:') + 4)),
                    'width' => $cover[1] ? substr($cover[1], strrpos($cover[1], 'width:') + 6) : '1',
                    'height' => $cover[2] ? substr($cover[2], strrpos($cover[2], 'height:') + 7) : '1',
                    'type' => $cover[3] ? substr($cover[3], strrpos($cover[3], 'type:') + 5) : '1',
                ],
                'learn_type' => $item['learn_type'],
                'learn' => $item['learn'],
                'share' => $item['share'],
                'click' => $item['click'],
                'integral' => $item['integral'],
                'distribute_level' => $item['distribute_level'][0] == 3 && count($item['distribute_level']) > 1 ? $item['distribute_level'][1] : $item['distribute_level'][0],
                'publish_time' => format_time($item['publish_time']),
                'image' => [],
                'video' => [
                    'url' => '',
                    'cover' => '',
                    'axis' => '1',
                ],
                'file' => [
                    'url' => '',
                    'type' => '',
                    'name' => '',
                ],
                'user' => [
                    'user_name' => '',
                    'head_pic' => '',
                ]
            ];
            if (!empty($item['file'])) {
                $item['file'] = explode(',', $item['file']);
                $fileList[$item['id']] = $item['file'];
            }
        }
        // 素材专区数据处理
        if (!empty($param['module_type']) && $param['module_type'] == 'module6') {
            $resource = M('school_article_resource')->where(['article_id' => ['IN', $articleIds]])->select();
            $official = M('school_config')->where(['type' => 'official'])->find();
            if ($official) {
                $headUrl = explode(',', $official['url']);
                $official['url'] = $this->ossClient::url(substr($headUrl[0], strrpos($headUrl[0], 'img:') + 4));
            }
            foreach ($list as $k => $l) {
                $list[$k]['file']['url'] = isset($fileList[$l['article_id']]) ? $this->ossClient::url(substr($fileList[$l['article_id']][0], strrpos($fileList[$l['article_id']][0], 'url:') + 4)) : '';
                $list[$k]['file']['type'] = isset($fileList[$l['article_id']]) ? substr($fileList[$l['article_id']][1], strrpos($fileList[$l['article_id']][1], 'type:') + 5) : '';
                $list[$k]['file']['name'] = isset($fileList[$l['article_id']]) ? substr($fileList[$l['article_id']][0], strrpos($fileList[$l['article_id']][0], '/') + 1) : '';
                $list[$k]['user']['user_name'] = $official ? $official['name'] : '';
                $list[$k]['user']['head_pic'] = $official ? $official['url'] : '';
                foreach ($resource as $r) {
                    if ($l['article_id'] == $r['article_id']) {
                        if (!empty($r['image'])) {
                            $image = explode(',', $r['image']);
                            $list[$k]['image'][] = [
                                'img' => $this->ossClient::url(substr($image[0], strrpos($image[0], 'img:') + 4)),
                                'width' => substr($image[1], strrpos($image[1], 'width:') + 6),
                                'height' => substr($image[2], strrpos($image[2], 'height:') + 7),
                                'type' => substr($image[3], strrpos($image[3], 'type:') + 5),
                            ];
                        } elseif (!empty($r['video'])) {
                            $list[$k]['video']['url'] = $this->ossClient::url($r['video']);
                            $list[$k]['video']['cover'] = $this->ossClient::url($r['video_cover']);
                            $list[$k]['video']['axis'] = $r['video_axis'];
                            break 1;
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
        if ($user) {
            // 查看阅览权限
            $res = $this->checkUserArticleLimit($articleInfo, $user, 1);
            if ($res['status'] != 1) {
                return $res;
            }
        }
        Cache::set('school_article_content_' . $param['article_id'], $articleInfo['content'], 60);  // 文章内容
        $cover = explode(',', $articleInfo['cover']);  // 封面图
        $articleInfo['distribute_level'] = explode(',', $articleInfo['distribute_level']);
        rsort($articleInfo['distribute_level']);   // 等级限制
        $info = [
            'article_id' => $articleInfo['id'],
            'class_id' => $articleInfo['class_id'],
            'title' => $articleInfo['title'],
            'subtitle' => $articleInfo['subtitle'],
            'content_url' => SITE_URL . '/#/news/app_school_article?article_id=' . $param['article_id'],
            'cover' => [
                'img' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'img:') + 4)),
                'width' => substr($cover[1], strrpos($cover[1], 'width:') + 6),
                'height' => substr($cover[2], strrpos($cover[2], 'height:') + 7),
                'type' => substr($cover[3], strrpos($cover[3], 'type:') + 5),
            ],
            'learn_time' => $articleInfo['learn_time'],
            'learn_time_format' => second_to_minute($articleInfo['learn_time']),
            'learn_type' => $articleInfo['learn_type'],
            'learn' => $articleInfo['learn'],
            'share' => $articleInfo['share'],
            'click' => $articleInfo['click'],
            'integral' => $articleInfo['integral'],
            'distribute_level' => $articleInfo['distribute_level'][0] == 3 && count($articleInfo['distribute_level']) > 1 ? $articleInfo['distribute_level'][1] : $articleInfo['distribute_level'][0],
            'show_goods' => $articleInfo['show_goods'] ? 1 : 0,
        ];
        if ($user) {
            // 查看用户是否已购买学习课程
            $info = $this->checkUserArticle([$info], [$articleInfo['id']], $user)[0];
        }
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
     * 获取文章分享二维码
     * @param $articleId
     * @param $user
     * @return array
     */
    public function getArticleShareCode($articleId, $user)
    {
        $qrCodeInfo = M('user_school_article_qrcode')->where(['user_id' => $user['user_id'], 'article_id' => $articleId])->value('img');
        if ($qrCodeInfo) {
            $qrCodeInfo = explode(',', $qrCodeInfo);
        }
        if ($qrCodeInfo && is_array($qrCodeInfo) && $this->ossClient->checkFile($qrCodeInfo[0])) {
            // 文件在oss服务器存在
            $img = [
                'img' => $this->ossClient::url(substr($qrCodeInfo[0], strrpos($qrCodeInfo[0], 'img:') + 4)),
                'width' => substr($qrCodeInfo[1], strrpos($qrCodeInfo[1], 'width:') + 6),
                'height' => substr($qrCodeInfo[2], strrpos($qrCodeInfo[2], 'height:') + 7),
                'type' => substr($qrCodeInfo[3], strrpos($qrCodeInfo[3], 'type:') + 5),
            ];
        } else {
            $param = [
                'article_id' => $articleId,
                'distribute_level' => $user['distribut_level']
            ];
            $qrPath = create_qrcode('school_article', $user['user_id'], $param);
            if (!$qrPath) {
                return ['status' => 0, 'msg' => '生成失败'];
            }
            // 上传到oss服务器
            $filePath = PUBLIC_PATH . substr($qrPath, strrpos($qrPath, 'public/') + 7);
            $fileName = substr($qrPath, strrpos($qrPath, '/') + 1);
            $object = 'image/' . date('Y/m/d/H/') . $fileName;
            $return_url = $this->ossClient->uploadFile($filePath, $object);
            if (!$return_url) {
                return ['status' => 0, 'msg' => 'ERROR：' . $this->ossClient->getError()];
            }
            // 二维码信息
            $imageInfo = getimagesize($filePath);
            $img = [
                'img' => $this->ossClient::url($object),
                'width' => $imageInfo[0] . '',
                'height' => $imageInfo[0] . '',
                'type' => substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1),
            ];
            unlink($filePath);
            // 信息记录
            M('user_school_article_qrcode')->where(['user_id' => $user['user_id'], 'article_id' => $articleId])->delete();
            M('user_school_article_qrcode')->add([
                'user_id' => $user['user_id'],
                'article_id' => $articleId,
                'img' => 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1),
                'add_time' => NOW_TIME
            ]);
        }
        // 文章信息
        $articleInfo = M('school_article')->where(['id' => $articleId])->field('title, subtitle, cover')->find();
        $cover = explode(',', $articleInfo['cover']);  // 封面图
        $data = [
            'qrcode' => $img,
            'title' => $articleInfo['title'],
            'subtitle' => $articleInfo['subtitle'],
            'cover' => [
                'img' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'img:') + 4)),
                'width' => substr($cover[1], strrpos($cover[1], 'width:') + 6),
                'height' => substr($cover[2], strrpos($cover[2], 'height:') + 7),
                'type' => substr($cover[3], strrpos($cover[3], 'type:') + 5),
            ],
            'user_id' => $user['user_id'],
            'share_link' => SITE_URL . '/#/news/school_article?article_id=' . $articleId . '&distribute_level=' . $user['distribut_level']
        ];
        // 更新分享次数
        M('school_article')->where(['id' => $articleId])->setInc('share', 1);
        return $data;
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
        $where['usa.integral'] = ['>', 0];
        // 数据数量
        $count = M('user_school_article usa')->join('school_article sa', 'sa.id = usa.article_id')->where($where)->count();
        // 查询数据
        $page = new Page($count, $limit);
        $userArticle = M('user_school_article usa')->join('school_article sa', 'sa.id = usa.article_id')->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)->field('sa.*, usa.integral as use_integral, usa.credit')->select();
        $list = [];
        foreach ($userArticle as $item) {
            $cover = explode(',', $item['cover']);  // 封面图
            $item['distribute_level'] = explode(',', $item['distribute_level']);
            rsort($item['distribute_level']);   // 等级限制
            $list[] = [
                'article_id' => $item['id'],
                'class_id' => $item['class_id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'cover' => [
                    'img' => $this->ossClient::url(substr($cover[0], strrpos($cover[0], 'img:') + 4)),
                    'width' => substr($cover[1], strrpos($cover[1], 'width:') + 6),
                    'height' => substr($cover[2], strrpos($cover[2], 'height:') + 7),
                    'type' => substr($cover[3], strrpos($cover[3], 'type:') + 5),
                ],
                'learn' => $item['learn'],
                'share' => $item['share'],
                'integral' => $item['use_integral'],    // 用户购买消费积分
                'distribute_level' => $item['distribute_level'][0] == 3 && count($item['distribute_level']) > 1 ? $item['distribute_level'][1] : $item['distribute_level'][0],
                'publish_time' => format_time($item['publish_time']),
                'status' => $item['status'],
                'times' => $item['times'],
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
        // 学习次数+1
        M('user_school_article')->where(['id' => $userSchoolArticle['id']])->setInc('times');
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
        return $this->checkUserArticleLimit($articleInfo, $user, 2);
    }

    /**
     * 获取兑换商品列表
     * @param $limit
     * @return array
     */
    public function getExchangeList($limit)
    {
        // 查询条件
        $where = [
            'se.is_open' => 1,
            'g.is_on_sale' => 1
        ];
        // 数据数量
        $count = M('school_exchange se')->join('goods g', 'g.goods_id = se.goods_id')->where($where)->count();
        // 查询数据
        $page = new Page($count, $limit);
        $goodsIds = M('school_exchange se')->join('goods g', 'g.goods_id = se.goods_id')->where($where)->getField('se.goods_id', true);
        $goodsList = M('school_exchange se')->join('goods g', 'g.goods_id = se.goods_id')->where($where)
            ->field('se.*, g.goods_name, g.original_img')->order('se.sort DESC, se.id ASC')->limit($page->firstRow . ',' . $page->listRows)->select();
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $goodsIds], 'title' => ['NEQ', ''], 'status' => 1])->select();
        $list = [];
        foreach ($goodsList as $key => $goods) {
            $list[$key] = [
                'goods_id' => $goods['goods_id'],
                'item_id' => $goods['item_id'],
                'goods_name' => $goods['goods_name'],
                'original_img_new' => getFullPath($goods['original_img']),
                'credit' => $goods['credit'],
                'tabs' => []
            ];
            // 规格属性
            if ($goods['item_id']) {
                $list[$key]['goods_name'] .= ' ' . M('spec_goods_price')->where(['item_id' => $goods['item_id']])->value('key_name');
            }
            // 商品标签
            if (!empty($goodsTab)) {
                foreach ($goodsTab as $value) {
                    if ($goods['goods_id'] == $value['goods_id']) {
                        $list[$key]['tabs'][] = $value['title'];
                    }
                }
            }
        }
        return ['total' => $count, 'list' => $list];
    }

    /**
     * 获取兑换商品详情
     * @param $goodsId
     * @param $itemId
     * @return array
     */
    public function getExchangeInfo($goodsId, $itemId)
    {
        $where = [
            'se.goods_id' => $goodsId,
            'se.item_id' => $itemId,
            'se.is_open' => 1,
            'g.is_on_sale' => 1
        ];
        $goodsInfo = M('school_exchange se')->join('goods g', 'g.goods_id = se.goods_id')->where($where)
            ->field('se.item_id, se.credit, g.*')->order('se.sort DESC, se.id ASC')->find();
        if (empty($goodsInfo)) {
            return ['status' => 0, 'msg' => '商品已下架'];
        }
        if ($goodsInfo['item_id']) {
            $itemInfo = M('spec_goods_price')->where(['item_id' => $goodsInfo['item_id']])->find();
            $goodsInfo['goods_name'] .= ' ' . $itemInfo['key_name'];
            $goodsInfo['store_count'] = $itemInfo['store_count'];
        }
        // 轮播图
        $goodsImages = M('GoodsImages')->where(['goods_id' => $goodsId])->getField('image_url', true);
        if (!empty($goodsImages)) {
            foreach ($goodsImages as &$image) {
                $image = getFullPath($image);
            }
        }
        // 标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => $goodsId, 'title' => ['NEQ', ''], 'status' => 1])->getField('title', true);
        $data = [
            'goods_id' => $goodsInfo['goods_id'],
            'item_id' => $goodsInfo['item_id'],
            'goods_name' => $goodsInfo['goods_name'],
            'goods_remark' => $goodsInfo['goods_remark'],
            'original_img_new' => getFullPath($goodsInfo['original_img']),
            'content_url' => SITE_URL . '/index.php?m=Home&c=api.Goods&a=goodsContent&goods_id=' . $goodsInfo['goods_id'], // 内容url请求链接
            'credit' => $goodsInfo['credit'],
            'store_count' => $goodsInfo['store_count'],
            'images' => $goodsImages,
            'tabs' => $goodsTab
        ];
        return $data;
    }

    /**
     * 创建兑换订单
     * @param $user
     * @param $payPwd
     * @param $userAddress
     * @param $goodsInfo
     * @param $isCreate
     * @return array
     */
    public function createExchangeOrder($user, $payPwd, $userAddress, $goodsInfo, $isCreate = true)
    {
        $goods = M('goods')->where(['goods_id' => $goodsInfo['goods_id']])->find();
        if ($goods['is_on_sale'] == 0) {
            return ['status' => 0, 'msg' => '商品已下架', 'result' => ''];
        }
        $buyGoods = [
            'user_id' => $user['user_id'],
            'session_id' => $user['token'],
            'type' => 2,
            'cart_type' => 1,
            'goods_id' => $goods['goods_id'],
            'goods_sn' => $goods['goods_sn'],
            'goods_name' => $goods['goods_name'],
            'market_price' => $goods['market_price'],
            'goods_price' => $goods['shop_price'],
            'member_goods_price' => $goods['shop_price'],
            'goods_num' => $goodsInfo['goods_num'],
            'add_time' => NOW_TIME,
            'prom_type' => 0,
            'prom_id' => 0,
            'weight' => $goods['weight'],
            'goods' => $goods,
            'item_id' => $goodsInfo['item_id'],
            'zone' => $goods['zone'],
            'cut_fee' => 0,
            'goods_fee' => 0,
            'total_fee' => 0,
            'school_credit' => $goodsInfo['credit']
        ];
        $store_count = $goods['store_count'];
        if ($goodsInfo['item_id']) {
            $specGoods = M('spec_goods_price')->where(['item_id' => $goodsInfo['item_id'], 'key' => ['NEQ', '']])->find();
            $buyGoods['goods']['spec_key'] = $specGoods['key'];
            $buyGoods['goods']['spec_key_name'] = $specGoods['key_name'];
            $buyGoods['goods']['shop_price'] = $specGoods['price'];
            $buyGoods['spec_key'] = $specGoods['key'];
            $buyGoods['spec_key_name'] = $specGoods['key_name'];
            $buyGoods['sku'] = $specGoods['sku'];
            $store_count = $specGoods['store_count'];
        }
        try {
            if ($goodsInfo['goods_num'] > $store_count) {
                throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => $goods['goods_name'] . '，商品库存不足，剩余' . $store_count]);
            }
            $schoolCredit = bcmul($goodsInfo['credit'], $goodsInfo['goods_num'], 2);
            $payLogic = new Pay();
            $payLogic->setUserId($user['user_id']);
            $payLogic->payCart([$buyGoods]);
            $payLogic->setSchoolCredit($schoolCredit);
            // 配送物流
            $res = $payLogic->delivery($userAddress['district']);
            if ($isCreate) {
                if (isset($res['status']) && $res['status'] == -1) {
                    throw new TpshopException('商学院兑换商品下单', 0, ['status' => 0, 'msg' => '订单中部分商品不支持对当前地址的配送']);
                }
                // 订单创建
                $placeOrder = new PlaceOrder($payLogic);
                $placeOrder->setUser($user);
                $placeOrder->setPayPsw($payPwd);
                $placeOrder->setUserAddress($userAddress);
                $placeOrder->setOrderType(5);
                Db::startTrans();
                $placeOrder->addNormalOrder(3);
                // 扣除用户商学院学分
                accountLog($user['user_id'], 0, 0, '兑换商品消费学分', 0, 0, '', 0, 24, true, 0, -$schoolCredit);
                Db::commit();
                return ['status' => 1, 'msg' => '订单创建成功'];
            } else {
                if (isset($res['status']) && $res['status'] == -1) {
                    $userAddress['out_range'] = 1;
                    $userAddress['limit_tips'] = '当前地址不在配送范围内，请重新选择';
                }
                return $userAddress;
            }
        } catch (TpshopException $tpe) {
            Db::rollback();
            return $tpe->getErrorArr();
        }
    }

    /**
     * 获取兑换订单记录
     * @param $limit
     * @param $user
     * @return array
     */
    public function getExchangeLog($limit, $user)
    {
        $where = [
            'o.order_type' => 5,
            'o.user_id' => $user['user_id']
        ];
        // 数据数量
        $count = M('order o')->where($where)->count();
        // 查询数据
        $page = new Page($count, $limit);
        $orderIds = M('order o')->where($where)->limit($page->firstRow . ',' . $page->listRows)->getField('o.order_id', true);
        $orderList = M('order o')->where($where)->limit($page->firstRow . ',' . $page->listRows)
            ->field('o.order_id, o.pay_time, o.school_credit')->order('o.pay_time DESC')->select();
        // 订单商品
        $orderGoods = M('order_goods og')->join('goods g', 'g.goods_id = og.goods_id')->join('spec_goods_price sgp', 'sgp.goods_id = og.goods_id AND sgp.key = og.spec_key', 'LEFT')
            ->where(['og.order_id' => ['IN', $orderIds]])
            ->field('og.order_id, og.goods_id, og.goods_name, og.spec_key_name, og.goods_num, g.original_img, sgp.item_id')->select();
        $list = [];
        foreach ($orderList as $order) {
            $buyGoods = [];
            foreach ($orderGoods as $goods) {
                if ($order['order_id'] == $goods['order_id']) {
                    $buyGoods = $goods;
                    break;
                }
            }
            $list[] = [
                'order_id' => $order['order_id'],
                'goods_id' => $buyGoods ? $buyGoods['goods_id'] : '0',
                'item_id' => $buyGoods ? $buyGoods['item_id'] ?? '0' : '0',
                'goods_name' => $buyGoods ? $buyGoods['goods_name'] . ' ' . $buyGoods['spec_key_name'] : '',
                'goods_num' => $buyGoods ? $buyGoods['goods_num'] : '0',
                'original_img_new' => $buyGoods ? getFullPath($buyGoods['original_img']) : '0',
                'credit' => $order['school_credit'],
                'add_time' => date('Y-m-d H:i:s', $order['pay_time']),
            ];
        }
        return ['total' => $count, 'list' => $list];
    }
}
