<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\util;

use think\Exception;

/**
 * Class File.
 */
class TpshopException extends Exception
{
    private $errorArr = [];

    //重定义构造器使第一个参数message变为必须被指定的属性
    public function __construct($message, $code = 0, $errorArr)
    {
        //可以在这里定义一些自己的代码
        //建议同时调用parent::construct()来检查所有的变量是否已被赋值
        $this->errorArr = $errorArr;
        parent::__construct($message, $code);
    }

    //重写父类中继承过来的方法，自定义字符串输出的样式
    public function __toString()
    {
        return __CLASS__.':['.$this->code.']:'.$this->message.'<br>';
    }

    //为这个异常自定义一个处理方法
    public function customFunction()
    {
        echo '按自定义的方法处理出现的这个类型的异常';
    }

    public function getErrorArr()
    {
        return $this->errorArr;
    }
}
