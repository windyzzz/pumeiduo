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
class MessageLogic extends Model
{
    protected $tableName = 'message';
    protected $_validate = [];
    protected $message_category_num = 6;

    /**
     * 获取用户的消息个数.
     *
     * @return array
     */
    public function getUserMessageCount($userId = '')
    {
        if (!$userId) {
            $userId = session('user')['user_id'];
        }
        $this->checkPublicMessage();
        $user_system_message_no_read_where = [
            'um.user_id' => $userId,
            'um.status' => 0,
            'm.title' => ['neq', ''],
            'm.send_time' => ['BETWEEN', [strtotime("-3 month"), time()]]  // 三个月内
        ];
        $user_system_message_no_read = DB::name('user_message')
            ->alias('um')
            ->join('__MESSAGE__ m', 'um.message_id = m.message_id', 'LEFT')
            ->where($user_system_message_no_read_where)
            ->count();

        return $user_system_message_no_read;
    }

    /**
     * 获取用户的活动消息.
     *
     * @return array
     */
    public function getUserSellerMessage()
    {
        $user_info = session('user');
        $user_system_message_no_read_where = [
            'user_id' => $user_info['user_id'],
            'status' => 0,
            'm.category' => ['<>', 0],
        ];
        $user_system_message_no_read = Db::name('user_message')
            ->alias('um')
            ->field('um.rec_id,um.user_id,um.category,um.message_id,um.status,m.send_time,m.type,m.message')
            ->join('__MESSAGE__ m', 'um.message_id = m.message_id', 'LEFT')
            ->where($user_system_message_no_read_where)
            ->select();

        return $user_system_message_no_read;
    }

    /**
     * 获取用户的全部消息.
     *
     * @return array
     */
    public function getUserAllMessage()
    {
        $this->checkPublicMessage();
        $user_info = session('user');
        $user_system_message_no_read_where = [
            'user_id' => $user_info['user_id'],
            'status' => 0,
        ];
        $user_system_message_no_read = Db::name('user_message')
            ->alias('um')
            ->field('um.rec_id,um.user_id,um.category,um.message_id,um.status,m.send_time,m.type,m.message')
            ->join('__MESSAGE__ m', 'um.message_id = m.message_id', 'LEFT')
            ->where($user_system_message_no_read_where)
            ->select();

        return $user_system_message_no_read;
    }

    /**
     * 获取用户的系统消息.
     *
     * @return array
     */
    public function getUserMessageNotice($user_info = [], $status = '', $isIndex = false)
    {
        $this->checkPublicMessage($user_info);
        if (empty($user_info)) {
            $user_info = session('user');
        }
        if (!empty($user_info)) {
            $user_system_message_no_read_where = [
                'user_id' => $user_info['user_id'],
                'm.category' => 0,
            ];
            if (!empty($status) || $status === 0) {
                $user_system_message_no_read_where['status'] = $status;
            } else {
                $user_system_message_no_read_where['status'] = ['in', [0, 1]];
            }
            $where['m.title'] = ['neq', ''];
            if ($isIndex) {
                $num = 3;
                $field = 'm.message_id id, m.title';
                $where['m.send_time'] = ['BETWEEN', [strtotime("-1 month"), time()]];  // 一个月内
            } else {
                $num = 10;
                $field = 'um.rec_id,um.user_id,um.category,um.message_id,um.status,m.send_time,m.type,m.title,m.message';
//                $where['m.send_time'] = ['BETWEEN', [strtotime("-3 month"), time()]];  // 三个月内
            }
            $count = Db::name('user_message')
                ->alias('um')
                ->join('__MESSAGE__ m', 'um.message_id = m.message_id', 'LEFT')
                ->where($user_system_message_no_read_where)
//                ->where($where)
                ->count();
            $Page = new Page($count, $num);
            $user_system_message_no_read = Db::name('user_message')
                ->alias('um')
                ->field($field)
                ->join('__MESSAGE__ m', 'um.message_id = m.message_id', 'LEFT')
                ->where($user_system_message_no_read_where)
//                ->where($where)
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->order('send_time desc')
                ->select();
            return $user_system_message_no_read;
        } else {
            $messageNotice = Db::name('message')->where([
                'type' => 1,
                'distribut_level' => 0,
                'title' => ['neq', ''],
                'send_time' => ['BETWEEN', [strtotime("-1 month"), time()]]  // 一个月内
            ])->field('message_id id, title')->limit(0, 3)->order('send_time DESC')->select();
            return $messageNotice;
        }
    }

    /**
     * 查询系统全体消息，如有将其插入用户信息表.
     *
     * @author dyr
     * @time 2016/09/01
     */
    public function checkPublicMessage($user_info = [])
    {
        if (empty($user_info)) {
            $user_info = session('user');
        }
        if (empty($user_info)) return true;
        $user_message = Db::name('user_message')->where(['user_id' => $user_info['user_id'], 'category' => 0])->select();
        $message_where = [
            'category' => 0,
            'type' => 1,
            'distribut_level' => ['elt', $user_info['distribut_level']],
//            'send_time' => ['BETWEEN', [strtotime("-1 month"), time()]]  // 一个月内
        ];
        if (!empty($user_message)) {
            $user_id_array = get_arr_column($user_message, 'message_id');
            $message_where['message_id'] = ['NOT IN', $user_id_array];
        }
        $user_system_public_no_read = Db::name('message')->field('message_id')->where($message_where)->select();
        foreach ($user_system_public_no_read as $key) {
            DB::name('user_message')->insert(['user_id' => $user_info['user_id'], 'message_id' => $key['message_id'], 'category' => 0, 'status' => 0]);
        }
    }

    /**
     * 获取用户的全部关注的消息.
     *
     * @return array
     */
    public function getUserAllMaskMessage()
    {
        $this->checkPublicMessage();
        $user_info = session('user');

        $categorys = $categorys = $this->getUserMessageCategory($user_info);
        if (empty($categorys)) {
            return [];
        }

        $user_system_message_no_read_where = [
            'user_id' => $user_info['user_id'],
            'status' => 0,
            'um.category' => ['in', $categorys],
        ];
        $user_system_message_no_read = Db::name('user_message')
            ->alias('um')
            ->field('um.rec_id,um.user_id,um.category,um.message_id,um.status,m.send_time,m.type,m.message')
            ->join('__MESSAGE__ m', 'um.message_id = m.message_id', 'LEFT')
            ->where($user_system_message_no_read_where)
            ->select();

        return $user_system_message_no_read;
    }

    /**
     * 获取用户关注的消息类型.
     *
     * @param type $user
     * @param type $filter 是否强制过滤
     *
     * @return int
     */
    public function getUserMessageCategory($user, $filter = 0)
    {
        $categorys = [];
        for ($i = 0; $i < $this->message_category_num; ++$i) {
            //目前限定为四种类型,过滤掉 '3商品提醒'、'5商城好店'
            if ($filter && (3 == $i || 5 == $i)) {
                continue;
            }
            if ($user['message_mask'] & (1 << $i)) {
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
    public function getUserPerTypeLastMessage()
    {
        $this->checkPublicMessage();
        $user = session('user');

        if ($user) {
            $categorys = $this->getUserMessageCategory($user, 1);
            if (empty($categorys)) {
                return [];
            }
        } else {
            $categorys = [0, 2, 3, 5]; //0系统消息，1物流通知，2优惠促销，3商品提醒，4我的资产，5商城好店
        }

        $data = [];
        foreach ($categorys as $c) {
            $query = Db::query('SELECT m.category,m.message_id,um.status,m.send_time,m.type,m.data FROM __PREFIX__message m '
                . 'INNER JOIN __PREFIX__user_message um ON (um.message_id=m.message_id AND um.user_id = ?) '
                . 'WHERE m.type = 0 AND m.category = ? AND m.data!=""  '
                . 'UNION (SELECT m.category,m.message_id, 1 AS status,m.send_time,m.type,m.data FROM __PREFIX__message m '
                . 'WHERE m.type = 1 AND m.category = ? AND m.data!="") '
                . 'ORDER BY send_time DESC LIMIT 1', [$user['user_id'], $c, $c]);

            if (!empty($query[0])) {
                $query = $query[0];
                $msgdata = unserialize($query['data']);
                $query['message'] = $msgdata['discription'];
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
    public function getUserMessageList($user_id, $category, $p = 1)
    {
        if ($p < 1) {
            $p = 1;
        }
        $p = ($p - 1) * 15;

        $data = Db::query('SELECT m.category,m.message_id,um.status,m.send_time,m.type,m.data FROM __PREFIX__message m '
            . 'INNER JOIN __PREFIX__user_message um ON (um.message_id=m.message_id AND um.user_id = ?) '
            . 'WHERE m.type = 0 AND m.category = ? AND m.data!=""  '
            . 'UNION (SELECT m.category,m.message_id, 1 AS status,m.send_time,m.type,m.data FROM __PREFIX__message m '
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

        $logic = new MessageLogic();
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
     * @param type $msg message表字段必要的数据
     * @param type $push_data 推送的消息主体
     * @param array $user_ids 用户的id集
     *
     * @return type
     */
    public function sendMessage($msg, $push_data, $user_ids = [])
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

        $message = [
            'admin_id' => isset($msg['admin_id']) ? $msg['admin_id'] : 0,
            'seller_id' => isset($msg['seller_id']) ? $msg['seller_id'] : 0,
            'category' => $msg['category'],
            'type' => $msg['type'],
            'message' => $push_data['data']['discription'],
            'send_time' => $push_data['data']['post_time'],
            'data' => serialize($push_data['data']),
        ];

        //推送成功才入库
        if (1 == $msg['type']) {
            M('Message')->add($message);
        } elseif (!empty($user_ids)) {
            $msg_id = M('Message')->add($message);
            foreach ($user_ids as $uid) {
                M('user_message')->add(['user_id' => $uid, 'message_id' => $msg_id, 'status' => 0, 'category' => $msg['category']]);
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
    public function getMessageSwitch($mask)
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
    public function setMessageSwitch($type, $val, $user)
    {
        if ($type > 5) {
            return ['status' => -1, 'msg' => '开关类型错误'];
        }

        if ($val) {
            $user['message_mask'] |= (1 << $type);
        } else {
            $user['message_mask'] &= ~(1 << $type);
        }
        M('users')->where('user_id', $user['user_id'])->save(['message_mask' => $user['message_mask']]);

        return ['status' => 1, 'msg' => '设置成功'];
    }

    /**
     * 设置消息为已读.
     */
    public function setMessageRead($user_id)
    {
        M('user_message')->where('user_id', $user_id)->save(['status' => 1]);
    }

    /**
     * 添加消息通知
     * @param $adminId
     * @param $title
     * @param $content
     * @param int $type 个体消息:0，全体消息：1
     * @param int $level 要发送的分销用户等级id，没限制0
     * @param int $category 系统消息：0，活动消息：1
     * @param int $userId
     * @return bool
     */
    public function addMessage($adminId, $title, $content, $type, $level, $category, $userId = 0)
    {
        $messageData = [
            'admin_id' => $adminId,
            'title' => $title,
            'message' => $content,
            'type' => $type,
            'distribut_level' => $level,
            'category' => $category,
            'send_time' => NOW_TIME,
            'data' => ''
        ];
        $messageId = M('message')->insertGetId($messageData);
        if ($messageId && $userId) {
            M('user_message')->add([
                'user_id' => $userId,
                'message_id' => $messageId
            ]);
        }
        return true;
    }
}
