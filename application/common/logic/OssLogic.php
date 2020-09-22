<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

use OSS\Core\OssException;
use OSS\OssClient;

require_once './vendor/aliyun-openapi-php-sdk/aliyun-oss-php-sdk/autoload.php';

/**
 * Class OssLogic
 * 对象存储逻辑类.
 */
class OssLogic
{
    private static $initConfigFlag = false;
    private static $accessKeyId = '';
    private static $accessKeySecret = '';
    private static $endpoint = '';
    private static $bucket = '';

    /** @var \OSS\OssClient */
    private static $ossClient = null;
    private static $errorMsg = '';

    private static $waterPos = [
        1 => 'nw',     //标识左上角水印
        2 => 'north',  //标识上居中水印
        3 => 'ne',     //标识右上角水印
        4 => 'west',   //标识左居中水印
        5 => 'center', //标识居中水印
        6 => 'east',   //标识右居中水印
        7 => 'sw',     //标识左下角水印
        8 => 'south',  //标识下居中水印
        9 => 'se',     //标识右下角水印
    ];

    public function __construct()
    {
        self::initConfig();
    }

    /**
     * 获取错误信息，一旦其他接口返回false时，可调用此接口查看具体错误信息.
     *
     * @return string
     */
    public function getError()
    {
        return self::$errorMsg;
    }

    private static function initConfig()
    {
        if (self::$initConfigFlag) {
            return;
        }
        self::$accessKeyId = C('OSS_ACCESSKEY_ID');
        self::$accessKeySecret = C('OSS_ACCESSKEY_SECRET');
        self::$endpoint = C('OSS_ENDPOINT');
        self::$bucket = C('OSS_BUCKET');
        self::$initConfigFlag = true;
    }

    public static function url($path)
    {
        return 'http://' . self::$bucket .'.'. self::$endpoint . '/' . $path;
    }

    private static function getOssClient()
    {
        if (!self::$ossClient) {
            self::initConfig();
            try {
                self::$ossClient = new OssClient(self::$accessKeyId, self::$accessKeySecret, self::$endpoint, false);
            } catch (OssException $e) {
                self::$errorMsg = '创建oss对象失败，' . $e->getMessage();

                return null;
            }
        }

        return self::$ossClient;
    }

    public function getSiteUrl()
    {
        return 'http://' . self::$bucket . '.' . self::$endpoint;
    }

    public function uploadFile($filePath, $object = null)
    {
        $ossClient = self::getOssClient();
        if (!$ossClient) {
            return false;
        }

        if (is_null($object)) {
            $object = $filePath;
        }

        try {
            $ossClient->uploadFile(self::$bucket, $object, $filePath);
        } catch (OssException $e) {
            self::$errorMsg = 'oss上传文件失败，' . $e->getMessage();

            return false;
        }

        return $this->getSiteUrl() . '/' . $object;
    }

    /**
     * 获取商品图片的url.
     *
     * @param type $originalImg
     * @param type $width
     * @param type $height
     * @param type $defaultImg
     *
     * @return type
     */
    public function getGoodsThumbImageUrl($originalImg, $width, $height, $defaultImg = '')
    {
        if (!$this->isOssUrl($originalImg)) {
            return $defaultImg;
        }

        // 图片缩放（等比缩放）
        $url = $originalImg . "?x-oss-process=image/resize,m_pad,h_$height,w_$width";

        $water = tpCache('water');
        if ($water['is_mark']) {
            if ($width > $water['mark_width'] && $height > $water['mark_height']) {
                if ('img' == $water['mark_type']) {
                    if ($this->isOssUrl($water['mark_img'])) {
                        $url = $this->withImageWaterUrl($url, $water['mark_img'], $water['mark_degree'], $water['sel']);
                    }
                } else {
                    $url = $this->withTextWaterUrl($url, $water['mark_txt'], $water['mark_txt_size'], $water['mark_txt_color'], $water['mark_degree'], $water['sel']);
                }
            }
        }

        return $url;
    }

    /**
     * 获取商品相册的url.
     *
     * @param type $originalImg
     * @param type $width
     * @param type $height
     * @param type $defaultImg
     *
     * @return type
     */
    public function getGoodsAlbumThumbUrl($originalImg, $width, $height, $defaultImg = '')
    {
        if (!($originalImg && 0 === strpos($originalImg, 'http') && strpos($originalImg, 'aliyuncs.com'))) {
            return $defaultImg;
        }

        // 图片缩放（等比缩放）
        $url = $originalImg . "?x-oss-process=image/resize,m_pad,h_$height,w_$width";

        return $url;
    }

    /**
     * 链接加上文本水印参数（文字水印(方针黑体，黑色)）.
     *
     * @param string $url
     * @param type $text
     * @param type $size
     * @param type $posSel
     *
     * @return string
     */
    private function withTextWaterUrl($url, $text, $size, $color, $transparency, $posSel)
    {
        $color = $color ?: '#000000';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#000000';
        }
        $color = ltrim($color, '#');
        $text_encode = urlsafe_b64encode($text);
        $url .= ",image/watermark,text_{$text_encode},type_ZmFuZ3poZW5naGVpdGk,color_{$color},size_{$size},t_{$transparency},g_" . self::$waterPos[$posSel];

        return $url;
    }

    /**
     * 链接加上图片水印参数.
     *
     * @param string $url
     * @param type $image
     * @param type $transparency
     * @param type $posSel
     *
     * @return string
     */
    private function withImageWaterUrl($url, $image, $transparency, $posSel)
    {
        $image = ltrim(parse_url($image, PHP_URL_PATH), '/');
        $image_encode = urlsafe_b64encode($image);
        $url .= ",image/watermark,image_{$image_encode},t_{$transparency},g_" . self::$waterPos[$posSel];

        return $url;
    }

    /**
     * 是否是oss的链接.
     *
     * @param type $url
     *
     * @return bool
     */
    public function isOssUrl($url)
    {
        if ($url && 0 === strpos($url, 'http') && strpos($url, 'aliyuncs.com')) {
            return true;
        }

        return false;
    }

    /**
     * 下载文件
     * @param $filePath
     * @param $path
     * @return bool|string
     */
    public function downloadFile($filePath, $path)
    {
        $ossClient = self::getOssClient();
        if (!$ossClient) {
            return false;
        }
        $object = $filePath;
        $localFile = $path . substr($filePath, strrpos($filePath, '/') + 1);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $options = [
            OssClient::OSS_FILE_DOWNLOAD => $localFile
        ];
        try {
            $ossClient->getObject(self::$bucket, $object, $options);
        } catch (OssException $e) {
            return false;
        }
        return $localFile;
    }
}
