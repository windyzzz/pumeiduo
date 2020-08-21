<?php

namespace app\home\controller\api;

use app\common\logic\STS as STSLogic;

class STS extends Base
{
    /**
     * 获取STS凭证
     * @return \think\response\Json
     */
    public function getVoucher()
    {
        $type = I('type', 'video');
        $path = $type . '/' . date('Y/m/d/H/');
        $res = (new STSLogic($this->user_id))->sts();
        if (empty($res)) {
            return json(['status' => 0, 'msg' => '凭证获取失败，请重试']);
        } else {
            $res['bucket'] = C('OSS_BUCKET');
            $res['end_point'] = C('OSS_ENDPOINT');
            $res['path'] = $path;
            $res['file_name'] = get_rand_str(32, 1, 1);
            return json(['status' => 1, 'result' => $res]);
        }
    }
}