<?php

namespace app\home\controller\api;


use app\common\logic\Token as TokenLogic;
use think\cache\driver\Redis;
use think\Controller;
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
        $isApp = Request::instance()->header('is-app', null);
        if ($isApp == 1) {
            // APP请求
            $token = Request::instance()->header('user-token', null);
            // 处理url
            if (in_array(self::getUrl(), self::specialPath()) && !$token) {
                $this->userToken = TokenLogic::setToken();
                return true;
            }
            C();
            // 验证token
            $res = TokenLogic::checkToken($token);
            if ($res['status'] == 0) die(json_encode(['status' => 0, 'msg' => $res['msg']]));
            $this->user = $res['user'];
            $this->user_id = $res['user']['user_id'];
            $this->userToken = $token;
        } else {
            // 网页请求
            session_start();
            $this->userToken = session_id();
        }
    }

    /**
     * 获取请求路径
     * @return string
     */
    private function getUrl()
    {
        return $this->request->url();
    }

    /**
     * 特别路径
     * @return array
     */
    private function specialPath()
    {
        return [
            '/index.php?m=Home&c=api.Login&a=reg', // 用户注册
            '/index.php?m=Home&c=api.Login&a=do_login', // 用户登录
        ];
    }
}