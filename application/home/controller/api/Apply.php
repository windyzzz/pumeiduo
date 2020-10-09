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
            // 查看用户等级预升级记录
            $userPreLog = M('user_pre_distribute_log')->where(['user_id' => $this->user_id, 'status' => 0])->order('id DESC')->find();
            if (empty($userPreLog)) {
                $tips = "您尚未有资格开通金卡会员\r\n请购买SVIP升级套餐后\r\n按系统提示进行申请\r\n有任何疑问请联系客服" . tpCache('shop_info.mobile');
                return json(['status' => 0, 'msg' => $tips]);
            }
            $res = $userLogic->apply_customs($this->user_id, I('post.'), $userPreLog['order_id'], $this->isApp);
            if ($res) {
                return json(['status' => 1, 'msg' => $userLogic->getError(), 'result' => ['tips' => $userLogic->getError()]]);
            } else {
                return json(['status' => 0, 'msg' => $userLogic->getError()]);
            }
        } else {
//            $applyStatus = 11;      // 申请状态 -11不符合资格 11可以申请 0审核中 1审核完成
            if ($this->user['distribut_level'] >= 3) {
                // SVIP显示审核完成
                $applyStatus = 1;
                $trueName = $this->user['real_name'];
                $idCard = $this->user['id_cart'];
                $mobile = $this->user['mobile'];
            } else {
//                // 用户已升级的下级
//                $memberCount = M('users')->where(['distribut_level' => ['>=', 2]])->where(['first_leader' => $this->user_id])->count('user_id');
//                if (tpCache('basic.apply_check_num') > $memberCount) {
//                    $applyStatus = -11; // 不符合资格
//                }
                // 查看用户等级预升级记录
                $userPreLog = M('user_pre_distribute_log')->where(['user_id' => $this->user_id, 'status' => 0])->find();
                if (empty($userPreLog)) {
                    $applyStatus = -11; // 不符合资格
                    $tips = "您尚未有资格开通金卡会员\r\n请购买SVIP升级套餐后\r\n按系统提示进行申请\r\n有任何疑问请联系客服" . tpCache('shop_info.mobile');
                } else {
                    // 获取申请信息
                    $apply = M('apply_customs')->where(['user_id' => $this->user_id])->find();
                    if (!$apply || (isset($apply) && $apply['status'] == 2)) {
                        // 没有申请 / 申请记录已撤销
                        $applyStatus = 11;
                    } elseif ($apply['status'] == 0) {
                        // 审核中
                        $applyStatus = $apply['status'];
                        $trueName = $apply['true_name'];
                        $idCard = $apply['id_card'];
                        $mobile = $apply['mobile'];
                        $tips = "资料提交成功，请等待审核，审核时间为5-7个工作日有任何疑问请联系客服" . tpCache('shop_info.mobile');
                    } else {
                        // 审核完成
                        $applyStatus = $apply['status'];
                    }
                }
            }
            $return = [
                'status' => $applyStatus,
                'true_name' => $trueName ?? '',
                'id_card' => $idCard ?? '',
                'mobile' => $mobile ?? '',
                'referee_user_id' => $userLogic->nk($this->user['invite_uid'], 3) ?? '无',   // 推荐人
                'member_num' => 0,
                'service_phone' => tpCache('shop_info.mobile'),
                'card_num' => $this->user['user_name'],
                'notice' => M('article')->where(['article_id' => 105])->value('app_content'),
                'card_cover' => tpCache('basic.apply_card_cover') ?? '',
                'tips' => $tips ?? ''
            ];
            return json(['status' => 1, 'result' => $return]);
        }
    }
}