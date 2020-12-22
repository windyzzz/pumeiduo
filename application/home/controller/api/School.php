<?php

namespace app\home\controller\api;

use app\common\logic\School as SchoolLogic;
use app\common\logic\UsersLogic;
use app\common\util\TpshopException;

class School extends Base
{
    protected $logic;

    public function __construct()
    {
        parent::__construct();
        $this->logic = new SchoolLogic();
    }

    /**
     * 轮播图列表
     * @return \think\response\Json
     */
    public function rotate()
    {
        $moduleId = I('module_id', 0);
        $data = $this->logic->getRotate($moduleId);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 模块列表
     * @return \think\response\Json
     */
    public function module()
    {
        $data = $this->logic->getModule();
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 模块分类列表
     * @return \think\response\Json
     */
    public function moduleClass()
    {
        $moduleId = I('module_id', 0);
        $data = $this->logic->getModuleClass($moduleId);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 用户文章权限检查
     * @return \think\response\Json
     */
    public function checkArticle()
    {
        if (!$this->user) return json(['status' => -999, 'msg' => '请先登录']);
        $param = [
            'article_id' => I('article_id', ''),
        ];
        $res = $this->logic->checkArticle($param, $this->user);
        return json($res);
    }

    /**
     * 文章列表
     * @return \think\response\Json
     */
    public function articleList()
    {
        $limit = I('limit', 10);
        $param = [
            'module_type' => I('code', ''),
            'class_id' => I('class_id', ''),
            'status' => I('status', ''),
            'is_recommend' => I('is_recommend', ''),
            'is_integral' => I('is_integral', ''),
            'distribute_level' => I('level', '')
        ];
        if (!empty($param['module_type']) && empty($param['class_id']) && empty($param['is_recommend'])) {
            // 查找模块下第一个分类
            $param['class_id'] = M('school_class sc')->join('school s', 's.id = sc.module_id')
                ->where(['s.type' => $param['module_type'], 'sc.is_open' => 1])->order('sc.sort DESC')->value('sc.id');
            if (!$param['class_id']) return json(['status' => 0, 'msg' => '模块下没有分类']);
        }
        $data = $this->logic->getArticleList($limit, $param, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 文章详情
     * @return \think\response\Json
     */
    public function articleInfo()
    {
        if (!$this->user) return json(['status' => -999, 'msg' => '请先登录']);
        $param = [
            'article_id' => I('article_id', ''),
        ];
        $data = $this->logic->getArticleInfo($param, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 文章详情（H5专用）
     * @return \think\response\Json
     */
    public function articleInfoH5()
    {
        $param = [
            'article_id' => I('article_id', ''),
        ];
        $data = $this->logic->getArticleInfo($param, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 文章内容
     * @return \think\response\Json
     */
    public function articleContent()
    {
        $articleId = I('article_id', 0);
        $data = $this->logic->getArticleContent($articleId);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 文章分享二维码
     * @return \think\response\Json
     */
    public function articleShareCode()
    {
        $articleId = I('article_id', 0);
        $data = $this->logic->getArticleShareCode($articleId, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 用户文章列表
     * @return \think\response\Json
     */
    public function userArticle()
    {
        $limit = I('limit', 10);
        $param = [
            'status' => I('status', ''),
        ];
        $data = $this->logic->getUserArticle($limit, $param, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 学习课程文章（完成）
     * @return \think\response\Json
     */
    public function learnArticle()
    {
        $articleId = I('article_id', 0);
        $data = $this->logic->learnArticle($articleId, $this->user);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 下载文章素材
     * @return \think\response\Json
     */
    public function getResource()
    {
        $param = [
            'article_id' => I('article_id', ''),
        ];
        $data = $this->logic->downloadArticleResource($param, $this->user);
        return json($data);
    }

    /**
     * 兑换商品列表
     * @return \think\response\Json
     */
    public function exchange()
    {
        $limit = I('limit', 10);
        $data = $this->logic->getExchangeList($limit);
        $data['user'] = [
            'user_id' => $this->user['user_id'],
            'nickname' => $this->user['nickname'] ?? ($this->user['user_name'] ?? '用户' . $this->user_id),
            'head_pic' => $this->user['head_pic'],
            'level' => $this->user['distribut_level'],
            'level_name' => M('DistributLevel')->where('level_id', $this->user['distribut_level'])->getField('level_name') ?? '普通会员',
            'credit' => $this->user['school_credit']
        ];
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 兑换商品详情
     * @return \think\response\Json
     */
    public function exchangeInfo()
    {
        $goodsId = I('goods_id', 0);
        $itemId = I('item_id', 0);
        $data = $this->logic->getExchangeInfo($goodsId, $itemId);
        if (isset($data['status']) && $data['status'] != 1) {
            return json($data);
        }
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }

    /**
     * 兑换订单
     * @return \think\response\Json
     */
    public function exchangeOrder()
    {
        $goodsId = I('goods_id', 0);
        $itemId = I('item_id', 0);
        $goodsNum = I('goods_num', 0);
        $addressId = I('address_id', '');
        $payPwd = I('pay_pwd', '');
        try {
            if (!$goodsId || !$goodsNum) {
                throw new TpshopException('商学院兑换商品下单', 0, ['status' => 0, 'msg' => '请选择商品']);
            }
            // 商品信息
            $data = $this->logic->getExchangeInfo($goodsId, $itemId);
            if (isset($data['status']) && $data['status'] != 1) {
                throw new TpshopException('商学院兑换商品下单', 0, $data);
            }
            unset($data['content_url']);
            $data['goods_num'] = $goodsNum;
            $goodsInfo = $data;
            // 价格计算
            $orderAmount = bcmul($goodsInfo['credit'], $goodsNum, 2);
            if ($orderAmount > $this->user['school_credit']) {
                throw new TpshopException('商学院兑换商品下单', 0, ['status' => 0, 'msg' => '您当前的乐活豆不足，只有' . $this->user['school_credit']]);
            }
            if ($this->request->isPost()) {
                /*
                 * 下单处理
                 */
                if (!$addressId) {
                    throw new TpshopException('商学院兑换商品下单', 0, ['status' => 0, 'msg' => '请选择地址']);
                }
                if (!$this->user['paypwd']) {
                    throw new TpshopException('商学院兑换商品下单', 0, ['status' => 0, 'msg' => '请先设置支付密码']);
                }
                if ($this->user['paypwd'] != systemEncrypt($payPwd)) {
                    throw new TpshopException('商学院兑换商品下单', 0, ['status' => 0, 'msg' => '支付密码错误']);
                }
                $userAddress = M('user_address')->where(['address_id' => $addressId])->find();
                if (empty($userAddress)) {
                    throw new TpshopException('商学院兑换商品下单', 0, ['status' => 0, 'msg' => '收货人信息不存在']);
                }
                $res = $this->logic->createExchangeOrder($this->user, $payPwd, $userAddress, $goodsInfo);
                return json($res);
            }
            // 地址信息
            if (!$addressId) {
                // 用户默认地址
                $userAddress = get_user_address_list_new($this->user_id, true);
                if (!empty($userAddress)) {
                    $userAddress = $userAddress[0];
                }
            } else {
                $userAddress = get_user_address_list_new($this->user_id, false, $addressId);
                if (empty($userAddress)) {
                    return json(['status' => 0, 'msg' => '收货人信息不存在']);
                }
                $userAddress = $userAddress[0];
            }
            if (!empty($userAddress)) {
                $userAddress['town_name'] = $userAddress['town_name'] ?? '';
                $userAddress['is_illegal'] = 0;     // 非法地址
                $userAddress['out_range'] = 0;      // 超出配送范围
                $userAddress['limit_tips'] = '';    // 限制的提示
                unset($userAddress['zipcode']);
                unset($userAddress['is_pickup']);
                $userLogic = new UsersLogic();
                // 地址标签
                $addressTab = $userLogic->getAddressTab($this->user_id);
                $tabs = $userAddress['tabs'];
                $userAddress['tabs'] = [];
                if ($userAddress['is_default'] == 1) {
                    $userAddress['tabs'][] = [
                        'tab_id' => 0,
                        'name' => '默认',
                        'is_selected' => 1
                    ];
                }
                if (!empty($addressTab) && !empty($tabs)) {
                    $tabs = explode(',', $tabs);
                    foreach ($addressTab as $item) {
                        if (in_array($item['tab_id'], $tabs)) {
                            $userAddress['tabs'][] = [
                                'tab_id' => $item['tab_id'],
                                'name' => $item['name'],
                                'is_selected' => 1
                            ];
                        }
                    }
                }
                // 判断用户地址是否合法
                $userAddress = $userLogic->checkAddressIllegal($userAddress);
                if ($userAddress['is_illegal'] == 1) {
                    $userAddress['limit_tips'] = '当前地址信息不完整，请添加街道后补充完整地址信息再提交订单';
                } else {
                    // 判断用户地址是否超出范围
                    $res = $this->logic->createExchangeOrder($this->user, $payPwd, $userAddress, $goodsInfo, false);
                    if (isset($res['status']) && $res['status'] != 1) {
                        throw new TpshopException('商学院兑换商品下单', 0, $res);
                    }
                    $userAddress = $res;
                }
            }
            // 订单信息
            $orderInfo = [
                'order_amount' => $orderAmount
            ];
            $return = [
                'user_address' => !empty($userAddress) ? [$userAddress] : [],
                'goods_info' => [$goodsInfo],
                'order_info' => $orderInfo
            ];
            return json(['status' => 1, 'msg' => '', 'result' => $return]);
        } catch (TpshopException $tpe) {
            return json($tpe->getErrorArr());
        }
    }

    /**
     * 兑换订单记录
     * @return \think\response\Json
     */
    public function exchangeLog()
    {
        $limit = I('limit', 10);
        $data = $this->logic->getExchangeLog($limit, $this->user);
        return json(['status' => 1, 'msg' => '', 'result' => $data]);
    }
}
