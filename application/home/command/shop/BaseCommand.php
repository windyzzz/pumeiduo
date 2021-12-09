<?php


namespace app\home\command\shop;


use think\console\Command;

class BaseCommand extends Command
{
    public function __construct($name = null)
    {
        parent::__construct($name);
        ini_set('memory_limit', '512M');
    }


}