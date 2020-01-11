<?php

namespace app\home\controller\api;


use app\common\logic\UsersLogic;

class Apply extends Base
{
    /**
     * 金卡申请信息
     * @return \think\response\Json
     */
    public function info()
    {
        $userLogic = new UsersLogic();
        if ($this->request->isPost()) {
            $res = $userLogic->apply_customs($this->user_id, I('post.'));
            if ($res) {
                return json(['status' => 1, 'msg' => $userLogic->getError()]);
            } else {
                return json(['status' => 0, 'msg' => $userLogic->getError()]);
            }
        } else {
            $applyStatus = 11;      // 申请状态 -11不符合资格 11可以申请 0审核中 1审核完成
            $memberNum = tpCache('basic.apply_check_num');      // 申请资格下级人数
            $refereeUserId = $userLogic->nk($this->user['invite_uid'], 3) ?? '无';   // 推荐人

            // 用户已升级的下级
            $memberCount = M('users')->where(['distribut_level' => ['>=', 2]])->where(['first_leader' => $this->user_id])->count('user_id');
            if ($memberNum > $memberCount) {
                $applyStatus = -11; // 不符合资格
            } else {
                // 获取申请信息
                $apply = M('apply_customs')->where(['user_id' => $this->user_id])->find();
                if (!$apply || (isset($apply) && $apply['status'] == 2)) {
                    // 没有申请 / 申请记录已撤销
                    $applyStatus = 11;  // 可以申请
                } elseif ($apply['status'] == 0) {
                    $applyStatus = $apply['status'];   // 审核中
                    $trueName = $apply['true_name'];
                    $idCard = $apply['id_card'];
                    $mobile = $apply['mobile'];
                } else {
                    $applyStatus = $apply['status'];    // 审核完成
                }
            }

            $return = [
                'status' => $applyStatus,
                'true_name' => $trueName ?? '',
                'id_card' => $idCard ?? '',
                'mobile' => $mobile ?? '',
                'referee_user_id' => $refereeUserId,
                'member_num' => $memberNum,
                'service_phone' => tpCache('shop_info.mobile'),
                'card_num' => $this->user['user_name'],
                'notice' => M('article')->where(['article_id' => 105])->value('app_content'),
                'card_cover' => tpCache('basic.apply_card_cover')
            ];
            return json(['status' => 1, 'result' => $return]);
        }
    }
}