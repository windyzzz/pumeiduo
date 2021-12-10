<?php


namespace app\home\command\shop;


use think\Collection;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class MonthlyAgentGrade extends BaseCommand
{
    protected function configure()
    {
        $this->setName('monthly_agent_grade')->setDescription('经销商每月定级');
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
        $this->setAgentUserMonthlyGrade($this->getAgentUserWithGradeLog());
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    /**
     * 获取经销商销售等级及业绩
     * @return bool|false|\PDOStatement|string|Collection
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    protected function getAgentUserWithGradeLog(){
        return Db::name('users u')
            ->join('user_agent_sale_grade_log glog','u.user_id=glog.user_id AND glog.status=1','LEFT')
            ->join('user_order_pv_log pv','u.user_id=pv.chain_user_id','LEFT')
            ->where('u.level',1) // 只查询经销商级别
            ->group('u.user_id')
            ->field('u.user_id,glog.grade,glog.user_level,glog.expire_time,glog.status,sum(pv.team_pv) team_pv_sum')
            ->select();
    }

    /**
     * 设置经销商销售等级
     * @param $userAgentList
     */
    protected function setAgentUserMonthlyGrade($userAgentList){
        $time = time();
        $effect_time = strtotime(date('Y-m',$time) . " +1 month");
        $expire_time = strtotime(date('Y-m',$effect_time) . ' +3 month');


        if (empty($userAgentList)){
            return;
        }
        foreach ($userAgentList as $user){
            $grade = false;
            if (empty($user['team_pv_sum'])){
                continue;
            }
            $grade = $this->getPvGrade($user['team_pv_sum']);
            if (!$user['grade']){
                // 未定级的经销商
                if ($grade === false){
                    continue;
                }
            }else{
                if ($user['grade'] >= $grade && $user['expire_time'] > $time ){
                    continue;
                }
                if ($user['expire_time'] <= $time){
                    $grade = $this->getPvGrade($this->getNearly3MonthSvgPv($user['user_id']));
                }
            }

            if ($grade !== false){
                Db::transaction(function () use ($user,$grade,$time,$effect_time,$expire_time){
                    Db::name("user_agent_sale_grade_log")->where('user_id',$user['user_id'])->update(['status'=>0]);
                    Db::name("user_agent_sale_grade_log")->insertGetId([
                        'user_id'     => $user['user_id'],
                        'grade'       => $grade,
                        'user_level'  => $user['level']??0,
                        'grade_name'  => '',
                        'effect_time' => $effect_time,
                        'expire_time' => $expire_time,
                        'status'      => 1,
                        'add_time'    => time(),
                    ]);
                });
            }
        }
    }

    /**
     * 获取某个会员近三个月的平均业绩
     * @param $userId
     * @return string|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function getNearly3MonthSvgPv($userId){
        $log = Db::name('user_agent_sale_grade_log')->where('chain_user_id',$userId)->group("month")->field("chain_user_id,month,SUM(team_pv) team_pv_sum")->select();
        $count = count($log);
        $sum = 0.00;
        foreach ($log as $l){
            $sum = bcadd($sum,$l['team_pv_sum'],2);
        }
        return bcdiv($sum,$count,2);
    }

    /**
     * 会员等级：会员/经销商 职级/（会员等级）
     * 销售定级：经销商 -》 1-6
     * 根据业绩获取销售等级
     * @param $pv
     * @return bool|int|string
     */
    protected function getPvGrade($pv){
        $pvGradeSetting = [
            1=>[0,3000],
            2=>[3000,10000],
            3=>[10000,40000],
            4=>[40000,120000],
            5=>[120000],
        ];
        foreach ($pvGradeSetting as $grade => $setting){
            if (isset($setting[1])){
                if ($pv >= $setting[0] && $pv< $setting[1]){
                    return $grade;
                }
            }else{
                if ($pv >= $setting[0]){
                    return $grade;
                }
            }
        }
        return false;
    }
}