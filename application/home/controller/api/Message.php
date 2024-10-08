<?php

namespace app\home\controller\api;


use app\common\logic\UsersLogic;
use app\common\logic\ArticleLogic;
use app\common\logic\MessageLogic;

class Message extends Base
{
    /**
     * 消息通知数量
     * @return \think\response\Json
     */
    public function messageNum()
    {
        $messageNum = 0;
        // 获取系统消息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount($this->user_id);
        $messageNum += $user_message_count;
        // 获取活动消息的数量
        $articleLogic = new ArticleLogic();
        $user_article_count = $articleLogic->getUserArticleCount($this->user_id);
        $messageNum += $user_article_count;
        return json(['status' => 1, 'result' => ['message_num' => $messageNum]]);
    }

    /**
     * 公告列表
     * @return \think\response\Json
     */
    public function announce()
    {
        $messageLogic = new MessageLogic();
        $messageNotice = $messageLogic->getUserMessageNotice($this->user, '', true);
        $return = ['list' => $messageNotice];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 消息中心
     * @return \think\response\Json
     */
    public function center()
    {
        $return = [];
        // 活动消息
        $articleLogic = new ArticleLogic();
        $messageNotice = $articleLogic->getUserArticleNotice($this->user, 0);
        $return[] = [
            'id' => 1,
            'title' => '活动消息',
            'message' => !empty($messageNotice[0]) ? $messageNotice[0]['title'] : '',
            'num' => $articleLogic->getUserArticleCount($this->user_id) . ''
        ];
        // 系统消息
        $messageLogic = new MessageLogic();
        $messageNotice = $messageLogic->getUserMessageNotice($this->user, 0);
        $return[] = [
            'id' => 2,
            'title' => '系统消息',
            'message' => !empty($messageNotice[0]) ? $messageNotice[0]['message'] : '',
            'num' => $messageLogic->getUserMessageCount($this->user_id) . ''
        ];
        // 常见问题
        $return[] = [
            'id' => 3,
            'title' => '常见问题',
            'message' => '这里或许可以寻找到你所需要的答案',
            'num' => '0'
        ];
        // 在线客服
//        $return[] = [
//            'id' => 4,
//            'title' => '在线客服',
//            'message' => '客服服务时间为：周一到周五9:00—18:00',
//            'num' => '0'
//        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 消息列表
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function messageList()
    {
        $type = I('type', 1);
        $nowDate = date('Y-m-d', time());
        switch ($type) {
            case 1:
                // 活动消息
                $userLogic = new UsersLogic();
                $articleLogic = new ArticleLogic();
                $messageNotice = $articleLogic->getUserArticleNotice($this->user);
                $message = [];
                foreach ($messageNotice as $value) {
                    $publishTime = date('Y' . '年' . 'm' . '月' . 'd' . '日' . ' H:i', $value['publish_time']);
                    if (date('Y-m-d', $value['publish_time']) == date('Y-m-d', strtotime($nowDate))) {
                        // 今天
                        $publishTime = date('H:i', $value['publish_time']);
                    } elseif (date('Y-m-d', $value['publish_time']) == date('Y-m-d', strtotime($nowDate) - 3600 * 24)) {
                        // 昨天
                        $publishTime = '昨天 ' . date('H:i', $value['publish_time']);
                    } elseif (date('Y-m-d', $value['publish_time']) == date('Y-m-d', strtotime($nowDate) - 3600 * 24 * 2)
                        || date('Y-m-d', $value['publish_time']) == date('Y-m-d', strtotime($nowDate) - 3600 * 24 * 3)) {
                        // 前天 大前天
                        $publishTime = getWeekDay($value['publish_time']) . date('H:i', $value['publish_time']);
                    }
                    $message[] = [
                        'message_id' => $value['article_id'],
                        'title' => $value['title'],
                        'publish_time' => $publishTime,
                        'finish_time' => !empty($value['finish_time']) ? date('Y.m.d', $value['finish_time']) . '结束' : '',
                        'desc' => $value['description'],
                        'cover_pic' => $value['thumb'],
                        'message_url' => SITE_URL . '/#/news/app_news_particulars?article_id=' . $value['article_id']
                    ];
                    // 设置已读
                    $userLogic->setArticleForRead($value['article_id'], $this->user);
                }
                break;
            case 2:
                // 系统消息
                $userLogic = new UsersLogic();
                $messageLogic = new MessageLogic();
                $messageNotice = $messageLogic->getUserMessageNotice($this->user);
                $message = [];
                foreach ($messageNotice as $value) {
                    $publishTime = date('Y' . '年' . 'm' . '月' . 'd' . '日' . ' H:i', $value['send_time']);
                    if (date('Y-m-d', $value['send_time']) == date('Y-m-d', strtotime($nowDate))) {
                        // 今天
                        $publishTime = date('H:i', $value['send_time']);
                    } elseif (date('Y-m-d', $value['send_time']) == date('Y-m-d', strtotime($nowDate) - 3600 * 24)) {
                        // 昨天
                        $publishTime = '昨天 ' . date('H:i', $value['send_time']);
                    } elseif (date('Y-m-d', $value['send_time']) == date('Y-m-d', strtotime($nowDate) - 3600 * 24 * 2)
                        || date('Y-m-d', $value['send_time']) == date('Y-m-d', strtotime($nowDate) - 3600 * 24 * 3)) {
                        // 前天 大前天
                        $publishTime = getWeekDay($value['send_time']) . date('H:i', $value['send_time']);
                    }
                    $message[] = [
                        'message_id' => $value['message_id'],
                        'title' => $value['title'],
                        'publish_time' => $publishTime,
                        'finish_time' => '',
                        'desc' => $value['message'],
                        'cover_pic' => '',
                        'message_url' => ''
                    ];
                    // 设置已读
                    $userLogic->setMessageForRead(0, $value['message_id'], $this->user);
                }
                break;
            case 3:
                // 常见问题
                $questionCate = M('question_cate')->where(['is_show' => 1])->order('sort')->select();
                $message = [];
                foreach ($questionCate as $cate) {
                    $questionList = M('article')->where(['cat_id' => 81, 'extend_cate_id' => $cate['id'], 'is_open' => 1])
                        ->order('extend_sort')->field('article_id message_id, title, content, relate_article_id')->select();
                    $dataList = [];
                    foreach ($questionList as $list) {
                        $dataList[] = [
                            'message_id' => $list['message_id'],
                            'title' => $list['title'],
                            'content' => htmlspecialchars_decode($list['content']),
                            'relate_url' => !empty($list['relate_article_id']) ? SITE_URL . '/#/news/app_news_particulars?article_id=' . $list['relate_article_id'] : '',
                        ];
                    }
                    $message[] = [
                        'cate_id' => $cate['id'],
                        'cate_name' => $cate['name'],
                        'message_list' => $dataList
                    ];
                }
                break;
            default:
                return json(['status' => 0, 'msg' => '类型错误']);
        }
        $return = [
            'list' => $message
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 消息详情
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function info()
    {
        $messageId = I('message_id', '');
        if (!$messageId) {
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        // 设置已读
        $userLogic = new UsersLogic();
        $userLogic->setArticleForRead($messageId, $this->user);
        // 内容
        $article = M('article')->where(['article_id' => $messageId, 'is_open' => 1])->field('article_id message_id, title, content')->find();
        $article['app_content'] = htmlspecialchars_decode($article['content']);
        $return = $article;
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 浮窗消息列表
     * @return \think\response\Json
     */
    public function floatMessage()
    {
        $position = I('position', 1);
        $page = I('p', 1);
        switch ($position) {
            case 1:
                /*
                 * 商品详情
                 */
                $goodsId = I('goods_id', '');
                $where = [
                    'og.goods_id' => $goodsId,
                    'o.order_status' => ['IN', [2, 4, 6]],
                    'u.head_pic' => ['NEQ', '']
                ];
                $userInfo = M('order_goods og')
                    ->join('order o', 'o.order_id = og.order_id')
                    ->join('users u', 'u.user_id = o.user_id')
                    ->where($where)->group('o.user_id')
                    ->limit(10 * ($page - 1) . ',' . 10)
                    ->order('o.add_time DESC')
                    ->field('u.nickname, u.user_name, u.head_pic, og.goods_num')->select();
                if ($page != 1 && empty($userInfo)) {
                    $page = 1;
                    $userInfo = M('order_goods og')
                        ->join('order o', 'o.order_id = og.order_id')
                        ->join('users u', 'u.user_id = o.user_id')
                        ->where($where)->group('o.user_id')
                        ->limit(10 * ($page - 1) . ',' . 10)
                        ->order('o.add_time DESC')
                        ->field('u.nickname, u.user_name, u.head_pic, og.goods_num')->select();
                } elseif (empty($userInfo)) {
                    $page = 0;
                }
                $returnData = [];
                foreach ($userInfo as $user) {
                    $userName = $user['nickname'] ?? $user['user_name'];
                    $returnData[] = [
                        'head_pic' => $user['head_pic'],
                        'title' => $userName . '买了' . $user['goods_num'] . '件该商品'
                    ];
                }
                $return = [
                    'next_page' => $page + 1,
                    'list' => $returnData
                ];
                break;
            default:
                return json(['status' => 0, 'msg' => '位置错误']);
        }
        return json(['status' => 1, 'result' => $return]);
    }
}
