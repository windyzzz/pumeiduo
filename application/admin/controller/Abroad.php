<?php

namespace app\admin\controller;

class Abroad extends Base
{
    /**
     * 物流设置
     * @return mixed
     */
    public function freight()
    {
        if (IS_POST) {
            $param = I('post.');
            tpCache('abroad', $param);
        }
        $config = tpCache('abroad');
        $this->assign('config', $config);
        return $this->fetch('freight_config');
    }
}