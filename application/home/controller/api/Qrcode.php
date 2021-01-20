<?php

namespace app\home\controller\api;

use app\common\logic\Qrcode as QrcodeLogic;
use think\Exception;

class Qrcode extends Base
{
    /**
     * 获取扫码信息
     * @return \think\response\Json
     */
    public function info()
    {
        $code = I('code', '');
        if (!$code) return json(['status' => 0, 'msg' => '请传入兑换码']);
        try {
            $res = (new QrcodeLogic())->getInfo($code, $this->user_id);
            return json($res);
        } catch (Exception $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
