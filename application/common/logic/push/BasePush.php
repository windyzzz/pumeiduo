<?php

namespace app\common\logic\push;

class BasePush
{
    function __construct()
    {
        date_default_timezone_set('Asia/Shanghai');
    }

    /**
     * 发送请求
     * @param string $url 目标链接
     * @param array $postData 发送数据
     * @param array $header header头信息
     * @param bool $formUrlencoded 是否url编码
     * @param bool $returnJson 是否返回Jsondecode之后的数据
     * @return bool|mixed|string
     */
    protected function request($url, $postData = [], $header = [], $formUrlencoded = false, $returnJson = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($formUrlencoded) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }
        $response = curl_exec($ch);
        if ($errno = curl_errno($ch)) {
            $error = curl_error($ch);
            $this->errmsg = $error;
            $this->errno = $errno;
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        if ($returnJson) {
            return json_decode($response, TRUE);
        }
        return $response;
    }

    /**
     * 获取缓存文件
     * @return mixed
     */
    protected function get_php_file($fileName)
    {
        if (!file_exists($fileName)) {
            $expire_time = time() - 100;
            return json_decode(json_encode(array(
                "access_token" => '',
                "expire_time" => $expire_time
            )), TRUE);
        }
        return json_decode(trim(substr(file_get_contents($fileName, true), 15)), TRUE);
    }

    /**
     * 写入缓存文件
     * @param $content
     */
    protected function set_php_file($fileName, $content)
    {
        $fp = fopen($fileName, "w");
        fwrite($fp, "<?php exit();?>" . $content);
        fclose($fp);
    }
}
