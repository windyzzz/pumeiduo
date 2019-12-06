<?php

use think\migration\Seeder;

class SmsTemplateData extends Seeder
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        $data = [
            'sms_sign' => '圃美多乐活',
            'sms_tpl_code' => 'SMS_135802975',
            'tpl_content' => '您的验证码是${code}，该验证码3分钟内有效，请在页面中提交完成验证。',
            'send_scene' => '8',
            'add_time' => time()
        ];
//        $this->table('sms_template')->insert($data)->save();
    }
}