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
        $timeOut = Db::name('users')->where(['token' => $token])->value('time_out');
        if (!isset($timeOut)) return ['status' => -2, 'msg' => 'token参数错误'];   // 通常是账号另一个端登录使得token更改，后面要改提示账号已在另一个地方登录
        if ($timeOut != 0) {
            if (time() - $timeOut > 0) {
                return ['status' => -1, 'msg' => '请重新登录'];
            }
            $newTimeOut = time() + (config('redis_time'));
            Db::name('users')->where(['token' => $token])->setField(['time_out' => $newTimeOut]);
        }
        // 是否有用户信息缓存
        $redis = new Redis();
        if ($redis->has('user_' . $token)) {
            $user = $redis->get('user_' . $token);
        } else {
            $user = Db::name('users')->where(['token' => $token])->find();
            $redis->set('user_' . $token, $user, config('redis_time'));
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