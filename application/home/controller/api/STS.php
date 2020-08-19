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
        $res = (new STSLogic($this->user_id))->sts();
        if (empty($res)) {
            return json(['status' => 0, 'msg' => '凭证获取失败，请重试']);
        } else {
            $res['bucket'] = C('OSS_BUCKET');
            $res['end_point'] = C('OSS_ENDPOINT');
            $res['path'] = $type;
            return json(['status' => 1, 'result' => $res]);
        }
    }
}