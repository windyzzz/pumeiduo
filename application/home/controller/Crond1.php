<?php
/**
 * 公共
 */

namespace app\home\controller;
use think\Controller;
use think\Db;
use think\Session;
use app\common\logic\UsersLogic;
use app\admin\logic\OrderLogic;
use think\Verify;
use think\Cookie;

class Crond1   extends Controller
{

    function __construct(){
        parent::__construct();
    }
	
function order_check(){
  $order = M('order')->where(array('add_time'=>array('gt',1565452800),'order_status'=>array('in',array(0,1,2,4)) ) ) ->field('order_id,order_sn')->select();
foreach($order as $k=>$v){
$rebate_log = M('rebate_log')->where(array('order_id'=>$v['order_id']))->find();
if(!$rebate_log){
echo $v['order_sn'].'<br />';
}
}
}
}