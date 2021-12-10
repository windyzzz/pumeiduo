<?php


namespace app\common\logic\user;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
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
        $userInfo = Db::name('users u')
            ->join("user_chain chain",'u.user_id=chain.user_id')
            ->where('u.user_id',$userId)
            ->find();
        if (!$userInfo){
            return ['status' => 0, 'msg' => '用户不存在'];
        }
        if ((integer)$userInfo['first_leader'] !== (integer)$old){
            return ['status' => 0, 'msg' => '用户上级信息不一致！'];
        }

        $newUserInfo = Db::name('users u')
            ->join('user_chain chain','u.user_id=chain.user_id')
            ->where('u.user_id',$new)->find();

        if (!$newUserInfo){
            return ['status' => 0, 'msg' => '用户不存在'];
        }
        Db::transaction(function () use ($userInfo,$newUserInfo){
            $updateData = [
                'first_leader'   => $newUserInfo['user_id'],
                'second_leader'  => $newUserInfo['first_leader'],
                'third_leader'   => $newUserInfo['second_leader'],
            ];
            $updateUserRes = Db::name("users")->where('user_id',$userInfo['user_id'])->update($updateData);
            $updateChainLogRes = Db::name("user_chain")
                ->where('user_id',$userInfo['user_id'])
                ->update(['referee_ids'=>$newUserInfo['referrer_chain'] . $newUserInfo['user_id'] . ","]);

            if (!$updateUserRes || !$updateChainLogRes){
                throw new \Exception('修改会员信息失败');
            }

            $updateFirstChildRes = Db::name('users')->where('first_leader',$userInfo['user_id'])->update([
                'second_leader' => $newUserInfo['user_id'],
                'third_leader'  => $newUserInfo['first_leader'],
            ]);

            $updateSecondChildRes = Db::name('users')->where('second_leader',$userInfo['user_id'])->update([
                'third_leader'  => $newUserInfo['first_leader'],
            ]);

            $table = Db::name('user_chain')->getTable();
            // UPDATE `tp_users` SET `referrer_chain` = REPLACE(referrer_chain,'9,','16,9,') WHERE `referrer_chain` LIKE '%9'
            $sql = sprintf("UPDATE `%s` SET `%s` = REPLACE(`%s`,'%s','%s') WHERE `referee_ids` LIKE '%s%%'",
                $table,
                'referee_ids',
                'referee_ids',
                "{$userInfo['referee_ids']}{$userInfo['user_id']},",
            "{$newUserInfo['referee_ids']}{$newUserInfo['user_id']},{$userInfo['user_id']},",
            "{$userInfo['referee_ids']}{$userInfo['user_id']},"
            );
            $updateChildChainRes = Db::execute($sql);

            if (($updateFirstChildRes || $updateSecondChildRes) && !$updateChildChainRes){
                throw new \Exception('更新失败');
            }

            $logRes = Db::name('user_referrer_log')->insertGetId([
                'user_id'             => $userInfo['user_id'],
                'user_level'          => $userInfo['distribut_level'],
                'user_referrer_chain' => $userInfo['referee_ids'],
                'old_referrer'        => $userInfo['first_leader'],
                'new_referrer'        => $newUserInfo['user_id'],
                'add_time'            => time(),
            ]);
            if (!$logRes){
                throw new \Exception('记录添加失败');
            }
        });


    }
}