<?php


namespace app\home\command\shop;


use app\common\logic\pv\UserOrderPvLogic;
use think\console\Input;
use think\console\Output;

class MonthlyBonus extends BaseCommand
{
    protected function configure()
    {
        $this->setName('monthly_bonus')->setDescription('月结奖金');
    }

    protected function execute(Input $input, Output $output)
    {
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        // 更新商品信息（excel文件导入）
        $this->publicityAndEducation();
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    /**
     * 宣传教育费
     */
    protected function publicityAndEducation(){
        $pvLogic = new UserOrderPvLogic();
        $month = date("Y-m",strtotime(date("Y-m",$this->time) . " -1 month"));
        $userPvLog = $pvLogic->getPvByUserLevel(1,null,$month);
        foreach ($userPvLog as $item){
            if ($item['total_pv'] >= 300 && $item['total_team_pv'] >= 1000){
                $sendBonus = bcmul($item['total_team_pv'],bcdiv('8','100',2),2);
                $this->output->writeln("用户：「{$item['user_id']}」获得宣传教育费 {$sendBonus}");
            }else{
                continue;
            }
        }
    }

    /**
     * 经销商辅导费
     */
    protected function dealerGuidance(){
        $pvLogic = new UserOrderPvLogic();
        $month = date("Y-m",strtotime(date("Y-m",$this->time) . " -1 month"));
        $userPvLog = $pvLogic->getPvByUserLevel(1,null,$month);
        foreach ($userPvLog as $item){
            if ($item['total_pv'] >= 300 && $item['total_team_pv'] >= 1000){
                $sendBonus = bcmul($item['total_team_pv'],bcdiv('8','100',2),2);
                $this->output->writeln("用户：「{$item['user_id']}」获得宣传教育费 {$sendBonus}");
            }else{
                continue;
            }
        }
    }

}