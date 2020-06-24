<?php

namespace app\common\logic\supplier;


class AccountService extends Base
{
    /**
     * 查询商家/平台 预存款
     * @return array|mixed
     */
    public function storeMoney()
    {
        $data = [

        ];
        return $this->getData('/api_rechargeapi/queryStoreMoney', $data);
    }

    /**
     * 查询预存款 记录
     * @param $type 1充值记录  2消费记录
     * @param $page
     * @param int $num
     * @return array|mixed
     */
    public function rechargeLog($type, $page, $num = 20)
    {
        $data = [
            'type' => $type,
            'page' => $page,
            'num' => $num
        ];
        return $this->getData('/api_rechargeapi/queryRechargeLog', $data);
    }
}