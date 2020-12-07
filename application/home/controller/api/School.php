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
            'class_id' => I('class_id', ''),
            'status' => I('status', ''),
            'is_recommend' => I('is_recommend', ''),
            'is_integral' => I('is_integral', ''),
            'distribute_level' => I('level', '')
        ];
        $data = $this->logic->getArticleList($limit, $param, $this->user);
        if (isset($data['status']) && $data['status'] == 0) {
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
        if (isset($data['status']) && $data['status'] == 0) {
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
}
