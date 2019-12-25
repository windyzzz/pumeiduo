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
            session_start();
            session_destroy();
            $token = Request::instance()->header('user-token', null);
            // 处理url
            $url = self::getUrl();
            if (in_array($url, self::whiteListPath()) || !$token) {
                $this->userToken = TokenLogic::setToken();
                return true;
            }
            if (in_array($url, self::specialListPath()) && $token) {
                $user = TokenLogic::getValue('user', $token);
                $this->user = $user;
                $this->user_id = !empty($user) ? $user['user_id'] : '';
                $this->userToken = $token;
                return true;
            }
            // 验证token
            $res = TokenLogic::checkToken($token);
            if ($res['status'] !== 1) die(json_encode(['status' => $res['status'], 'msg' => $res['msg']]));
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
        $url = $this->request->url();
        $urlArr = explode('&', $url);
        return $urlArr[1] . '&' . $urlArr[2];
    }

    /**
     * 白名单路径
     * 绕过登录验证
     * @return array
     */
    private function whiteListPath()
    {
        return [
            'c=api.Login&a=reg', // 用户注册
            'c=api.Login&a=do_login', // 用户登录,
            'c=api.Index&a=indexNew',   // 主页
            'c=api.Goods&a=all_category',   // 商品分类
            'c=api.Goods&a=goodsList',   // 商品分类
            'c=api.Goods&a=getSeriesGoodsList',   // 超值套装列表
            'c=api.Goods&a=getGroupBuyGoodsListNew',   // 团购商品列表
            'c=api.Goods&a=getNewGoodsList',   // 新品列表
            'c=api.Goods&a=getRecommendGoodsList',   // 促销商品
            'c=api.Goods&a=getHotGoodsList',   // 热销商品
            'c=api.Goods&a=getFlashSalesGoodsList',   // 秒杀商品
            'c=api.Goods&a=look_see',   // 猜你喜欢
            'c=api.Message&a=announce',   // 公告列表
        ];
    }

    /**
     * 特殊路径
     * 可绕过登录验证，且又可以获取用户信息
     * @return array
     */
    private function specialListPath()
    {
        return [
            'c=api.Goods&a=goodsInfoNew',   // 商品详情
            'c=api.Coupon&a=couponList',   // 领券中心
        ];
    }
}