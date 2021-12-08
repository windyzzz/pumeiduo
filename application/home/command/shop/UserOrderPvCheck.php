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
        $orderLog =  $this->getUserOrderPv(1,6);
        if (!$orderLog){
            return ;
        }
        foreach ($orderLog as $oderLogItem){
            if ($oderLogItem['pv_sum'] <= 0){
                // 降级处理
            }
        }

    }

    protected function getUserOrderPv($userLevel,$monthNearly = 6,$pvType = 1){
        $userOrderLog = M('user u')
            ->join('user_order_pv_log olog','u.user_id=olog.user_id AND olog.chain_user_generation=0','LEFT')
            ->where('month','>=',date('Y-m',strtotime("-{$monthNearly} month")))
            ->where('u.level',$userLevel)
            ->group("u.user_id")
            ->field("u.user_id,u.level,SUM(olog.pv) as pv_sum")
            ->select();
        return $userOrderLog;
    }

}