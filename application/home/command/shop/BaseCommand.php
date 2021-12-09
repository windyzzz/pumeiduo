<?php


namespace app\home\command\shop;


use think\console\Command;

class BaseCommand extends Command
{
    protected $time;
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->time = time();
        ini_set('memory_limit', '512M');
    }


}