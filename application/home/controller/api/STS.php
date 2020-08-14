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
        $res = (new STSLogic($this->user_id))->sts();
        if (empty($res)) {
            return json(['status' => 0, 'msg' => '凭证获取失败，请重试']);
        } else {
            $res['url'] = 'http://' . C('OSS_BUCKET') . '.' . C('OSS_ENDPOINT');
            return json(['status' => 1, 'result' => $res]);
        }
    }
}