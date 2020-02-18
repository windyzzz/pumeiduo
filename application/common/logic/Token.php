<?php

namespace app\common\logic;


use think\cache\driver\Redis;
use think\Db;

class Token
{
    /**
     * 设置用户Token
     * @return string
     */
    public static function setToken()
    {
        $token = md5(uniqid(md5(microtime(true)), true));
        $token = sha1($token);
        return $token;
    }

    /**
     * 验证用户Token
     * @param $token
     * @return array
     */
    public static function checkToken($token)
    {
        $userInfo = Db::name('users')->where(['token' => $token])->field('is_lock, time_out, is_cancel')->find();
        if (!isset($userInfo)) return ['status' => -999, 'msg' => '账号已在另一个地方登录'];
        if ($userInfo['is_lock'] == 1) return ['status' => 0, 'msg' => '账号已冻结'];
        if ($userInfo['is_cancel'] == 1) return ['status' => 0, 'msg' => '账号已注销'];
        if ($userInfo['time_out'] != 0 && time() - $userInfo['time_out'] > 0) return ['status' => -999, 'msg' => '请重新登录'];
        // 是否有用户信息缓存
        $redis = new Redis();
        if ($redis->has('user_' . $token)) {
            $user = $redis->get('user_' . $token);
        } else {
            $user = Db::name('users')->where(['token' => $token])->find();
            $redis->set('user_' . $token, $user, config('REDIS_TIME'));
        }
        return ['status' => 1, 'user' => $user];
    }

    /**
     * 判断是session取值还是redis取值判
     * @param $name
     * @param $token
     * @return mixed|null
     */
    public static function getValue($name, $token)
    {
        if (session($name)) {
            return session($name);
        }
        $redis = new Redis();
        if ($redis->has($name . '_' . $token)) {
            return $redis->get($name . '_' . $token);
        }
        return null;
    }

    /**
     * 更新session缓存与redis缓存
     * @param $name
     * @param $token
     * @param $value
     * @param $time
     * @return bool
     */
    public static function updateValue($name, $token, $value, $time)
    {
        // 更新session
        session($name, $value);
        // 更新redis
        $redis = new Redis();
        $redis->set($name . '_' . $token, $value, $time - time());
        return true;
    }

    /**
     * 判断是session取值还是缓存取值
     * @param $name
     * @param $token
     * @return mixed|null
     */
    public static function getCache($name, $token)
    {
        if (session($name)) {
            return session($name);
        }
        if (S($name . '_' . $token)) {
            return S($name . '_' . $token);
        }
        return null;
    }
}