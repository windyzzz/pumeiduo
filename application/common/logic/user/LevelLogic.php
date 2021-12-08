<?php
namespace app\common\logic\user;

use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

/**
 * 会员等级逻辑类
 * Class LevelLogic
 * @package app\common\logic\user
 */
class LevelLogic
{
    /**
     * 会员等级变更
     * @param $from integer 原来的等级
     * @param $to integer 变更等级
     * @param $userId
     * @param string $levelType distribut_level=分销商级别
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function change($from,$to,$userId,$levelType = 'distribut_level'){
        if ((integer)$from === (integer)$to){
            return ['status' => 0, 'msg' => '变更等级不能与当前等级一样'];
        }
        $userInfo = Db::name('users')->where('user_id',$userId)->find();
        if (!$userInfo){
            return ['status' => 0, 'msg' => '用户不存在'];
        }
        if ((integer)$userInfo[$levelType] === (integer)$to){
            return ['status' => 0, 'msg' => '变更等级与当前等级一致！'];
        }

        if ((integer)$userInfo[$levelType] !== (integer)$from){
            return ['status' => 0, 'msg' => '当前等级与会员等级不一致'];
        }

        Db::transaction(function () use ($userId,$to,$from,$levelType){
            $logRes = logDistribut('',$userId,$to,$from,4);
            if (!$logRes){
                throw new \Exception('更新等级日志表失败');
            }

             $userUpdateRes = Db::name('users')->where('user_id', $userId)->update([
                 $levelType => $to,
                'is_distribut' => 1,
            ]);
            if (!$userUpdateRes){
                throw new \Exception('更新会员数据失败');
            }
        });

        return ['status' => 1, 'msg' => "操作成功"];

    }
}