<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

/**
 * Description of SmsLogic.
 *
 * 短信类
 */
class SmsLogic
{
    private $config;

    public function __construct()
    {
        $this->config = tpCache('sms') ?: [];
    }

    /**
     * 发送短信
     * @param $scene
     * @param $sender
     * @param $params
     * @param string $userToken
     * @return array
     */
    public function sendSms($scene, $sender, $params, $userToken = '')
    {
        // 模板
        $smsTemp = M('sms_template')->where('send_scene', $scene)->find();
        if (!$smsTemp) {
            $smsTemp = M('sms_template')->where('send_scene', 1)->find();
            $scene = 1;
        }
        // 参数
        $code = !empty($params['code']) ? $params['code'] : false;
        $userId = !empty($params['user_id']) ? $params['user_id'] : false;
        $user_name = !empty($params['user_name']) ? $params['user_name'] : false;
        // 模板字段
        $smsParams = [
            1 => ['code' => $code],                                                                                                          //1. 用户注册 (验证码类型短信只能有一个变量)
            9 => ['user_name' => $user_name, 'user_id1' => $userId, 'user_id2' => $userId],
            10 => []
        ];
        $smsParam = $smsParams[$scene];
        // 提取发送短信内容
        $scenes = C('SEND_SCENE');
        $msg = $scenes[$scene][1];
        foreach ($smsParam as $k => $v) {
            $msg = str_replace('${' . $k . '}', $v, $msg);
        }

        // 发送记录存储数据库
        if (empty($userToken)) {
            $session_id = session_id();
        } else {
            $session_id = $userToken;
        }
        $log_id = M('sms_log')->insertGetId(['mobile' => $sender, 'code' => $code, 'add_time' => time(), 'session_id' => $session_id, 'status' => 0, 'scene' => $scene, 'msg' => $msg]);
        if ('' != $sender && check_mobile($sender)) {
            // 如果是正常的手机号码才发送
            try {
                $resp = $this->sendSmsByFeige($sender, $smsTemp['sms_sign'], $smsParam, $smsTemp['sms_tpl_code']);
            } catch (\Exception $e) {
                $resp = ['status' => -1, 'msg' => $e->getMessage()];
            }
            if (1 == $resp['status']) {
                M('sms_log')->where(['id' => $log_id])->save(['status' => 1]); // 修改发送状态为成功
            } else {
                M('sms_log')->where(['id' => $log_id])->update(['error_msg' => $resp['msg']]); // 发送失败, 将发送失败信息保存数据库
            }
            return $resp;
        }
        return $result = ['status' => -1, 'msg' => '接收手机号不正确[' . $sender . ']'];
    }

    /**
     * 发送短信（飞鸽）
     * @param $mobile
     * @param $smsSign
     * @param $smsParam
     * @param $templateCode
     * @return array
     */
    private function sendSmsByFeige($mobile, $smsSign, $smsParam, $templateCode)
    {
        $Content = implode('||', $smsParam);
        $data = array(
            'Account' => 'pumeiduo',
            'Pwd' => '284a62d65f82a5c20901fae7c',
            'Content' => $Content,
            'Mobile' => $mobile,
            'TemplateId' => $templateCode,
            'SignId' => $smsSign
        );

        $url = "http://api.feige.ee/SmsService/Template";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); //在HTTP请求中包含一个"User-Agent: "头的字符串。
        curl_setopt($curl, CURLOPT_HEADER, 0); //启用时会将头文件的信息作为数据流输出。
        curl_setopt($curl, CURLOPT_POST, true); //发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);//Post提交的数据包
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); //启用时会将服务器服务器返回的"Location: "放在header中递归的返回给服务器，使用CURLOPT_MAXREDIRS可以限定递归返回的数量。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); //文件流形式
        curl_setopt($curl, CURLOPT_TIMEOUT, 20); //设置cURL允许执行的最长秒数。
        $content = curl_exec($curl);
        curl_close($curl);
        unset($curl);
        $content = json_decode($content, true);
        if ($content['Code'] === 0) {
            // 成功
            return array('status' => 1, 'msg' => $content['Message']);
        } else {
            return array('status' => 0, 'msg' => $content['Message']);
        }

//        $type = (int)$this->config['sms_platform'] ?: 0;
//        switch ($type) {
//            case 0:
//                $result = $this->sendSmsByAlidayu($mobile, $smsSign, $smsParam, $templateCode);
//                break;
//            case 1:
//                $result = $this->sendSmsByAliyun($mobile, $smsSign, $smsParam, $templateCode);
//                break;
//            case 2:
//                //重新组装发送内容, 将变量内容组装成:  13800138006##张三格式
//                foreach ($smsParam as $k => $v) {
//                    $contents[] = $v;
//                }
//                $content = implode($contents, '##');
//                $result = $this->sendSmsByCloudsp($mobile, $smsSign, $content, $templateCode);
//                break;
//            default:
//                $result = ['status' => -1, 'msg' => '不支持的短信平台'];
//        }
//        return $result;
    }

    /**
     * 发送短信（阿里大于）.
     *
     * @param $mobile  手机号码
     * @param $code    验证码
     *
     * @return bool 短信发送成功返回true失败返回false
     */
    private function sendSmsByAlidayu($mobile, $smsSign, $smsParam, $templateCode)
    {
        //时区设置：亚洲/上海
        date_default_timezone_set('Asia/Shanghai');
        //这个是你下面实例化的类
        vendor('Alidayu.TopClient');
        //这个是topClient 里面需要实例化一个类所以我们也要加载 不然会报错
        vendor('Alidayu.ResultSet');
        //这个是成功后返回的信息文件
        vendor('Alidayu.RequestCheckUtil');
        //这个是错误信息返回的一个php文件
        vendor('Alidayu.TopLogger');
        //这个也是你下面示例的类
        vendor('Alidayu.AlibabaAliqinFcSmsNumSendRequest');

        $c = new \TopClient();
        //App Key的值 这个在开发者控制台的应用管理点击你添加过的应用就有了
        $c->appkey = $this->config['sms_appkey'];
        //App Secret的值也是在哪里一起的 你点击查看就有了
        $c->secretKey = $this->config['sms_secretKey'];
        //这个是用户名记录那个用户操作
        $req = new \AlibabaAliqinFcSmsNumSendRequest();
        //代理人编号 可选
        $req->setExtend('123456');
        //短信类型 此处默认 不用修改
        $req->setSmsType('normal');
        //短信签名 必须
        $req->setSmsFreeSignName($smsSign);
        //短信模板 必须
        $smsParam = json_encode($smsParam, JSON_UNESCAPED_UNICODE); // 短信模板中字段的值
        $req->setSmsParam($smsParam);
        //短信接收号码 支持单个或多个手机号码，传入号码为11位手机号码，不能加0或+86。群发短信需传入多个号码，以英文逗号分隔，
        $req->setRecNum("$mobile");
        //短信模板ID，传入的模板必须是在短信平台“管理中心-短信模板管理”中的可用模板。
        $req->setSmsTemplateCode($templateCode); // templateCode

        $c->format = 'json';
        //发送短信
        $resp = $c->execute($req);

        //短信发送成功返回True，失败返回false
        if ($resp && $resp->result) {
            return ['status' => 1, 'msg' => $resp->sub_msg];
        }

        return ['status' => -1, 'msg' => $resp->msg . ' ,sub_msg :' . $resp->sub_msg . ' subcode:' . $resp->sub_code];
    }

    /**
     * 发送短信（天瑞短信）.
     *
     * @param unknown $mobile
     * @param unknown $smsSign
     * @param unknown $smsParam
     * @param unknown $templateCode
     */
    private function sendSmsByCloudsp($mobile, $smsSign, $smsParam, $templateCode)
    {
        $url = 'http://api.1cloudsp.com/api/v2/send';
        $post_data = ['accesskey' => $this->config['sms_appkey'],
            'secret' => $this->config['sms_secretKey'],
            'sign' => $smsSign,
            'templateId' => $templateCode,
            'mobile' => $mobile,
            'content' => $smsParam,];

        $resp = httpRequest($url, 'post', $post_data);
        $resp = json_decode($resp);
        if ($resp && 0 == $resp->code) {
            return ['status' => 1, 'msg' => '已发送成功, 请注意查收'];
        }

        if ('9006' == $resp->code) {
            return ['status' => -1, 'msg' => '请在后台配置短信或按照文档接入短信' . $resp->code];
        }

        return ['status' => -1, 'msg' => '发生失败:' . $resp->msg . ' , 错误代码:' . $resp->code];
    }

    /**
     * 发送短信（阿里云短信）.
     *
     * @param $mobile  手机号码
     * @param $code    验证码
     *
     * @return bool 短信发送成功返回true失败返回false
     */
    private function sendSmsByAliyun($mobile, $smsSign, $smsParam, $templateCode)
    {
        include_once './vendor/aliyun-php-sdk-core/ConfigData.php';
        include_once './vendor/Dysmsapi/Request/V20170525/SendSmsRequest.php';

        $accessKeyId = $this->config['sms_appkey'];
        $accessKeySecret = $this->config['sms_secretKey'];

        //短信API产品名
        $product = 'Dysmsapi';
        //短信API产品域名
        $domain = 'dysmsapi.aliyuncs.com';
        //暂时不支持多Region
        $region = 'cn-hangzhou';

        //初始化访问的acsCleint
        $profile = \DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
        \DefaultProfile::addEndpoint('cn-hangzhou', 'cn-hangzhou', $product, $domain);
        $acsClient = new \DefaultAcsClient($profile);

        $request = new \Dysmsapi\Request\V20170525\SendSmsRequest();
        //必填-短信接收号码
        $request->setPhoneNumbers($mobile);
        //必填-短信签名
        $request->setSignName($smsSign);
        //必填-短信模板Code
        $request->setTemplateCode($templateCode);
        // 短信模板中字段的值
        $smsParam = json_encode($smsParam, JSON_UNESCAPED_UNICODE);
        //选填-假如模板中存在变量需要替换则为必填(JSON格式)
        $request->setTemplateParam($smsParam);
        //选填-发送短信流水号
        //$request->setOutId("1234");

        //发起访问请求
        $resp = $acsClient->getAcsResponse($request);

        //短信发送成功返回True，失败返回false
        if ($resp && 'OK' == $resp->Code) {
            return ['status' => 1, 'msg' => $resp->Code];
        }

        return ['status' => -1, 'msg' => $resp->Message . '. Code: ' . $resp->Code];
    }
}
