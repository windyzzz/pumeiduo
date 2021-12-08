<?php
namespace app\home\command\shop;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;

class UserOrderPvCheck extends Command
{
    protected function configure()
    {
        $this->setName('user_order_pv_check_monthly')
            ->setDescription('每月检查会员个人业绩');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '512M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        $this->getUserOrderPv(1,1);

    }

    protected function getUserOrderPv($userLevel,$month,$pvType = 1){
        M('order_pv_log')->where('test',1)->select();
    }

}