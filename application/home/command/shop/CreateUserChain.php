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
    protected $deal = 0;
    protected $total = 0;

    protected $referrerChainData = [];

    protected function configure()
    {
        $this->setName('create_user_chain')->setDescription('记录用户推荐链');
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '1024M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        $this->createUserChain();
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    protected function setInsert($data){
        $this->referrerChainData[] = $data;
        if (count($this->referrerChainData) === 1000){
            $res = Db::name('user_chain')->insertAll($this->referrerChainData, true) ;
            if ($res){
                $this->referrerChainData = [];
            }
        }
    }

    protected function end(){
        $res = Db::name('user_chain')->insertAll($this->referrerChainData, true) ;
        if ($res){
            $this->referrerChainData = [];
        }
    }

    protected function createUserChain()
    {
        $userList = Db::name('users')->field('user_id,first_leader')->select();
        $this->total = count($userList);
        $this->getReferrerChain($userList,[],$return);
        $this->end();
//        $userList = M('users')->where(['first_leader' => ['>', 0]])->limit(0, 10)->getField('user_id', true);
//        array_unshift($userList, 1);
//        $userIds = M('user_chain')->getField('user_id', true);
//        $userChainData = [];
//        $usersLogic = new UsersLogic();
//        foreach ($userList as $userId) {
//            if (!in_array($userId, $userIds)) {
//                $chain = $usersLogic->getInviteChain($userId);
//                $userChainData[] = [
//                    'user_id' => $userId,
//                    'referee_ids' => $chain
//                ];
//            }
//        }
//        (new UserChain())->saveAll($userChainData);
    }

    protected function updateCount(){
        $this->deal++;
        strlen((string)$this->total);
        $percent = (integer)(($this->deal/$this->total)*100);
        $stringDeal = str_pad((string)$this->deal,strlen((string)$this->total),'0',STR_PAD_LEFT);
        $this->output->write(  $stringDeal. "/" . (string)$this->total . '(进度:' . str_pad((string)$percent,2,'0',STR_PAD_LEFT) .  "%)\033[105D" );
        if ($this->deal === $this->total){
            $this->output->writeln("");
        }
    }

    protected function getReferrerChain(&$list,$parentItem = [],&$return = []){
        foreach ($list as $k => $user){
            $is = false;
            if (empty($parentItem) && empty($user['first_leader'])){
                // 如果没有传上级信息，找到没有上级会员的信息
                $user['referee_ids_str'] = ",";
                unset($list[$k]);
                $this->updateCount();
                $is = true;
            }
            if (!empty($parentItem) && $parentItem['user_id'] == $user['first_leader']){
                $user['referee_ids_str'] = "{$parentItem['referee_ids_str']}{$parentItem['user_id']},";
                $is = true;
                unset($list[$k]);
                $this->updateCount();
            }
            if (!$is){
                continue;
            }
            $this->setInsert(['user_id'=>$user['user_id'],'referee_ids'=>$user['referee_ids_str']]);
            $user['child'] = [];
            $this->getReferrerChain($list,$user,$user['child']);
            $return[] = $user;
        }
    }
}
