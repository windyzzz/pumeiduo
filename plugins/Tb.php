<?php

Class Tb
{

    private $error = '';
    private $my_system = 2;//系统 1为直销  2商城  3仓储

    function getError()
    {
        return $this->error;
    }

    function add_tb($system, $type, $from_id, $status)
    {
        $data = array(
            'type' => $type,
            'from_id' => $from_id,
            'system' => $system,
            'status' => $status,
            'add_time' => NOW_TIME,
            'from_system' => $this->my_system,
            'tb_sn' => get_rand_str(6, 1, 2)
        );
        return M('tb')->data($data)->add();
    }

    /**
     * 到达的链接
     * @param $to_system
     * @return mixed
     */
    function get_url($to_system)
    {
        // 测试连接
        $test_ip = '61.244.8.79';
        $test_url_arr = array(
            1 => 'http://bestputest.lohas211.com/index.php/Tb/get_system',
            2 => '',
            3 => 'http://testerp.pumeiduo.com/index.php/Tb/get_system'
        );
        // 正式链接
        $online_ip = '61.238.101.138';
        $online_url_arr = array(
            1 => 'http://www.lohas211.com/index.php/Tb/get_system',
            2 => '',
            3 => 'http://192.168.194.7/index.php/Tb/get_system'
        );
        // 本地链接
        $local_url_arr = array(
            3 => 'http://pumeiduo.erp/index.php/Tb/get_system'
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

    function tb_now($system, $tb_data)
    {
        $url = $this->get_url($system);
        $request_send = $this->request_send($url, "POST", $url, array('result' => json_encode($tb_data)));
        return $request_send;
    }

    function request_send($url, $method, $host, $post_data = array())
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        if ($method == 'POST') {

            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);

            /*curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($post_data))
            );*/
        }

        $result = curl_exec($curl);
        $jsonarr = json_decode($result, true);
        return $jsonarr;
    }
}
