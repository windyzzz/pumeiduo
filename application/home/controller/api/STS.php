<?php

namespace app\home\controller\api;

use app\common\logic\STS as STSLogic;

class STS extends Base
{
    public function __construct()
    {
        parent::__construct();
        if ($this->passAuth) {
            die(json_encode(['status' => -999, 'msg' => '请先登录']));
        }
    }

    /**
     * 获取STS凭证
     * @return \think\response\Json
     */
    public function getVoucher()
    {
        $type = I('type', 'video');
        $num = I('num', 1);
        $path = $type . '/' . date('Y/m/d/H/');
        $res = (new STSLogic($this->user_id))->sts();
        if (empty($res)) {
            return json(['status' => 0, 'msg' => '凭证获取失败，请重试']);
        } else {
            $res['bucket'] = C('OSS_BUCKET');
            $res['end_point'] = C('OSS_ENDPOINT');
            $res['path'] = $path;
            $res['file_name'] = get_rand_str(32, 1, 1);
            $res['file_name_arr'] = [];
            for ($i = 1; $i <= $num; $i++) {
                $res['file_name_arr'][] = get_rand_str(32, 1, 1);
            }
            return json(['status' => 1, 'result' => $res]);
        }
    }
}