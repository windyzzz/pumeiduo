<?php


namespace app\home\controller\api;

use think\Url;

class System
{
    public function __construct()
    {
        // header('Access-Control-Allow-Origin:*');
        // header('Access-Control-Allow-Method:POST,GET');
    }

    function happy_day()
    {
        $time = NOW_TIME;
        $field = 'top1,top2,top3,top4,top5,top6,top7,top8,bg1';
        $icon = M('icon')->field($field)->where(array('from_time' => array('elt', $time), 'to_time' => array('egt', $time)))->find();
        if (!$icon) {
            $icon = M('icon')->field($field)->where(array('id' => 1))->find();
        }
        return json(['status' => 1, 'msg' => 'success', 'result' => $icon]);
    }

    function footer_icon()
    {
        $time = NOW_TIME;
        $field = 'footer1,footer2,footer3,footer4,footer5,footer6,footer7,footer8';
        $icon = M('icon')->field($field)->where(array('from_time' => array('elt', $time), 'to_time' => array('egt', $time)))->find();
        if (!$icon) {
            $icon = M('icon')->field($field)->where(array('id' => 1))->find();
        }
        return json(['status' => 1, 'msg' => 'success', 'result' => $icon]);
    }

    /**
     * H5获取苹果安卓APP下载链接
     * @return \think\response\Json
     */
    public function downLink()
    {
        $androidUrl = SITE_URL . tpCache('android.app_path');
        $iosUrl = tpCache('ios.app_path');
        $return = [
            'android_url' => $androidUrl,
            'ios_url' => $iosUrl,
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 检测更新
     * @return \think\response\Json
     */
    function checkUpdate()
    {
        $type = I('type', '');
        $version = I('version', '');
        if (empty($version)) {
            return json(['status' => 0, 'msg' => '请传入版本号']);
        }
        $version = explode('.', $version);
        $appVersion = '';
        foreach ($version as $item) {
            $appVersion .= $item * 10;
        }
        switch ($type) {
            case 'ios':
                $version = explode('.', tpCache('ios.app_version'));
                $iosVersion = '';
                foreach ($version as $item) {
                    $iosVersion .= $item * 10;
                }
                if ($appVersion == $iosVersion) {
                    $result['state'] = 0;   // 无需更新
                } else {
                    $result['state'] = tpCache('ios.is_update') ? (int)tpCache('ios.is_update') : 0;    // 是否需要更新
                }
                $result['is_force'] = tpCache('ios.is_force') ? (int)tpCache('ios.is_force') : 0;  // 是否强制更新
                $result['app_url'] = tpCache('ios.app_path');
                break;
            case 'android':
                $version = explode('.', tpCache('ios.app_version'));
                $androidVersion = '';
                foreach ($version as $item) {
                    $androidVersion .= $item * 10;
                }
                if ($appVersion == $androidVersion) {
                    $result['state'] = 0;   // 无需更新
                } else {
                    $result['state'] = tpCache('ios.is_update') ? (int)tpCache('ios.is_update') : 0;    // 是否需要更新
                }
                $result['is_force'] = tpCache('android.is_force') ? (int)tpCache('android.is_force') : 0;  // 是否强制更新
                Url::root('/');
                $baseUrl = url('/', '', '', true);
                $result['app_url'] = $baseUrl . tpCache('android.app_path');
                break;
            default:
                return json(['status' => 0, 'msg' => '请求类型错误']);
        }
        return json(['status' => 1, 'result' => $result]);
    }
}