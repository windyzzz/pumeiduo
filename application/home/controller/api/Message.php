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
        // 获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount($this->user_id);
        $messageNum += $user_message_count;
        // 获取用户活动信息的数量
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
        $announceList = M('announce')->where(['is_open' => 1])
            ->limit(0, 3)->order('create_time DESC')
            ->field('id, title, type, goods_id, item_id')->select();
        $return = ['list' => $announceList];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 消息中心
     * @return \think\response\Json
     */
    public function center()
    {
        // 活动消息
        $articleLogic = new ArticleLogic();
        $messageNotice = $articleLogic->getUserArticleNotice($this->user);
        $activity = [
            'id' => 1,
            'title' => '活动消息',
            'message' => !empty($messageNotice[0]) ? $messageNotice[0]['title'] : ''
        ];
        // 系统消息
        $messageLogic = new MessageLogic();
        $messageNotice = $messageLogic->getUserMessageNotice($this->user);
        $system = [
            'id' => 2,
            'title' => '系统消息',
            'message' => !empty($messageNotice[0]) ? $messageNotice[0]['message'] : ''
        ];
        // 常见问题
        $question = [
            'id' => 3,
            'title' => '常见问题',
            'message' => '这里或许可以寻找到你所需要的答案'
        ];
        // 在线客服
//        $service = [
//            'id' => 4,
//            'title' => '在线客服',
//            'message' => '客服服务时间为：周一到周五9:00—18:00'
//        ];
        $return = [
            $activity,
            $system,
            $question
        ];
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
                $articleLogic = new ArticleLogic();
                $messageNotice = $articleLogic->getUserArticleNotice($this->user);
                $message = [];
                foreach ($messageNotice as $value) {
                    $publishTime = date('m' . '月' . 'd' . '日' . ' H:i', $value['publish_time']);
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
                        'message_url' => SITE_URL . '/#/news/news_particulars?article_id=' . $value['article_id']
                    ];
                }
                break;
            case 2:
                // 系统消息
                $userLogic = new UsersLogic();
                $messageLogic = new MessageLogic();
                $messageNotice = $messageLogic->getUserMessageNotice($this->user);
                $message = [];
                foreach ($messageNotice as $value) {
                    $publishTime = date('m' . '月' . 'd' . '日' . ' H:i', $value['send_time']);
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
                        'title' => '',
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
                        ->order('extend_sort')->field('article_id message_id, title, content')->select();
                    $message[] = [
                        'cate_id' => $cate['id'],
                        'cate_name' => $cate['name'],
                        'message_list' => $questionList,
                        'message_url' => ''
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
        $article = M('article')->where(['article_id' => $messageId, 'is_open' => 1])->field('article_id message_id, title, app_content')->find();
        $return = $article;
        return json(['status' => 1, 'result' => $return]);
    }
}