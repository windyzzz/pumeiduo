<?php

namespace app\home\controller\api\supplier;

class Sync
{
    /**
     * 发送数据同步
     * @param $system
     * @param $type
     * @param $syncData
     * @return mixed
     */
    function sendSyncData($system, $type, $syncData)
    {
        $url = $this->get_url($system);
        $res = httpRequest($url, 'POST', ['type' => $type, 'data' => json_encode($syncData)]);
        return $res;
    }

    /**
     * 同步方法地址
     * @param $to_system
     * @return mixed
     */
    function get_url($to_system)
    {
        // 测试连接
        $test_ip = '61.238.101.139';
        $test_url_arr = array(
            1 => '',
            2 => '',
            3 => 'http://pmderp.meetlan.com/index.php/supplier.Sync/getSyncData'
        );
        // 正式链接
        $online_ip = '61.238.101.138';
        $online_url_arr = array(
            1 => '',
            2 => '',
            3 => 'http://192.168.194.7/index.php/supplier.Sync/getSyncData'
        );
        // 本地链接
        $local_url_arr = array(
            3 => 'http://pumeiduo.erp/index.php/supplier.Sync/getSyncData'
        );
        switch ($_SERVER['SERVER_ADDR']) {
            case $test_ip:
                return $test_url_arr[$to_system];
            case $online_ip:
                return $online_url_arr[$to_system];
            default:
                return $local_url_arr[$to_system];
        }
    }

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