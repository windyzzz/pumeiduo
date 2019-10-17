<?php

namespace app\home\controller\api;


use app\common\logic\Token as TokenLogic;
use think\cache\driver\Redis;
use think\Controller;
use think\Exception;
use think\Request;

class Base extends Controller
{
    protected $user;
    protected $user_id;
    protected $userToken;
    protected $redis;

    /**
     * 初始化token验证
     * Base constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->redis = new Redis();

        session_start();
        if (session_id()) {
            // 网页请求
            $this->userToken = session_id();
            return true;
        }
        // APP请求
        $token = Request::instance()->header('user-token', null);
        if (!$token) {
            $this->userToken = TokenLogic::setToken();
            return true;
        }
        // 验证token
        $res = TokenLogic::checkToken($token);
        if ($res['status'] == 0) die(json_encode(['status' => 0, 'msg' => $res['msg']]));
        $this->user = $res['user'];
        $this->user_id = $res['user']['user_id'];
        $this->userToken = $token;
    }
}