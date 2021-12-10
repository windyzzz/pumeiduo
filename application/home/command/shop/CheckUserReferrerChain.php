<?php


namespace app\home\command\shop;


use think\console\Input;
use think\console\Output;
use think\Db;

class CheckUserReferrerChain extends BaseCommand
{

    protected function configure()
    {
        $this->setName('check_user_referrer_chain')->setDescription('检查会员推荐关系链条')->addOption("name");
    }

    protected function execute(Input $input, Output $output)
    {
        ini_set('memory_limit', '1024M');
        $start = microtime(true);
        $output->writeln('开始处理：' . date('Y-m-d H:i:s'));
        $memberList = Db::name('users u')
            ->join('user_chain chain','u.user_id=chain.user_id','LEFT')
            ->field('u.user_id,chain.referee_ids,u.first_leader')->select();
        $this->formatTree($memberList,['user_id'=>1,'referee_ids_str'=>''],$return,$return2);
        $output->writeln('程序结束：' . date('Y-m-d H:i:s'));
        $end = microtime(true);
        $output->writeln('所用时间：' . bcsub($end, $start, 5));
    }

    protected function formatTree(&$list,$itemArr = [],&$return = [],&$return2=[]){

        $count= 0;
        foreach ($list as $k => $item){
            if ($itemArr['user_id'] == $item['first_leader']){
                $item['referee_ids_str'] = isset($itemArr['referee_ids_str'])?"{$itemArr['referee_ids_str']}{$itemArr['user_id']},":",{$itemArr['user_id']},{$item['user_id']},";
                $item['child'] = [];
                $childCount = $this->formatTree($list,$item,$item['child'],$return2);
                $item['count'] = $childCount + 1;
                $item['open'] = false;
                if ( trim(trim($item['referee_ids']),',') != trim(trim($item['referee_ids_str']),',') ){
                    $return2[] = $item;
                    dump($item);
                }
                $return[] = $item;
                $count += $item['count'];
                unset($list[$k]);
            }
        }
        return $count;
    }
}