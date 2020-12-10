<?php

namespace app\home\controller\api;

use app\common\logic\School as SchoolLogic;

class School extends Base
{
    protected $logic;

    public function __construct()
    {
        parent::__construct();
        $this->logic = new SchoolLogic();
    }

    /**
     * 获取轮播图
     * @return \think\response\Json
     */
    public function rotate()
    {
        $moduleId = I('module_id', 0);
        $data = $this->logic->getRotate($moduleId);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 获取模块列表
     * @return \think\response\Json
     */
    public function module()
    {
        $data = $this->logic->getModule();
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 获取模块分类列表
     * @return \think\response\Json
     */
    public function moduleClass()
    {
        $moduleId = I('module_id', 0);
        $data = $this->logic->getModuleClass($moduleId);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 获取文章列表
     * @return \think\response\Json
     */
    public function articleList()
    {
        $limit = I('limit', 10);
        $param = [
            'module_type' => I('code', ''),
            'class_id' => I('class_id', ''),
            'status' => I('status', ''),
            'is_recommend' => I('is_recommend', ''),
            'is_integral' => I('is_integral', ''),
            'distribute_level' => I('level', '')
        ];
        if (!empty($param['module_type']) && empty($param['class_id']) && empty($param['is_recommend'])) {
            // 查找模块下第一个分类
            $param['class_id'] = M('school_class sc')->join('school s', 's.id = sc.module_id')
                ->where(['s.type' => $param['module_type'], 'sc.is_open' => 1])->order('sc.sort DESC')->value('sc.id');
            if (!$param['class_id']) return json(['status' => 0, 'msg' => '模块下没有分类']);
        }
        $data = $this->logic->getArticleList($limit, $param, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 获取文章详情
     * @return \think\response\Json
     */
    public function articleInfo()
    {
        $param = [
            'article_id' => I('article_id', ''),
        ];
        $data = $this->logic->getArticleInfo($param, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 获取文章内容
     * @return \think\response\Json
     */
    public function articleContent()
    {
        $articleId = I('article_id', 0);
        $data = $this->logic->getArticleContent($articleId);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 获取用户文章列表
     * @return \think\response\Json
     */
    public function userArticle()
    {
        $limit = I('limit', 10);
        $param = [
            'status' => I('status', ''),
        ];
        $data = $this->logic->getUserArticle($limit, $param, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 学习课程文章（完成）
     * @return \think\response\Json
     */
    public function learnArticle()
    {
        $articleId = I('article_id', 0);
        $data = $this->logic->learnArticle($articleId, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 下载文章素材
     * @return \think\response\Json
     */
    public function getResource()
    {
        $param = [
            'article_id' => I('article_id', ''),
        ];
        $data = $this->logic->downloadArticleResource($param, $this->user);
        return json($data);
    }

    /**
     * 获取兑换商品列表
     * @return \think\response\Json
     */
    public function exchange()
    {
        $limit = I('limit', 10);
        $data = $this->logic->getExchangeList($limit);
        $data['user'] = [
            'user_id' => $this->user['user_id'],
            'nickname' => $this->user['nickname'] ?? ($this->user['user_name'] ?? '用户' . $this->user_id),
            'head_pic' => $this->user['head_pic'],
            'level' => $this->user['distribut_level'],
            'level_name' => M('DistributLevel')->where('level_id', $this->user['distribut_level'])->getField('level_name') ?? '普通会员',
            'credit' => $this->user['school_credit']
        ];
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 获取兑换商品详情
     * @return \think\response\Json
     */
    public function exchangeInfo()
    {
        $goodsId = I('goods_id', 0);
        $data = $this->logic->getExchangeInfo($goodsId);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }
}
