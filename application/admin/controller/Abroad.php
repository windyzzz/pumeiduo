<?php

namespace app\admin\controller;

class Abroad extends Base
{
    /**
     * 设置
     * @return mixed
     */
    public function config()
    {
        if (IS_POST) {
            $param = I('post.');
            foreach ($param as $k => $v) {
                $data = [
                    'type' => $k,
                    'title' => isset($v['title']) ? $v['title'] : '',
                    'content' => isset($v['content']) ? $v['content'] : '',
                ];
                $config = M('abroad_config')->where(['type' => $k])->find();
                if (!empty($config)) {
                    M('abroad_config')->where(['id' => $config['id']])->update($data);
                } else {
                    M('abroad_config')->add($data);
                }
            }
        }
        $abroadConfig = M('abroad_config')->select();
        $config = [];
        foreach ($abroadConfig as $val) {
            $config[$val['type']] = [
                'title' => $val['title'],
                'content' => $val['content']
            ];
        }
        $this->assign('config', $config);
        return $this->fetch('config');
    }
}