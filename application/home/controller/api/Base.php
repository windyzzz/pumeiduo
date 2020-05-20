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
    protected $passAuth = false;
    protected $isApp = false;

    /**
     * 初始化token验证
     * Base constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->redis = new Redis();
        $this->isApp = Request::instance()->header('is-app', null);
        if ($this->isApp == 1) {
            // APP请求
            session_start();
            session_destroy();
            $token = Request::instance()->header('user-token', null);
            // 处理url
            $url = self::getUrl();
            if (in_array($url, self::whiteListPath()) || !$token) {
                $this->userToken = TokenLogic::setToken();
                $this->passAuth = true;
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
//            if (!in_array($this->user_id, [1, 107, 24749, 36167, 36175, 36294, 36383, 36430, 36472, 36518, 36527, 36558, 39730])) {
//                die(json_encode(['status' => $this->user_id, 'msg' => '抱歉，这只是测试服务器，正式服务器已2020-3-9中午12点正式发布，请及时下载最新版APP']));
//            }
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
            'c=api.Login&a=checkLogin', // 检查登录（旧版）
            'c=api.Login&a=reg', // 用户注册
            'c=api.Login&a=reg', // 用户注册
            'c=api.Login&a=do_login', // 用户登录,
            'c=api.Index&a=indexNew',   // 主页
            'c=api.Goods&a=all_category',   // 商品分类
            'c=api.Goods&a=calcSpecPrice',   // 获取规格组合价格
            'c=api.Goods&a=goodsListNew',   // 商品列表
            'c=api.Goods&a=searchList',   // 商品搜索列表
            'c=api.Goods&a=getSeriesGoodsList',   // 超值套装列表
            'c=api.Goods&a=getGroupBuyGoodsListNew',   // 团购商品列表
            'c=api.Goods&a=getNewGoodsList',   // 新品列表
            'c=api.Goods&a=getRecommendGoodsList',   // 促销商品
            'c=api.Goods&a=getHotGoodsList',   // 热销商品
            'c=api.Goods&a=getFlashSalesGoodsList',   // 秒杀商品
            'c=api.Goods&a=look_see',   // 猜你喜欢
            'c=api.Goods&a=indexGoods',   // 主页展示不同类型商品
            'c=api.User&a=findPassword',   // 找回密码（登录前忘记密码）
            'c=api.Adv&a=index',   // 广告
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
            'c=api.Message&a=announce',   // 公告列表
            'c=api.Goods&a=goodsInfoNew',   // 商品详情
            'c=api.Adv&a=popup',   // 活动弹窗
        ];
    }
}