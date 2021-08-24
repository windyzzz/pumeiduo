<?php

namespace app\admin\controller;

use app\common\logic\OssLogic;

class Applet extends Base
{

    private $ossClient = null;

    public function __construct()
    {
        parent::__construct();
        $this->ossClient = new OssLogic();
    }

    /**
     * 小程序配置
     * @return mixed
     */
    public function config()
    {
        if (IS_POST) {
            $param = I('post.');
            // 配置
            foreach ($param as $k => $v) {
                switch ($k) {
                    case 'publicize':
                        if (strstr($v['url'], 'aliyuncs.com')) {
                            // 原图
                            $v['url'] = M('applet_config')->where(['type' => 'publicize'])->value('url');
                        } else {
                            // 新图
                            $filePath = PUBLIC_PATH . substr($v['url'], strrpos($v['url'], '/public/') + 8);
                            $fileName = substr($v['url'], strrpos($v['url'], '/') + 1);
                            $object = 'image/' . date('Y/m/d/H/') . $fileName;
                            $return_url = $this->ossClient->uploadFile($filePath, $object);
                            if (!$return_url) {
                                $this->error('图片上传错误');
                            } else {
                                // 图片信息
                                $imageInfo = getimagesize($filePath);
                                $v['url'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                                unlink($filePath);
                            }
                        }
                        $data = [
                            'type' => $k,
                            'name' => isset($v['name']) ? $v['name'] : '',
                            'url' => isset($v['url']) ? $v['url'] : '',
                            'content' => isset($v['content']) ? $v['content'] : '',
                        ];
                        $config = M('applet_config')->where(['type' => $k])->find();
                        if (!empty($config)) {
                            M('applet_config')->where(['id' => $config['id']])->update($data);
                        } else {
                            M('applet_config')->add($data);
                        }
                        break;
                }
            }
            $this->success('操作成功', U('Applet/config'));
        }
        // 配置
        $brandConfig = M('applet_config')->select();
        $config = [];
        foreach ($brandConfig as $val) {
            if ($val['type'] == 'publicize' && !empty($val['url'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $this->ossClient::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            }
            $config[$val['type']] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }

        $this->assign('config', $config);
        return $this->fetch();
    }
}
