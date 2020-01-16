<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

use think\Db;
use think\Model;
use think\Page;

/**
 * Class OrderGoodsLogic.
 */
class ArticleLogic extends Model
{
    protected $tableName = 'article';
    protected $_validate = [];
    protected $article_category_num = 6;

    /**
     * 获取用户的消息个数.
     *
     * @return array
     */
    public function getUserArticleCount($userId = '')
    {
        if (!$userId) {
            $userId = session('user')['user_id'];
        }
        $this->checkPublicArticle();
        $user_system_article_no_read_where = [
            'um.user_id' => $userId,
            'um.status' => 0,
            'm.is_open' => 1,
        ];
        $user_system_article_no_read = DB::name('user_article')
            ->alias('um')
            ->join('__ARTICLE__ m', 'um.article_id = m.article_id', 'LEFT')
            ->where($user_system_article_no_read_where)
            ->count();

        return $user_system_article_no_read;
    }

    /**
     * 获取用户的活动消息.
     *
     * @return array
     */
    public function getUserSellerArticle()
    {
        $user_info = session('user');
        $user_system_article_no_read_where = [
            'user_id' => $user_info['user_id'],
            'status' => 0,
            'm.category' => ['<>', 0],
        ];
        $user_system_article_no_read = Db::name('user_article')
            ->alias('um')
            ->field('um.rec_id,um.user_id,um.category,um.article_id,um.status,m.send_time,m.type,m.article')
            ->join('__ARTICLE__ m', 'um.article_id = m.article_id', 'LEFT')
            ->where($user_system_article_no_read_where)
            ->select();

        return $user_system_article_no_read;
    }

    /**
     * 获取用户的全部消息.
     *
     * @return array
     */
    public function getUserAllArticle()
    {
        $this->checkPublicArticle();
        $user_info = session('user');
        $user_system_article_no_read_where = [
            'user_id' => $user_info['user_id'],
            'status' => 0,
        ];
        $user_system_article_no_read = Db::name('user_article')
            ->alias('um')
            ->field('um.rec_id,um.user_id,um.category,um.article_id,um.status,m.send_time,m.type,m.article')
            ->join('__ARTICLE__ m', 'um.article_id = m.article_id', 'LEFT')
            ->where($user_system_article_no_read_where)
            ->select();

        return $user_system_article_no_read;
    }

    /**
     * 获取用户的系统消息.
     *
     * @return array
     */
    public function getUserArticleNotice($user_info = [])
    {
        $this->checkPublicArticle($user_info);
        if (empty($user_info)) {
            $user_info = session('user');
        }
        $user_system_article_no_read_where = [
            'user_id' => $user_info['user_id'],
            'status' => ['in', [0, 1]],
            'm.is_open' => 1,
        ];
        $count = Db::name('user_article')
            ->alias('um')
            ->join('__ARTICLE__ m', 'um.article_id = m.article_id', 'LEFT')
            ->where($user_system_article_no_read_where)
            ->count();
        $Page = new Page($count, 10);
        $user_system_article_no_read = Db::name('user_article')
            ->alias('um')
            ->field('um.rec_id,um.user_id,um.category,um.article_id,um.status,m.publish_time,m.finish_time,m.title,m.description,m.link,m.thumb')
            ->join('__ARTICLE__ m', 'um.article_id = m.article_id', 'LEFT')
            ->where($user_system_article_no_read_where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('publish_time desc')
            ->select();

        return $user_system_article_no_read;
    }

    /**
     * 查询系统全体消息，如有将其插入用户信息表.
     *
     * @author dyr
     * @time 2016/09/01
     */
    public function checkPublicArticle($user_info = [])
    {
        $cat_id = I('cat_id', 1);
        if (empty($user_info)) {
            $user_info = session('user');
        }
        if (empty($user_info)) return true;
        $user_article = Db::name('user_article')->where(['user_id' => $user_info['user_id'], 'category' => $cat_id])->select();
        $article_where = [
            'cat_id' => $cat_id,
            'distribut_level' => ['elt', $user_info['distribut_level']],
//            'publish_time' => ['elt', time()],
        ];
        if (!empty($user_article)) {
            $user_id_array = get_arr_column($user_article, 'article_id');
            $article_where['article_id'] = ['NOT IN', $user_id_array];
        }
        $user_system_public_no_read = Db::name('article')->field('article_id')->where($article_where)->select();
        foreach ($user_system_public_no_read as $key) {
            DB::name('user_article')->insert(['user_id' => $user_info['user_id'], 'article_id' => $key['article_id'], 'category' => $cat_id, 'status' => 0]);
        }
    }

    /**
     * 获取用户的全部关注的消息.
     *
     * @return array
     */
    public function getUserAllMaskArticle()
    {
        $this->checkPublicArticle();
        $user_info = session('user');

        $categorys = $categorys = $this->getUserArticleCategory($user_info);
        if (empty($categorys)) {
            return [];
        }

        $user_system_article_no_read_where = [
            'user_id' => $user_info['user_id'],
            'status' => 0,
            'um.category' => ['in', $categorys],
        ];
        $user_system_article_no_read = Db::name('user_article')
            ->alias('um')
            ->field('um.rec_id,um.user_id,um.category,um.article_id,um.status,m.send_time,m.type,m.article')
            ->join('__ARTICLE__ m', 'um.article_id = m.article_id', 'LEFT')
            ->where($user_system_article_no_read_where)
            ->select();

        return $user_system_article_no_read;
    }

    /**
     * 获取用户关注的消息类型.
     *
     * @param type $user
     * @param type $filter 是否强制过滤
     *
     * @return int
     */
    public function getUserArticleCategory($user, $filter = 0)
    {
        $categorys = [];
        for ($i = 0; $i < $this->article_category_num; ++$i) {
            //目前限定为四种类型,过滤掉 '3商品提醒'、'5商城好店'
            if ($filter && (3 == $i || 5 == $i)) {
                continue;
            }
            if ($user['article_mask'] & (1 << $i)) {
                $categorys[] = $i;
            }
        }

        return $categorys;
    }

    /**
     * 获取用户的每个类型最新一条消息.
     *
     * @return array
     */
    public function getUserPerTypeLastArticle()
    {
        $this->checkPublicArticle();
        $user = session('user');

        if ($user) {
            $categorys = $this->getUserArticleCategory($user, 1);
            if (empty($categorys)) {
                return [];
            }
        } else {
            $categorys = [0, 2, 3, 5]; //0系统消息，1物流通知，2优惠促销，3商品提醒，4我的资产，5商城好店
        }

        $data = [];
        foreach ($categorys as $c) {
            $query = Db::query('SELECT m.category,m.article_id,um.status,m.send_time,m.type,m.data FROM __PREFIX__article m '
                . 'INNER JOIN __PREFIX__user_article um ON (um.article_id=m.article_id AND um.user_id = ?) '
                . 'WHERE m.type = 0 AND m.category = ? AND m.data!=""  '
                . 'UNION (SELECT m.category,m.article_id, 1 AS status,m.send_time,m.type,m.data FROM __PREFIX__article m '
                . 'WHERE m.type = 1 AND m.category = ? AND m.data!="") '
                . 'ORDER BY send_time DESC LIMIT 1', [$user['user_id'], $c, $c]);

            if (!empty($query[0])) {
                $query = $query[0];
                $msgdata = unserialize($query['data']);
                $query['article'] = $msgdata['discription'];
                unset($query['data']);
                $data[] = $query;
            }
        }

        return $data;
    }

    /**
     * 获取具体类型的消息列表.
     *
     * @param type $user_id
     * @param type $category
     * @param type $p
     *
     * @return type
     */
    public function getUserArticleList($user_id, $category, $p = 1)
    {
        if ($p < 1) {
            $p = 1;
        }
        $p = ($p - 1) * 15;

        $data = Db::query('SELECT m.category,m.article_id,um.status,m.send_time,m.type,m.data FROM __PREFIX__article m '
            . 'INNER JOIN __PREFIX__user_article um ON (um.article_id=m.article_id AND um.user_id = ?) '
            . 'WHERE m.type = 0 AND m.category = ? AND m.data!=""  '
            . 'UNION (SELECT m.category,m.article_id, 1 AS status,m.send_time,m.type,m.data FROM __PREFIX__article m '
            . 'WHERE m.type = 1 AND m.category = ? AND m.data!="") '
            . 'ORDER BY send_time DESC LIMIT ?,15', [$user_id, $category, $category, $p]);

        foreach ($data as &$d) {
            $d['data'] = unserialize($d['data']);
        }

        return $data;
    }

    /**
     * 创建推送消息.
     *
     * @param int $type：0系统消息，1物流通知，2优惠促销，3商品提醒，4我的资产，5商城好店
     */
    public function createPushMsg($type, $data)
    {
        $title = isset($data['title']) ? $data['title'] : '';
        $order_id = isset($data['order_id']) ? $data['order_id'] : 0;
        $discription = isset($data['discription']) ? $data['discription'] : '';
        $goods_id = isset($data['goods_id']) ? $data['goods_id'] : 0;
        $change_type = isset($data['change_type']) ? $data['change_type'] : 0;
        $money = isset($data['money']) ? $data['money'] : 0;
        $cover = isset($data['cover']) ? $data['cover'] : '';

        $logic = new ArticleLogic();
        if (0 === $type) {
            $data = $logic->createSystemMsg($type, $title, $discription);
        } elseif (1 === $type) {
            $data = $logic->createShippingMsg($type, $title, $order_id, $goods_id, $discription);
        } elseif (2 === $type) {
            $data = $logic->createPromotionMsg($type, $title, $goods_id, $cover, $discription);
        } elseif (4 === $type) {
            $data = $logic->createAssetMsg($type, $change_type, $title, $discription, $money);
        } else {
            $data = [];
        }

        return $data;
    }

    /**
     * 推送物流消息.
     *
     * @param type $title
     * @param type $order_id
     * @param type $goods_id
     * @param type $discription
     */
    public function createShippingMsg($t, $title, $order_id, $goods_id, $discription = '')
    {
        $row = M('order')->field('order_sn, shipping_name')->where('order_id', $order_id)->find();
        if (!$row) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }

        $discription = $discription ?: "您的订单已炼货完毕，待出库交付{$row['shipping_name']},"
            . "运单号为{$row['order_sn']}";
        $title = $title ?: '发货提醒';
        $data = [
            'category' => $t,
            'data' => [
                'title' => $title,
                'post_time' => time(),
                'order_id' => $order_id,
                'discription' => $discription,
                'goods_id' => $goods_id,
            ],
        ];

        return $data;
    }

    /**
     * 推送促销消息.
     *
     * @param type $title
     * @param type $goods_id
     * @param type $cover
     * @param type $discription
     */
    public function createPromotionMsg($t, $title, $goods_id, $cover = '', $discription = '')
    {
        $data = [
            'category' => $t,
            'data' => [
                'title' => $title,
                'post_time' => time(),
                'cover' => $cover,
                'discription' => $discription,
                'goods_id' => $goods_id,
            ],
        ];

        return $data;
    }

    /**
     * 推送资金变动/我的资产消息.
     *
     * @param type $change_type 1:积分,2:余额,3:优惠券
     * @param type $title
     * @param type $discription
     * @param type $money 优惠券类型通知时该值才大于0
     */
    public function createAssetMsg($t, $change_type, $title, $discription = '', $money = 0)
    {
        $data = [
            'category' => $t,
            'data' => [
                'change_type' => $change_type,
                'title' => $title,
                'post_time' => time(),
                'discription' => $discription,
                'money' => $money,
            ],
        ];

        return $data;
    }

    /**
     * 推送系统/服务消息.
     *
     * @param type $title
     * @param type $discription
     */
    public function createSystemMsg($t, $title, $discription = '')
    {
        $data = [
            'category' => $t,
            'data' => [
                'title' => $title,
                'post_time' => time(),
                'discription' => $discription,
            ],
        ];

        return $data;
    }

    /**
     * 发送消息.
     *
     * @param type $msg article表字段必要的数据
     * @param type $push_data 推送的消息主体
     * @param array $user_ids 用户的id集
     *
     * @return type
     */
    public function sendArticle($msg, $push_data, $user_ids = [])
    {
        //创建推送消息
        $push_data = $this->createPushMsg($msg['category'], $push_data);
        if (!$push_data) {
            return ['status' => -1, 'msg' => '推送的内容不能为空'];
        }

        if (is_string($user_ids)) {
            $user_ids = explode(',', $user_ids);
        }
        //推送消息
        $push_ids = [];
        if (!$msg['type']) {
            $push_ids = M('users')->where(['user_id' => ['IN', $user_ids]])->column('push_id');
        }
        $push = new PushLogic();
        $res = $push->push($push_data, $msg['type'], $push_ids);
        if (1 !== $res['status']) {
            return $res;
        }

        $article = [
            'admin_id' => isset($msg['admin_id']) ? $msg['admin_id'] : 0,
            'seller_id' => isset($msg['seller_id']) ? $msg['seller_id'] : 0,
            'category' => $msg['category'],
            'type' => $msg['type'],
            'article' => $push_data['data']['discription'],
            'send_time' => $push_data['data']['post_time'],
            'data' => serialize($push_data['data']),
        ];

        //推送成功才入库
        if (1 == $msg['type']) {
            M('Article')->add($article);
        } elseif (!empty($user_ids)) {
            $msg_id = M('Article')->add($article);
            foreach ($user_ids as $uid) {
                M('user_article')->add(['user_id' => $uid, 'article_id' => $msg_id, 'status' => 0, 'category' => $msg['category']]);
            }
        }

        return $res;
    }

    /**
     * 获取消息开关.
     *
     * @param type $mask 开关掩码
     *
     * @return type
     */
    public function getArticleSwitch($mask)
    {
        $notice[] = boolval($mask & (1 << 0)); //'system'
        $notice[] = boolval($mask & (1 << 1)); //'express'
        $notice[] = boolval($mask & (1 << 2)); //'promotion'
        $notice[] = boolval($mask & (1 << 3)); //'goods'
        $notice[] = boolval($mask & (1 << 4)); //'asset'
        $notice[] = boolval($mask & (1 << 5)); //'store'

        return $notice;
    }

    /**
     * 设置消息开关.
     *
     * @param type $type 开关类型
     * @param type $val开关值
     */
    public function setArticleSwitch($type, $val, $user)
    {
        if ($type > 5) {
            return ['status' => -1, 'msg' => '开关类型错误'];
        }

        if ($val) {
            $user['article_mask'] |= (1 << $type);
        } else {
            $user['article_mask'] &= ~(1 << $type);
        }
        M('users')->where('user_id', $user['user_id'])->save(['article_mask' => $user['article_mask']]);

        return ['status' => 1, 'msg' => '设置成功'];
    }

    /**
     * 设置消息为已读.
     */
    public function setArticleRead($user_id)
    {
        M('user_article')->where('user_id', $user_id)->save(['status' => 1]);
    }

    public function getArticleListByCatId($cat_id)
    {
        // $count = M('article')
        //     ->where('cat_id',$cat_id)
        //     ->where('publish_time','elt',time())
        //     ->where('is_open',1)
        //     ->count();
        // $Page = new Page($count,10);
        $article_list = M('article')
            ->where('cat_id', $cat_id)
            ->where('publish_time', 'elt', time())
            ->where('is_open', 1)
            // ->limit($Page->firstRow,$Page->listRows)
            ->select();

        return $article_list;
    }

    public function getCatListById($id)
    {
        $cat_list = M('article_cat')
            ->where('parent_id', $id)
            ->order('sort_order')
            ->select();
        return $cat_list;
    }

    public function getCatInfo($id)
    {
        $info = M('article_cat')->find($id);

        return $info;
    }

    public function getCatList()
    {
        return M('article_cat')->where([
            'parent_id' => 0,
//            'show_in_nav' => 1,
        ])->select();
    }

    /**
     * 递归--获取所有分类信息.
     *
     * @param $catList
     *
     * @return mixed
     */
    public function _getCatArticleList($catList)
    {
        foreach ($catList as $k => $v) {
            $catList[$k]['article_list'] = $this->getArticleListByCatId($v['cat_id']);
            $child = $this->getCatListById($v['cat_id']);
            if (!empty($child)) {
                $catList[$k]['child'] = $this->_getCatArticleList($child);
            } else {
                $catList[$k]['child'] = '';
            }
        }

        return $catList;
    }

    public function getCateListById($id)
    {
        if ($id == 2) {
            // 帮助中心
            $helpCenterCate = M('help_center_cate')->order('sort ASC')->select();
            $cateList = M('article_cat')->where('parent_id', $id)->where('extend_cate_id', 'not null')
                ->order('extend_cate_id ASC, extend_sort ASC')->select();
            $cate_list = [];
            foreach ($helpCenterCate as $k => $center) {
                $cat_list[$k] = [
                    'id' => $center['id'],
                    'name' => $center['name'],
                    'list' => []
                ];
                foreach ($cateList as $cate) {
                    if ($center['id'] == $cate['extend_cate_id']) {
                        $cate_list[$k]['list'][] = [
                            'cate_id' => $cate['cat_id'],
                            'cate_name' => $cate['cat_name'],
                            'cate_icon' => $cate['icon']
                        ];
                    }
                }
            }
        } else {
            $cate_list = M('article_cat')->where('parent_id', $id)->order('sort_order')->select();
        }
        return $cate_list;
    }

    public function getArticleListByCateId($cateId, $keyword = '')
    {
        $where = ['cat_id' => $cateId, 'is_open' => 1];
        $whereOr = [];
        if ($keyword) {
            $whereOr['title'] = ['like', '%' . $keyword . '%'];
            $whereOr['content'] = ['like', '%' . $keyword . '%'];
        }
        $articleList = M('article')->where($where)
            ->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            })->field('article_id, title')->select();
        return $articleList;
    }
}
