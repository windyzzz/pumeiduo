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
     * 获取首页icon
     * @return \think\response\Json
     */
    public function indexIcon()
    {
        $data = $this->logic->getIcon();
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
            'article_id' => I('article_id', ''),
            'class_id' => I('class_id', ''),
            'status' => I('status', ''),
            'is_recommend' => I('is_recommend', ''),
            'is_integral' => I('is_integral', ''),
            'distribute_level' => I('level', '')
        ];
        $data = $this->logic->getArticleList($limit, $param);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }
}
