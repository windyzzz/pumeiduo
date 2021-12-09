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

        $newUserInfo = Db::name('users')->where('user_id',$new)->find();

        if (!$newUserInfo){
            return ['status' => 0, 'msg' => '用户不存在'];
        }
        Db::transaction(function () use ($userInfo,$newUserInfo){
            $updateData = [
                'first_leader'   => $newUserInfo['user_id'],
                'second_leader'  => $newUserInfo['first_leader'],
                'third_leader'   => $newUserInfo['second_leader'],
                'referrer_chain' => $newUserInfo['referrer_chain'] . $newUserInfo['user_id'] . ",",
            ];
            $updateUserRes = Db::name("users")->where('user_id',$userInfo['user_id'])->update($updateData);

            if ($updateUserRes){
                throw new \Exception('修改会员信息失败');
            }

            $updateFirstChildRes = Db::name('users')->where('first_leader',$userInfo['user_id'])->update([
                'second_leader' => $newUserInfo['user_id'],
                'third_leader'  => $newUserInfo['first_leader'],
            ]);

            $updateSecondChildRes = Db::name('users')->where('second_leader',$userInfo['user_id'])->update([
                'third_leader'  => $newUserInfo['first_leader'],
            ]);

            $table = Db::name('users')->getTable();
            $updateChildChainRes = Db::execute("UPDATE `{$table}` SET `referrer_chain` = REPLACE(referrer_chain,'{$userInfo['referrer_chain']}{$userInfo['user_id']},','{$newUserInfo['referrer_chain']}{$newUserInfo['user_id']},{$userInfo['user_id']},') WHERE `referrer_chain` LIKE '%{$userInfo['referrer_chain']}{$userInfo['user_id']},'");

            if (($updateFirstChildRes || $updateSecondChildRes) && !$updateChildChainRes){
                throw new \Exception('更新失败');
            }
        });

    }
}