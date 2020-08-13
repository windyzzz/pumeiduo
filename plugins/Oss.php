<?php

namespace plugins;

use OSS\OssClient;

class Oss
{
    /** @var \OSS\OssClient */
    private $client;

    public function __construct()
    {
        $accessKeyId = C('OSS_ACCESSKEY_ID');
        $accessKeySecret = C('OSS_ACCESSKEY_SECRET');
        $endpoint = C('OSS_ENDPOINT');
        try {
            $this->client = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        } catch (\OSS\Core\OssException $e) {
            throw new \app\common\util\TpshopException('oss连接错误', 701, ['msg' => $e->getMessage()]);
        }
    }

    public static function url($path)
    {
        return 'http://' . C('OSS_BUCKET') .'.'. C('OSS_ENDPOINT') . '/' . $path;
    }
}