<?php

namespace app\home\controller\api;


use think\Request;

class Token
{
    /**
     * 检查用户token
     * @param Request $request
     * @return array|\think\response\Json
     */
    public function userToken(Request $request)
    {
        $userToken = $request->header('user-token');
        if ($userToken) {
            // 验证token
            $timeOut = M('users')->where(['token' => $userToken])->value('time_out');
            if (!isset($timeOut)) return json(['status' => 0, 'msg' => '账号已在另一个地方登录']);
            if ($timeOut != 0) {
                if (time() - $timeOut > 0) {
                    return json(['status' => 0, 'msg' => '请重新登录']);
                }
            }
        }
        return json(['status' => 1, 'msg' => 'ok']);
    }
}