<?php

namespace app\home\command\shop;

use app\common\logic\UsersLogic;
use app\common\model\UserChain;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;

class CreateUserChain extends Command
{
    protected function configure()
    {
        $this->setName('create_user_chain')->setDescription('记录用户推荐链');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '1024M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        // 更新商品信息（excel文件导入）
        $this->createUserChain();
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    protected function createUserChain()
    {
        $userList = M('users')->where(['first_leader' => ['>', 0]])->limit(0, 10)->getField('user_id', true);
        array_unshift($userList, 1);
        $userIds = M('user_chain')->getField('user_id', true);
        $userChainData = [];
        $usersLogic = new UsersLogic();
        foreach ($userList as $userId) {
            if (!in_array($userId, $userIds)) {
                $chain = $usersLogic->getInviteChain($userId);
                $userChainData[] = [
                    'user_id' => $userId,
                    'referee_ids' => $chain
                ];
            }
        }
        (new UserChain())->saveAll($userChainData);
    }
}
