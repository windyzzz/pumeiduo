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
     * @param bool $isFirst
     * @return bool
     */
    public function bindPushTag($user, $isFirst = true)
    {
        switch ($user['distribut_level']) {
            case 1:
                $tag = 'member';
                break;
            case 2:
                $tag = 'vip';
                break;
            case 3:
                $tag = 'svip';
                break;
            default:
                return true;
        }
        if ($isFirst) {
            // 首次绑定
            if (!empty($user['push_tag'])) {
                return true;
            } else {
                M('users')->where(['user_id' => $user])->update(['push_tag' => $tag]);
            }
        } else {
            M('users')->where(['user_id' => $user])->update(['push_tag' => $tag]);
        }
    }

    /**
     * 推送消息
     * @param array $data 发送的数据
     * @param int $all 1向所有用户发送 0向指定用户发送
     * @param array $push_ids
     * @return array
     */
    public function push($data, $all = 0, $push_ids = [])
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
        if (!$this->jpush) {
            return ['status' => 0, 'msg' => '推送初始化失败'];
        } elseif (!$all && !$push_ids) {
            return ['status' => 0, 'msg' => '个体推送时没有指定用户！'];
        }
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $push = $this->jpush->push()->setPlatform('all')->message($data);
        if ($all) {
            $push = $push->addAllAudience();
        } else {
            $push = $push->addRegistrationId($push_ids);
        }
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
