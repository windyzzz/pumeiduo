<?php
namespace app\home\command\shop;

use app\common\logic\user\ReferrerLogic;
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
        foreach ($orderLog as $orderLogItem){
            if ($orderLogItem['pv_sum'] <= 0){
                // 降级处理
                $this->output->writeln("用户：{$orderLogItem['user_name']}({$orderLogItem['user_id']}) 降级处理");

                $userLevelLogic = new ReferrerLogic();

                $userLevelLogic->change($orderLogItem['user_id'],$orderLogItem['first_leader'],$orderLogItem['second_leader']);
            }
        }

    }

    protected function getUserOrderPv($userLevel,$monthNearly = 6,$pvType = 1){

        $subQuery = Db::name('user_order_pv_log')
            ->where('month','>=',date('Y-m',strtotime("-{$monthNearly} month")))
            ->group('user_id')
            ->field('*,SUM(pv) as pv_sum')
            ->buildSql();
        $userOrderLog = Db::name('users u')->join($subQuery. " olog", 'u.user_id=olog.user_id','LEFT')
            ->where('u.level',$userLevel)
            ->group("u.user_id")
            ->field("u.user_id,u.level,u.user_name,u.first_leader,u.second_leader,SUM(olog.pv) as pv_sum")
            ->select();
        return $userOrderLog;
    }

}