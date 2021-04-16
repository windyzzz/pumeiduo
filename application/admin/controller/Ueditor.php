<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

use app\common\logic\EditorLogic;
use app\common\logic\OssLogic;
use common\util\File;
use think\Image;
use think\Request;

/**
 * Class UeditorController.
 */
class Ueditor extends Base
{
    private $sub_name = ['date', 'Y/m-d'];
    private $savePath = 'temp/';
    private $ossClient;

    public function __construct()
    {
        parent::__construct();

        //header('Access-Control-Allow-Origin: http://www.baidu.com'); //设置http://www.baidu.com允许跨域访问
        //header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With'); //设置允许的跨域header

        date_default_timezone_set('Asia/Shanghai');

        $savePath = I('savepath') ?: I('savePath');
        $this->savePath = $savePath ? $savePath . '/' : 'temp/';

        error_reporting(E_ERROR | E_WARNING);

        header('Content-Type: text/html; charset=utf-8');

        $this->ossClient = new OssLogic();
    }

    public function index()
    {
        $CONFIG2 = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", '', file_get_contents('./public/plugins/Ueditor/php/config.json')), true);
        $action = $_GET['action'];
        switch ($action) {
            case 'config':
                $result = json_encode($CONFIG2);
                break;
            /* 上传图片 */
            case 'uploadimage':
                //$fieldName = $CONFIG2['imageFieldName'];
                $result = $this->imageUp();
                break;
            /* 上传涂鸦 */
            case 'uploadscrawl':
                $config = [
                    'pathFormat' => $CONFIG2['scrawlPathFormat'],
                    'maxSize' => $CONFIG2['scrawlMaxSize'],
                    'allowFiles' => $CONFIG2['scrawlAllowFiles'],
                    'oriName' => 'scrawl.png',
                ];
                $fieldName = $CONFIG2['scrawlFieldName'];
                $base64 = 'base64';
                $result = $this->upBase64($config, $fieldName);
                break;
            /* 上传视频 */
            case 'uploadvideo':
//                $fieldName = $CONFIG2['videoFieldName'];
                $result = $this->videoUp();
                break;
            /* 上传文件 */
            case 'uploadfile':
                $fieldName = $CONFIG2['fileFieldName'];
                $result = $this->upFile($fieldName);
                break;
            /* 列出图片 */
            case 'listimage':
                $allowFiles = $CONFIG2['imageManagerAllowFiles'];
                $listSize = $CONFIG2['imageManagerListSize'];
                $path = $CONFIG2['imageManagerListPath'];
                $get = $_GET;
                $result = $this->fileList($allowFiles, $listSize, $get);
                break;
            /* 列出文件 */
            case 'listfile':
                $allowFiles = $CONFIG2['fileManagerAllowFiles'];
                $listSize = $CONFIG2['fileManagerListSize'];
                $path = $CONFIG2['fileManagerListPath'];
                $get = $_GET;
                $result = $this->fileList($allowFiles, $listSize, $get);
                break;
            /* 抓取远程文件 */
            case 'catchimage':
                $config = [
                    'pathFormat' => $CONFIG2['catcherPathFormat'],
                    'maxSize' => $CONFIG2['catcherMaxSize'],
                    'allowFiles' => $CONFIG2['catcherAllowFiles'],
                    'oriName' => 'remote.png',
                ];
                $fieldName = $CONFIG2['catcherFieldName'];
                /* 抓取远程图片 */
                $list = [];
                isset($_POST[$fieldName]) ? $source = $_POST[$fieldName] : $source = $_GET[$fieldName];

                foreach ($source as $imgUrl) {
                    $info = json_decode($this->saveRemote($config, $imgUrl), true);
                    array_push($list, [
                        'state' => $info['state'],
                        'url' => $info['url'],
                        'size' => $info['size'],
                        'title' => htmlspecialchars($info['title']),
                        'original' => htmlspecialchars($info['original']),
                        'source' => htmlspecialchars($imgUrl),
                    ]);
                }

                $result = json_encode([
                    'state' => count($list) ? 'SUCCESS' : 'ERROR',
                    'list' => $list,
                ]);
                break;
            default:
                $result = json_encode([
                    'state' => '请求地址出错',
                ]);
                break;
        }

        /* 输出结果 */
        if (isset($_GET['callback'])) {
            if (preg_match("/^[\w_]+$/", $_GET['callback'])) {
                echo htmlspecialchars($_GET['callback']) . '(' . $result . ')';
            } else {
                echo json_encode([
                    'state' => 'callback参数不合法',
                ]);
            }
        } else {
            echo $result;
        }
    }

    //上传文件
    private function upFile($fieldName)
    {
        $file = request()->file('file');
        if (empty($file)) {
            $file = request()->file('upfile');
        }
        if (empty($file)) {
            $state = 'ERROR';

            return json_encode(['state' => $state]);
        } elseif ('php' == strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION))) {
            return json_encode(['state' => 'ERROR' . '后缀不允许']);
        }

        // 移动到框架应用根目录/public/uploads/ 目录下
        $this->savePath = $this->savePath . date('Y') . '/' . date('m-d') . '/';
        // 使用自定义的文件保存规则
        $info = $file->move(UPLOAD_PATH . $this->savePath, false);  // 保留文件原名

        if ($info) {
            $url = '/' . UPLOAD_PATH . $this->savePath . $info->getSaveName();
            $data = [
                'state' => 'SUCCESS',
                'url' => $url,
                'title' => $info->getFilename(),
                'original' => $info->getFilename(),
                'type' => '.' . $info->getExtension(),
                'size' => $info->getSize(),
            ];
            if (I('is_oss', 'yes') == 'yes') {
                // 上传到OSS服务器
                $res = (new Oss())->uploadFile('file', $url);
                if ($res['status'] == 0) {
                    $data['state'] = 'ERROR：' . $res['msg'];
                } else {
                    unset($info);
                    unlink(PUBLIC_PATH . substr($url, strrpos($url, 'public') + 7));
                    $data['url'] = $res['url'];
                }
            }
            //图片加水印
            //图片应该不会走这个函数(走imageUp)，为了避免还有调用，先屏蔽。 by lhb
//			if($this->savePath=='goods/'){
//        		$imgresource = ".".$data['url'];
//        		$image = \think\Image::open($imgresource);
//        		$water = tpCache('water');
//        		//$image->open($imgresource);
//        		$return_data['mark_type'] = $water['mark_type'];
//        		if($water['is_mark']==1 && $image->width()>$water['mark_width'] && $image->height()>$water['mark_height']){
//        			if($water['mark_type'] == 'text'){
//        				//$image->text($water['mark_txt'],'./hgzb.ttf',20,'#000000',9)->save($imgresource);
//        				$ttf = './hgzb.ttf';
//        				if (file_exists($ttf)) {
//        					$size = $water['mark_txt_size'] ? $water['mark_txt_size'] : 30;
//        					$color = $water['mark_txt_color'] ?: '#000000';
//        					if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
//        						$color = '#000000';
//        					}
//        					$transparency = intval((100 - $water['mark_degree']) * (127/100));
//        					$color .= dechex($transparency);
//        					$image->open($imgresource)->text($water['mark_txt'], $ttf, $size, $color, $water['sel'])->save($imgresource);
//        					$return_data['mark_txt'] = $water['mark_txt'];
//        				}
//        			}else{
//        				//$image->water(".".$water['mark_img'],9,$water['mark_degree'])->save($imgresource);
//        				$waterPath = "." . $water['mark_img'];
//        				$quality = $water['mark_quality'] ? $water['mark_quality'] : 80;
//        				$waterTempPath = dirname($waterPath).'/temp_'.basename($waterPath);
//        				$image->open($waterPath)->save($waterTempPath, null, $quality);
//        				$image->open($imgresource)->water($waterTempPath, $water['sel'], $water['mark_degree'])->save($imgresource);
//        				@unlink($waterTempPath);
//        			}
//        		}
//        	}
        } else {
            $data = ['state' => 'ERROR' . $file->getError()];
        }

        return json_encode($data);
    }

    //列出图片
    private function fileList($allowFiles, $listSize, $get)
    {
        $savePath = '';
        if ($this->savePath && 'temp/' != $this->savePath) {
            $savePath = $this->savePath;
        }
        $dirname = './' . UPLOAD_PATH . $savePath;
        $allowFiles = substr(str_replace('.', '|', join('', $allowFiles)), 1);
        /* 获取参数 */
        $size = isset($get['size']) ? htmlspecialchars($get['size']) : $listSize;
        $start = isset($get['start']) ? htmlspecialchars($get['start']) : 0;
        $end = $start + $size;
        /* 获取文件列表 */
        $path = $dirname;
        $files = $this->getFiles($path, $allowFiles);
        if (!count($files)) {
            return json_encode([
                'state' => 'no match file',
                'list' => [],
                'start' => $start,
                'total' => count($files),
            ]);
        }
        /* 获取指定范围的列表 */
        $len = count($files);
        for ($i = min($end, $len) - 1, $list = []; $i < $len && $i >= 0 && $i >= $start; --$i) {
            $list[] = $files[$i];
        }

        /* 返回数据 */
        $result = json_encode([
            'state' => 'SUCCESS',
            'list' => $list,
            'start' => $start,
            'total' => count($files),
        ]);

        return $result;
    }

    /*
     * 遍历获取目录下的指定类型的文件
     * @param $path
     * @param array $files
     * @return array
    */
    private function getFiles($path, $allowFiles, &$files = [])
    {
        if (!is_dir($path)) {
            return null;
        }
        if ('/' != substr($path, strlen($path) - 1)) {
            $path .= '/';
        }
        $handle = opendir($path);

        while (false !== ($file = readdir($handle))) {
            if ('.' != $file && '..' != $file) {
                $path2 = $path . $file;
                if (is_dir($path2)) {
                    $this->getFiles($path2, $allowFiles, $files);
                } else {
                    if (preg_match("/\.(" . $allowFiles . ')$/i', $file)) {
                        $files[] = [
                            'url' => substr($path2, 1),
                            'mtime' => filemtime($path2),
                        ];
                    }
                }
            }
        }

        return $files;
    }

    //抓取远程图片
    private function saveRemote($config, $fieldName)
    {
        $imgUrl = htmlspecialchars($fieldName);
        $imgUrl = str_replace('&amp;', '&', $imgUrl);

        //http开头验证
        if (0 !== strpos($imgUrl, 'http')) {
            $data = [
                'state' => '链接不是http链接',
            ];

            return json_encode($data);
        }
        //获取请求头并检测死链
        $heads = get_headers($imgUrl);
        if (!(stristr($heads[0], '200') && stristr($heads[0], 'OK'))) {
            $data = [
                'state' => '链接不可用',
            ];

            return json_encode($data);
        }
        //格式验证(扩展名验证和Content-Type验证)
        $fileType = strtolower(strrchr($imgUrl, '.'));
        if (!in_array($fileType, $config['allowFiles']) || stristr($heads['Content-Type'], 'image')) {
            $data = [
                'state' => '链接contentType不正确',
            ];

            return json_encode($data);
        }

        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            ['http' => [
                'follow_location' => false, // don't follow redirects
            ]]
        );
        readfile($imgUrl, false, $context);
        $img = ob_get_contents();
        ob_end_clean();
        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

        $dirname = UPLOAD_PATH . 'remote/';
        $file['oriName'] = $m ? $m[1] : '';
        $file['filesize'] = strlen($img);
        $file['ext'] = strtolower(strrchr($config['oriName'], '.'));
        $file['name'] = uniqid() . $file['ext'];
        $file['fullName'] = $dirname . $file['name'];
        $fullName = $file['fullName'];

        //检查文件大小是否超出限制
        if ($file['filesize'] >= ($config['maxSize'])) {
            $data = [
                'state' => '文件大小超出网站限制',
            ];

            return json_encode($data);
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $data = [
                'state' => '目录创建失败',
            ];

            return json_encode($data);
        } elseif (!is_writeable($dirname)) {
            $data = [
                'state' => '目录没有写权限',
            ];

            return json_encode($data);
        }

        //移动文件
        if (!(file_put_contents($fullName, $img) && file_exists($fullName))) { //移动失败
            $data = [
                'state' => '写入文件内容错误',
            ];

            return json_encode($data);
        } //移动成功
        $data = [
            'state' => 'SUCCESS',
            'url' => substr($file['fullName'], 1),
            'title' => $file['name'],
            'original' => $file['oriName'],
            'type' => $file['ext'],
            'size' => $file['filesize'],
        ];

        return json_encode($data);
    }

    /*
     * 处理base64编码的图片上传
     * 例如：涂鸦图片上传
    */
    private function upBase64($config, $fieldName)
    {
        $base64Data = $_POST[$fieldName];
        $img = base64_decode($base64Data);

        $dirname = UPLOAD_PATH . 'scrawl/';
        $file['filesize'] = strlen($img);
        $file['oriName'] = $config['oriName'];
        $file['ext'] = strtolower(strrchr($config['oriName'], '.'));
        $file['name'] = uniqid() . $file['ext'];
        $file['fullName'] = $dirname . $file['name'];
        $fullName = $file['fullName'];

        //检查文件大小是否超出限制
        if ($file['filesize'] >= ($config['maxSize'])) {
            $data = [
                'state' => '文件大小超出网站限制',
            ];

            return json_encode($data);
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $data = [
                'state' => '目录创建失败',
            ];

            return json_encode($data);
        } elseif (!is_writeable($dirname)) {
            $data = [
                'state' => '目录没有写权限',
            ];

            return json_encode($data);
        }

        //移动文件
        if (!(file_put_contents($fullName, $img) && file_exists($fullName))) { //移动失败
            $data = [
                'state' => '写入文件内容错误',
            ];
        } else { //移动成功
            $data = [
                'state' => 'SUCCESS',
                'url' => substr($file['fullName'], 1),
                'title' => $file['name'],
                'original' => $file['oriName'],
                'type' => $file['ext'],
                'size' => $file['filesize'],
            ];
        }

        return json_encode($data);
    }

    /**
     * @function imageUp
     */
    public function imageUp()
    {
        // 上传图片框中的描述表单名称，
        $pictitle = I('pictitle');
        $dir = I('dir');
        $title = htmlspecialchars($pictitle, ENT_QUOTES);
        $path = htmlspecialchars($dir, ENT_QUOTES);
        //$input_file ['upfile'] = $info['Filedata'];  一个是上传插件里面来的, 另外一个是 文章编辑器里面来的
        // 获取表单上传文件
        $file = request()->file('file');
        $return_url = '';
        $editor = new EditorLogic();

        if (empty($file)) {
            $file = request()->file('upfile');
        }
        $result = $this->validate(
            ['file' => $file],
            ['file' => 'image|fileSize:1073741824|fileExt:jpg,jpeg,gif,png'],
            ['file.image' => '上传文件必须为图片', 'file.fileSize' => '上传文件过大', 'file.fileExt' => '上传文件后缀名必须为jpg,jpeg,gif,png']
        );
        if (true !== $result || !$file) {
            $state = 'ERROR' . $result;
        } else {
            $return = $editor->saveUploadImage($file, $this->savePath);
            $state = $return['state'];
            $return_data['url'] = $return['url'];
            if (I('is_oss', 'yes') == 'yes') {
                // 上传到OSS服务器
                $res = (new Oss())->uploadFile('image', $return_data['url']);
                if ($res['status'] == 0) {
                    $state = 'ERROR：' . $res['msg'];
                } else {
                    unlink(PUBLIC_PATH . substr($return_data['url'], strrpos($return_data['url'], 'public') + 7));
                    $return_data['url'] = $res['url'];
                }
            }
        }

        $return_data['title'] = $title;
        $return_data['original'] = ''; // 这里好像没啥用 暂时注释起来
        $return_data['state'] = $state;
        $return_data['path'] = $path;

        $this->ajaxReturn($return_data);
    }

    /**
     * app文件上传.
     */
    public function appFileUp()
    {
        $path = UPLOAD_PATH . 'appfile/';
        if (!file_exists($path)) {
            mkdir($path);
        }

        //$input_file  ['upfile'] = $info['Filedata'];  一个是上传插件里面来的, 另外一个是 文章编辑器里面来的
        // 获取表单上传文件
        $file = request()->file('Filedata');
        if (empty($file)) {
            $file = request()->file('upfile');
        }

        $result = $this->validate(
            ['file2' => $file],
            ['file2' => 'fileSize:1073741824|fileExt:apk,ipa,pxl,deb'],
            ['file2.fileSize' => '上传文件过大', 'file2.fileExt' => '上传文件后缀不正确']
        );
        if (true !== $result || empty($file)) {
            $state = 'ERROR' . $result;
        } else {
            $info = $file->rule(function ($file) {
                return date('YmdHis_') . input('Filename'); // 使用自定义的文件保存规则
            })->move($path);
            if ($info) {
                $state = 'SUCCESS';
            } else {
                $state = 'ERROR' . $file->getError();
            }
            $return_data['url'] = $path . $info->getSaveName();
        }

        $return_data['title'] = 'app文件';
        $return_data['original'] = ''; // 这里好像没啥用 暂时注释起来
        $return_data['state'] = $state;
        $return_data['path'] = $path;

        $this->ajaxReturn($return_data);
    }

    /**
     * 微信公众号图片素材列表.
     *
     * @param $listSize int 拉取多少
     * @param $get array get数组
     *
     * @return string
     */
    public function wechatImageList($listSize, $get)
    {
        $size = isset($get['size']) ? intval($get['size']) : $listSize;
        $start = isset($get['start']) ? intval($get['start']) : 0;

        $logic = new \app\common\logic\WechatLogic();

        return $logic->getPluginImages($size, $start);
    }

    /**
     * 上传视频/音频
     */
    public function videoUp()
    {
        $pictitle = I('pictitle');
        $dir = I('dir');
        $title = htmlspecialchars($pictitle, ENT_QUOTES);
        $path = htmlspecialchars($dir, ENT_QUOTES);
        // 获取表单上传文件
        $file = request()->file('file');
        if (empty($file)) {
            $file = request()->file('upfile');
        }
        $result = $this->validate(
            ['file' => $file],
            ['file' => 'fileSize:1073741824|fileExt:mp4,3gp,flv,avi,wmv,mp3,wma,wav'],
            ['file.fileSize' => '上传文件过大', 'file.fileExt' => '上传文件后缀名必须为mp4,3gp,flv,avi,wmv,mp3,wma,wav']
        );
        if (true !== $result || !$file) {
            $state = 'ERROR' . $result;
        } else {
            // 移动到框架应用根目录/public/uploads/ 目录下
            $new_path = $this->savePath . date('Y') . '/' . date('m-d') . '/';
            // 使用自定义的文件保存规则
            $info = $file->rule(function ($file) {
                return md5(mt_rand());
            })->move(UPLOAD_PATH . $new_path);
            if ($info) {
                $state = 'SUCCESS';
            } else {
                $state = 'ERROR' . $file->getError();
            }
            $return_data['url'] = '/' . UPLOAD_PATH . $new_path . $info->getSaveName();
            // 暂不上传到OSS服务器，定时任务处理
//            if (I('is_oss', 'yes') == 'yes') {
//                // 上传到OSS服务器
//                if (in_array($ext, ['mp3', 'wma', 'wav'])) {
//                    $res = (new Oss())->uploadFile('audio', $return_data['url']);
//                } else {
//                    $res = (new Oss())->uploadFile('video', $return_data['url']);
//                }
//                if ($res['status'] == 0) {
//                    $state = 'ERROR：' . $res['msg'];
//                } else {
//                    unset($info);
//                    unlink(PUBLIC_PATH . substr($return_data['url'], strrpos($return_data['url'], 'public') + 7));
//                    $return_data['url'] = $res['url'];
//                }
//            }
        }

        $return_data['title'] = $title;
        $return_data['original'] = ''; // 这里好像没啥用 暂时注释起来
        $return_data['state'] = $state;
        $return_data['path'] = $path;
        $this->ajaxReturn($return_data);
    }
}
