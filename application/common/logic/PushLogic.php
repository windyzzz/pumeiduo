<?php

namespace app\common\logic;

use app\common\logic\Token as TokenLogic;

require_once './vendor/jpush/jpush/autoload.php';

class PushLogic
{
    private $jpush = null;

    public function __construct()
    {
        $config = M('config')->field('name,value')->where('name', 'IN', 'jpush_app_key,jpush_master_secret')->select();
        foreach ($config as $v) {
            $c[$v['name']] = $v['value'];
        }
        if ($c['jpush_app_key'] && $c['jpush_master_secret']) {
            $this->jpush = new \JPush\Client($c['jpush_app_key'], $c['jpush_master_secret']);
        }
    }

    /**
     * 用户绑定push_id
     * @param $userId
     * @param $pushId
     * @return bool
     */
    public function bindPushId($userId, $pushId)
    {
        // 查找push_id是否已绑定
        $user = M('users')->where(['push_id' => $pushId])->find();
        if ($user) {
            if ($userId == $user['user_id']) {
                // 绑定用户和当前用户相同
                return true;
            } else {
                // 更新这个push_id的绑定用户
                M('users')->where(['user_id' => $userId])->update(['push_id' => $pushId]);
                // 清除原本用户push_id
                M('users')->where(['user_id' => $user['user_id']])->update(['push_id' => 0]);
            }
        } else {
            M('users')->where(['user_id' => $userId])->update(['push_id' => $pushId]);
        }
        return true;
    }

    /**
     * 用户绑定push_tag
     * @param $user
     * @return array
     */
    public function bindPushTag($user)
    {
        switch ($user['distribut_level']) {
            case 1:
                $levelTag = 'member';
                break;
            case 2:
                $levelTag = 'vip';
                break;
            case 3:
                $levelTag = 'svip';
                break;
            default:
                return ['status' => 1];
        }
        $userTag = explode(',', $user['push_tag']); // 用户推送tag，顺序第一个为等级
        if (in_array($levelTag, $userTag)) {
            // 不需要更新
            return ['status' => 1];
        } else {
            // 需要更新
            $newTag = [];
            foreach ($userTag as $k => $tag) {
                switch ($k) {
                    case 0:
                        $newTag[] = $levelTag;
                        break;
                    default:
                        $newTag[] = $tag;
                }
            }
            $newTag = implode($newTag, ',');
            M('users')->where(['user_id' => $user['user_id']])->update(['push_tag' => $newTag]);
            return ['status' => 2];
        }
    }

    /**
     * 推送消息
     * @param array $data 标题内容数据
     * @param array $extra 点击处理数据
     * @param int $all 1向所有用户发送 0向指定用户发送
     * @param array $push_ids
     * @param array $push_tags
     * @param string $alias
     * @return array
     */
    public function push($data, $extra = [], $all = 0, $push_ids = [], $push_tags = [], $alias = '')
    {
        if ($push_ids && is_array($push_ids)) {
            foreach ($push_ids as $k => $p) {
                if (empty($p)) {
                    unset($push_ids[$k]);
                }
            }
            if (empty($push_ids)) {
                return ['status' => 0, 'msg' => '用户的推送ID无效'];
            }
        }
        if ($push_tags && is_array($push_tags)) {
            foreach ($push_tags as $k => $p) {
                if (empty($p)) {
                    unset($push_tags[$k]);
                }
            }
            if (empty($push_tags)) {
                return ['status' => 0, 'msg' => '用户的推送TAG无效'];
            }
        }
        if (!$this->jpush) {
            return ['status' => 0, 'msg' => '推送初始化失败'];
        }
        if (!$all && empty($push_ids) && empty($push_tags) && empty($alias)) {
            return ['status' => 0, 'msg' => '非全体推送时没有指定用户标识！'];
        }
        $push = $this->jpush->push()->setPlatform('all');
        if ($all) {
            // 全体发送
            $push = $push->addAllAudience();
        } else {
            if (!empty($push_ids)) {
                $push = $push->addRegistrationId($push_ids);
            }
            if (!empty($push_tags)) {
                $push = $push->addTag($push_tags);
            }
            if (!empty($alias)) {
                $push = $push->addAlias($alias);
            }
        }
        $push = $push
            ->iosNotification(
                [
                    'title' => $data['title'],
                    'body' => $data['desc']
                ],
                [
                    'sound' => '1',
                    'badge' => 0,   // ios角标数
                    'content-available' => true,
                    'category' => 'JPush',
                    'extras' => $extra
                ]
            )->androidNotification(
                $data['desc'],
                [
                    'title' => $data['title'],
                    'extras' => $extra,
                ]
            )->options([
                'apns_production' => true   // 生成环境
            ]);
        try {
            $response = $push->send();
            if (200 != $response['http_code']) {
                return ['status' => -1, 'msg' => "http错误码:{$response['http_code']}", 'result' => $response];
            }
            return ['status' => 1, 'msg' => '已推送', 'result' => $response];
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            return ['status' => 0, 'msg' => $e->getMessage()];
        } catch (\JPush\Exceptions\APIRequestException $e) {
            return ['status' => 0, 'msg' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }
}
