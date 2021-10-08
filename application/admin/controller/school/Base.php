<?php

namespace app\admin\controller\school;

use app\admin\controller\Base as BaseController;
use app\common\logic\OssLogic;

class Base extends BaseController
{
    protected $ossClient = null;
    protected $appGrade = [];
    protected $svipGrade = [];
    protected $svipLevel = [];

    public function __construct()
    {
        parent::__construct();
        $this->ossClient = new OssLogic();
        // APP等级列表
        $this->appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $this->svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $this->svipLevel = M('svip_level')->getField('app_level, name', true);
    }
}
