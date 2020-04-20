<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * @param $arr
 * @param $key_name
 *
 * @return array
 *               将数据库中查出的列表以指定的 id 作为数组的键名
 */
function convert_arr_key($arr, $key_name)
{
    $arr2 = [];
    foreach ($arr as $key => $val) {
        $arr2[$val[$key_name]] = $val;
    }

    return $arr2;
}

function systemEncrypt($str)
{
    return md5(C('AUTH_CODE') . md5($str) . 'milan');
}

function shopEncrypt($time, $user_name)
{
    return md5($time . C('AUTH_CODE_SHOP') . md5($user_name));
}

/**
 * 时间条件.
 *
 * @return bool
 */
function whereTime($add_time_begin, $add_time_end, $add_time = 0)
{
    if ($add_time_begin && $add_time_end) {
        $time = ['between', [strtotime($add_time_begin), strtotime($add_time_end) + $add_time]];
    } elseif ($add_time_begin) {
        $time = ['egt', strtotime($add_time_begin)];
    } elseif ($add_time_end) {
        $time = ['elt', strtotime($add_time_end) + $add_time];
    } else {
        $time = '';
    }

    return $time;
}

/**
 * 获取数组中的某一列.
 *
 * @param array $arr 数组
 * @param string $key_name 列名
 *
 * @return array 返回那一列的数组
 */
function get_arr_column($arr, $key_name)
{
    $arr2 = [];
    foreach ($arr as $key => $val) {
        $arr2[] = $val[$key_name];
    }

    return $arr2;
}

/**
 * 获取url 中的各个参数  类似于 pay_code=alipay&bank_code=ICBC-DEBIT.
 *
 * @param type $str
 *
 * @return type
 */
function parse_url_param($str)
{
    $data = [];
    $str = explode('?', $str);
    $str = end($str);
    $parameter = explode('&', $str);
    foreach ($parameter as $val) {
        $tmp = explode('=', $val);
        $data[$tmp[0]] = $tmp[1];
    }

    return $data;
}

/**
 * 二维数组排序.
 *
 * @param $arr
 * @param $keys
 * @param string $type
 *
 * @return array
 */
function array_sort($arr, $keys, $type = 'desc')
{
    $key_value = $new_array = [];
    foreach ($arr as $k => $v) {
        $key_value[$k] = $v[$keys];
    }
    if ('asc' == $type) {
        asort($key_value);
    } else {
        arsort($key_value);
    }
    reset($key_value);
    foreach ($key_value as $k => $v) {
        $new_array[$k] = $arr[$k];
    }

    return $new_array;
}

/**
 * 多维数组转化为一维数组.
 *
 * @param 多维数组
 *
 * @return array 一维数组
 */
function array_multi2single($array)
{
    static $result_array = [];
    foreach ($array as $value) {
        if (is_array($value)) {
            array_multi2single($value);
        } else {
            $result_array[] = $value;
        }
    }

    return $result_array;
}

/**
 * 友好时间显示.
 *
 * @param $time
 *
 * @return bool|string
 */
function friend_date($time)
{
    if (!$time) {
        return false;
    }
    $fdate = '';
    $d = time() - intval($time);
    $ld = $time - mktime(0, 0, 0, 0, 0, date('Y')); //得出年
    $md = $time - mktime(0, 0, 0, date('m'), 0, date('Y')); //得出月
    $byd = $time - mktime(0, 0, 0, date('m'), date('d') - 2, date('Y')); //前天
    $yd = $time - mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')); //昨天
    $dd = $time - mktime(0, 0, 0, date('m'), date('d'), date('Y')); //今天
    $td = $time - mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')); //明天
    $atd = $time - mktime(0, 0, 0, date('m'), date('d') + 2, date('Y')); //后天
    if (0 == $d) {
        $fdate = '刚刚';
    } else {
        switch ($d) {
            case $d < $atd:
                $fdate = date('Y年m月d日', $time);
                break;
            case $d < $td:
                $fdate = '后天' . date('H:i', $time);
                break;
            case $d < 0:
                $fdate = '明天' . date('H:i', $time);
                break;
            case $d < 60:
                $fdate = $d . '秒前';
                break;
            case $d < 3600:
                $fdate = floor($d / 60) . '分钟前';
                break;
            case $d < $dd:
                $fdate = floor($d / 3600) . '小时前';
                break;
            case $d < $yd:
                $fdate = '昨天' . date('H:i', $time);
                break;
            case $d < $byd:
                $fdate = '前天' . date('H:i', $time);
                break;
            case $d < $md:
                $fdate = date('m月d日 H:i', $time);
                break;
            case $d < $ld:
                $fdate = date('m月d日', $time);
                break;
            default:
                $fdate = date('Y年m月d日', $time);
                break;
        }
    }

    return $fdate;
}

/**
 * 返回状态和信息.
 *
 * @param $status
 * @param $info
 *
 * @return array
 */
function arrayRes($status, $info, $url = '')
{
    return ['status' => $status, 'info' => $info, 'url' => $url];
}

/**
 * @param $arr
 * @param $key_name
 * @param $key_name2
 *
 * @return array
 *               将数据库中查出的列表以指定的 id 作为数组的键名 数组指定列为元素 的一个数组
 */
function get_id_val($arr, $key_name, $key_name2)
{
    $arr2 = [];
    foreach ($arr as $key => $val) {
        $arr2[$val[$key_name]] = $val[$key_name2];
    }

    return $arr2;
}

// 服务器端IP
function serverIP()
{
    return gethostbyname($_SERVER['SERVER_NAME']);
}

/**
 * 自定义函数递归的复制带有多级子目录的目录
 * 递归复制文件夹.
 *
 * @param type $src 原目录
 * @param type $dst 复制到的目录
 */
//参数说明：
//自定义函数递归的复制带有多级子目录的目录
function recurse_copy($src, $dst)
{
    $now = time();
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== $file = readdir($dir)) {
        if (('.' != $file) && ('..' != $file)) {
            if (is_dir($src . '/' . $file)) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                if (file_exists($dst . DIRECTORY_SEPARATOR . $file)) {
                    if (!is_writeable($dst . DIRECTORY_SEPARATOR . $file)) {
                        exit($dst . DIRECTORY_SEPARATOR . $file . '不可写');
                    }
                    @unlink($dst . DIRECTORY_SEPARATOR . $file);
                }
                if (file_exists($dst . DIRECTORY_SEPARATOR . $file)) {
                    @unlink($dst . DIRECTORY_SEPARATOR . $file);
                }
                $copyrt = copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                if (!$copyrt) {
                    echo 'copy ' . $dst . DIRECTORY_SEPARATOR . $file . ' failed<br>';
                }
            }
        }
    }
    closedir($dir);
}

// 递归删除文件夹
function delFile($path, $delDir = false)
{
    if (!is_dir($path)) {
        return false;
    }
    $handle = @opendir($path);
    if ($handle) {
        while (false !== ($item = readdir($handle))) {
            if ('.' != $item && '..' != $item) {
                is_dir("$path/$item") ? delFile("$path/$item", $delDir) : unlink("$path/$item");
            }
        }
        closedir($handle);
        if ($delDir) {
            return rmdir($path);
        }
    } else {
        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }
}

/**
 * 多个数组的笛卡尔积.
 *
 * @param unknown_type $data
 */
function combineDika()
{
    $data = func_get_args();
    $data = current($data);
    $cnt = count($data);
    $result = [];
    $arr1 = array_shift($data);
    foreach ($arr1 as $key => $item) {
        $result[] = [$item];
    }

    foreach ($data as $key => $item) {
        $result = combineArray($result, $item);
    }

    return $result;
}

/**
 * 两个数组的笛卡尔积.
 *
 * @param unknown_type $arr1
 * @param unknown_type $arr2
 */
function combineArray($arr1, $arr2)
{
    $result = [];
    foreach ($arr1 as $item1) {
        foreach ($arr2 as $item2) {
            $temp = $item1;
            $temp[] = $item2;
            $result[] = $temp;
        }
    }

    return $result;
}

/**
 * 将二维数组以元素的某个值作为键 并归类数组
 * array( array('name'=>'aa','type'=>'pay'), array('name'=>'cc','type'=>'pay') )
 * array('pay'=>array( array('name'=>'aa','type'=>'pay') , array('name'=>'cc','type'=>'pay') )).
 *
 * @param $arr 数组
 * @param $key 分组值的key
 *
 * @return array
 */
function group_same_key($arr, $key)
{
    $new_arr = [];
    foreach ($arr as $k => $v) {
        $new_arr[$v[$key]][] = $v;
    }

    return $new_arr;
}

/**
 * 获取随机字符串.
 *
 * @param int $randLength 长度
 * @param int $addtime 是否加入当前时间戳
 * @param int $includenumber 是否包含数字
 *
 * @return string
 */
function get_rand_str($randLength = 6, $addtime = 1, $includenumber = 0)
{
    if ($includenumber == 2) {
        $chars = '1234567890';
    } else if ($includenumber) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQEST123456789';
    } else {
        $chars = 'abcdefghijklmnopqrstuvwxyz';
    }
    $len = strlen($chars);
    $randStr = '';
    for ($i = 0; $i < $randLength; ++$i) {
        $randStr .= $chars[rand(0, $len - 1)];
    }
    $tokenvalue = $randStr;
    if ($addtime) {
        $tokenvalue = $randStr . time();
    }

    return $tokenvalue;
}

/**
 * CURL请求
 *
 * @param $url string 请求url地址
 * @param $method string 请求方法 get post
 * @param mixed $postfields post数据数组
 * @param array $headers 请求header信息
 * @param bool|false $debug 调试开启 默认false
 *
 * @return mixed
 */
function httpRequest($url, $method = 'GET', $postfields = null, $headers = [], $debug = false)
{
    $method = strtoupper($method);
    $ci = curl_init();
    /* Curl settings */
    curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($ci, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0');
    curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
    curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
    curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
    switch ($method) {
        case 'POST':
            curl_setopt($ci, CURLOPT_POST, true);
            if (!empty($postfields)) {
                $tmpdatastr = is_array($postfields) ? http_build_query($postfields) : $postfields;
                curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
            }
            break;
        default:
            curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
            break;
    }
    $ssl = preg_match('/^https:\/\//i', $url) ? true : false;
    curl_setopt($ci, CURLOPT_URL, $url);
    if ($ssl) {
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false); // https请求 不验证证书和hosts
        curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, false); // 不从证书中检查SSL加密算法是否存在
    }
    //curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
    curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ci, CURLOPT_MAXREDIRS, 2); /*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/
    curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ci, CURLINFO_HEADER_OUT, true);
    /*curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
    $response = curl_exec($ci);
    $requestinfo = curl_getinfo($ci);
    $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
    if ($debug) {
        echo "=====post data======\r\n";
        var_dump($postfields);
        echo "=====info===== \r\n";
        print_r($requestinfo);
        echo "=====response=====\r\n";
        print_r($response);
    }
    curl_close($ci);

    return $response;
    //return array($http_code, $response,$requestinfo);
}

/**
 * 过滤数组元素前后空格 (支持多维数组).
 *
 * @param $array 要过滤的数组
 *
 * @return array|string
 */
function trim_array_element($array)
{
    if (!is_array($array)) {
        return trim($array);
    }

    return array_map('trim_array_element', $array);
}

/**
 * 检查手机号码格式.
 *
 * @param $mobile 手机号码
 */
function check_mobile($mobile)
{
    if (preg_match('/1[23456789]\d{9}$/', $mobile)) {
        return true;
    }

    return false;
}

/**
 * 检查密码格式
 * @param $password
 * @param $type
 * @return bool
 */
function check_password($password, $type = 'login')
{
    switch ($type) {
        case 'login':
            // 登录密码 6-20位，要有数字+字母
            $pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/';
            if (preg_match($pattern, $password)) {
                return true;
            }
            break;
        case 'pay':
            // 支付密码 6位，数字
            $pattern = '/^\d{6}$/';
            if (preg_match($pattern, $password)) {
                return true;
            }
            break;
        default:
            return false;
    }
}

function check_id_card($id)
{
    $id = strtoupper($id);
    $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
    $arr_split = [];
    if (!preg_match($regx, $id)) {
        return false;
    }
    if (15 == strlen($id)) { //检查15位
        $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";

        @preg_match($regx, $id, $arr_split);
        //检查生日日期是否正确
        $dtm_birth = '19' . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
        if (!strtotime($dtm_birth)) {
            return false;
        }

        return true;
    }
    //检查18位

    $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
    @preg_match($regx, $id, $arr_split);
    $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
    if (!strtotime($dtm_birth)) { //检查生日日期是否正确
        return false;
    }

    //检验18位身份证的校验码是否正确。
    //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
    $arr_int = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
    $arr_ch = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
    $sign = 0;
    for ($i = 0; $i < 17; ++$i) {
        $b = (int)$id[$i];
        $w = $arr_int[$i];
        $sign += $b * $w;
    }
    $n = $sign % 11;
    $val_num = $arr_ch[$n];
    if ($val_num != substr($id, 17, 1)) {
        return false;
    } //phpfensi.com

    return true;
}

function check_length($content, $min, $max)
{
    return (mb_strlen($content, 'utf8') >= $min && mb_strlen($content, 'utf8') <= $max) ? true : false;
}

/**
 * 检查固定电话.
 *
 * @param $mobile
 *
 * @return bool
 */
function check_telephone($mobile)
{
    if (preg_match('/^([0-9]{3,4}-)?[0-9]{7,8}$/', $mobile)) {
        return true;
    }

    return false;
}

/**
 * 检查邮箱地址格式.
 *
 * @param $email 邮箱地址
 */
function check_email($email)
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return true;
    }

    return false;
}

/**
 * 实现中文字串截取无乱码的方法.
 */
function getSubstr($string, $start, $length)
{
    if (mb_strlen($string, 'utf-8') > $length) {
        $str = mb_substr($string, $start, $length, 'utf-8');

        return $str . '...';
    }

    return $string;
}

/**
 * 判断当前访问的用户是  PC端  还是 手机端  返回true 为手机端  false 为PC 端.
 *
 * @return bool
 */
/**
 * 　　* 是否移动端访问访问.
 * 　　*
 * 　　* @return bool
 * 　　*/
function isMobile()
{
    // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
    if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
        return true;
    }
    // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
    if (isset($_SERVER['HTTP_VIA'])) {
        // 找不到为flase,否则为true
        return stristr($_SERVER['HTTP_VIA'], 'wap') ? true : false;
    }
    // 脑残法，判断手机发送的客户端标志,兼容性有待提高
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $clientkeywords = ['nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile'];
        // 从HTTP_USER_AGENT中查找手机浏览器的关键字
        if (preg_match('/(' . implode('|', $clientkeywords) . ')/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
            return true;
        }
    }
    // 协议法，因为有可能不准确，放到最后判断
    if (isset($_SERVER['HTTP_ACCEPT'])) {
        // 如果只支持wml并且不支持html那一定是移动设备
        // 如果支持wml和html但是wml在html之前则是移动设备
        if ((false !== strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml')) && (false === strpos($_SERVER['HTTP_ACCEPT'], 'text/html') || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
            return true;
        }
    }
    return false;
}

function is_weixin()
{
    if (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
        return true;
    }

    return false;
}

function is_qq()
{
    if (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'QQ')) {
        return true;
    }

    return false;
}

function is_alipay()
{
    if (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient')) {
        return true;
    }

    return false;
}

//php获取中文字符拼音首字母
function getFirstCharter($str)
{
    if (empty($str)) {
        return '';
    }
    $fchar = ord($str[0]);
    if ($fchar >= ord('A') && $fchar <= ord('z')) {
        return strtoupper($str[0]);
    }
    $s1 = iconv('UTF-8', 'gb2312//TRANSLIT//IGNORE', $str);
    $s2 = iconv('gb2312', 'UTF-8//TRANSLIT//IGNORE', $s1);
    $s = $s2 == $str ? $s1 : $str;
    $asc = ord($s[0]) * 256 + ord($s[1]) - 65536;
    if ($asc >= -20319 && $asc <= -20284) {
        return 'A';
    }
    if ($asc >= -20283 && $asc <= -19776) {
        return 'B';
    }
    if ($asc >= -19775 && $asc <= -19219) {
        return 'C';
    }
    if ($asc >= -19218 && $asc <= -18711) {
        return 'D';
    }
    if ($asc >= -18710 && $asc <= -18527) {
        return 'E';
    }
    if ($asc >= -18526 && $asc <= -18240) {
        return 'F';
    }
    if ($asc >= -18239 && $asc <= -17923) {
        return 'G';
    }
    if ($asc >= -17922 && $asc <= -17418) {
        return 'H';
    }
    if ($asc >= -17417 && $asc <= -16475) {
        return 'J';
    }
    if ($asc >= -16474 && $asc <= -16213) {
        return 'K';
    }
    if ($asc >= -16212 && $asc <= -15641) {
        return 'L';
    }
    if ($asc >= -15640 && $asc <= -15166) {
        return 'M';
    }
    if ($asc >= -15165 && $asc <= -14923) {
        return 'N';
    }
    if ($asc >= -14922 && $asc <= -14915) {
        return 'O';
    }
    if ($asc >= -14914 && $asc <= -14631) {
        return 'P';
    }
    if ($asc >= -14630 && $asc <= -14150) {
        return 'Q';
    }
    if ($asc >= -14149 && $asc <= -14091) {
        return 'R';
    }
    if ($asc >= -14090 && $asc <= -13319) {
        return 'S';
    }
    if ($asc >= -13318 && $asc <= -12839) {
        return 'T';
    }
    if ($asc >= -12838 && $asc <= -12557) {
        return 'W';
    }
    if ($asc >= -12556 && $asc <= -11848) {
        return 'X';
    }
    if ($asc >= -11847 && $asc <= -11056) {
        return 'Y';
    }
    if ($asc >= -11055 && $asc <= -10247) {
        return 'Z';
    }

    return null;
}

/**
 * 获取整条字符串汉字拼音首字母.
 *
 * @param $zh
 *
 * @return string
 */
function pinyin_long($zh)
{
    $ret = '';
    $s1 = iconv('UTF-8', 'gb2312', $zh);
    $s2 = iconv('gb2312', 'UTF-8', $s1);
    if ($s2 == $zh) {
        $zh = $s1;
    }
    for ($i = 0; $i < strlen($zh); ++$i) {
        $s1 = substr($zh, $i, 1);
        $p = ord($s1);
        if ($p > 160) {
            $s2 = substr($zh, $i++, 2);
            $ret .= getFirstCharter($s2);
        } else {
            $ret .= $s1;
        }
    }

    return $ret;
}

function ajaxReturn($data)
{
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

function flash_sale_time_space()
{
    $now_day = date('Y-m-d');
    $now_time = date('H');
    if (0 == $now_time % 2) {
        $flash_now_time = $now_time;
    } else {
        $flash_now_time = $now_time - 1;
    }
    $flash_sale_time = strtotime($now_day . ' ' . $flash_now_time . ':00:00');
    $space = 7200;
    $time_space = [
        '1' => ['font' => date('H:i', $flash_sale_time), 'start_time' => $flash_sale_time, 'end_time' => $flash_sale_time + $space],
        '2' => ['font' => date('H:i', $flash_sale_time + $space), 'start_time' => $flash_sale_time + $space, 'end_time' => $flash_sale_time + 2 * $space],
        '3' => ['font' => date('H:i', $flash_sale_time + 2 * $space), 'start_time' => $flash_sale_time + 2 * $space, 'end_time' => $flash_sale_time + 3 * $space],
        '4' => ['font' => date('H:i', $flash_sale_time + 3 * $space), 'start_time' => $flash_sale_time + 3 * $space, 'end_time' => $flash_sale_time + 4 * $space],
        '5' => ['font' => date('H:i', $flash_sale_time + 4 * $space), 'start_time' => $flash_sale_time + 4 * $space, 'end_time' => $flash_sale_time + 5 * $space],
    ];

    return $time_space;
}

/**
 * 验证码操作(不生成图片).
 *
 * @param array $inconfig 配置
 * @param sring $id 要生成验证码的标识
 * @param string $incode 验证码,若为null生成验证码,否则检验验证码
 */
function capache($inconfig = [], $id = '', $incode = null)
{
    $config = [
        'seKey' => 'ThinkPHP.CN',   // 验证码加密密钥
        'codeSet' => '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY', // 验证码字符集合
        'expire' => 1800,            // 验证码过期时间（s）
        'useZh' => false,           // 使用中文验证码
        'zhSet' => '们以我到他会作时要动国产的一是工就年阶义发成部民可出能方进在了不和有大这主中人上为来分生对于学下级地个用同行面说种过命度革而多子后自社加小机也经力线本电高量长党得实家定深法表着水理化争现所二起政三好十战无农使性前等反体合斗路图把结第里正新开论之物从当两些还天资事队批点育重其思与间内去因件日利相由压员气业代全组数果期导平各基或月毛然如应形想制心样干都向变关问比展那它最及外没看治提五解系林者米群头意只明四道马认次文通但条较克又公孔领军流入接席位情运器并飞原油放立题质指建区验活众很教决特此常石强极土少已根共直团统式转别造切九你取西持总料连任志观调七么山程百报更见必真保热委手改管处己将修支识病象几先老光专什六型具示复安带每东增则完风回南广劳轮科北打积车计给节做务被整联步类集号列温装即毫知轴研单色坚据速防史拉世设达尔场织历花受求传口断况采精金界品判参层止边清至万确究书术状厂须离再目海交权且儿青才证低越际八试规斯近注办布门铁需走议县兵固除般引齿千胜细影济白格效置推空配刀叶率述今选养德话查差半敌始片施响收华觉备名红续均药标记难存测士身紧液派准斤角降维板许破述技消底床田势端感往神便贺村构照容非搞亚磨族火段算适讲按值美态黄易彪服早班麦削信排台声该击素张密害侯草何树肥继右属市严径螺检左页抗苏显苦英快称坏移约巴材省黑武培著河帝仅针怎植京助升王眼她抓含苗副杂普谈围食射源例致酸旧却充足短划剂宣环落首尺波承粉践府鱼随考刻靠够满夫失包住促枝局菌杆周护岩师举曲春元超负砂封换太模贫减阳扬江析亩木言球朝医校古呢稻宋听唯输滑站另卫字鼓刚写刘微略范供阿块某功套友限项余倒卷创律雨让骨远帮初皮播优占死毒圈伟季训控激找叫云互跟裂粮粒母练塞钢顶策双留误础吸阻故寸盾晚丝女散焊功株亲院冷彻弹错散商视艺灭版烈零室轻血倍缺厘泵察绝富城冲喷壤简否柱李望盘磁雄似困巩益洲脱投送奴侧润盖挥距触星松送获兴独官混纪依未突架宽冬章湿偏纹吃执阀矿寨责熟稳夺硬价努翻奇甲预职评读背协损棉侵灰虽矛厚罗泥辟告卵箱掌氧恩爱停曾溶营终纲孟钱待尽俄缩沙退陈讨奋械载胞幼哪剥迫旋征槽倒握担仍呀鲜吧卡粗介钻逐弱脚怕盐末阴丰雾冠丙街莱贝辐肠付吉渗瑞惊顿挤秒悬姆烂森糖圣凹陶词迟蚕亿矩康遵牧遭幅园腔订香肉弟屋敏恢忘编印蜂急拿扩伤飞露核缘游振操央伍域甚迅辉异序免纸夜乡久隶缸夹念兰映沟乙吗儒杀汽磷艰晶插埃燃欢铁补咱芽永瓦倾阵碳演威附牙芽永瓦斜灌欧献顺猪洋腐请透司危括脉宜笑若尾束壮暴企菜穗楚汉愈绿拖牛份染既秋遍锻玉夏疗尖殖井费州访吹荣铜沿替滚客召旱悟刺脑措贯藏敢令隙炉壳硫煤迎铸粘探临薄旬善福纵择礼愿伏残雷延烟句纯渐耕跑泽慢栽鲁赤繁境潮横掉锥希池败船假亮谓托伙哲怀割摆贡呈劲财仪沉炼麻罪祖息车穿货销齐鼠抽画饲龙库守筑房歌寒喜哥洗蚀废纳腹乎录镜妇恶脂庄擦险赞钟摇典柄辩竹谷卖乱虚桥奥伯赶垂途额壁网截野遗静谋弄挂课镇妄盛耐援扎虑键归符庆聚绕摩忙舞遇索顾胶羊湖钉仁音迹碎伸灯避泛亡答勇频皇柳哈揭甘诺概宪浓岛袭谁洪谢炮浇斑讯懂灵蛋闭孩释乳巨徒私银伊景坦累匀霉杜乐勒隔弯绩招绍胡呼痛峰零柴簧午跳居尚丁秦稍追梁折耗碱殊岗挖氏刃剧堆赫荷胸衡勤膜篇登驻案刊秧缓凸役剪川雪链渔啦脸户洛孢勃盟买杨宗焦赛旗滤硅炭股坐蒸凝竟陷枪黎救冒暗洞犯筒您宋弧爆谬涂味津臂障褐陆啊健尊豆拔莫抵桑坡缝警挑污冰柬嘴啥饭塑寄赵喊垫丹渡耳刨虎笔稀昆浪萨茶滴浅拥穴覆伦娘吨浸袖珠雌妈紫戏塔锤震岁貌洁剖牢锋疑霸闪埔猛诉刷狠忽灾闹乔唐漏闻沈熔氯荒茎男凡抢像浆旁玻亦忠唱蒙予纷捕锁尤乘乌智淡允叛畜俘摸锈扫毕璃宝芯爷鉴秘净蒋钙肩腾枯抛轨堂拌爸循诱祝励肯酒绳穷塘燥泡袋朗喂铝软渠颗惯贸粪综墙趋彼届墨碍启逆卸航衣孙龄岭骗休借',              // 中文验证码字符串
        'length' => 4,               // 验证码位数
        'reset' => true,           // 验证成功后是否重置
    ];
    $config = array_merge($config, $inconfig);
    $authcode = function ($str) use ($config) {
        $key = substr(md5($config['seKey']), 5, 8);
        $str = substr(md5($str), 8, 10);

        return md5($key . $str);
    };

    /* 生成验证码 */
    if (null === $incode) {
        for ($i = 0; $i < $config['length']; ++$i) {
            $code[$i] = $config['codeSet'][mt_rand(0, strlen($config['codeSet']) - 1)];
        }
        // 保存验证码
        $code_str = implode('', $code);
        $key = $authcode($config['seKey']);
        $code = $authcode(strtoupper($code_str));
        $secode = [];
        $secode['verify_code'] = $code; // 把校验码保存到session
        $secode['verify_time'] = NOW_TIME;  // 验证码创建时间
        session($key . $id, $secode);

        return $code_str;
    }

    /* 检验验证码 */
    if (is_string($incode)) {
        $key = $authcode($config['seKey']) . $id;
        // 验证码不能为空
        $secode = session($key);
        if (empty($incode) || empty($secode)) {
            return false;
        }
        // session 过期
        if (NOW_TIME - $secode['verify_time'] > $config['expire']) {
            session($key, null);

            return false;
        }

        if ($authcode(strtoupper($incode)) == $secode['verify_code']) {
            $config['reset'] && session($key, null);

            return true;
        }

        return false;
    }

    return false;
}

function urlsafe_b64encode($string)
{
    $data = base64_encode($string);
    $data = str_replace(['+', '/', '='], ['-', '_', ''], $data);

    return $data;
}

/**
 * 当前请求是否是https.
 *
 * @return type
 */
function is_https()
{
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && 'off' != $_SERVER['HTTPS'];
}

function mobile_hide($mobile)
{
    return substr_replace($mobile, '****', 3, 4);
}

/**
 * BMP 创建函数.
 *
 * @param string $filename path of bmp file
 *
 * @return resource of GD
 * @example who use,who knows
 *
 * @author simon
 *
 */
function imagecreatefrombmp_my($filename)
{
    if (!$f1 = fopen($filename, 'rb')) {
        return false;
    }
    $FILE = unpack('vfile_type/Vfile_size/Vreserved/Vbitmap_offset', fread($f1, 14));
    if (19778 != $FILE['file_type']) {
        return false;
    }
    $BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel' . '/Vcompression/Vsize_bitmap/Vhoriz_resolution' . '/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1, 40));
    $BMP['colors'] = pow(2, $BMP['bits_per_pixel']);
    if (0 == $BMP['size_bitmap']) {
        $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
    }
    $BMP['bytes_per_pixel'] = $BMP['bits_per_pixel'] / 8;
    $BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
    $BMP['decal'] = ($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
    $BMP['decal'] -= floor($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
    $BMP['decal'] = 4 - (4 * $BMP['decal']);
    if (4 == $BMP['decal']) {
        $BMP['decal'] = 0;
    }
    $PALETTE = [];
    if ($BMP['colors'] < 16777216) {
        $PALETTE = unpack('V' . $BMP['colors'], fread($f1, $BMP['colors'] * 4));
    }
    $IMG = fread($f1, $BMP['size_bitmap']);
    $VIDE = chr(0);
    $res = imagecreatetruecolor($BMP['width'], $BMP['height']);
    $P = 0;
    $Y = $BMP['height'] - 1;
    while ($Y >= 0) {
        $X = 0;
        while ($X < $BMP['width']) {
            if (32 == $BMP['bits_per_pixel']) {
                $COLOR = unpack('V', substr($IMG, $P, 3));
                $B = ord(substr($IMG, $P, 1));
                $G = ord(substr($IMG, $P + 1, 1));
                $R = ord(substr($IMG, $P + 2, 1));
                $color = imagecolorexact($res, $R, $G, $B);
                if (-1 == $color) {
                    $color = imagecolorallocate($res, $R, $G, $B);
                }
                $COLOR[0] = $R * 256 * 256 + $G * 256 + $B;
                $COLOR[1] = $color;
            } elseif (24 == $BMP['bits_per_pixel']) {
                $COLOR = unpack('V', substr($IMG, $P, 3) . $VIDE);
            } elseif (16 == $BMP['bits_per_pixel']) {
                $COLOR = unpack('n', substr($IMG, $P, 2));
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            } elseif (8 == $BMP['bits_per_pixel']) {
                $COLOR = unpack('n', $VIDE . substr($IMG, $P, 1));
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            } elseif (4 == $BMP['bits_per_pixel']) {
                $COLOR = unpack('n', $VIDE . substr($IMG, floor($P), 1));
                if (0 == ($P * 2) % 2) {
                    $COLOR[1] = ($COLOR[1] >> 4);
                } else {
                    $COLOR[1] = ($COLOR[1] & 0x0F);
                }
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            } elseif (1 == $BMP['bits_per_pixel']) {
                $COLOR = unpack('n', $VIDE . substr($IMG, floor($P), 1));
                if (0 == ($P * 8) % 8) {
                    $COLOR[1] = $COLOR[1] >> 7;
                } elseif (1 == ($P * 8) % 8) {
                    $COLOR[1] = ($COLOR[1] & 0x40) >> 6;
                } elseif (2 == ($P * 8) % 8) {
                    $COLOR[1] = ($COLOR[1] & 0x20) >> 5;
                } elseif (3 == ($P * 8) % 8) {
                    $COLOR[1] = ($COLOR[1] & 0x10) >> 4;
                } elseif (4 == ($P * 8) % 8) {
                    $COLOR[1] = ($COLOR[1] & 0x8) >> 3;
                } elseif (5 == ($P * 8) % 8) {
                    $COLOR[1] = ($COLOR[1] & 0x4) >> 2;
                } elseif (6 == ($P * 8) % 8) {
                    $COLOR[1] = ($COLOR[1] & 0x2) >> 1;
                } elseif (7 == ($P * 8) % 8) {
                    $COLOR[1] = ($COLOR[1] & 0x1);
                }
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            } else {
                return false;
            }
            imagesetpixel($res, $X, $Y, $COLOR[1]);
            ++$X;
            $P += $BMP['bytes_per_pixel'];
        }
        --$Y;
        $P += $BMP['decal'];
    }
    fclose($f1);

    return $res;
}

/**
 * 创建bmp格式图片.
 *
 * @param resource $im 图像资源
 * @param string $filename 如果要另存为文件，请指定文件名，为空则直接在浏览器输出
 * @param int $bit 图像质量(1、4、8、16、24、32位)
 * @param int $compression 压缩方式，0为不压缩，1使用RLE8压缩算法进行压缩
 *
 * @return int
 * @version: 0.1
 *
 * @author: legend(legendsky@hotmail.com)
 * @link: http://www.ugia.cn/?p=96
 * @description: create Bitmap-File with GD library
 */
function imagebmp_my(&$im, $filename = '', $bit = 8, $compression = 0)
{
    if (!in_array($bit, [1, 4, 8, 16, 24, 32])) {
        $bit = 8;
    } elseif (32 == $bit) { // todo:32 bit
        $bit = 24;
    }
    $bits = pow(2, $bit);
    // 调整调色板
    imagetruecolortopalette($im, true, $bits);
    $width = imagesx($im);
    $height = imagesy($im);
    $colors_num = imagecolorstotal($im);
    if ($bit <= 8) {
        // 颜色索引
        $rgb_quad = '';
        for ($i = 0; $i < $colors_num; ++$i) {
            $colors = imagecolorsforindex($im, $i);
            $rgb_quad .= chr($colors['blue']) . chr($colors['green']) . chr($colors['red']) . "\0";
        }
        // 位图数据
        $bmp_data = '';
        // 非压缩
        if (0 == $compression || $bit < 8) {
            if (!in_array($bit, [1, 4, 8])) {
                $bit = 8;
            }
            $compression = 0;
            // 每行字节数必须为4的倍数，补齐。
            $extra = '';
            $padding = 4 - ceil($width / (8 / $bit)) % 4;
            if (0 != $padding % 4) {
                $extra = str_repeat("\0", $padding);
            }
            for ($j = $height - 1; $j >= 0; --$j) {
                $i = 0;
                while ($i < $width) {
                    $bin = 0;
                    $limit = $width - $i < 8 / $bit ? (8 / $bit - $width + $i) * $bit : 0;
                    for ($k = 8 - $bit; $k >= $limit; $k -= $bit) {
                        $index = imagecolorat($im, $i, $j);
                        $bin |= $index << $k;
                        ++$i;
                    }
                    $bmp_data .= chr($bin);
                }
                $bmp_data .= $extra;
            }
        } // RLE8 压缩
        elseif (1 == $compression && 8 == $bit) {
            for ($j = $height - 1; $j >= 0; --$j) {
                $last_index = "\0";
                $same_num = 0;
                for ($i = 0; $i <= $width; ++$i) {
                    $index = imagecolorat($im, $i, $j);
                    if ($index !== $last_index || $same_num > 255) {
                        if (0 != $same_num) {
                            $bmp_data .= chr($same_num) . chr($last_index);
                        }
                        $last_index = $index;
                        $same_num = 1;
                    } else {
                        ++$same_num;
                    }
                }
                $bmp_data .= "\0\0";
            }
            $bmp_data .= "\0\1";
        }
        $size_quad = strlen($rgb_quad);
        $size_data = strlen($bmp_data);
    } else {
        // 每行字节数必须为4的倍数，补齐。
        $extra = '';
        $padding = 4 - ($width * ($bit / 8)) % 4;
        if (0 != $padding % 4) {
            $extra = str_repeat("\0", $padding);
        }
        // 位图数据
        $bmp_data = '';
        for ($j = $height - 1; $j >= 0; --$j) {
            for ($i = 0; $i < $width; ++$i) {
                $index = imagecolorat($im, $i, $j);
                $colors = imagecolorsforindex($im, $index);
                if (16 == $bit) {
                    $bin = 0 << $bit;
                    $bin |= ($colors['red'] >> 3) << 10;
                    $bin |= ($colors['green'] >> 3) << 5;
                    $bin |= $colors['blue'] >> 3;
                    $bmp_data .= pack('v', $bin);
                } else {
                    $bmp_data .= pack('c*', $colors['blue'], $colors['green'], $colors['red']);
                }
                // todo: 32bit;
            }
            $bmp_data .= $extra;
        }
        $size_quad = 0;
        $size_data = strlen($bmp_data);
        $colors_num = 0;
    }
    // 位图文件头
    $file_header = 'BM' . pack('V3', 54 + $size_quad + $size_data, 0, 54 + $size_quad);
    // 位图信息头
    $info_header = pack('V3v2V*', 0x28, $width, $height, 1, $bit, $compression, $size_data, 0, 0, $colors_num, 0);
    // 写入文件
    if ('' != $filename) {
        $fp = fopen('test.bmp', 'wb');
        fwrite($fp, $file_header);
        fwrite($fp, $info_header);
        fwrite($fp, $rgb_quad);
        fwrite($fp, $bmp_data);
        fclose($fp);

        return 1;
    }
    // 浏览器输出
    header('Content-Type: image/bmp');
    echo $file_header . $info_header;
    echo $rgb_quad;
    echo $bmp_data;

    return 1;
}

/**
 *    作用：array转xml.
 */
function arrayToXml($arr)
{
    $xml = '<xml>';
    foreach ($arr as $key => $val) {
        if (is_numeric($val)) {
            $xml .= '<' . $key . '>' . $val . '</' . $key . '>';
        } else {
            $xml .= '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
        }
    }
    $xml .= '</xml>';

    return $xml;
}

//  function filter($nickname) {
//     $nickname = '我是一只小傻瓜';

//     $nickname = preg_replace("#(\\\ud[0-9a-f]{3})|(\\\ue[0-9a-f]{3})#i","",$nickname);
//     $nickname = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $nickname);
//     $nickname = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $nickname);
//     $nickname = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $nickname);
//     $nickname = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $nickname);
//     $nickname = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $nickname);
//     $nickname = str_replace(array('"','\''), '', $nickname);

//     dump($nickname);
//     exit;
//     return trim($nickname);
// }

// function removeEmoji($nickname) {

//     $clean_text = "";

//     // Match Emoticons
//     $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
//     $clean_text = preg_replace($regexEmoticons, '', $text);

//     // Match Miscellaneous Symbols and Pictographs
//     $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
//     $clean_text = preg_replace($regexSymbols, '', $clean_text);

//     // Match Transport And Map Symbols
//     $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
//     $clean_text = preg_replace($regexTransport, '', $clean_text);

//     // Match Miscellaneous Symbols
//     $regexMisc = '/[\x{2600}-\x{26FF}]/u';
//     $clean_text = preg_replace($regexMisc, '', $clean_text);

//     // Match Dingbats
//     $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
//     $clean_text = preg_replace($regexDingbats, '', $clean_text);

//     return $clean_text;
// }
function filter($str)
{
    if ($str) {
        $name = $str;
        $name = preg_replace('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', '', $name);
        $name = preg_replace('/xE0[x80-x9F][x80-xBF]‘.‘|xED[xA0-xBF][x80-xBF]/S', '?', $name);
        $return = json_decode(preg_replace("#(\\\ud[0-9a-f]{3})#i", '', json_encode($name)));
    } else {
        $return = '';
    }

    return $return;
}

function rebate_status($status)
{
    $rebate_status = [
        '未付款',
        '已付款',
        '等待分成',
        '已分成',
        '已取消',
        '已统计'
    ];

    return $rebate_status[$status];
}

function checkIdCard($idcard)
{
    $preg_card = "/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/i";

    return preg_match($preg_card, $idcard) ? true : false;
}


/**
 * 分享图片生成
 * @param $gData  商品数据，array
 * @param $codeName 二维码图片
 * @param $fileName string 保存文件名,默认空则直接输入图片
 */
function createSharePng($gData, $codeName, $fileName = '')
{
    //创建画布
    $im = imagecreatetruecolor(618, 1000);

    //填充画布背景色
    $color = imagecolorallocate($im, 255, 255, 255);
    imagefill($im, 0, 0, $color);

    //字体文件
    $font_file = "code_png/msyh.ttf";
    $font_file_bold = "code_png/msyh_bold.ttf";

    //设定字体的颜色
    $font_color_1 = ImageColorAllocate($im, 140, 140, 140);
    $font_color_2 = ImageColorAllocate($im, 28, 28, 28);
    $font_color_3 = ImageColorAllocate($im, 129, 129, 129);
    $font_color_red = ImageColorAllocate($im, 217, 45, 32);

    $fang_bg_color = ImageColorAllocate($im, 254, 216, 217);

    //Logo
    list($l_w, $l_h) = getimagesize('code_png/logo100_100.png');
    $logoImg = @imagecreatefrompng('code_png/logo100_100.png');
    imagecopyresized($im, $logoImg, 245, -10, 0, 0, 150, 150, $l_w, $l_h);

    //温馨提示
    // imagettftext($im, 14,0, 100, 130, $font_color_1 ,$font_file, '温馨提示：喜欢长按图片识别二维码即可前往购买');
    imagettftext($im, 16, 0, 255, 130, $font_color_1, $font_file, '乐活优选商城');

    //商品图片
    list($g_w, $g_h) = getimagesize($gData['pic']);
    $goodImg = createImageFromFile($gData['pic']);
    // imagecopyresized($im, $goodImg, 0, 185, 0, 0, 618, 618, $g_w, $g_h);
    imagecopyresized($im, $goodImg, 0, 155, 0, 0, 618, 600, $g_w, $g_h);

    //二维码
    list($code_w, $code_h) = getimagesize($codeName);
    $codeImg = createImageFromFile($codeName);
    imagecopyresized($im, $codeImg, 440, 780, 0, 0, 170, 170, $code_w, $code_h);

    //商品描述
    $theTitle = cn_row_substr($gData['title'], 2, 12);
    imagettftext($im, 24, 0, 8, 805, $font_color_2, $font_file, $theTitle[1]);
    imagettftext($im, 24, 0, 8, 835, $font_color_2, $font_file, $theTitle[2]);

//    $fontWidth = imagefontwidth(20);//获取文字宽度
//    $textWidth = $fontWidth * mb_strlen($gData["price"]);
//    $x         = ceil((110 - $textWidth) / 2);
//    $x += 50;

    imagettftext($im, 14, 0, 8, 875, $font_color_2, $font_file, "会员价￥");
    imagettftext($im, 28, 0, 80, 875, $font_color_red, $font_file, $gData["price"] . ' + ' . $gData["point"]);

    $fontWidth = 22;//获取文字宽度
    $textWidth = $fontWidth * mb_strlen($gData["price"] . ' + ' . $gData["point"]);


//    $y         = ceil((110 - $textWidth) / 2);
    $y = $textWidth + 70;

//    imagettftext($im, 28,0, 198, 875, $font_color_red ,$font_file, '+');
//    imagettftext($im, 28,0, $y, 875, $font_color_red ,$font_file, $gData["point"]);
    imagettftext($im, 14, 0, $y, 875, $font_color_2, $font_file, "积分");

    imagettftext($im, 14, 0, 8, 910, $font_color_3, $font_file, "原价￥" . $gData["original_price"]);

    imagettftext($im, 14, 0, 8, 940, $font_color_3, $font_file, "昵称：" . $gData["user_name"]);

    imagettftext($im, 14, 0, 100, 980, $font_color_1, $font_file, '温馨提示：喜欢长按图片识别二维码即可前往购买');
    //优惠券
    // if($gData['coupon_price']){
    //     imagerectangle ($im, 125 , 950 , 160 , 975 , $font_color_3);
    //     imagefilledrectangle ($im, 126 , 951 , 159 , 974 , $fang_bg_color);
    //     imagettftext($im, 14,0, 135,970, $font_color_3 ,$font_file, "券");

    //     $coupon_price = strval($gData['coupon_price']);
    //     imagerectangle ($im, 160 , 950 , 198 + (strlen($coupon_price)* 10), 975 , $font_color_3);
    //     imagettftext($im, 14,0, 170,970, $font_color_3 ,$font_file, $coupon_price."元");
    // }

    //输出图片
    if ($fileName) {
        imagepng($im, $fileName);
    } else {
        Header("Content-Type: image/png");
        imagepng($im);
    }

    //释放空间
    imagedestroy($im);
    imagedestroy($goodImg);
    imagedestroy($codeImg);
}

/**
 * 从图片文件创建Image资源
 * @param $file 图片文件，支持url
 * @return bool|resource    成功返回图片image资源，失败返回false
 */
function createImageFromFile($file)
{
    if (preg_match('/http(s)?:\/\//', $file)) {
        $fileSuffix = getNetworkImgType($file);
    } else {
        $fileSuffix = pathinfo($file, PATHINFO_EXTENSION);
    }

    if (!$fileSuffix) return false;

    switch ($fileSuffix) {
        case 'jpeg':
            $theImage = @imagecreatefromjpeg($file);
            break;
        case 'jpg':
            $theImage = @imagecreatefromjpeg($file);
            break;
        case 'png':
            $theImage = @imagecreatefrompng($file);
            break;
        case 'gif':
            $theImage = @imagecreatefromgif($file);
            break;
        default:
            $theImage = @imagecreatefromstring(file_get_contents($file));
            break;
    }

    return $theImage;
}

/**
 * 获取网络图片类型
 * @param $url  网络图片url,支持不带后缀名url
 * @return bool
 */
function getNetworkImgType($url)
{
    $ch = curl_init(); //初始化curl
    curl_setopt($ch, CURLOPT_URL, $url); //设置需要获取的URL
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);//设置超时
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //支持https
    curl_exec($ch);//执行curl会话
    $http_code = curl_getinfo($ch);//获取curl连接资源句柄信息
    curl_close($ch);//关闭资源连接

    if ($http_code['http_code'] == 200) {
        $theImgType = explode('/', $http_code['content_type']);

        if ($theImgType[0] == 'image') {
            return $theImgType[1];
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * 分行连续截取字符串
 * @param $str  需要截取的字符串,UTF-8
 * @param int $row 截取的行数
 * @param int $number 每行截取的字数，中文长度
 * @param bool $suffix 最后行是否添加‘...’后缀
 * @return array    返回数组共$row个元素，下标1到$row
 */
function cn_row_substr($str, $row = 1, $number = 10, $suffix = true)
{
    $result = array();
    for ($r = 1; $r <= $row; $r++) {
        $result[$r] = '';
    }

    $str = trim($str);
    if (!$str) return $result;

    $theStrlen = strlen($str);

    //每行实际字节长度
    $oneRowNum = $number * 3;
    for ($r = 1; $r <= $row; $r++) {
        if ($r == $row and $theStrlen > $r * $oneRowNum and $suffix) {
            $result[$r] = mg_cn_substr($str, $oneRowNum - 6, ($r - 1) * $oneRowNum) . '...';
        } else {
            $result[$r] = mg_cn_substr($str, $oneRowNum, ($r - 1) * $oneRowNum);
        }
        if ($theStrlen < $r * $oneRowNum) break;
    }

    return $result;
}

/**
 * 按字节截取utf-8字符串
 * 识别汉字全角符号，全角中文3个字节，半角英文1个字节
 * @param $str  需要切取的字符串
 * @param $len  截取长度[字节]
 * @param int $start 截取开始位置，默认0
 * @return string
 */
function mg_cn_substr($str, $len, $start = 0)
{
    $q_str = '';
    $q_strlen = ($start + $len) > strlen($str) ? strlen($str) : ($start + $len);

    //如果start不为起始位置，若起始位置为乱码就按照UTF-8编码获取新start
    if ($start and json_encode(substr($str, $start, 1)) === false) {
        for ($a = 0; $a < 3; $a++) {
            $new_start = $start + $a;
            $m_str = substr($str, $new_start, 3);
            if (json_encode($m_str) !== false) {
                $start = $new_start;
                break;
            }
        }
    }

    //切取内容
    for ($i = $start; $i < $q_strlen; $i++) {
        //ord()函数取得substr()的第一个字符的ASCII码，如果大于0xa0的话则是中文字符
        if (ord(substr($str, $i, 1)) > 0xa0) {
            $q_str .= substr($str, $i, 3);
            $i += 2;
        } else {
            $q_str .= substr($str, $i, 1);
        }
    }
    return $q_str;
}

/**
 * 获取星期几
 * @param $time
 * @return string
 */
function getWeekDay($time)
{
    $weekArr = ["日", "一", "二", "三", "四", "五", "六"];
    return "星期" . $weekArr[date('w', $time)];
}

/**
 * url参数拼接
 * @param $params
 * @return string
 */
function ToUrlParams($params)
{
    $string = '';
    if (!empty($params)) {
        $array = array();
        foreach ($params as $key => $value) {
            $array[] = $key . '=' . $value;
        }
        $string = implode("&", $array);
    }
    return $string;
}

/**
 * 两个时间的相差
 * @param $time1
 * @param $time2
 * @param $seconds
 * @return string
 */
function differTimeStr($time1, $time2, $seconds = false)
{
    $second = $time1 - $time2;
    $day = floor($second / (3600 * 24));
    $second = $second % (3600 * 24);    // 除去整天之后剩余的时间
    $hour = floor($second / 3600);
    $second = $second % 3600;   // 除去整小时之后剩余的时间
    $minute = floor($second / 60);
    $second = $second % 60; // 除去整分钟之后剩余的时间
    // 返回字符串
    if ($seconds) {
        return $day . '天' . $hour . '小时' . $minute . '分' . $second . '秒';
    }
    return $day . '天' . $hour . '小时' . $minute . '分';
}

/**
 * +----------------------------------------------------------
 * 将一个字符串部分字符用*替代隐藏
 * +----------------------------------------------------------
 * @param string $string 待转换的字符串
 * @param int $bengin 起始位置，从0开始计数，当$type=4时，表示左侧保留长度
 * @param int $len 需要转换成*的字符个数，当$type=4时，表示右侧保留长度
 * @param int $type 转换类型：0，从左向右隐藏；1，从右向左隐藏；2，从指定字符位置分割前由右向左隐藏；3，从指定字符位置分割后由左向右隐藏；4，保留首末指定字符串
 * @param string $glue 分割符
 * +----------------------------------------------------------
 * @return string 处理后的字符串
 * +----------------------------------------------------------
 */
function hideStr($string, $bengin = 0, $len = 4, $type = 0, $glue = '')
{
    if (empty($string))
        return false;
    $array = array();
    if ($type == 0 || $type == 1 || $type == 4) {
        $strlen = $length = mb_strlen($string);
        while ($strlen) {
            $array[] = mb_substr($string, 0, 1, "utf8");
            $string = mb_substr($string, 1, $strlen, "utf8");
            $strlen = mb_strlen($string);
        }
    }
    if ($type == 0) {
        for ($i = $bengin; $i < ($bengin + $len); $i++) {
            if (isset($array[$i]))
                $array[$i] = "*";
        }
        $string = implode("", $array);
    } else if ($type == 1) {
        $array = array_reverse($array);
        for ($i = $bengin; $i < ($bengin + $len); $i++) {
            if (isset($array[$i]))
                $array[$i] = "*";
        }
        $string = implode("", array_reverse($array));
    } else if ($type == 2) {
        $array = explode($glue, $string);
        $array[0] = hideStr($array[0], $bengin, $len, 1);
        $string = implode($glue, $array);
    } else if ($type == 3) {
        $array = explode($glue, $string);
        $array[1] = hideStr($array[1], $bengin, $len, 0);
        $string = implode($glue, $array);
    } else if ($type == 4) {
        $left = $bengin;
        $right = $len;
        $tem = array();
        for ($i = 0; $i < ($length - $right); $i++) {
            if (isset($array[$i]))
                $tem[] = $i >= $left ? $glue : $array[$i];
        }
        $array = array_chunk(array_reverse($array), $right);
        $array = array_reverse($array[0]);
        for ($i = 0; $i < $right; $i++) {
            $tem[] = $array[$i];
        }
        $string = implode("", $tem);
    }
    return $string;
}

/**
 * RGB转十六进制
 * @param string $rgb RGB颜色的字符串 如：rgb(255,255,255);
 * @return string 十六进制颜色值 如：#FFFFFF
 */
function RGBToHex($rgb)
{
    $regexp = "/^rgb\(([0-9]{0,3})\,\s*([0-9]{0,3})\,\s*([0-9]{0,3})\)/";
    preg_match($regexp, $rgb, $match);
    array_shift($match);
    $hexColor = "#";
    $hex = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');
    for ($i = 0; $i < 3; $i++) {
        $r = null;
        $c = $match[$i];
        $hexAr = array();
        while ($c > 16) {
            $r = $c % 16;
            $c = ($c / 16) >> 0;
            array_push($hexAr, $hex[$r]);
        }
        array_push($hexAr, $hex[$c]);
        $ret = array_reverse($hexAr);
        $item = implode('', $ret);
        $item = str_pad($item, 2, '0', STR_PAD_LEFT);
        $hexColor .= $item;
    }
    return $hexColor;
}

/**
 * 十六进制转RGB
 * @param string $hexColor
 * @return array RBG颜色值
 */
function HexToRGB($hexColor)
{
    $color = str_replace('#', '', $hexColor);
    if (strlen($color) > 3) {
        $rgb = array(
            'r' => hexdec(substr($color, 0, 2)),
            'g' => hexdec(substr($color, 2, 2)),
            'b' => hexdec(substr($color, 4, 2))
        );
    } else {
        $color = $hexColor;
        $r = substr($color, 0, 1) . substr($color, 0, 1);
        $g = substr($color, 1, 1) . substr($color, 1, 1);
        $b = substr($color, 2, 1) . substr($color, 2, 1);
        $rgb = array(
            'r' => hexdec($r),
            'g' => hexdec($g),
            'b' => hexdec($b)
        );
    }
    return $rgb;
}

/**
 * 十六精致转RGBA
 * @param $color
 * @param integer $opacity 透明度
 * @return array
 */
function HexToRGBA($color, $opacity = 0)
{
    $default = [
        'r' => 0,
        'g' => 0,
        'b' => 0,
    ];
    // Return default if no color provided
    if (empty($color))
        return $default;

    // Sanitize $color if "#" is provided
    if ($color[0] == '#') {
        $color = substr($color, 1);
    }

    // Check if color has 6 or 3 characters and get values
    if (strlen($color) == 6) {
        $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
    } elseif (strlen($color) == 3) {
        $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
    } else {
        return $default;
    }

    // Convert hexadec to rgb
    $rgb = array_map('hexdec', $hex);

    if ($opacity) {
        if (abs($opacity) > 1) $opacity = 1.0;
        array_push($rgb, $opacity);
        $output = [
            'r' => $rgb[0],
            'g' => $rgb[1],
            'b' => $rgb[2],
            'a' => $rgb[3]
        ];
    } else {
        $output = [
            'r' => $rgb[0],
            'g' => $rgb[1],
            'b' => $rgb[2],
        ];
    }

    return $output;
}
