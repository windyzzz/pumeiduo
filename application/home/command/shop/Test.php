<?php


namespace app\home\command\shop;


use app\common\logic\user\LevelLogic;
use app\common\logic\user\ReferrerLogic;
use think\console\Input;
use think\console\Output;

class Test extends BaseCommand
{
    protected function configure()
    {
        $this->setName('test')->setDescription('更新用户代理商资金信息');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '512M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        $this->start();
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    protected function start(){
        $referrerLogic = new ReferrerLogic();
        $res = $referrerLogic->change(165,88,143);
        dump($res);
    }
}