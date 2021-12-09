<?php


namespace app\home\command\shop;


use app\common\logic\pv\UserOrderPvLogic;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class MonthlyBonus extends BaseCommand
{
    protected function configure()
    {
        $this->setName('monthly_bonus')->setDescription('月结奖金');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function execute(Input $input, Output $output)
    {
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        // 更新商品信息（excel文件导入）
        $this->getMonthlyPv();
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    protected function getMonthlyPv(){
        $pvLogic = new UserOrderPvLogic();
        $month = date("Y-m",strtotime(date("Y-m",$this->time) . " -1 month"));
        $pvLogic->setDate(null,$month);

        $userPvLog = $pvLogic->getPvByUserLevel(1);
        $secondUserPvLog = $pvLogic->getPvByUserChainNumber(2);
        foreach ($userPvLog as $item){
            if ($item['total_pv'] >= 300 && $item['total_team_pv'] >= 1000){
                $this->publicityAndEducation($item['user_id'],$item['total_team_pv']);
                $this->dealerGuidance($item['user_id'],$secondUserPvLog);
            }else{
                continue;
            }
        }

    }

    /**
     * 宣传教育费
     * @param $userId
     * @param $teamPv
     */
    protected function publicityAndEducation($userId,$teamPv){
        $sendBonus = bcmul($teamPv,bcdiv('8','100',2),2);
        $this->output->writeln("用户：「{$userId}」获得宣传教育费 {$sendBonus}");
    }

    /**
     * 经销商辅导费
     * @param $userId
     * @param $secondUserPvLog
     */
    protected function dealerGuidance($userId,$secondUserPvLog){
        foreach ($secondUserPvLog as $item){
            if ($item['user_id'] === $userId){
                $sendBonus = bcmul($item['total_team_pv'],bcdiv('3','100',2),2);
                $this->output->writeln("用户：「{$userId}」获得经销商辅导费 {$sendBonus}");
                return ;
            }
        }
    }

    /**
     * 公司会员服务奖金
     */
    protected function corporateMembershipServiceBonus(){
        $pvLogic = new UserOrderPvLogic();
        $pvLog = $pvLogic->getUserNearly2MonthPv($this->time);
    }

}