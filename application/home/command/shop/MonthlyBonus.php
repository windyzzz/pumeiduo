<?php


namespace app\home\command\shop;


use app\common\logic\pv\UserOrderPvLogic;
use think\console\Input;
use think\console\Output;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;

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
     * @throws Exception
     */
    protected function execute(Input $input, Output $output)
    {
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        $this->getMonthlyPv();
        $this->corporateMembershipServiceBonus();
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
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
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     * @throws PDOException
     */
    protected function publicityAndEducation($userId,$teamPv){
        $sendBonus = bcmul($teamPv,bcdiv('8','100',2),2);
        $this->output->writeln("用户：「{$userId}」获得宣传教育费 {$sendBonus}");
        accountLog($userId, $sendBonus, 0, '业绩达标获得宣传教育费', 0, 0, '', 0, 28, false);
    }

    /**
     * 经销商辅导费
     * @param $userId
     * @param $secondUserPvLog
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     * @throws PDOException
     */
    protected function dealerGuidance($userId,$secondUserPvLog){
        foreach ($secondUserPvLog as $item){
            if ($item['user_id'] === $userId){
                $sendBonus = bcmul($item['total_team_pv'],bcdiv('3','100',2),2);
                $this->output->writeln("用户：「{$userId}」获得经销商辅导费 {$sendBonus}");
                accountLog($userId, $sendBonus, 0, '业绩达标获得经销商辅导费', 0, 0, '', 0, 29, false);
                return ;
            }
        }
    }

    /**
     * 公司会员服务奖金
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException|Exception
     */
    protected function corporateMembershipServiceBonus(){
        $pvLogic = new UserOrderPvLogic();
        $pvLog = $pvLogic->getUserNearly2MonthPv($this->time);
        $userLog = [];
        foreach ($pvLog as $item){
            if (isset($userLog[$item['user_id']])){
                $userLog[$item['user_id']] = [];
            }
            $userLog[$item['user_id']][] = $item;

        }
        foreach ($userLog as $userId => $logArr){
            $less50000 = 0;
            $more80000 = 0;
            $lastMonthTeamPv = 0; // 上一个月的公司业绩
            array_walk($logArr,function ($item, $key) use (&$less50000,&$more80000,&$lastMonthTeamPv){
                if ($item['month'] == date('Y-m',strtotime(date('Y-m',$this->time)." -1 month")) ){
                    $lastMonthTeamPv = $item['total_team_pv'];
                }
                if ($item['total_team_pv'] < 50000){
                    $less50000++;
                }
                if ($item['total_team_pv'] > 80000){
                    $more80000++;
                }
            });
            if ($less50000 == 2){
                // 连续两个月小于50000 转为考核期
            }

            if ($more80000 == 2){
                // 连续两个月大于80000 转为合格期
            }

            $sendBonus = bcmul($lastMonthTeamPv,bcdiv('3','100',2),2);
            $this->output->writeln("用户：「{$userId}」获得经销商辅导费 {$sendBonus}");
            accountLog($userId, $sendBonus, 0, '获得公司会员服务奖金', 0, 0, '', 0, 30, false);

        }
    }

}