<?php


namespace app\common\logic\user;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

/**
 * 会员推荐逻辑类
 * Class ReferrerLogic
 * @package app\common\logic\user
 */
class ReferrerLogic
{
    /**
     * 变更推荐人
     * @param $userId
     * @param $old
     * @param $new
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function change($userId,$old,$new){
        if ($new === $old){
            return ['status' => 0, 'msg' => '变更前后推荐人不能一样！'];
        }
        $userInfo = Db::name('users')->where('user_id',$userId)->find();
        if (!$userInfo){
            return ['status' => 0, 'msg' => '用户不存在'];
        }
        if ((integer)$userInfo['first_leader'] !== (integer)$old){
            return ['status' => 0, 'msg' => '用户上级信息不一致！'];
        }
    }
}