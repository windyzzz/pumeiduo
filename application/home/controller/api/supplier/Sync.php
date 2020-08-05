<?php

namespace app\home\controller\api\supplier;

class Sync
{
    /**
     * 接收数据同步
     * @return false|string
     */
    public function getSyncData()
    {
        $type = $_POST['type'];
        $data = json_decode($_POST['data'], true);
        switch ($type) {
            case 'return_handle':
                $res = (new Order())->returnHandle($data);
                break;
            default:
                $res = ['status' => 0, 'msg' => '传输类型错误'];
        }
        return json_encode($res);
    }
}