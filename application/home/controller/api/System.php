<?php

namespace app\home\controller\api;

class System
{

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
        $downLink = M('config')->where(['inc_type' => ['IN', ['ios', 'android']], 'name' => 'app_path'])->order('id desc')->getField('inc_type, value', true);
        $androidUrl = $downLink['android'];
        $iosUrl = $downLink['ios'];
        $return = [
            'android_url' => htmlspecialchars_decode($androidUrl),
            'ios_url' => htmlspecialchars_decode($iosUrl),
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
        if (!in_array($type, ['ios', 'android'])) {
            return json(['status' => 0, 'msg' => '类型错误']);
        }
        if (empty($version)) {
            return json(['status' => 0, 'msg' => '请传入版本号']);
        }
        // 传入版本
        $version = explode('.', $version);
        $appVersion = '';
        foreach ($version as $item) {
            $appVersion .= $item * 10;
        }
        // 当前配置
        $config = M('config')->where(['inc_type' => $type])->group('name')->order('id desc')->getField('name, value', true);
        $version = explode('.', $config['app_version']);    // 当前版本
        $nowVersion = '';
        foreach ($version as $item) {
            $nowVersion .= $item * 10;
        }
        if ($appVersion == $nowVersion || $appVersion > $nowVersion) {
            $result['state'] = 0;   // 无需更新
        } else {
            $result['state'] = $config['is_update'] ? (int)$config['is_update'] : 0;    // 是否需要更新
        }
        $result['is_force'] = $config['is_force'] ? (int)$config['is_force'] : 0;  // 是否强制更新
        $result['app_url'] = htmlspecialchars_decode($config['app_path']);
        $result['target_version'] = $config['app_version'];
        return json(['status' => 1, 'result' => $result]);
    }

    /**
     * 点击记录
     * @return \think\response\Json
     */
    public function clickCount()
    {
        $position = I('position', 1);
        if (request()->isPost()) {
            M('click_log')->add([
                'position' => $position,
                'ip' => request()->ip(),
                'time' => time()
            ]);
            return json(['status' => 1]);
        }
        $count = M('click_log')->where(['position' => $position])->count('id');
        return json(['status' => 1, 'result' => ['count' => $count]]);
    }

    /**
     * 下载记录
     * @return \think\response\Json
     */
    public function downloadCount()
    {
        $type = I('type', '');
        if (!$type) return json(['status' => 0, 'msg' => 'APP类型错误']);
        if (request()->isPost()) {
            M('download_log')->add([
                'type' => $type,
                'down_ip' => request()->ip(),
                'down_time' => time()
            ]);
            return json(['status' => 1]);
        }
        $count = M('download_log')->where(['type' => $type])->count('id');
        return json(['status' => 1, 'result' => ['count' => $count]]);
    }
}