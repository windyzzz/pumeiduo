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

use app\common\logic\Token as TokenLogic;
use app\common\model\UserAddress;
use think\cache\driver\Redis;
use think\Db;
use think\Log;
use think\Model;
use think\Page;
use think\Url;

/**
 * 分类逻辑定义
 * Class CatsLogic.
 */
class UsersLogic extends Model
{
    protected $user_id = 0;

    /**
     * 设置用户ID.
     *
     * @param $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    function apply_customs_cancel($user_id)
    {
        $apply_customs = M('apply_customs')->where(array('user_id' => $user_id))->find();
        if (!$apply_customs) {
            $this->error = '撤销失败';
            return false;
        } else if ($apply_customs && $apply_customs['status'] == 2) {
            $this->error = '您的申请已撤销';
            return false;
        } else if ($apply_customs && $apply_customs['status'] == 1) {
            $this->error = '您的申请已完成，不能再撤销';
            return false;
        }
        Db::startTrans();
        $bapply_customs = M('apply_customs')->where(array('user_id' => $user_id))->data(array('status' => 2, 'cancel_time' => NOW_TIME))->save();

        include_once "plugins/Tb.php";
        $TbLogic = new \Tb();
        $badd_tb_zx = $TbLogic->add_tb(1, 8, $apply_customs['id'], 0);
        if ($bapply_customs && $badd_tb_zx) {
            Db::commit();
            $this->error = '撤销成功';
            return true;
        } else {
            Db::rollback();
            $this->error = '撤销失败';
            return true;
        }


    }

    function check_apply_customs($user_id)
    {
        $apply_customs = M('apply_customs')->where(array('user_id' => $user_id))->find();
        if ($apply_customs && $apply_customs['status'] == 0) {
            $this->error = '您的申请在审核中，请等待';
            return false;
        } else if ($apply_customs && $apply_customs['status'] == 1) {
            $this->error = '审核完成';
            return false;
        }

        $distribut_level = M('users')->where(array('user_id' => $user_id))->getField('distribut_level');
        if ($distribut_level >= 3) {
            $this->error = '您已经是金卡';
            return false;
        }
        if ($distribut_level < 2) {
            $this->error = '您尚未有资格开通金卡会员';
            return false;
        }
        $count = M('users')->where(array('first_leader' => $user_id, 'distribut_level' => array('egt', 2)))->count();
        $apply_check_num = tpCache('basic.apply_check_num');
        if ($count < $apply_check_num) {
            $this->error = '您尚未有资格开通金卡会员';
            return false;
        }

        $this->error = '提交';
        return true;
    }

    function apply_customs($user_id, $data)
    {
        Db::startTrans();
        $check_apply_customs = $this->check_apply_customs($user_id);
        if (!$check_apply_customs) {
            Db::rollback();
            $this->error = $this->getError();
            return false;
        }
        if (!check_length($data['true_name'], 1, 12)) {
            Db::rollback();
            $this->error = '姓名填写错误';
            return false;
        }
        if (!check_id_card($data['id_card'])) {
            Db::rollback();
            $this->error = '身份证填写错误';
            return false;
        }
        if (!check_mobile($data['mobile'])) {
            Db::rollback();
            $this->error = '手机号填写错误';
            return false;
        }

        $invite_uid = M('Users')->where('user_id', $user_id)->getField('invite_uid');
        $referee_user_id = $this->nk($invite_uid, 3);
        if (!empty($data['referee_user_id']) && $data['referee_user_id'] !== $referee_user_id) {
            $this->error = '推荐人信息有误';
            return false;
        }

        $apply_customs = M('apply_customs')->where(array('user_id' => $user_id))->find();
        $add_data = array(
            'user_id' => $user_id,
            'true_name' => $data['true_name'],
            'id_card' => $data['id_card'],
            'mobile' => $data['mobile'],
            'add_time' => NOW_TIME,
            'referee_user_id' => $referee_user_id,
            'status' => 0,
            'success_time' => 0,
            'cancel_time' => 0,
        );

        if ($apply_customs) {
            $bapply_customs = M('apply_customs')->where(array('user_id' => $user_id))->data($add_data)->save();
            $id = $apply_customs['id'];
        } else {
            $id = $bapply_customs = M('apply_customs')->data($add_data)->add();
        }

        //通知仓储系统
        include_once "plugins/Tb.php";
        $TbLogic = new \Tb();
        $badd_tb_zx = $TbLogic->add_tb(1, 8, $id, 0);

        if ($bapply_customs && $badd_tb_zx) {
            Db::commit();
            $this->error = '提交成功，等待审核';
            return true;
        } else {
            Db::rollback();
            $this->error = '申请失败';
            return false;
        }
    }

    function nk($uid, $level, $where = '')
    {
        if ($uid < 1) {
            return 0;
        }

        $shop_id = 0;
        //等级id大于2为商铺代理
        $user_info = M('users')->field('distribut_level,user_id,invite_uid')
            ->where('user_id', $uid)
            ->find();
        $res = true;
        if ($where) {
            $res = !in_array($user_info['user_id'], $where);
        }

        if ($user_info['distribut_level'] >= $level && $res) {
            return $user_info['user_id'];
        }
        $shop_id = $this->nk($user_info['invite_uid'], $level, $where);

        return $shop_id;
    }


    /*
     * 登录/注册之后的操作
     * */
    public function afterLogin($data, $source = 1)
    {
        $redis = new Redis();
//        define('SESSION_ID', session_id()); //将当前的session_id保存为常量，供其它方法调用

        session('user', $data);
        $redis->set('user_' . $data['token'], $data, config('REDIS_TIME'));
        session('server', $_SERVER);
        $redis->set('server_' . $data['token'], $data, config('REDIS_TIME'));
        setcookie('user_id', $data['user_id'], null, '/');
        setcookie('is_distribut', $data['is_distribut'], null, '/');
        setcookie('uname', $data['nickname'], null, '/');

//        // 登录后将购物车的商品的 user_id 改为当前登录的id
        M('cart')->where('session_id', $data['token'])->save(['user_id' => $data['user_id']]);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($data['user_id']);
        $cartLogic->setUserToken($data['token']);
        $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作

        $update_data = ['token' => $data['token'], 'time_out' => strtotime('+' . config('REDIS_DAY') . ' days'), 'last_login' => time(), 'last_login_source' => $source];
        M('users')->where('user_id', $data['user_id'])->save($update_data);
    }

    /*
     * App 登录
     * */
    public function handleAppLogin($data, $userToken = '')
    {
        // 1.判断是否需要注册新用户,先找到第三方登录数据，再判断用户状态是否正常
        $need_reg = true;
        $exist = M('oauth_users')->where('unionid', $data['unionid'])->where('oauth', $data['oauth'])->find();

        Db::startTrans(); // 开启事务
        $row_id0 = $row_id1 = $row_id2 = true;

        if ($exist) {
            $user_info = M('users')->where('user_id', $exist['user_id'])->find();
            if (!$user_info) { // 如果用户不存在，删掉残留的数据,并重新注册生成
                $row_id0 = M('oauth_users')->where('user_id', $exist['user_id'])->delete();
            } else {
                $need_reg = false;
                if (1 == $user_info['is_lock']) {
                    return ['status' => -1, 'msg' => '账号异常已被冻结，无法登录'];
                }
                $userId = $exist['user_id'];
            }
        }

        if (!$userToken) {
            $userToken = TokenLogic::setToken();
        }

        // 需要注册
        if ($need_reg) {
            $map = [];
            $map['password'] = '';
            $map['is_zhixiao'] = 0;
            $map['openid'] = $data['openid'];
            $map['unionid'] = $data['unionid'];
            $map['nickname'] = filter($data['nickname']);
            $map['reg_time'] = time();
            $map['reg_source'] = 1;
            $map['login_time'] = time();
            $map['oauth'] = $data['oauth'];
            $map['head_pic'] = !empty($data['head_pic']) ? $data['head_pic'] : url('/', '', '', true) . '/public/images/default_head.png';
            $map['sex'] = null === $data['sex'] ? 0 : $data['sex'];
            $map['type'] = 0;
            $map['token'] = $userToken;
            $map['time_out'] = strtotime('+' . config('REDIS_DAY') . ' days');
            $row_id1 = Db::name('users')->add($map);    // 注册新用户
            $data['user_id'] = $userId = $row_id1;
            $row_id2 = Db::name('OauthUsers')->data($data)->add();  // 记录oauth用户
            $user_info = M('users')->where('user_id', $row_id1)->find();
            session('is_new', 1);
            (new Redis())->set('is_new_' . $userToken, 1, config('REDIS_TIME'));
        }

        if (!$row_id0 || !$row_id1 || !$row_id2) {
            Db::rollback();
            $result = ['status' => -2, 'msg' => '系统繁忙，请稍后再试。'];
        } else {
            Db::commit();
            $this->afterLogin($user_info, 3);
            session('is_app', 1);
            (new Redis())->set('is_app_' . $userToken, 1, config('REDIS_TIME'));
            // 登录记录
            $this->setUserId($userId);
            $this->userLogin(1);
            $result = ['status' => 1, 'msg' => '登录成功'];
        }

        return $result;
    }

    /**
     * APP微信授权登录（新）
     * @param $data
     * @return array
     * @throws \think\Exception
     */
    public function handleAppLoginNew($data)
    {
        // 查看是否有oauth用户记录
        $oauthUser = M('oauth_users')->where([
            'unionid' => $data['unionid'],
            'oauth' => 'weixin',
        ])->order('tu_id desc')->find();
        $updateData = [
            'user_id' => '',
            'openid' => $data['openid'],
            'oauth' => 'weixin',
            'unionid' => $data['unionid'],
            'oauth_child' => 'open',
            'oauth_data' => serialize($data)
        ];
        if ($oauthUser) {
            // 已授权登录过
            $updateData['user_id'] = $oauthUser['user_id'];
            // 更新数据
            Db::name('oauth_users')->where(['tu_id' => $oauthUser['tu_id']])->update($updateData);
            if ($oauthUser['user_id'] == 0) {
                $result = ['status' => 2, 'result' => ['openid' => $data['openid']]]; // 需要绑定手机号
            } else {
                $user = Db::name('users')->where(['user_id' => $oauthUser['user_id']])->field('mobile, is_lock, is_cancel')->find();
                if (empty($user['mobile'])) {
                    $result = ['status' => 2, 'result' => ['openid' => $data['openid']]]; // 需要绑定手机号
                } elseif ($user['is_lock'] == 1) {
                    $result = ['status' => 0, 'msg' => '账号已被冻结'];
                } elseif ($user['is_cancel'] == 1) {
                    $result = ['status' => 2, 'result' => ['openid' => $data['openid']]]; // 之前的账号已注销，需要重新绑定手机号
                } else {
                    // 更新用户信息
                    $userToken = TokenLogic::setToken();
                    $updateData = [
                        'openid' => $data['openid'],
                        'unionid' => $data['unionid'],
                        'nickname' => $data['nickname'],
                        'head_pic' => !empty($data['headimgurl']) ? $data['headimgurl'] : url('/', '', '', true) . '/public/images/default_head.png',
                        'sex' => $oauthData['sex'] ?? 0,
                        'token' => $userToken,
                        'time_out' => strtotime('+' . config('REDIS_DAY') . ' days')
                    ];
                    Db::name('users')->where(['user_id' => $oauthUser['user_id']])->update($updateData);
                    $user = Db::name('users')->where(['user_id' => $oauthUser['user_id']])->find();
                    // 更新用户推送tags
                    $res = (new PushLogic())->bindPushTag($user);
                    if ($res['status'] == 2) {
                        $user = Db::name('users')->where('user_id', $user['user_id'])->find();
                    }
                    // 更新用户缓存
                    (new Redis())->set('user_' . $user['token'], $user, config('REDIS_TIME'));
                    $result = ['status' => 1, 'result' => $user];  // 登录成功
                }
            }
        } else {
            // 未授权登录过
            // 插入数据
            Db::name('oauth_users')->insert($updateData);
            $result = ['status' => 2, 'result' => ['openid' => $data['openid']]]; // 需要绑定手机号
        }
        return $result;
    }

    /*
     * 登陆
     */
    public function login($username, $password, $userToken = null, $source = 1)
    {
        if (!$username || !$password) {
            return ['status' => 0, 'msg' => '请填写账号或密码'];
        }
        if (check_mobile($username)) {
            $users = Db::name('users')->where('is_cancel', 0)->where(['mobile' => $username])->whereOr(['email' => $username]);
        } else {
            $users = Db::name('users')->where('is_cancel', 0)->where(['user_id' => $username])->whereOr(['email' => $username]);
        }
        $userData = $users->field('user_id, is_lock')->select(); // 手机号登陆的情况下会有多个账号
        if (empty($userData)) {
            return ['status' => -1, 'msg' => '账号不存在!'];
        }
        // 检验账号有效性
        $userId = 0;
        foreach ($userData as $user) {
            if ($user['is_lock'] == 0) {
                $userId = $user['user_id'];
                break;
            }
        }
        if ($userId == 0) {
            return ['status' => 0, 'msg' => '该账号已被冻结，请联系客服' . tpCache('shop_info.mobile')];
        }
        $userPassword = Db::name('users')->where('user_id', $userId)->value('password');
        if (systemEncrypt($password) != $userPassword) {
            $result = ['status' => -2, 'msg' => '密码错误!'];
        } else {
            // 更新用户token
            if (!$userToken) $userToken = TokenLogic::setToken();
            $save = [
                'last_login' => time(),
                'last_login_source' => $source,
                'token' => $userToken,
                'time_out' => strtotime('+' . config('REDIS_DAY') . ' days')
            ];
            Db::name('users')->where('user_id', $userId)->update($save);
            $user = Db::name('users')->where('user_id', $userId)->find();
            // 更新用户推送tags
            $res = (new PushLogic())->bindPushTag($user);
            if ($res['status'] == 2) {
                $user = Db::name('users')->where('user_id', $userId)->find();
            }
            $result = ['status' => 1, 'msg' => '登录成功', 'result' => $user];
            // 登录记录
            $this->setUserId($userId);
            $this->userLogin($source);
        }
        return $result;
    }

    /**
     * 退出登录
     * @param $userToken
     * @return bool
     */
    public function logout($userToken)
    {
        Db::name('users')->where('token', $userToken)->setField('time_out', time());
        return true;
    }

    public function login_ip($username, $password)
    {
        if (!$username || !$password) {
            return ['status' => 0, 'msg' => '请填写账号或密码'];
        }

        $user = Db::name('users')->where('user_id', $username)->find();
        if (!$user) {
            $result = ['status' => -1, 'msg' => '账号不存在!'];
        } elseif (systemEncrypt($password) != $user['password']) {
            $result = ['status' => -2, 'msg' => '密码错误!'];
        } elseif (1 == $user['is_lock']) {
            $result = ['status' => -3, 'msg' => '账号异常已被锁定！！！'];
        } else {
            //查询用户信息之后, 查询用户的登记昵称
//            $levelId = $user['level'];
//            $levelName = Db::name("user_level")->where("level_id", $levelId)->getField("level_name");
//            $user['level_name'] = $levelName;

            $result = ['status' => 1, 'msg' => '登录成功', 'result' => $user];
        }

        return $result;
    }

    /*
     * app端登陆
     */
    public function app_login($username, $password, $capache, $push_id = 0)
    {
        $result = [];
        if (!$username || !$password) {
            $result = ['status' => 0, 'msg' => '请填写账号或密码'];
        }
        $user = M('users')->where('mobile|email', '=', $username)->find();
        if (!$user) {
            $result = ['status' => -1, 'msg' => '账号不存在!'];
        } elseif ($password != $user['password']) {
            $result = ['status' => -2, 'msg' => '密码错误!'];
        } elseif (1 == $user['is_lock']) {
            $result = ['status' => -3, 'msg' => '账号异常已被锁定！！！'];
        } else {
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = M('user_level')->where('level_id', $levelId)->getField('level_name');
            $user['level_name'] = $levelName;
            $user['token'] = md5(time() . mt_rand(1, 999999999));
            $data = ['token' => $user['token'], 'last_login' => time(), 'last_login_source' => 3];
            $push_id && $data['push_id'] = $push_id;
            M('users')->where('user_id', $user['user_id'])->save($data);
            $result = ['status' => 1, 'msg' => '登陆成功', 'result' => $user];
        }

        return $result;
    }

    /*
     * app端登出
     */
    public function app_logout($token = '')
    {
        if (empty($token)) {
            ajaxReturn(['status' => -100, 'msg' => '已经退出账户']);
        }

        $user = M('users')->where('token', $token)->find();
        if (empty($user)) {
            ajaxReturn(['status' => -101, 'msg' => '用户不在登录状态']);
        }

        M('users')->where(['user_id' => $user['user_id']])->save(['last_login' => 0, 'token' => '']);
        session(null);

        return ['status' => 1, 'msg' => '退出账户成功'];
    }

    //绑定账号
    public function oauth_bind($data = [])
    {
        if (!empty($data['openid'])) {
            return false;
        }

        $user = session('user');
        if (empty($data['oauth_child'])) {
            $data['oauth_child'] = '';
        }

        if (empty($data['unionid'])) {
            $column = 'openid';
            $open_or_unionid = $data['openid'];
        } else {
            $column = 'unionid';
            $open_or_unionid = $data['unionid'];
        }

        $where = [$column => $open_or_unionid];
        if ('openid' == $column) {
            $where['oauth'] = $data['oauth']; //unionid不需要加这个限制
        }

        $ouser = Db::name('Users')->alias('u')->field('u.user_id,o.tu_id')->join('OauthUsers o', 'u.user_id = o.user_id')->where($where)->find();
        if ($ouser) {
            //删除原来绑定
            Db::name('OauthUsers')->where('tu_id', $ouser['tu_id'])->delete();
        }
        //绑定账号
        return Db::name('OauthUsers')->save(['oauth' => $data['oauth'], 'openid' => $data['openid'], 'user_id' => $user['user_id'], 'unionid' => $data['unionid'], 'oauth_child' => $data['oauth_child']]);
    }

    //绑定账号
    public function oauth_bind_new($user = [])
    {
        $thirdOauth = session('third_oauth');

        $thirdName = ['weixin' => '微信', 'qq' => 'QQ', 'alipay' => '支付宝', 'miniapp' => '微信小程序'];

        //1.检查账号密码是否正确
        $ruser = M('Users')->where(['mobile' => $user['mobile']])->find();
        if (empty($ruser)) {
            return ['status' => -1, 'msg' => '账号不存在', 'result' => ''];
        }

        if ($ruser['password'] != $user['password']) {
            return ['status' => -1, 'msg' => '账号或密码错误', 'result' => ''];
        }

        //2.检查第三方信息是否完整
        $openid = $thirdOauth['openid'];   //第三方返回唯一标识
        $unionid = $thirdOauth['unionid'];   //第三方返回唯一标识
        $oauth = $thirdOauth['oauth'];      //来源
        $oauthCN = $platform = $thirdName[$oauth];
        if ((empty($unionid) || empty($openid)) && empty($oauth)) {
            return ['status' => -1, 'msg' => '第三方平台参数有误[openid:' . $openid . ' , unionid:' . $unionid . ', oauth:' . $oauth . ']', 'result' => ''];
        }

        //3.检查当前当前账号是否绑定过开放平台账号
        //1.判断一个账号绑定多个QQ
        //2.判断一个QQ绑定多个账号
        if ($unionid) {
            //如果有 unionid

            //1.1此oauth是否已经绑定过其他账号
            $thirdUser = M('OauthUsers')->where(['unionid' => $unionid, 'oauth' => $oauth])->find();
            if ($thirdUser && $ruser['user_id'] != $thirdUser['user_id']) {
                return ['status' => -1, 'msg' => '此' . $oauthCN . '已绑定其它账号', 'result' => ''];
            }

            //1.2此账号是否已经绑定过其他oauth
            $thirdUser = M('OauthUsers')->where(['user_id' => $ruser['user_id'], 'oauth' => $oauth])->find();
            if ($thirdUser && $thirdUser['unionid'] != $unionid) {
                return ['status' => -1, 'msg' => '此' . $oauthCN . '已绑定其它账号', 'result' => ''];
            }
        } else {
            //如果没有unionid

            //2.1此oauth是否已经绑定过其他账号
            $thirdUser = M('OauthUsers')->where(['openid' => $openid, 'oauth' => $oauth])->find();
            if ($thirdUser) {
                return ['status' => -1, 'msg' => '此' . $oauthCN . '已绑定其它账号', 'result' => ''];
            }

            //2.2此账号是否已经绑定过其他oauth
            $thirdUser = M('OauthUsers')->where(['user_id' => $ruser['user_id'], 'oauth' => $oauth])->find();
            if ($thirdUser) {
                return ['status' => -1, 'msg' => '此账号已绑定其它' . $oauthCN . '账号', 'result' => ''];
            }
        }

        if (!isset($thirdOauth['oauth_child'])) {
            $thirdOauth['oauth_child'] = '';
        }
        //4.账号绑定
        M('OauthUsers')->save(['oauth' => $oauth, 'openid' => $openid, 'user_id' => $ruser['user_id'], 'unionid' => $unionid, 'oauth_child' => $thirdOauth['oauth_child']]);
        $ruser['token'] = md5(time() . mt_rand(1, 999999999));
        $ruser['last_login'] = time();
        $ruser['last_login_source'] = 1;

        M('Users')->where('user_id', $ruser['user_id'])->save(['token' => $ruser['token'], 'last_login' => $ruser['last_login'], 'last_login_source' => $ruser['last_login_source']]);

        return ['status' => 1, 'msg' => '绑定成功', 'result' => $ruser];
    }

    /**
     * 获取第三方登录的用户.
     *
     * @param $openid
     * @param $unionid
     * @param $oauth
     * @param $oauth_child
     *
     * @return array
     */
    private function getThirdUser($data)
    {
        $user = [];

        $thirdUser = Db::name('oauth_users')->where(['openid' => $data['openid'], 'oauth' => $data['oauth']])->find();
        if (!$thirdUser) {
            if ($data['unionid']) {
                $thirdUser = Db::name('oauth_users')->where(['unionid' => $data['unionid']])->find();
                if ($thirdUser) {
                    $data['user_id'] = $thirdUser['user_id'];
                    Db::name('oauth_users')->insert($data); //补充其他第三方登录方式
                    $user = Db::name('users')->where('user_id', $thirdUser['user_id'])->find();

                    return $user;
                }
            }

            $user = M('Users')->where(['openid' => $data['openid'], 'oauth' => $data['oauth'], 'is_lock' => 0])->find();

            if ($user) {
                $data['user_id'] = $user['user_id'];
                Db::name('oauth_users')->insert($data);
            }
        }

        if ($thirdUser) {
            $user = Db::name('users')->where('user_id', $thirdUser['user_id'])->find();
            if (!$user) {
                Db::name('oauth_users')->where(['openid' => $data['openid'], 'oauth' => $data['oauth']])->delete(); //删除残留数据
            }
        }

        return $user;
    }

    /*
     * 第三方登录: (第一种方式:第三方账号直接创建账号, 不需要额外绑定账号)
     */
    public function thirdLogin($data = [], $source = 1)
    {
        // Log::record('微信登录：第三方返回来的数据：'.json_encode($data));
        if (!$data['openid'] || !$data['oauth']) {
            return ['status' => -1, 'msg' => '参数有误', 'result' => null];
        }

        $data['push_id'] && $map['push_id'] = $data['push_id'];
        $map['token'] = isset($data['token']) ? $data['token'] : TokenLogic::setToken();
        $map['time_out'] = strtotime('+' . config('REDIS_DAY') . ' days');
        $map['last_login'] = time();
        $map['last_login_source'] = $source;

        $user = $this->getThirdUser($data);
        if (!$user) {
            //账户不存在 注册一个
            $map['password'] = '';
            $map['is_zhixiao'] = 0;
            $map['openid'] = $data['openid'];
            $map['nickname'] = filter($data['nickname']);
            // $map['nickname'] = $data['nickname'];
            $map['reg_time'] = time();
            $map['oauth'] = $data['oauth'];
            $map['head_pic'] = !empty($data['head_pic']) ? $data['head_pic'] : '/public/images/default_head.png';
            $map['sex'] = null === $data['sex'] ? 0 : $data['sex'];
            $map['type'] = 0;
            // $map['first_leader'] = cookie('first_leader'); // 推荐人id
            // if($_GET['invite'])
            //     $map['first_leader'] = $_GET['invite']; // 微信授权登录返回时 get 带着参数的
            $invite = TokenLogic::getValue('invite', $data['token']);
            $file = 'invite.txt';
            file_put_contents($file, '[' . date('Y-m-d H:i:s') . ']  把邀请人设置到待邀请人字段：' . $invite . "\n", FILE_APPEND | LOCK_EX);
            $map['invite_uid'] = $map['first_leader'] = 0;

            if ($invite > 0) {
                $map['will_invite_uid'] = $invite;

                // $first_leader = Db::name('users')->where("user_id = {$invite}")->find();

                // $map['first_leader'] = $invite;
                // $map['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
                // $map['third_leader'] = $first_leader['second_leader']; // 第二级推荐人

                // // //他上线分销的下线人数要加1
                // Db::name('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
                // Db::name('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
                // Db::name('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');

                // // 邀请送积分
                // $invite_integral = tpCache('basic.invite_integral');
                // accountLog($invite,0,$invite_integral,'邀请用户奖励积分');
            }

            // 成为分销商条件
            // $distribut_condition = tpCache('distribut.condition');
            // if($distribut_condition == 0){    // 直接成为分销商, 每个人都可以做分销
            //     $map['is_distribut']  = 1;
            // }
            $row_id = Db::name('users')->add($map);
            // if($row_id){
            //     $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
            //     if($pay_points > 0){
            //         accountLog($row_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
            //     }
            // }
            $user = Db::name('users')->where(['user_id' => $row_id])->find();
            session('is_new', 1);
            (new Redis())->set('is_new_' . $data['token'], 1, 86400);
            if (!isset($data['oauth_child'])) {
                $data['oauth_child'] = '';
            }

            //不存在则创建个第三方账号
            $data['user_id'] = $user['user_id'];
            // $user_level =Db::name('user_level')->where('amount = 0')->find(); //折扣
            // $data['discount'] = !empty($user_level) ? $user_level['discount']/100 : 1;  //新注册的会员都不打折
            Db::name('OauthUsers')->save($data);
        } else {
            Db::name('users')->where('user_id', $user['user_id'])->save($map);
            $user['token'] = $map['token'];
            $user['last_login'] = $map['last_login'];
            $user['last_login_source'] = $map['last_login_source'];
        }
        // 登录记录
        $this->setUserId($user['user_id']);
        $this->userLogin($source);

        return ['status' => 1, 'msg' => '登陆成功', 'result' => $user];
    }

    /*
     * 第三方登录(第二种方式:第三方账号登录必须绑定账号)
     */
    public function thirdLogin_new($data = [])
    {
        if ((empty($data['openid']) && empty($data['unionid'])) || empty($data['oauth'])) {
            return ['status' => -1, 'msg' => '参数错误, openid,unionid或oauth为空', 'result' => ''];
        }

        $user = $this->getThirdUser($data);
        if (!$user) {
            return ['status' => -1, 'msg' => '请绑定账号', 'result' => '100'];
        }

        $data['push_id'] && $map['push_id'] = $data['push_id'];
        $map['token'] = md5(time() . mt_rand(1, 999999999));
        $map['last_login'] = time();

        Db::name('users')->where(['user_id' => $user['user_id']])->save($map);
        //重新加载一次用户信息
        $user = Db::name('users')->where(['user_id' => $user['user_id']])->find();

        return ['status' => 1, 'msg' => '登陆成功', 'result' => $user];
    }

    /**
     * 注册.
     *
     * @param $username  邮箱或手机
     * @param $password  密码
     * @param $password2 确认密码
     *
     * @return array
     */
    public function reg($username, $password, $password2, $push_id = 0, $invite = 0, $nickname = '', $head_pic = '', $userToken = null, $source = 1)
    {
        $is_validated = 0;
//        if (check_email($username)) {
//            $is_validated = 1;
//            $map['email_validated'] = 1;
//            $map['nickname'] = $map['email'] = $username; //邮箱注册
//        }
        if (check_mobile($username)) {
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['user_name'] = $map['mobile'] = $username;
            $map['nickname'] = '手机用户' . substr($username, -4);
            $exists = M('Users')->where('mobile', $map['mobile'])->where('is_cancel', 0)->find();
            if ($exists) {
                return ['status' => -1, 'msg' => '手机号已经存在', 'result' => ''];
            }
        }
        $password = htmlspecialchars($password, ENT_NOQUOTES, 'UTF-8', false);
        if (!check_password($password)) {
            return ['status' => -1, 'msg' => '密码格式为6-20位字母数字组合'];
        }

        if (!empty($nickname)) {
            $map['nickname'] = $nickname;
        }
        Url::root('/');
        $map['head_pic'] = url('/', '', '', true) . 'public/images/default_head.png';

        // if(!empty($head_pic)){
        //     $map['head_pic'] = $head_pic;
        // }else{
        //     $map['head_pic']='/public/images/default_head.png';
        // }

        if (1 != $is_validated) {
            return ['status' => -1, 'msg' => '请用手机号或邮箱注册', 'result' => ''];
        }

        if (!$username || !$password) {
            return ['status' => -1, 'msg' => '请输入用户名或密码', 'result' => ''];
        }

        //验证两次密码是否匹配
        if ($password2 != '' && $password2 != $password) {
            return ['status' => -1, 'msg' => '两次输入密码不一致', 'result' => ''];
        }
        //验证是否存在用户名
        if (get_user_info($username, 1) || get_user_info($username, 2)) {
            return ['status' => -1, 'msg' => '账号已存在', 'result' => ''];
        }

        $map['password'] = systemEncrypt($password);
        $map['reg_time'] = time();
        $map['reg_source'] = $source;
        $map['invite_uid'] = $map['will_invite_uid'] = $map['first_leader'] = 0;

        if (!$invite) $invite = S('invite_' . $userToken);
        // 如果找到他老爸还要找他爷爷他祖父等
        if ($invite > 0) {
            $map['will_invite_uid'] = $invite;
            // $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
            // $map['second_leader'] = $first_leader['first_leader'];
            // $map['third_leader'] = $first_leader['second_leader'];
            // //他上线分销的下线人数要加1
            // M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
            // M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
            // M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
        }

        /* if(is_array($invite) && !empty($invite)){

            $map['first_leader'] = $invite['user_id'];
            $map['second_leader'] = $invite['first_leader'];
            $map['third_leader'] = $invite['second_leader'];

            // //他上线分销的下线人数要加1
            Db::name('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
            Db::name('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
            Db::name('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');

            // 邀请送积分
            // $invite_integral = tpCache('basic.invite_integral');
            // accountLog($invite['user_id'],0,$invite_integral,'邀请用户奖励积分');
        } else if(tpCache('basic.invite') ==1 && empty($invite)){
            return array('status'=>-1,'msg'=>'请填写正确的推荐人手机号');
        } */

        // 成为分销商条件
        // $distribut_condition = tpCache('distribut.condition');
        // if($distribut_condition == 0)  // 直接成为分销商, 每个人都可以做分销
        //     $map['is_distribut']  = 1;

        $map['push_id'] = $push_id; //推送id
        $map['token'] = $userToken;
        $map['time_out'] = strtotime('+' . config('REDIS_DAY') . ' days');
        $map['last_login'] = time();
        $map['last_login_source'] = $source;
        // $user_level =Db::name('user_level')->where('amount = 0')->find(); //折扣
        // $map['discount'] = !empty($user_level) ? $user_level['discount']/100 : 1;  //新注册的会员都不打折
        $user_id = M('users')->insertGetId($map);
        if (false === $user_id) {
            return ['status' => -1, 'msg' => '注册失败'];
        }
        (new Redis())->set('is_new_' . $userToken, 1, 180);
//        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
//        if ($pay_points > 0) {
//            accountLog($user_id, 0, $pay_points, '会员注册赠送积分'); // 记录日志流水
//        }
        $user = M('users')->where('user_id', $user_id)->find();
        // 更新用户推送tags
        $res = (new PushLogic())->bindPushTag($user);
        if ($res['status'] == 2) {
            $user = Db::name('users')->where('user_id', $user_id)->find();
        }
        $user = [
            'user_id' => $user['user_id'],
            'sex' => $user['sex'],
            'nickname' => $user['nickname'],
            'user_name' => $user['nickname'],
            'real_name' => $user['user_name'],
            'id_cart' => $user['id_cart'],
            'birthday' => $user['birthday'],
            'mobile' => $user['mobile'],
            'head_pic' => $user['head_pic'],
            'type' => $user['distribut_level'] >= 3 ? 2 : $user['type'],
            'invite_uid' => $user['invite_uid'],
            'is_distribut' => $user['is_distribut'],
            'is_lock' => $user['is_lock'],
            'level' => $user['distribut_level'],
            'level_name' => M('DistributLevel')->where('level_id', $user['distribut_level'])->getField('level_name') ?? '普通会员',
            'is_not_show_jk' => $user['is_not_show_jk'],  // 是否提示加入金卡弹窗
            'has_pay_pwd' => $user['paypwd'] ? 1 : 0,
            'is_app' => TokenLogic::getValue('is_app', $user['token']) ? 1 : 0,
            'token' => $user['token'],
            'jpush_tags' => [$user['push_tag']]
        ];
        // 登录记录
        $this->setUserId($user['user_id']);
        $this->userLogin($source);
        return ['status' => 1, 'msg' => '注册成功', 'result' => $user];
    }

    /**
     * 授权用户注册
     * @param $openid
     * @param $username
     * @param $password
     * @return array
     */
    public function oauthReg($openid, $username, $password)
    {
        $oauthUser = M('oauth_users')->where(['openid' => $openid])->find();
        if (!$oauthUser) {
            return ['status' => 0, 'msg' => 'openid错误'];
        }
        $oauthData = unserialize($oauthUser['oauth_data']);
        $isReg = false;
        if (check_mobile($username)) {
            $userId = M('users')->where('mobile', $username)->where('is_cancel', 0)->value('user_id');
            if ($userId) {
                //--- 手机已有账号
                if (M('oauth_users')->where(['user_id' => $userId])->find()) {
                    return ['status' => 0, 'msg' => '该手机号已绑定了微信号'];
                }
            } else {
                //--- 手机没有账号
                $password = htmlspecialchars($password, ENT_NOQUOTES, 'UTF-8', false);
                if (!check_password($password)) {
                    return ['status' => 0, 'msg' => '密码格式为6-20位字母数字组合'];
                }
                if ($oauthUser['user_id'] != 0) {
                    //--- 微信之前已绑定了账号（H5微信授权）
                    $userId = $oauthUser['user_id'];
                    $userInfo = M('users')->where(['user_id' => $userId])->field('user_id, is_lock, is_cancel')->find();
                    if (empty($userInfo)) {
                        // 账号被删了，重新注册
                        $isReg = true;
                        $data = [
                            'mobile' => $username,
                            'password' => systemEncrypt($password),
                            'openid' => $oauthData['openid'],
                            'unionid' => $oauthData['unionid'],
                            'oauth' => $oauthUser['oauth'],
                            'nickname' => $oauthData['nickname'],
                            'head_pic' => !empty($oauthData['headimgurl']) ? $oauthData['headimgurl'] : url('/', '', '', true) . '/public/images/default_head.png',
                            'sex' => $oauthData['sex'] ?? 0,
                            'reg_time' => time(),
                            'last_login' => time(),
                            'token' => TokenLogic::setToken(),
                            'time_out' => strtotime('+' . config('REDIS_DAY') . ' days')
                        ];
                        $userId = M('users')->add($data);
                    } elseif ($userInfo['is_lock'] == 1) {
                        return ['status' => 0, 'msg' => '微信绑定的账号已被冻结'];
                    } elseif ($userInfo['is_cancel'] == 1) {
                        return ['status' => 0, 'msg' => '微信绑定的账号已被注销'];
                    }
                } else {
                    // 用户注册
                    $isReg = true;
                    $data = [
                        'mobile' => $username,
                        'password' => systemEncrypt($password),
                        'openid' => $oauthData['openid'],
                        'unionid' => $oauthData['unionid'],
                        'oauth' => $oauthUser['oauth'],
                        'nickname' => $oauthData['nickname'],
                        'head_pic' => !empty($oauthData['headimgurl']) ? $oauthData['headimgurl'] : url('/', '', '', true) . '/public/images/default_head.png',
                        'sex' => $oauthData['sex'] ?? 0,
                        'reg_time' => time(),
                        'last_login' => time(),
                        'token' => TokenLogic::setToken(),
                        'time_out' => strtotime('+' . config('REDIS_DAY') . ' days')
                    ];
                    $userId = M('users')->add($data);
                }
            }
        } else {
            return ['status' => 0, 'msg' => '手机号格式不正确'];
        }
        // 更新oauth记录
        M('oauth_users')->where(['tu_id' => $oauthUser['tu_id']])->update(['user_id' => $userId]);
        if (!$isReg) {
            // 更新用户信息
            $updateData = [
                'mobile' => $username,
                'password' => systemEncrypt($password),
                'openid' => $oauthData['openid'],
                'unionid' => $oauthData['unionid'],
                'oauth' => $oauthUser['oauth'],
                'nickname' => $oauthData['nickname'],
                'head_pic' => !empty($oauthData['headimgurl']) ? $oauthData['headimgurl'] : url('/', '', '', true) . '/public/images/default_head.png',
                'last_login' => time(),
                'token' => TokenLogic::setToken(),
                'time_out' => strtotime('+' . config('REDIS_DAY') . ' days')
            ];
            M('users')->where(['user_id' => $userId])->update($updateData);
        }
        $user = M('users')->where(['user_id' => $userId])->find();
        // 更新用户推送tags
        $res = (new PushLogic())->bindPushTag($user);
        if ($res['status'] == 2) {
            $user = Db::name('users')->where('user_id', $user['user_id'])->find();
        }
        $user = [
            'user_id' => $user['user_id'],
            'sex' => $user['sex'],
            'nickname' => $user['nickname'],
            'user_name' => $user['nickname'],
            'real_name' => $user['user_name'],
            'id_cart' => $user['id_cart'],
            'birthday' => $user['birthday'],
            'mobile' => $user['mobile'],
            'head_pic' => $user['head_pic'],
            'type' => $user['distribut_level'] >= 3 ? 2 : $user['type'],
            'invite_uid' => $user['invite_uid'],
            'is_distribut' => $user['is_distribut'],
            'is_lock' => $user['is_lock'],
            'level' => $user['distribut_level'],
            'level_name' => M('DistributLevel')->where('level_id', $user['distribut_level'])->getField('level_name') ?? '普通会员',
            'is_not_show_jk' => $user['is_not_show_jk'],  // 是否提示加入金卡弹窗
            'has_pay_pwd' => $user['paypwd'] ? 1 : 0,
            'is_app' => TokenLogic::getValue('is_app', $user['token']) ? 1 : 0,
            'token' => $user['token'],
            'jpush_tags' => [$user['push_tag']]
        ];
        // 登录记录
        $this->setUserId($user['user_id']);
        $this->userLogin(3);
        return ['status' => 1, 'msg' => '注册成功', 'result' => $user];
    }

    /*
     * 获取当前登录用户信息
     */
    public function get_info($user_id)
    {
        if (!$user_id) {
            return ['status' => -1, 'msg' => '缺少参数'];
        }

        $user = M('users')
            ->field('user_id,sex,real_name,id_cart,birthday,mobile,head_pic,is_not_show_jk,type')
            ->where('user_id', $user_id)
            ->find();
        if (!$user) {
            return false;
        }

        // $activityLogic = new \app\common\logic\ActivityLogic;             //获取能使用优惠券个数
        // $user['coupon_count'] = $activityLogic->getUserCouponNum($user_id, 0);

        // $user['collect_count'] = Db::name('goods_collect')->where('user_id', $user_id)->count(); //获取收藏数量
        // $user['return_count'] = M('return_goods')->where(['user_id'=>$user_id,'status'=>['in', '0,1,2,3']])->count();   //退换货数量
        // $user['waitPay']     = M('order')->where("user_id = :user_id ".C('WAITPAY'))->bind(['user_id'=>$user_id])->count(); //待付款数量
        // $user['waitSend']    = M('order')->where("user_id = :user_id ".C('WAITSEND'))->bind(['user_id'=>$user_id])->count(); //待发货数量
        // $user['waitReceive'] = M('order')->where("user_id = :user_id ".C('WAITRECEIVE'))->bind(['user_id'=>$user_id])->count(); //待收货数量
        // $user['order_count'] = $user['waitPay'] + $user['waitSend'] + $user['waitReceive'];

        // $commentLogic = new CommentLogic;
        // $user['uncomment_count'] = $commentLogic->getCommentNum($user_id, 0); //待评论数
        // $user['comment_count'] = $commentLogic->getCommentNum($user_id, 1); //已评论数

        $user['show_mobile'] = '';
        if ($user['mobile']) {
            $pattern = '/(\d{3})(\d+)(\d{4})/';
            $replacement = '${1}****${3}';
            $user['show_mobile'] = preg_replace($pattern, $replacement, $user['mobile']);
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => $user];
    }

    /*
      * 获取当前登录用户信息
      */
    public function getApiUserInfo($user_id)
    {
        if (!$user_id) {
            return ['status' => -1, 'msg' => '账户未登陆'];
        }

        $user = M('users')->where('user_id', $user_id)->find();
        if (!$user) {
            return false;
        }

        $activityLogic = new \app\common\logic\ActivityLogic();             //获取能使用优惠券个数
        $user['coupon_count'] = $activityLogic->getUserCouponNum($user_id, 0);

        $user['collect_count'] = Db::name('goods_collect')->where('user_id', $user_id)->count(); //获取收藏数量
        $user['visit_count'] = M('goods_visit')->where('user_id', $user_id)->count();   //商品访问记录数
        $user['return_count'] = M('return_goods')->where("user_id=$user_id and status<2")->count();   //退换货数量
        $order_where = "deleted=0 AND order_status<>5 AND prom_type<5 AND user_id=$user_id ";
        $user['waitPay'] = M('order')->where($order_where . C('WAITPAY'))->count(); //待付款数量
        $user['waitSend'] = M('order')->where($order_where . C('WAITSEND'))->count(); //待发货数量
        $user['waitReceive'] = M('order')->where($order_where . C('WAITRECEIVE'))->count(); //待收货数量
        $user['order_count'] = $user['waitPay'] + $user['waitSend'] + $user['waitReceive'];

        $messageLogic = new \app\common\logic\MessageLogic();
        $user['message_count'] = $messageLogic->getUserMessageCount();

        $commentLogic = new CommentLogic();
        $user['uncomment_count'] = $commentLogic->getCommentNum($user_id, 0); //待评论数
        $user['comment_count'] = $commentLogic->getCommentNum($user_id, 1); //已评论数
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user_id);
        $user['cart_goods_num'] = $cartLogic->getUserCartGoodsNum();

        return ['status' => 1, 'msg' => '获取成功', 'result' => $user];
    }

    /*
     * 获取最近一笔订单
     */
    public function get_last_order($user_id)
    {
        $last_order = M('order')->where('user_id', $user_id)->order('order_id DESC')->find();

        return $last_order;
    }

    /*
     * 获取订单商品
     */
    public function get_order_goods($order_id)
    {
        $sql = 'SELECT og.*, g.commission, g.original_img, g.weight, og.use_integral, og.give_integral, rg.status, g.zone FROM __PREFIX__order_goods og 
                LEFT JOIN __PREFIX__goods g ON g.goods_id = og.goods_id 
                LEFT JOIN __PREFIX__return_goods rg ON rg.rec_id = og.rec_id 
                WHERE og.order_id = :order_id';
        $bind['order_id'] = $order_id;
        $goods_list = DB::query($sql, $bind);

        if ($goods_list) {
            foreach ($goods_list as $k => $v) {
                $goods_list[$k]['is_return'] = M('ReturnGoods')->where('rec_id', $v['rec_id'])->find() ? 1 : 0;
                $goods_list[$k]['status_desc'] = C('REFUND_STATUS')[$v['status']];
            }
        }

        $return['status'] = 1;
        $return['msg'] = '';
        $return['result'] = $goods_list;

        return $return;
    }

    /**
     * 获取账户资金记录.
     *
     * @param $user_id |用户id
     * @param int $account_type |收入：1,支出:2 所有：0
     * @param null $order_sn
     *
     * @return array
     */
    public function get_account_log($user_id, $account_type = 0, $order_sn = null)
    {
        $account_log_where['user_id'] = ['eq', $user_id];
        $account_log_where['pay_points'] = ['neq', '0'];
        if (1 == $account_type) {
            $account_log_where['pay_points'] = ['gt', 0];
        } elseif (2 == $account_type) {
            $account_log_where['pay_points'] = ['lt', 0];
        }
        $order_sn && $account_log_where['order_sn'] = $order_sn;
        $count = M('account_log')->where($account_log_where)->count();
        $Page = new Page($count, 15);
        $account_log = M('account_log')
            ->field('*')
            ->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $list = [];
        foreach ($account_log as $ak => $av) {
            $key = date('m', $av['change_time']);
            $av['change_time'] = date('Y-m-d H:i:s', $av['change_time']);
            $list[$key][] = $av;
        }

        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $list,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /**
     * 获取账户资金记录(pc端).
     *
     * @param $user_id |用户id
     * @param int $account_type |收入：1,支出:2 所有：0
     * @param null $order_sn
     *
     * @return array
     */
    public function get_account_log_pc($user_id, $account_type = 0, $order_sn = null)
    {
        $screen_times = I('screen_times', '');
        if ($screen_times) {
            list($start, $end) = explode(',', $screen_times);
            $start = strtotime($start);
            $end = strtotime($end);
            $account_log_where['change_time'] = ['between', [$start, $end]];
        }
        $account_log_where['user_id'] = ['eq', $user_id];
        $account_log_where['pay_points'] = ['neq', '0'];
        if (1 == $account_type) {
            $account_log_where['pay_points'] = ['gt', 0];
        } elseif (2 == $account_type) {
            $account_log_where['pay_points'] = ['lt', 0];
        }
        $order_sn && $account_log_where['order_sn'] = $order_sn;
        $count = M('account_log')->where($account_log_where)->count();
        $Page = new Page($count, 15);
        $account_log = M('account_log')
            ->field('*,FROM_UNIXTIME(change_time,"%Y-%m-%d %H:%i:%s") as change_time , IF(pay_points > 0,"转入","转出") AS caozuo')
            ->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $result['account_log'] = $account_log;
        $result['account_info'] = [
            'user_id' => $user_id,
            'allPage' => $count,
            'page_size' => 15,
        ];

        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $result,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /**
     * 获取账户电子币记录(pc端).
     *
     * @param $user_id |用户id
     * @param int $account_type |收入：1,支出:2 所有：0
     * @param null $order_sn
     *
     * @return array
     */
    public function get_electronic_log_pc($user_id, $account_type = 0, $order_sn = null)
    {
        $screen_times = I('screen_times', '');
        if ($screen_times) {
            list($start, $end) = explode(',', $screen_times);
            $start = strtotime($start);
            $end = strtotime($end);
            $account_log_where['change_time'] = ['between', [$start, $end]];
        }
        $account_log_where['user_id'] = ['eq', $user_id];
        $account_log_where['user_electronic'] = ['neq', '0.00'];
        $account_log_where['user_electronic'] = ['neq', '0'];
        if (1 == $account_type) {
            $account_log_where['user_electronic'] = ['gt', 0];
        } elseif (2 == $account_type) {
            $account_log_where['user_electronic'] = ['lt', 0];
        }
        $order_sn && $account_log_where['order_sn'] = $order_sn;
        $count = M('account_log')->where($account_log_where)->count();
        $Page = new Page($count, 15);
        $account_log = M('account_log')
            ->field('*,FROM_UNIXTIME(change_time,"%Y-%m-%d %H:%i:%s") as change_time , IF(user_electronic > 0,"转入","转出") AS caozuo')
            ->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $result['account_log'] = $account_log;
        $result['account_info'] = [
            'user_id' => $user_id,
            'allPage' => $count,
            'page_size' => 15,
        ];

        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $result,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /**
     * 获取账户余额记录(pc端).
     *
     * @param $user_id |用户id
     * @param int $account_type |收入：1,支出:2 所有：0
     * @param null $order_sn
     *
     * @return array
     */
    public function get_money_log_pc($user_id, $account_type = 0, $order_sn = null)
    {
        $screen_times = I('screen_times', '');
        if ($screen_times) {
            list($start, $end) = explode(',', $screen_times);
            $start = strtotime($start);
            $end = strtotime($end);
            $account_log_where['change_time'] = ['between', [$start, $end]];
        }
        $account_log_where['user_id'] = ['eq', $user_id];
        $account_log_where['user_money'] = ['neq', '0.00'];
        $account_log_where['user_money'] = ['neq', '0'];
        if (1 == $account_type) {
            $account_log_where['user_money'] = ['gt', 0];
        } elseif (2 == $account_type) {
            $account_log_where['user_money'] = ['lt', 0];
        }
        $order_sn && $account_log_where['order_sn'] = $order_sn;
        $count = M('account_log')->where($account_log_where)->count();
        $Page = new Page($count, 15);
        $account_log = M('account_log')
            ->field('*,FROM_UNIXTIME(change_time,"%Y-%m-%d %H:%i:%s") as change_time , IF(user_money > 0,"转入","转出") AS caozuo')
            ->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $result['account_log'] = $account_log;
        $result['account_info'] = [
            'user_id' => $user_id,
            'allPage' => $count,
            'page_size' => 15,
        ];

        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $result,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /**
     * 获取账户余额记录.
     *
     * @param $user_id |用户id
     * @param int $account_type |收入：1,支出:2 所有：0
     * @param null $order_sn
     *
     * @return array
     */
    public function get_money_log($user_id, $account_type = 0, $order_sn = null)
    {
        $account_log_where['user_id'] = ['eq', $user_id];
        $account_log_where['user_money'] = ['not in', ['0.00', '0']];
        if (1 == $account_type) {
            $account_log_where['user_money'] = ['gt', 0];
        } elseif (2 == $account_type) {
            $account_log_where['user_money'] = ['lt', 0];
        }
        $order_sn && $account_log_where['order_sn'] = $order_sn;
        $count = M('account_log')->where($account_log_where)->count();
        $Page = new Page($count, 15);
        $account_log = M('account_log')
            ->field('*,FROM_UNIXTIME(change_time,"%Y-%m-%d %H:%i:%s") as change_time')
            ->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $list = [];
        foreach ($account_log as $ak => $av) {
            $key = date('m', strtotime($av['change_time']));
            $list[$key][] = $av;
        }

        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $list,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /**
     * 获取账户电子币记录.
     *
     * @param $user_id |用户id
     * @param int $account_type |收入：1,支出:2 所有：0
     * @param null $order_sn
     *
     * @return array
     */
    public function get_electronic_log($user_id, $account_type = 0, $order_sn = null)
    {
        $account_log_where['user_id'] = ['eq', $user_id];
        $account_log_where['user_electronic'] = ['not in', ['0.00', '0']];
        if (1 == $account_type) {
            $account_log_where['user_electronic'] = ['gt', 0];
        } elseif (2 == $account_type) {
            $account_log_where['user_electronic'] = ['lt', 0];
        }
        $order_sn && $account_log_where['order_sn'] = $order_sn;
        $count = M('account_log')->where($account_log_where)->count();
        $Page = new Page($count, 15);
        $account_log = M('account_log')
            ->field('*,FROM_UNIXTIME(change_time,"%Y-%m-%d %H:%i:%s") as change_time')
            ->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $list = [];
        foreach ($account_log as $ak => $av) {
            $key = date('m', strtotime($av['change_time']));
            $list[$key][] = $av;
        }

        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $list,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /**
     * 提现记录.
     *
     * @param $user_id
     * @param int $withdrawals_status 提现状态 0:申请中 1:申请成功 2:申请失败
     *
     * @return mixed
     * @author lxl 2017-4-26
     *
     */
    public function get_withdrawals_log($user_id, $withdrawals_status = '')
    {
        $withdrawals_log_where['user_id'] = ['eq', $user_id];
        if ($withdrawals_status) {
            $withdrawals_log_where['status'] = $withdrawals_status;
        }
        $count = M('withdrawals')->where($withdrawals_log_where)->count();
        $Page = new Page($count, 15);
        $withdrawals_log = M('withdrawals')
            ->where($withdrawals_log_where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $withdrawals_log,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /**
     * 用户充值记录
     * $author lxl 2017-4-26.
     *
     * @param $user_id 用户ID
     * @param int $pay_status 充值状态0:待支付 1:充值成功 2:交易关闭
     *
     * @return mixed
     */
    public function get_recharge_log($user_id, $pay_status = 0)
    {
        $recharge_log_where = ['user_id' => $user_id];
        if ($pay_status) {
            $pay_status['status'] = $pay_status;
        }
        $count = M('recharge')->where($recharge_log_where)->count();
        $Page = new Page($count, 15);
        $recharge_log = M('recharge')->where($recharge_log_where)
            ->order('order_id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status' => 1,
            'msg' => '',
            'result' => $recharge_log,
            'show' => $Page->show(),
        ];

        return $return;
    }

    /*
     * 获取优惠券
     */
    public function get_coupon($user_id, $type = 0, $orderBy = null, $order_money = 0, $p = 1200)
    {
        $activityLogic = new \app\common\logic\ActivityLogic();
        $count = $activityLogic->getUserCouponNum($user_id, $type, $orderBy, $order_money);

        $page = new Page($count, $p);
        $list = $activityLogic->getUserCouponList($page->firstRow, $page->listRows, $user_id, $type, $orderBy, $order_money);

        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $list;
        $return['show'] = $page->show();

        return $return;
    }

    /*
    * 获取优惠券
    */
    public function get_coupons($user_id, $type = 0, $orderBy = null, $order_money = 0, $p = 100, $is_yhq = true)
    {
        $activityLogic = new \app\common\logic\ActivityLogic();
        $count = $activityLogic->getUserCouponNum($user_id, $type, $orderBy, $order_money);

        $page = new Page($count, $p);
        $list = $activityLogic->getUserCouponList(0, 99999, $user_id, $type, $orderBy, $order_money, $is_yhq);

        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $list;
        $return['show'] = $page->show();

        return $return;
    }

    /**
     * 获取商品收藏列表.
     *
     * @param $user_id
     *
     * @return mixed
     */
    public function get_goods_collect($user_id)
    {
        $count = Db::name('goods_collect')->where('user_id', $user_id)->count();
        $page = new Page($count, 10);
        $show = $page->show();
        //获取我的收藏列表
        $result = M('goods_collect')->alias('c')
            ->field('c.collect_id,c.add_time,g.goods_id,g.goods_name, g.goods_remark, g.shop_price,g.is_on_sale,g.store_count,g.cat_id,g.is_virtual,g.original_img,
                 c.goods_price - g.shop_price as low_price, g.exchange_integral')
            ->join('goods g', 'g.goods_id = c.goods_id', 'INNER')
            ->where("c.user_id = $user_id")
            ->limit($page->firstRow, $page->listRows)
            ->order('collect_id desc')
            ->select();

        foreach ($result as $k => $v) {
//            // 比起原价的升降关系
//            if ($v['low_price'] > 0) {
//                $result[$k]['type'] = 1;    // 降价
//            } else {
//                $result[$k]['type'] = 2;    // 升价
//            }
            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $result[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
            } else {
                $result[$k]['exchange_price'] = $v['shop_price'];
            }
        }

        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $result;
        $return['show'] = $show;

        return $return;
    }

    /**
     * 获取商品收藏列表Pc.
     *
     * @param $user_id
     *
     * @return mixed
     */
    public function get_goods_collect_pc($user_id)
    {
        $count = Db::name('goods_collect')->where('user_id', $user_id)->count();
        $page = new Page($count, 8);
//        $show = $page->show();
        //获取我的收藏列表
        $result = M('goods_collect')->alias('c')
            ->field('c.collect_id,c.add_time,g.goods_id,g.goods_name,g.shop_price,g.is_on_sale,g.store_count,g.cat_id,g.is_virtual,g.original_img,
                 c.goods_price - g.shop_price as low_price')
            ->join('goods g', 'g.goods_id = c.goods_id', 'INNER')
            ->where("c.user_id = $user_id")
            ->limit($page->firstRow, $page->listRows)
            ->order('collect_id desc')
            ->select();

        foreach ($result as $k => $v) {
            if ($v['low_price'] > 0) {
                $result[$k]['type'] = 1;
            } else {
                $result[$k]['type'] = 2;
            }
        }

        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $result;
//        $return['show'] = $show;
        $return['page'] = $page;

        return $return;
    }

    /**
     * 获取评论列表.
     *
     * @param $user_id 用户id
     * @param $status  状态 0 未评论 1 已评论 2全部
     *
     * @return mixed
     */
    public function get_comment($user_id, $status = 2)
    {
        if (1 == $status) {
            //已评论
            $commented_count = Db::name('comment')
                ->alias('c')
                ->join('__ORDER_GOODS__ g', 'c.goods_id = g.goods_id and c.order_id = g.order_id', 'inner')
                ->where('c.user_id', $user_id)
                ->count();
            $page = new Page($commented_count, 10);
            $comment_list = Db::name('comment')
                ->alias('c')
                ->field('c.*,g.*,(select order_sn from  __PREFIX__order where order_id = c.order_id ) as order_sn')
                ->join('__ORDER_GOODS__ g', 'c.goods_id = g.goods_id and c.order_id = g.order_id', 'inner')
                ->where('c.user_id', $user_id)
                ->order('c.add_time desc')
                ->limit($page->firstRow, $page->listRows)
                ->select();
        } else {
            $comment_where = ['o.user_id' => $user_id, 'og.is_send' => 1, 'o.order_status' => ['in', [2, 4]]];
            if (0 == $status) {
                $comment_where['og.is_comment'] = 0;
                $comment_where['o.order_status'] = 2;
            }
            $comment_count = Db::name('order_goods')->alias('og')->join('__ORDER__ o', 'o.order_id = og.order_id', 'left')->where($comment_where)->count();
            $page = new Page($comment_count, 10);
            $comment_list = Db::name('order_goods')
                ->alias('og')
                ->join('__ORDER__ o', 'o.order_id = og.order_id', 'left')
                ->where($comment_where)
                ->order('o.order_id desc')
                ->limit($page->firstRow, $page->listRows)
                ->select();
        }
        $show = $page->show();
        if ($comment_list) {
            $return['result'] = $comment_list;
            $return['show'] = $show; //分页
            return $return;
        }

        return [];
    }

    /**
     * 添加评论.
     *
     * @param $add
     *
     * @return array
     */
    public function add_comment($add)
    {
        if (!$add['order_id'] || !$add['goods_id']) {
            return ['status' => -1, 'msg' => '非法操作', 'result' => ''];
        }

        //检查订单是否已完成
        $order = M('order')->field('order_status')->where('order_id', $add['order_id'])->where('user_id', $add['user_id'])->find();
        if (2 != $order['order_status']) {
            return ['status' => -1, 'msg' => '该笔订单还未确认收货', 'result' => ''];
        }

        //检查是否已评论过
        $goods = M('comment')->where(['rec_id' => $add['rec_id']])->find();
        if ($goods) {
            return ['status' => -1, 'msg' => '您已经评论过该商品', 'result' => ''];
        }
        if ($add['goods_rank'] < 1 || $add['service_rank'] < 1) {
            return ['status' => -1, 'msg' => '请给商品评分', 'result' => ''];
        }
        $row = M('comment')->add($add);
        if ($row) {
            //更新订单商品表状态
            M('order_goods')->where(['rec_id' => $add['rec_id'], 'order_id' => $add['order_id']])->save(['is_comment' => 1]);
            M('goods')->where(['goods_id' => $add['goods_id']])->setInc('comment_count', 1); // 评论数加一
            // 查看这个订单是否全部已经评论,如果全部评论了 修改整个订单评论状态
            $comment_count = M('order_goods')->where('order_id', $add['order_id'])->where('is_comment', 0)->count();
            if (0 == $comment_count) { // 如果所有的商品都已经评价了 订单状态改成已评价
                M('order')->where('order_id', $add['order_id'])->save(['order_status' => 4]);
            }

            return ['status' => 1, 'msg' => '评论成功', 'result' => ''];
        }

        return ['status' => -1, 'msg' => '评论失败', 'result' => ''];
    }

    /**
     * 邮箱或手机绑定.
     *
     * @param $email_mobile  邮箱或者手机
     * @param int $type 1 为更新邮箱模式  2 手机
     * @param int $user_id 用户id
     *
     * @return bool
     */
    public function update_email_mobile($email_mobile, $user_id, $type = 2)
    {
        //检查是否存在邮件
        if (1 == $type) {
            $field = 'email';
        }
        if (2 == $type) {
            $field = 'mobile';
        }
        if (M('users')->where([$field => $email_mobile, 'is_cancel' => 0])->find()) {
            return false;
        }

        $current_user = M('users')->find($user_id);
        if ($current_user['mobile'] == $email_mobile) {
            return false;
        }

        // $condition['is_lock'] = 0;
//        $condition[$field] = $email_mobile;

        // //如果输入的手机号码有直销用户，不能绑定该手机
        // if(M('users')->where($condition)->where('is_zhixiao', 1)->find())
        // {
        //     return false;
        // }

        // // 如果找到该用户的手机号码有绑定的用户需判定
        // // 1.如果是直销系统的用户 不给绑定该手机号码 确保手机号唯一
        // // 2.如果是h5之前就注册的用户 则合并 并且清空手机号 确保手机号唯一
        // $user = M('users')->where($condition)->find();
        // // 如果存在，合并信息
        // if($user){

        //     if($user['is_zhixiao'] != 0 || $user['oauth'])
        //     {
        //         return false;
        //     }
        //     //1.判断用户来源 不能为直销系统过来的用户
        //     $is_zhixiao = false;
        //     // 如果用户分销等级大于1 并且没有购买过升级记录 则该用户肯定是直销系统过来的
        //     if($user['distribut_level'] > 1)
        //     {
        //         $level = 1;
        //         $levelRecord = M('Order')->alias('oi')
        //         ->join('__ORDER_GOODS__ og','oi.order_id = og.order_id','LEFT')
        //         ->join('__GOODS__ g','g.goods_id = og.goods_id','LEFT')
        //         ->where([
        //             'oi.user_id' => $user['user_id'],
        //             'oi.order_status' => ['not in',[3,5]],
        //             'oi.pay_status' => 1,
        //             'g.zone' => 3,
        //             'g.distribut_id' => ['gt',0],
        //         ])
        //         ->getField('g.distribut_id');

        //         if($levelRecord > 0){
        //             $level = $levelRecord;
        //         }

        //        if($level == 1){
        //          $is_zhixiao = true;
        //        }
        //     }

        //     if(!$is_zhixiao)
        //     {

        //         $user_data = array();
        //         $user_data['bind_uid'] = $user['user_id'];
        //         $user_data['bind_time'] = time();
        //         $user_data['mobile'] = $email_mobile;
        //         $user_data['mobile_validated'] = 1;
        //         M('Users')->where('user_id',$current_user['user_id'])->update($user_data);

        //         //冻结新账户
        //         M('Users')->where('user_id'$user['user_id'])->update(array('is_lock'=>1));
        //         return true;
        //     }

        // }else{
//            unset($condition[$field]);
        $condition['user_id'] = $user_id;
        $validate = $field . '_validated';
        M('users')->where($condition)->save([$field => $email_mobile, $validate => 1]);
        // 更新缓存
        $user = M('users')->where($condition)->find();
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);

        return true;
        // }
        // return false;
    }

    /**
     * 更新用户信息.
     *
     * @param $user_id
     * @param $post  要更新的信息
     *
     * @return bool
     */
    public function update_info($user_id, $post = [])
    {
        $model = M('users')->where('user_id', $user_id);
        $row = $model->setField($post);
        if (false === $row) {
            return false;
        }
        // 更新缓存
        $user = M('users')->where('user_id', $user_id)->find();
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);

        return true;
    }

    function is_consummate($user_id)
    {
        // 完善资料获得积分
        $user_info = get_user_info($user_id, 0);
        if ($user_info['birthday'] && $user_info['id_cart'] && $user_info['real_name'] && $user_info['mobile'] && $user_info['is_consummate'] == 0) {
            $pay_points = tpCache('basic.user_confirm_integral'); // 完善资料赠送积分
            if ($pay_points > 0) {
                accountLog($user_id, 0, $pay_points, '完善资料赠送积分', 0, 0, '', 0, 21); // 记录日志流水
                M('users')->where(array('user_id' => $user_id))->data(array('is_consummate' => 1))->save();
                return $pay_points;
            }
        }
        return false;
    }

    /**
     * 地址添加/编辑.
     *
     * @param $user_id 用户id
     * @param $user_id 地址id(编辑时需传入)
     *
     * @return array
     */
    public function add_address($user_id, $address_id = 0, $data)
    {
        $post = $data;
        if (0 == $address_id) {
            $c = M('UserAddress')->where('user_id', $user_id)->count();
            if ($c >= 20) {
                return ['status' => -1, 'msg' => '最多只能添加20个收货地址', 'result' => ''];
            }
        }

        if ('' == $post['consignee']) {
            return ['status' => -1, 'msg' => '收货人不能为空', 'result' => ''];
        }
        if (!($post['province'] > 0) || !($post['city'] > 0) || !($post['district'] > 0)) {
            return ['status' => -1, 'msg' => '所在地区不能为空', 'result' => ''];
        }
        if (!$post['address']) {
            return ['status' => -1, 'msg' => '地址不能为空', 'result' => ''];
        }
        if (!check_mobile($post['mobile']) && !check_telephone($post['mobile'])) {
            return ['status' => -1, 'msg' => '手机号码格式有误', 'result' => ''];
        }

        //编辑模式
        if ($address_id > 0) {
            $address = M('user_address')->where(['address_id' => $address_id, 'user_id' => $user_id])->find();
            if (1 == $post['is_default'] && 1 != $address['is_default']) {
                M('user_address')->where(['user_id' => $user_id])->save(['is_default' => 0]);
            }
            $row = M('user_address')->where(['address_id' => $address_id, 'user_id' => $user_id])->save($post);
            if (false !== $row) {
                return ['status' => 1, 'msg' => '编辑成功', 'result' => $address_id];
            }

            return ['status' => -1, 'msg' => '操作完成', 'result' => $address_id];
        }
        //添加模式
        $post['user_id'] = $user_id;

        // 如果目前只有一个收货地址则改为默认收货地址
        $c = M('user_address')->where('user_id', $post['user_id'])->count();
        if (0 == $c) {
            $post['is_default'] = 1;
        }

        $address_id = M('user_address')->add($post);
        //如果设为默认地址
        $insert_id = DB::name('user_address')->getLastInsID();
        $map['user_id'] = $user_id;
        $map['address_id'] = ['neq', $insert_id];

        if (isset($post['is_default']) && 1 == $post['is_default']) {
            M('user_address')->where($map)->save(['is_default' => 0]);
        }
        if (!$address_id) {
            return ['status' => -1, 'msg' => '添加失败', 'result' => ''];
        }

        return ['status' => 1, 'msg' => '添加成功', 'result' => $address_id];
    }

    /**
     * 获取地址标签
     * @param $userId
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getAddressTab($userId)
    {
        return Db::name('address_tab')->whereOr(['user_id' => ['in', [0, $userId]]])->field('id tab_id, name')->select();
    }

    /**
     * 添加地址标签
     * @param $userId
     * @param $name
     * @return array|\think\db\Query
     */
    public function addAddressTab($userId, $name)
    {
        if (Db::name('address_tab')->where(['user_id' => $userId, 'name' => $name])->find()) {
            return ['status' => 0, 'msg' => '标签已重复'];
        }
        Db::name('address_tab')->add(['name' => $name, 'user_id' => $userId]);
        return ['status' => 1, 'msg' => '添加成功'];
    }

    /**
     * 删除地址标签
     * @param $userId
     * @param $tabId
     * @return array
     */
    public function delAddressTab($userId, $tabId)
    {
        Db::name('address_tab')->where(['id' => $tabId, 'user_id' => $userId])->delete();
        return ['status' => 1, 'msg' => '删除成功'];
    }

    /**
     * 添加自提点.
     *
     * @param $user_id
     * @param $post
     *
     * @return array
     * @author dyr
     *
     */
    public function add_pick_up($user_id, $post)
    {
        //检查用户是否已经有自提点
        $user_pickup_address_id = M('user_address')->where(['user_id' => $user_id, 'is_pickup' => 1])->getField('address_id');
        $pick_up = M('pick_up')->where(['pickup_id' => $post['pickup_id']])->find();
        $post['address'] = $pick_up['pickup_address'];
        $post['is_pickup'] = 1;
        $post['user_id'] = $user_id;
        $user_address = new UserAddress();
        if (!empty($user_pickup_address_id)) {
            //更新自提点
            $user_address_save_result = $user_address->allowField(true)->validate(true)->save($post, ['address_id' => $user_pickup_address_id]);
        } else {
            //添加自提点
            $user_address_save_result = $user_address->allowField(true)->validate(true)->save($post);
        }
        if (false === $user_address_save_result) {
            return ['status' => -1, 'msg' => '保存失败', 'result' => $user_address->getError()];
        }

        return ['status' => 1, 'msg' => '保存成功', 'result' => ''];
    }

    /**
     * 设置默认收货地址
     *
     * @param $user_id
     * @param $address_id
     */
    public function set_default($user_id, $address_id)
    {
        M('user_address')->where(['user_id' => $user_id])->save(['is_default' => 0]); //改变以前的默认地址地址状态
        $row = M('user_address')->where(['user_id' => $user_id, 'address_id' => $address_id])->save(['is_default' => 1]);
        if (!$row) {
            return false;
        }

        return true;
    }

    /**
     * 重新设置登录密码 By J.
     *
     * @param $user_id
     * @param $new_password
     * @param $confirm_password
     * @param $isApp
     *
     * @return array
     */
    public function resetPassword($user_id, $new_password, $confirm_password, $isApp = false)
    {
        $new_password = htmlspecialchars($new_password, ENT_NOQUOTES, 'UTF-8', false);
        if (!check_password($new_password)) {
            return ['status' => -1, 'msg' => '密码格式为6-20位字母数字组合', 'result' => ''];
        }
        if (!$isApp && $new_password != $confirm_password) {
            return ['status' => -1, 'msg' => '两次密码输入不一致', 'result' => ''];
        }
        $old_password = M('users')->where('user_id', $user_id)->getField('password');
        if (systemEncrypt($new_password) == $old_password) {
            return ['status' => -1, 'msg' => '设置失败,你重新设置的密码必须要跟原来的密码不一样。', 'result' => ''];
        }
        $row = M('users')->where('user_id', $user_id)->save(['password' => systemEncrypt($new_password)]);
        if (!$row) {
            return ['status' => -1, 'msg' => '设置失败', 'result' => ''];
        }
        // 更新缓存
        $user = M('users')->where('user_id', $user_id)->find();
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
        return ['status' => 1, 'msg' => '设置成功', 'result' => ''];
    }

    /**
     * 设置登录密码 By J.
     *
     * @param $user_id
     * @param $new_password
     * @param $confirm_password
     *
     * @return array
     */
    public function setPassword($user_id, $new_password, $confirm_password)
    {
        $user = M('users')->where('user_id', $user_id)->find();
        if (strlen($new_password) < 6) {
            return ['status' => -1, 'msg' => '密码不能低于6位字符', 'result' => ''];
        }
        if ($new_password != $confirm_password) {
            return ['status' => -1, 'msg' => '两次密码输入不一致', 'result' => ''];
        }
        if ('' != $user['password']) {
            return ['status' => -1, 'msg' => '已存在密码，请勿重新设置', 'result' => ''];
        }
        $row = M('users')->where('user_id', $user_id)->save(['password' => systemEncrypt($new_password)]);
        if (!$row) {
            return ['status' => -1, 'msg' => '设置失败', 'result' => ''];
        }

        return ['status' => 1, 'msg' => '设置成功', 'result' => ''];
    }

    /**
     * 修改密码
     *
     * @param $user_id  用户id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     * @param bool|true $is_update
     *
     * @return array
     */
    public function password($user_id, $old_password, $new_password, $confirm_password, $is_update = true)
    {
        $user = M('users')->where('user_id', $user_id)->find();
        $new_password = htmlspecialchars($new_password, ENT_NOQUOTES, 'UTF-8', false);
        if (!check_password($new_password)) {
            return ['status' => -1, 'msg' => '密码格式为6-20位字母数字组合', 'result' => ''];
        }
        if ($new_password !== $confirm_password) {
            return ['status' => -1, 'msg' => '两次密码输入不一致', 'result' => ''];
        }
        //验证原密码
        if ($is_update && ('' != $user['password'] && systemEncrypt($old_password) != $user['password'])) {
            return ['status' => -1, 'msg' => '原密码验证失败', 'result' => ''];
        }
        $row = M('users')->where('user_id', $user_id)->save(['password' => systemEncrypt($new_password)]);
        if (!$row) {
            return ['status' => -1, 'msg' => '修改失败', 'result' => ''];
        }
        // 更新缓存
        $user = M('users')->where('user_id', $user_id)->find();
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);

        return ['status' => 1, 'msg' => '修改成功', 'result' => ''];
    }

    /**
     *  针对 APP 修改密码的方法.
     *
     * @param $user_id  用户id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param bool $is_update
     *
     * @return array
     */
    public function passwordForApp($user_id, $old_password, $new_password, $is_update = true)
    {
        $user = M('users')->where('user_id', $user_id)->find();
        if (strlen($new_password) < 6) {
            return ['status' => -1, 'msg' => '密码不能低于6位字符', 'result' => ''];
        }
        //验证原密码
        if ($is_update && ('' != $user['password'] && $old_password != $user['password'])) {
            return ['status' => -1, 'msg' => '旧密码错误', 'result' => ''];
        }

        $row = M('users')->where("user_id='{$user_id}'")->update(['password' => $new_password]);
        if (!$row) {
            return ['status' => -1, 'msg' => '密码修改失败', 'result' => ''];
        }

        return ['status' => 1, 'msg' => '密码修改成功', 'result' => ''];
    }

    /**
     * 设置支付密码
     *
     * @param $user_id  用户id
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     */
    public function paypwd($user_id, $new_password, $confirm_password, $userToken = null)
    {
        $new_password = htmlspecialchars($new_password, ENT_NOQUOTES, 'UTF-8', false);
        if (!check_password($new_password, 'pay')) {
            return ['status' => -1, 'msg' => '密码格式为6位数字', 'result' => ''];
        }
        if ($new_password != $confirm_password) {
            return ['status' => -1, 'msg' => '两次密码输入不一致', 'result' => ''];
        }
        $row = M('users')->where('user_id', $user_id)->update(['paypwd' => systemEncrypt($new_password)]);
        if (!$row) {
            return ['status' => -1, 'msg' => '支付密码重复', 'result' => ''];
        }
        $url = TokenLogic::getValue('payPriorUrl', $userToken);
        $url = $url ?? U('User/userinfo');
        session('payPriorUrl', null);
        (new Redis)->rm('payPriorUrl_' . $userToken);
        // 更新缓存
        $user = M('users')->where('user_id', $user_id)->find();
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);

        return ['status' => 1, 'msg' => '修改成功', 'url' => $url];
    }

    /**
     *  针对 APP 修改支付密码的方法.
     *
     * @param $user_id  用户id
     * @param $new_password  新密码
     *
     * @return array
     */
    public function payPwdForApp($user_id, $new_password)
    {
        if (strlen($new_password) < 6) {
            return ['status' => -1, 'msg' => '密码不能低于6位字符', 'result' => ''];
        }

        $row = Db::name('users')->where(['user_id' => $user_id])->update(['paypwd' => $new_password]);
        if (!$row) {
            return ['status' => -1, 'msg' => '密码修改失败', 'result' => ''];
        }

        return ['status' => 1, 'msg' => '密码修改成功', 'result' => ''];
    }

    /**
     * 发送验证码: 该方法只用来发送邮件验证码, 短信验证码不再走该方法.
     *
     * @param $sender 接收人
     * @param $type 发送类型
     *
     * @return json
     */
    public function send_email_code($sender)
    {
        $sms_time_out = tpCache('sms.sms_time_out');
        $sms_time_out = $sms_time_out ? $sms_time_out : 180;
        //获取上一次的发送时间
        $send = S('validate_code_' . $sender);
        if (!empty($send) && $send['time'] > time() && $send['sender'] == $sender) {
            //在有效期范围内 相同号码不再发送
            $res = ['status' => -1, 'msg' => '规定时间内,不要重复发送验证码'];

            return $res;
        }
        $code = mt_rand(1000, 9999);
        //检查是否邮箱格式
        if (!check_email($sender)) {
            $res = ['status' => -1, 'msg' => '邮箱码格式有误'];

            return $res;
        }
        $send = send_email($sender, '验证码', '您好，你的验证码是：' . $code);
        if (1 == $send['status']) {
            $info['code'] = $code;
            $info['sender'] = $sender;
            $info['is_check'] = 0;
            $info['time'] = time() + $sms_time_out; //有效验证时间
            S('validate_code_' . $sender, $info, 180);
            $res = ['status' => 1, 'msg' => '验证码已发送，请注意查收'];
        } else {
            $res = $send;
        }

        return $res;
    }

    /**
     * 检查短信/邮件验证码验证码
     *
     * @param $code
     * @param $sender
     * @param string $type
     * @param int $session_id
     * @param int $scene
     *
     * @return array
     */
    public function check_validate_code($code, $sender, $type = 'email', $session_id = 0, $scene = -1)
    {
//        if ($code == '1238') {
//            return ['status' => 1];
//        }
        $timeOut = time();
        $inValid = true;  //验证码失效

        // // 测试代码
        // if($code == '1234')
        // {
        //     return array('status'=>1,'msg'=>'验证成功');
        // }

        //短信发送否开启
        //-1:用户没有发送短信
        //空:发送验证码关闭
        $sms_status = checkEnableSendSms($scene);

        //邮件证码是否开启
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');

        if ('email' == $type) {
            if (!$reg_smtp_enable) {//发生邮件功能关闭
                $validate_code = TokenLogic::getCache('validate_code', $sender);
                $validate_code['sender'] = $sender;
                $validate_code['is_check'] = 1; //标示验证通过
                session('validate_code', $validate_code);
                S('validate_code_' . $sender, $validate_code, 180);

                return ['status' => 1, 'msg' => '邮件验证码功能关闭, 无需校验验证码'];
            }
            if (!$code) {
                return ['status' => -1, 'msg' => '请输入邮件验证码'];
            }
            //邮件
            $data = S('validate_code_' . $sender);
            $timeOut = $data['time'];
            if ($data['code'] != $code || $data['sender'] != $sender) {
                $inValid = false;
            }
        } else {
            if (-1 == $scene) {
                return ['status' => -1, 'msg' => '参数错误, 请传递合理的scene参数'];
            } elseif (0 == $sms_status['status']) {
                $data['sender'] = $sender;
                $data['is_check'] = 1; //标示验证通过
                session('validate_code', $data);
                S('validate_code_' . $sender, $data, 180);

                return ['status' => 1, 'msg' => '短信验证码功能关闭, 无需校验验证码'];
            }

            if (!$code) {
                return ['status' => -1, 'msg' => '请输入短信验证码'];
            }
            //短信
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 180;
            $data = M('sms_log')->where(['mobile' => $sender, 'session_id' => $session_id, 'status' => 1])->order('id DESC')->find();

            //file_put_contents('./test.log', json_encode(['mobile'=>$sender,'session_id'=>$session_id, 'data' => $data]));
            if (is_array($data) && $data['code'] == $code) {
                $data['sender'] = $sender;
                $timeOut = $data['add_time'] + $sms_time_out;
            } else {
                $inValid = false;
            }
        }

        if (empty($data)) {
            $res = ['status' => -1, 'msg' => '请先获取验证码'];
        } elseif ($timeOut < time()) {
            $res = ['status' => -1, 'msg' => '验证码已超时失效'];
        } elseif (!$inValid) {
            $res = ['status' => -1, 'msg' => '验证失败,验证码有误'];
        } else {
            $data['is_check'] = 1; //标示验证通过
            session('validate_code', $data);
            S('validate_code_' . $sender, $data, 180);
            $res = ['status' => 1, 'msg' => '验证成功'];
        }

        return $res;
    }

    /**
     * @time 2016/09/01
     * 设置用户系统消息已读
     */
    public function setSysMessageForRead()
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            M('user_message')->where(['user_id' => $user_info['user_id'], 'category' => 0])->save($data);
        }
    }

    /**
     * 设置用户消息已读.
     *
     * @param int $category 0:系统消息|1：活动消息
     * @param $msg_id
     *
     * @throws \think\Exception
     */
    public function setMessageForRead($category = 0, $msg_id, $user_info = [])
    {
        if (empty($user_info)) {
            $user_info = session('user');
        }
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            $set_where['user_id'] = $user_info['user_id'];
            $set_where['category'] = $category;
            $set_where['status'] = 0;
            if ($msg_id) {
                $set_where['message_id'] = $msg_id;
            }
            $updat_meg_res = Db::name('user_message')->where($set_where)->update($data);
            if ($updat_meg_res) {
                return ['status' => 1, 'msg' => '操作成功'];
            }
        }

        return ['status' => -1, 'msg' => '操失败'];
    }

    /**
     * 设置用户消息已读.
     *
     * @param int $category 0:系统消息|1：活动消息
     * @param $msg_id
     *
     * @throws \think\Exception
     */
    public function setMessageForDelete($category = 0, $msg_id)
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 2;
            $set_where['user_id'] = $user_info['user_id'];
            $set_where['category'] = $category;
            if ($msg_id) {
                $set_where['message_id'] = $msg_id;
            }
            $updat_meg_res = Db::name('user_message')->where($set_where)->update($data);
            if ($updat_meg_res) {
                return ['status' => 1, 'msg' => '操作成功'];
            }
        }

        return ['status' => -1, 'msg' => '操失败'];
    }

    /**
     * 设置用户消息已读.
     *
     * @param int $category 0:系统消息|1：活动消息
     * @param $msg_id
     *
     * @throws \think\Exception
     */
    public function setArticleForRead($msg_id, $user_info = [])
    {
        if (empty($user_info)) {
            $user_info = session('user');
        }
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            $set_where['user_id'] = $user_info['user_id'];
            // $set_where['category'] = $category;
            if ($msg_id) {
                $set_where['article_id'] = $msg_id;
            }

            $updat_meg_res = Db::name('user_article')->where($set_where)->update($data);
            if ($updat_meg_res) {
                return ['status' => 1, 'msg' => '操作成功'];
            }
        }

        return ['status' => -1, 'msg' => '操失败'];
    }

    /**
     * 设置用户消息已读.
     *
     * @param int $category 0:系统消息|1：活动消息
     * @param $msg_id
     *
     * @throws \think\Exception
     */
    public function setArticleForDelete($msg_id)
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 2;
            $set_where['user_id'] = $user_info['user_id'];
            // $set_where['category'] = $category;
            if ($msg_id) {
                $set_where['article_id'] = $msg_id;
            }
            $updat_meg_res = Db::name('user_article')->where($set_where)->update($data);
            if ($updat_meg_res) {
                return ['status' => 1, 'msg' => '操作成功'];
            }
        }

        return ['status' => -1, 'msg' => '操失败'];
    }

    /**
     * 获取访问记录.
     *
     * @param type $user_id
     * @param type $p
     *
     * @return type
     */
    public function getVisitLog($user_id, $p = 1)
    {
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $user_id)
            ->order('v.visittime desc')
            ->page($p, 20)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        return $visit_list;
    }

    /**
     * 上传头像.
     */
    public function upload_headpic($must_upload = true)
    {
        if ($_FILES['head_pic']['tmp_name']) {
            $file = request()->file('head_pic');
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];
            $dir = UPLOAD_PATH . 'head_pic/';
            if (!($_exists = file_exists($dir))) {
                mkdir($dir);
            }
            $parentDir = date('Ymd');
            $info = $file->validate($validate)->move($dir, true);
            if ($info) {
                $pic_path = '/' . $dir . $parentDir . '/' . $info->getFilename();
            } else {
                return ['status' => -1, 'msg' => $file->getError()];
            }
        } elseif ($must_upload) {
            return ['status' => -1, 'msg' => '图片不存在！'];
        }

        return ['status' => 1, 'msg' => '上传成功', 'result' => $pic_path];
    }

    /**
     * 账户明细.
     */
    public function account($user_id, $type = 'all')
    {
        if ('all' == $type) {
            $count = M('account_log')->where('user_money!=0 and user_id=' . $user_id)->count();
            $page = new Page($count, 16);
            $account_log = M('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where('user_money!=0 and user_id=' . $user_id)
                ->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $where = 'plus' == $type ? ' and user_money>0 ' : ' and user_money<0 ';
            $count = M('account_log')->where('user_id=' . $user_id . $where)->count();
            $page = new Page($count, 16);
            $account_log = Db::name('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where('user_id=' . $user_id . $where)
                ->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        }
        $result['account_log'] = $account_log;
        $result['page'] = $page;

        return $result;
    }

    /**
     * 积分明细.
     */
    public function points($user_id, $type = 'all')
    {
        if ('all' == $type) {
            $count = M('account_log')->where('user_id=' . $user_id . ' and pay_points!=0 ')->count();
            $page = new Page($count, 16);
            $account_log = M('account_log')->where('user_id=' . $user_id . ' and pay_points!=0 ')->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $where = 'plus' == $type ? ' and pay_points>0 ' : ' and pay_points<0 ';
            $count = M('account_log')->where('user_id=' . $user_id . $where)->count();
            $page = new Page($count, 16);
            $account_log = M('account_log')->where('user_id=' . $user_id . $where)->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        }

        $result['account_log'] = $account_log;
        $result['page'] = $page;

        return $result;
    }

    /**
     * 添加用户签到信息.
     *
     * @param $user_id int 用户id
     * @param $date date 日期
     *
     * @return array
     */
    public function addUserSign($user_id, $date)
    {
        $config = tpCache('sign');
        $data['user_id'] = $user_id;
        $data['sign_total'] = 1;
        $data['sign_last'] = $date;
        $data['cumtrapz'] = $config['sign_integral'];
        $data['sign_time'] = "$date";
        $data['sign_count'] = 1;
        $data['this_month'] = $config['sign_integral'];
        $result['status'] = false;
        $result['msg'] = '签到失败!';
        if (Db::name('user_sign')->add($data)) {
            $result['status'] = true;
            $result['msg'] = $config['sign_integral'];
            accountLog($user_id, 0, $config['sign_integral'], '第一次签到赠送' . $config['sign_integral'] . '积分', 0, 0, '', 0, 8);
        }

        return $result;
    }

    /**
     * 累计用户签到信息.
     *
     * @param $userInfo  array   用户信息
     * @param $date      date    日期
     *
     * @return array
     */
    public function updateUserSign($userInfo, $date)
    {
        $config = tpCache('sign');  // 默认2
        $update_data = [
            'sign_total' => ['exp', 'sign_total+' . 1],                                     //累计签到天数
            'sign_last' => ['exp', "'$date'"],                                             //最后签到时间
            'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_integral']],                //累计签到获取积分
            'sign_time' => ['exp', "CONCAT_WS(',',sign_time ,'$date')"],                   //历史签到记录
            'sign_count' => ['exp', 'sign_count+' . 1],                                     //连续签到天数
            'this_month' => ['exp', 'this_month+' . $config['sign_integral']],              //本月累计积分
        ];
        $daya = $userInfo['sign_last'];
        $dayb = date('Y-n-j', strtotime($date) - 86400);
        if ($daya != $dayb) {                                                               //不是连续签
            $update_data['sign_count'] = ['exp', 1];
        }
//        $mb = date('m', strtotime($date));
//        if (intval($mb) != intval(date("m", strtotime($daya)))) {                            //不是本月签到
//            $update_data['sign_count'] = ['exp', 1];
//            $update_data['sign_time']  = ['exp', "'$date'"];
//            $update_data['this_month'] = ['exp', $config['sign_integral']];
//        }
        $update = Db::name('user_sign')->where(['user_id' => $userInfo['user_id']])->update($update_data);
        $result['status'] = false;
        $result['msg'] = '签到失败!';
        if ($update > 0) {
            accountLog($userInfo['user_id'], 0, $config['sign_integral'], '签到赠送' . $config['sign_integral'] . '积分', 0, 0, '', 0, 8);
            $result['status'] = true;
            $result['msg'] = $config['sign_integral'];
            $userFind = Db::name('user_sign')->where(['user_id' => $userInfo['user_id']])->find();
            //满足额外奖励
            if ($userFind['sign_count'] >= $config['sign_signcount']) {
                $rewar_jifen = $config['sign_integral'] + $config['sign_award'];
                $result['msg'] = $rewar_jifen;
                $this->extraAward($userInfo, $config);
            }
        }

        return $result;
    }

    /**
     * 累计签到额外奖励.
     *
     * @param $userSingInfo array 用户信息
     */
    public function extraAward($userSingInfo)
    {
        $config = tpCache('sign');
        //满足额外奖励
        Db::name('user_sign')->where(['user_id' => $userSingInfo['user_id']])->update([
            'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_award']],
            'this_month' => ['exp', 'this_month+' . $config['sign_award']],
        ]);
        $msg = '连续签到奖励' . $config['sign_award'] . '积分';
        accountLog($userSingInfo['user_id'], 0, $config['sign_award'], $msg, 0, 0, '', 0, 8);
    }

    /**
     * 标识用户签到信息.
     *
     * @param $user_id int 用户id
     *
     * @return array
     */
    public function idenUserSign($user_id)
    {
        $config = tpCache('sign');
        $map['us.user_id'] = $user_id;
        $field = [
            'u.user_id as user_id',
            'u.nickname',
            'u.mobile',
            'us.*',
        ];
        $join = [['users u', 'u.user_id=us.user_id', 'left']];
        $info = Db::name('user_sign')->alias('us')->field($field)->join($join)->where($map)->find();
        ($info['sign_last'] != date('Y-n-j', time())) && $tab = '1';
        $signTime = explode(',', $info['sign_time']);
        $str = '';
        $tips = true;
        //是否标识历史签到
        if (date('m', strtotime($info['sign_last'])) == date('m', time())) {
            foreach ($signTime as $val) {
                $str .= date('j', strtotime($val)) . ',';
            }
        } else {
            $info['sign_count'] = 0; //不是本月清除连续签到
        }
        if ($info['sign_count'] >= $config['sign_signcount']) {
            $display_sign = $config['sign_award'] + $config['sign_integral'];
            $tips = false;
        } else {
            $display_sign = $config['sign_integral'];
        }
        $jiFen = ($config['sign_signcount'] * $config['sign_integral']) + $config['sign_award'];
        $reward_list = [];

        $info['is_sign_yesteday'] = ($info['sign_last'] == date('Y-n-j', strtotime('- 1day')) || $info['sign_last'] == date('Y-n-j')) ? 1 : 0;

        for ($i = 0; $i < $config['sign_signcount']; ++$i) {
            $data = [];
            $data['is_sign'] = 0;
            $j = $i + 1;
            $data['day'] = "第{$j}天";
            $data['integral'] = $config['sign_integral'];
            if ($info['sign_count'] % $config['sign_signcount'] > $i && 1 == $info['is_sign_yesteday']) {
                $data['is_sign'] = 1;
            }
            if ($i + 1 == $config['sign_signcount'] || $info['sign_count'] > $config['sign_signcount']) {
                $data['integral'] = $config['sign_integral'] + $config['sign_award'];
            }
            $reward_list[] = $data;
        }
        // $info['is_sign_yesteday'] = ($info['sign_last'] == date('Y-n-j',strtotime('- 1day')) && $info['sign_last'] !== date('Y-n-j')) ? 1 : 0;

        return ['info' => $info, 'str' => $str, 'jifen' => $jiFen, 'config' => $config, 'tab' => $tab ?? 0, 'display_sign' => $display_sign, 'reward_list' => $reward_list, 'tips' => $tips];
    }

    /**
     * 标识用户签到信息.
     *
     * @param $user_id int 用户id
     *
     * @return array
     */
    public function idenUserSignPc($user_id)
    {
        $config = tpCache('sign');
        $map['us.user_id'] = $user_id;
        $field = [
            'u.user_id as user_id',
            'u.nickname',
            'u.mobile',
            'us.*',
        ];
        $join = [['users u', 'u.user_id=us.user_id', 'left']];
        $info = Db::name('user_sign')->alias('us')->field($field)->join($join)->where($map)->find();
        ($info['sign_last'] != date('Y-n-j', time())) && $tab = '1';
        $signTime = explode(',', $info['sign_time']);
        $str = '';
        //是否标识历史签到
        if (date('m', strtotime($info['sign_last'])) == date('m', time())) {
            foreach ($signTime as $val) {
                $str .= date('j', strtotime($val)) . ',';
            }
        } else {
            $info['sign_count'] = 0; //不是本月清除连续签到
        }
        if ($info['sign_count'] >= $config['sign_signcount']) {
            $display_sign = $config['sign_award'] + $config['sign_integral'];
        } else {
            $display_sign = $config['sign_integral'];
        }
        $jiFen = ($config['sign_signcount'] * $config['sign_integral']) + $config['sign_award'];
        $reward_list = [];

        $info['is_sign_yesteday'] = ($info['sign_last'] == date('Y-n-j', strtotime('- 1day')) || $info['sign_last'] == date('Y-n-j')) ? 1 : 0;

        for ($i = 0; $i < $config['sign_signcount']; ++$i) {
            $data = [];
            $data['is_sign'] = 0;
            $j = $i + 1;
            $data['day'] = "第{$j}天";
            $data['integral'] = $config['sign_integral'];
            if ($info['sign_count'] % $config['sign_signcount'] > $i && 1 == $info['is_sign_yesteday']) {
                $data['is_sign'] = 1;
            }
            if ($i + 1 == $config['sign_signcount']) {
                $data['integral'] = $config['sign_integral'] + $config['sign_award'];
            }
            $reward_list[] = $data;
        }
        // $info['is_sign_yesteday'] = ($info['sign_last'] == date('Y-n-j',strtotime('- 1day')) && $info['sign_last'] !== date('Y-n-j')) ? 1 : 0;
        $info['sign_time'] = str_replace('-', '/', $info['sign_time']);
        $info['sign_time'] = explode(',', $info['sign_time']);

        return ['info' => $info, 'str' => $str, 'jifen' => $jiFen, 'config' => $config, 'tab' => $tab, 'display_sign' => $display_sign, 'reward_list' => $reward_list];
    }

    /**
     * 登录记录
     * @param $source
     */
    public function userLogin($source)
    {
        if ($source == 3) {
            // 查看是否是第一次使用APP登陆
            $isAppFirst = M('user_login_log')->where(['user_id' => $this->user_id, 'source' => 3])->value('id') ? 0 : 1;
        }
        M('user_login_log')->add([
            'user_id' => $this->user_id,
            'login_ip' => request()->ip(),
            'login_time' => time(),
            'login_date' => date('Y-m-d', time()),
            'source' => $source,
            'is_app_first' => $isAppFirst ?? 0
        ]);
        M('users')->where(['user_id' => $this->user_id])->update(['last_login_source' => $source]);
    }
}
