<?php

namespace app\home\command\shop;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;

class UpdateUserAccount extends Command
{
    protected function configure()
    {
        $this->setName('update_user_account')->setDescription('更新用户代理商资金信息');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '512M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        // 更新商品信息（excel文件导入）
        $this->updateUserAccount();
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    protected function updateUserAccount()
    {
        $svipInfo = M('svip_info')->where(['account_sync' => 0])->field('id, user_id, account_money, customs_money')->select();
        foreach ($svipInfo as $svip) {
            // 增加余额
            accountLog($svip['user_id'], $svip['account_money'], 0, '代理商资金同步-奖金账户', 0, 0, '', 0, 27);
            // 增加电子币
            accountLog($svip['user_id'], 0, 0, '代理商资金同步-电子币', 0, 0, '', $svip['customs_money'], 27);
            M('svip_info')->where(['id' => $svip['id']])->update(['account_sync' => 1]);
        }
    }
}
