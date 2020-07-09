<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use app\common\logic\PushLogic;
use app\common\logic\Token as TokenLogic;
use app\common\logic\wechat\WechatUtil;
use think\Db;

/**
 * tpshop检验登陆.
 *
 * @param
 *
 * @return bool
 */
function is_login()
{
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0) {
        return $_SESSION['admin_id'];
    }

    return false;
}

function bonus_time()
{
    return 1562083200;
}

function order_time()
{
    return 1561910400;
}

/**
 * 获取用户信息.
 *
 * @param $user_value  用户id 邮箱 手机 第三方id
 * @param int $type 类型 0 user_id查找 1 邮箱查找 2 手机查找 3 第三方唯一标识查找
 * @param string $oauth 第三方来源
 *
 * @return mixed
 */
function get_user_info($user_value, $type = 0, $oauth = '')
{
    $map = ['is_cancel' => 0];
    if (0 == $type) {
        $map['user_id'] = $user_value;
    } elseif (1 == $type) {
        $map['email'] = $user_value;
    } elseif (2 == $type) {
        $map['mobile'] = $user_value;
    } elseif (3 == $type) {
        $thirdUser = Db::name('oauth_users')->where(['openid' => $user_value, 'oauth' => $oauth])->find();
        $map['user_id'] = $thirdUser['user_id'];
    } elseif (4 == $type) {
        $thirdUser = Db::name('oauth_users')->where(['unionid' => $user_value])->find();
        $map['user_id'] = $thirdUser['user_id'];
    } elseif (5 == $type) {
        $map['user_name'] = $user_value;
    }

    return Db::name('users')->where($map)->find();
}

/**
 * 更新会员等级,折扣，消费总额.
 *
 * @param $user_id  用户ID
 *
 * @return bool
 */
function update_user_level($user_id)
{
    $level_info = M('user_level')->order('level_id')->select();
    $total_amount = M('order')->master()->where('user_id=:user_id AND pay_status=1 and order_status not in (3,5)')->bind(['user_id' => $user_id])->sum('order_amount+user_money');
    if ($level_info) {
        foreach ($level_info as $k => $v) {
            if ($total_amount >= $v['amount']) {
                $level = $level_info[$k]['level_id'];
                $discount = $level_info[$k]['discount'] / 100;
            }
        }
        $user = session('user');
        $updata['total_amount'] = $total_amount; //更新累计修复额度
        //累计额度达到新等级，更新会员折扣
        if (isset($level) && $level > $user['level']) {
            $updata['level'] = $level;
            $updata['discount'] = $discount;
        }
        M('users')->where('user_id', $user_id)->save($updata);
    }
}

/**
 * 更新分销等级,折扣，消费总额 BY J.
 *
 * @param $user_id  用户ID
 * @param $order_id  订单ID
 *
 * @return bool
 */
function update_user_distribut($user_id, $order_id)
{
    $orderGoodsArr = M('OrderGoods')->where(['order_id' => $order_id])->select();

    $level = [];

    //1.判断购买的商品是否包含升级专区的商品 zone == 3
    //且有且只有distribut_id > 0 的商品才更新用户等级
    foreach ($orderGoodsArr as $v) {
        $goods_info = M('goods')->field('zone, distribut_id')->where(['goods_id' => $v['goods_id']])->find();
        if (3 == $goods_info['zone'] && $goods_info['distribut_id'] > 0) {
            $level[] = $goods_info['distribut_id'];
        }
    }

    //2.更新用户分销等级,等级下属对应关系
    if ($level) {
        //2.1更新用户分销等级 根据order_money字段排序更新最大等级
        $level_list = M('distribut_level')->where('level_id', 'in', $level)->order('order_money')->select();
        $level = end($level_list);
        $update['is_distribut'] = 1;
        $update['distribut_level'] = $level['level_id'];
        $user_info = M('users')->master()->field('user_id, distribut_level, first_leader')->where('user_id', $user_id)->find() ?: 1;
        M('users')->where('user_id', $user_id)->save($update);
        $user = Db::name('users')->where('user_id', $user_id)->find();
        // 更新用户推送tags
        $res = (new PushLogic())->bindPushTag($user);
        if ($res['status'] == 2) {
            $user = Db::name('users')->where('user_id', $user_id)->find();
        }
        // 更新缓存
        TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
        $order = M('order')->where('order_id', $order_id)->find();
        logDistribut($order['order_sn'], $user_id, $update['distribut_level'], $user_info['distribut_level'], 1);

        //2.2购买vip套餐用户领取优惠券
        $CouponLogic = new \app\common\logic\CouponLogic();
        $CouponLogic->sendNewVipUser($user_id, $order_id);

        //2.3推荐人奖励
        $firstLeaderInfo = M('users')->where(['user_id' => $user_info['first_leader']])->field('distribut_level, first_leader')->find();
        $updateRebate = false;
        switch ($firstLeaderInfo['distribut_level']) {
            case 1:
                break;
            case 2:
                // VIP推荐VIP奖励
                if (tpCache('distribut.referee_vip_money') > 0) {
                    // 奖励金额
                    accountLog($user_info['first_leader'], tpCache('distribut.referee_vip_money'), 0, '推广318套组奖励金额', 0, $order_id, '', 0, 14, false);
                    $updateRebate = true;
                }
                if (tpCache('distribut.referee_vip_point') > 0) {
                    // 奖励积分
                    accountLog($user_info['first_leader'], 0, tpCache('distribut.referee_vip_point'), '推广318套组奖励积分', 0, $order_id, '', 0, 14, false);
                    $updateRebate = true;
                }
                // VIP的直接上级SVIP
                $vipSvipInfo = M('users')->where(['user_id' => $firstLeaderInfo['first_leader']])->field('distribut_level')->find();
                if (!empty($vipSvipInfo) && $vipSvipInfo['distribut_level'] >= 3) {
                    if (tpCache('distribut.referee_vip_svip_money') > 0) {
                        // 奖励金额
                        accountLog($firstLeaderInfo['first_leader'], tpCache('distribut.referee_vip_svip_money'), 0, '直属推广318套组奖励金额', 0, $order_id, '', 0, 14, false);
                        $updateRebate = true;
                    }
                    if (tpCache('distribut.referee_vip_svip_point') > 0) {
                        // 奖励积分
                        accountLog($firstLeaderInfo['first_leader'], 0, tpCache('distribut.referee_vip_svip_point'), '直属推广318套组奖励积分', 0, $order_id, '', 0, 14, false);
                        $updateRebate = true;
                    }
                }
                break;
            case 3:
                // SVIP推荐VIP奖励
                if (tpCache('distribut.referee_svip_money') > 0) {
                    // 奖励金额
                    accountLog($user_info['first_leader'], tpCache('distribut.referee_svip_money'), 0, '推广318套组奖励金额', 0, $order_id, '', 0, 14, false);
                    $updateRebate = true;
                }
                if (tpCache('distribut.referee_svip_point') > 0) {
                    // 奖励积分
                    accountLog($user_info['first_leader'], 0, tpCache('distribut.referee_svip_point'), '推广318套组奖励积分', 0, $order_id, '', 0, 14, false);
                    $updateRebate = true;
                }
                break;
        }
        if ($updateRebate) {
            // 更新订单已分成
            M('order')->where(['order_id' => $order_id])->update(['is_distribut' => 1]);
            // 更新分成记录
            M('rebate_log')->where(['buy_user_id' => $order['user_id'], 'order_id' => $order['order_id']])->update([
                'status' => 3,
                'confirm_time' => time()
            ]);
        }
    }
}

/**
 * 向下更新分销下级关系 BY J.
 *
 * @param $first_leader
 * @param $user_id
 *
 * @return bool
 */
function updateDistributByIdDown($first_leader, $user_id)
{
    $updateAllData = [];
    $user_list = M('users')->field('user_id,first_leader,second_leader,third_leader')
        ->where('first_leader', $first_leader)
        ->where('user_id', 'neq', $user_id)
        ->select();
    foreach ($user_list as $uv) {
        $update = [];
        $update['user_id'] = $uv['user_id'];
        $update['first_leader'] = $first_leader;
        $update['second_leader'] = $user_id;
        $update['third_leader'] = $uv['third_leader'];
        $updateAllData[] = $update;
    }
    $user_list = M('users')->field('user_id,first_leader,second_leader,third_leader')
        ->where('second_leader', $first_leader)
        ->where('user_id', 'neq', $user_id)
        ->select();
    foreach ($user_list as $uv) {
        $update = [];
        $update['user_id'] = $uv['user_id'];
        $update['first_leader'] = $uv['first_leader'];
        $update['second_leader'] = $uv['second_leader'];
        $update['third_leader'] = $user_id;
        $updateAllData[] = $update;
    }

    return $updateAllData;
}

/**
 * 向上更新分销下级关系 BY J.
 *
 * @param $first_leader
 * @param $user_id
 *
 * @return bool
 */
function updateDistributByIdUp($first_leader, $user_id, $ids)
{
    $updateAllData = [];
    $user_list = M('users')->field('user_id,first_leader,second_leader,third_leader')
        ->where('first_leader', $first_leader)
        ->where('user_id', 'neq', $user_id)
        ->select();
    foreach ($user_list as $uv) {
        $update = [];
        $update['user_id'] = $uv['user_id'];
        $update['first_leader'] = $user_id;
        $update['second_leader'] = $uv['first_leader'];
        $update['third_leader'] = $uv['second_leader'];
        $updateAllData[] = $update;
    }
    $user_list = M('users')->field('user_id,first_leader,second_leader,third_leader')
        ->where('second_leader', $first_leader)
        ->where('user_id', 'neq', $user_id)
        ->select();
    foreach ($user_list as $uv) {
        $update = [];
        $update['user_id'] = $uv['user_id'];
        $update['first_leader'] = $uv['first_leader'];
        $update['second_leader'] = $user_id;
        $update['third_leader'] = $uv['second_leader'];
        $updateAllData[] = $update;
    }
    $user_list = M('users')->field('user_id,first_leader,second_leader,third_leader')
        ->where('third_leader', $first_leader)
        ->where('user_id', 'neq', $user_id)
        ->select();
    foreach ($user_list as $uv) {
        $update = [];
        $update['user_id'] = $uv['user_id'];
        $update['first_leader'] = $uv['first_leader'];
        $update['second_leader'] = $uv['second_leader'];
        $update['third_leader'] = $user_id;
        $updateAllData[] = $update;
    }

    return $updateAllData;
}

/**
 * 递归方法--通过推荐ID获取用户分销关系列表 BY J.
 *
 * @param $id
 * @param $user_id
 *
 * @return array|\PDOStatement|string|\think\Collection
 */
function _getLevelIds($id)
{
    $uids = '';
    $users = M('Users')->field('user_id')->where('invite_uid', $id)->select();

    if ($users) {
        foreach ($users as $uk => $uv) {
            $uids .= $uv['user_id'] . ',' . _getLevelIds($uv['user_id']);
        }
    }

    return $uids;
}

/**
 * 递归方法--通过推荐ID获取用户分销关系列表 BY J.
 *
 * @param $id
 * @param $user_id
 *
 * @return array|\PDOStatement|string|\think\Collection
 */
function _getListById($id, $user_id)
{
    $update_arr = [];

    $list = M('users')
        ->field('first_leader,user_id')
        ->where('invite_uid', $id)
        ->select();

    if ($list) {
        foreach ($list as $key => $value) {
            if ($value['first_leader'] < 1) {
                $update = [];
                $update['user_id'] = $value['user_id'];
                $update['first_leader'] = $user_id;
                $update_arr[] = $update;
                $update_arr = array_merge($update_arr, _getListById($value['user_id'], $user_id));
            }
        }
    }

    return $update_arr;
}

/**
 * 递归方法--通过推荐ID获取用户分销关系的顶层Id BY J.
 *
 * @param $id
 *
 * @return array|\PDOStatement|string|\think\Collection
 */
function _getFirstLeaderId($id)
{
    $first_leader = [];

    $list = M('users')
        ->field('first_leader,user_id,is_distribut')
        ->where('invite_uid', $id)
        ->select();

    if ($list) {
        foreach ($list as $key => $value) {
            if ($value['is_distribut'] > 0) {
                $first_leader[] = $value['user_id'];
                // return $first_leader ;
            } else {
                $first_leader = _getFirstLeaderId($value['user_id']);
            }
        }
    }

    return $first_leader;
}

/**
 *  商品缩略图 给于标签调用 拿出商品表的 original_img 原始图来裁切出来的.
 *
 * @param type $goods_id 商品id
 * @param type $width 生成缩略图的宽度
 * @param type $height 生成缩略图的高度
 */
function goods_thum_images($goods_id, $width, $height)
{
    if (empty($goods_id)) {
        return '';
    }

    //判断缩略图是否存在
    $path = UPLOAD_PATH . "goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_thumb_{$goods_id}_{$width}_{$height}";

    // 这个商品 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) {
        return '/' . $path . $goods_thumb_name . '.jpg';
    }
    if (is_file($path . $goods_thumb_name . '.jpeg')) {
        return '/' . $path . $goods_thumb_name . '.jpeg';
    }
    if (is_file($path . $goods_thumb_name . '.gif')) {
        return '/' . $path . $goods_thumb_name . '.gif';
    }
    if (is_file($path . $goods_thumb_name . '.png')) {
        return '/' . $path . $goods_thumb_name . '.png';
    }
    $original_img = Db::name('goods')->where('goods_id', $goods_id)->cache(true, 30, 'original_img_cache')->value('original_img');
    if (empty($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    $ossClient = new \app\common\logic\OssLogic();
    if (($ossUrl = $ossClient->getGoodsThumbImageUrl($original_img, $width, $height))) {
        return $ossUrl;
    }

    $original_img = '.' . $original_img; // 相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if (strstr(strtolower($original_img), '.gif')) {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, null, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {
        return $original_img;
    }
}

/**
 * 商品相册缩略图.
 */
function get_sub_images($sub_img, $goods_id, $width, $height)
{
    //判断缩略图是否存在
    $path = UPLOAD_PATH . "goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_sub_thumb_{$sub_img['img_id']}_{$width}_{$height}";

    //这个缩略图 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) {
        return '/' . $path . $goods_thumb_name . '.jpg';
    }
    if (is_file($path . $goods_thumb_name . '.jpeg')) {
        return '/' . $path . $goods_thumb_name . '.jpeg';
    }
    if (is_file($path . $goods_thumb_name . '.gif')) {
        return '/' . $path . $goods_thumb_name . '.gif';
    }
    if (is_file($path . $goods_thumb_name . '.png')) {
        return '/' . $path . $goods_thumb_name . '.png';
    }

    $ossClient = new \app\common\logic\OssLogic();
    if (($ossUrl = $ossClient->getGoodsAlbumThumbUrl($sub_img['image_url'], $width, $height))) {
        return $ossUrl;
    }

    $original_img = '.' . $sub_img['image_url']; //相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        require_once 'vendor/topthink/think-image/src/Image.php';
        require_once 'vendor/topthink/think-image/src/image/Exception.php';
        if (strstr(strtolower($original_img), '.gif')) {
            require_once 'vendor/topthink/think-image/src/image/gif/Encoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Decoder.php';
            require_once 'vendor/topthink/think-image/src/image/gif/Gif.php';
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, null, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {
        return $original_img;
    }
}

/**
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加.
 *
 * @param type $goods_id 商品id
 */
function refresh_stock($goods_id)
{
    $count = M('SpecGoodsPrice')->where('goods_id', $goods_id)->count();
    if (0 == $count) {
        return false;
    } // 没有使用规格方式 没必要更改总库存

    $store_count = M('SpecGoodsPrice')->where('goods_id', $goods_id)->sum('store_count');
    M('Goods')->where('goods_id', $goods_id)->save(['store_count' => $store_count]); // 更新商品的总库存
}

/**
 * 根据 order_goods 表扣除商品库存.
 *
 * @param $order |订单对象或者数组
 *
 * @throws \think\Exception
 */
function minus_stock($order)
{
    $orderGoodsArr = M('OrderGoods')->master()->where('order_id', $order['order_id'])->select();
    foreach ($orderGoodsArr as $key => $val) {
        // 有选择规格的商品
        if (!empty($val['spec_key'])) {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
        } else {
            $specGoodsPrice = null;
            M('Goods')->where('goods_id', $val['goods_id'])->setDec('store_count', $val['goods_num']); // 直接扣除商品总数量
        }

        // 更新库存日志
        $goods = M('Goods')->where('goods_id', $val['goods_id'])->find(); // 商品剩余库存
        update_stock_log($order['user_id'], -$val['goods_num'], $goods, $order['order_sn']);

        M('Goods')->where('goods_id', $val['goods_id'])->setInc('sales_sum', $val['goods_num']); // 增加商品销售量

        //套组扣库存
        if (2 == $val['sale_type']) {
            $g_list = M('GoodsSeries')->where('goods_id', $val['goods_id'])->select();
            /*if ($g_list) {
                foreach ($g_list as $k => $v) {
                    if ($v['item_id']) {
                        // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
                        $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
                        $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $v['g_id'], 'item_id' => $v['item_id']]);
                        $specGoodsPrice->where(['goods_id' => $v['g_id'], 'item_id' => $v['item_id']])->setDec('store_count', $v['g_number'] * $val['goods_num']);
                        refresh_stock($val['goods_id']);
                    } else {
                        M('Goods')->where('goods_id', $v['g_id'])->setDec('store_count', $v['g_number'] * $val['goods_num']); // 直接扣除商品总数量
                        M('Goods')->where('goods_id', $v['g_id'])->setInc('sales_sum', $v['g_number'] * $val['goods_num']); // 增加商品销售量
                    }
                }
            }*/
        }

        //更新活动商品购买量
        // if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
        if (1 == $val['prom_type']) {
            $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
            $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom = $goodsPromLogic->getPromModel();
            if (0 == $prom['is_end']) {
                $tb = 1 == $val['prom_type'] ? 'flash_sale' : 'group_buy';
                M($tb)->where('id', $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where('id', $val['prom_id'])->setInc('order_num');
            }
        }
    }
}

/**
 * 邮件发送
 *
 * @param $to    接收人
 * @param string $subject 邮件标题
 * @param string $content 邮件内容(html模板渲染后的内容)
 *
 * @throws Exception
 * @throws phpmailerException
 */
function send_email($to, $subject = '', $content = '')
{
    vendor('phpmailer.PHPMailerAutoload'); ////require_once vendor/phpmailer/PHPMailerAutoload.php';
    //判断openssl是否开启
    $openssl_funcs = get_extension_funcs('openssl');
    if (!$openssl_funcs) {
        return ['status' => -1, 'msg' => '请先开启openssl扩展'];
    }
    $mail = new PHPMailer();
    $config = tpCache('smtp');
    $mail->CharSet = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->isSMTP();
    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = 0;
    //调试输出格式
    //$mail->Debugoutput = 'html';
    //smtp服务器
    $mail->Host = $config['smtp_server'];
    //端口 - likely to be 25, 465 or 587
    $mail->Port = $config['smtp_port'];

    if (465 == $mail->Port) {
        $mail->SMTPSecure = 'ssl';
    } // 使用安全协议
    //Whether to use SMTP authentication
    $mail->SMTPAuth = true;
    //用户名
    $mail->Username = $config['smtp_user'];
    //密码
    $mail->Password = $config['smtp_pwd'];
    //Set who the message is to be sent from
    $mail->setFrom($config['smtp_user']);
    //回复地址
    //$mail->addReplyTo('replyto@example.com', 'First Last');
    //接收邮件方
    if (is_array($to)) {
        foreach ($to as $v) {
            $mail->addAddress($v);
        }
    } else {
        $mail->addAddress($to);
    }

    $mail->isHTML(true); // send as HTML
    //标题
    $mail->Subject = $subject;
    //HTML内容转换
    $mail->msgHTML($content);
    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';
    //添加附件
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        return ['status' => -1, 'msg' => '发送失败: ' . $mail->ErrorInfo];
    }

    return ['status' => 1, 'msg' => '发送成功'];
}

/**
 * 检测是否能够发送短信
 *
 * @param unknown $scene
 *
 * @return multitype:number string
 */
function checkEnableSendSms($scene)
{
//    $scenes = C('SEND_SCENE');
//    $sceneItem = $scenes[$scene];
//    if (!$sceneItem) {
//        return ['status' => -1, 'msg' => "场景参数'scene'错误!"];
//    }
//    $key = $sceneItem[2];
//    $sceneName = $sceneItem[0];
//    $config = tpCache('sms');
//    $smsEnable = $config[$key];
//
//    if (!$smsEnable) {
//        return ['status' => -1, 'msg' => "['$sceneName']发送短信被关闭'"];
//    }
//    //判断是否添加"注册模板"
//    $size = M('sms_template')->where('send_scene', $scene)->count('tpl_id');
//    if (!$size) {
//        return ['status' => -1, 'msg' => "请先添加['$sceneName']短信模板"];
//    }

    return ['status' => 1, 'msg' => '可以发送短信'];
}

/**
 * 发送短信逻辑.
 *
 * @param unknown $scene
 */
function sendSms($scene, $sender, $params, $unique_id = 0)
{
    $smsLogic = new \app\common\logic\SmsLogic();

    return $smsLogic->sendSms($scene, $sender, $params, $unique_id);
}

/**
 * 查询快递.
 *
 * @param $postcom  快递公司编码
 * @param $getNu  快递单号
 *
 * @return array 物流跟踪信息数组
 */
function queryExpress($postcom, $getNu)
{
    $url = 'https://m.kuaidi100.com/query?type=' . $postcom . '&postid=' . $getNu . '&id=1&valicode=&temp=0.49738534969422676';
    $resp = httpRequest($url, 'GET');

    return json_decode($resp, true);
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id.
 *
 * @param type $cat_id
 */
function getCatGrandson($cat_id)
{
    $GLOBALS['catGrandson'] = [];
    $GLOBALS['category_id_arr'] = [];
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M('GoodsCategory')->cache(true, TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsCategory')->where('parent_id', $cat_id)->cache(true, TPSHOP_CACHE_TIME)->getField('id', true);
    foreach ($son_id_arr as $k => $v) {
        getCatGrandson2($v);
    }

    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个文章分类的 儿子 孙子  重子重孙 的 id.
 *
 * @param type $cat_id
 */
function getArticleCatGrandson($cat_id)
{
    $GLOBALS['ArticleCatGrandson'] = [];
    $GLOBALS['cat_id_arr'] = [];
    // 先把自己的id 保存起来
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['cat_id_arr'] = M('ArticleCat')->getField('cat_id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('ArticleCat')->where('parent_id', $cat_id)->getField('cat_id', true);
    foreach ($son_id_arr as $k => $v) {
        getArticleCatGrandson2($v);
    }

    return $GLOBALS['ArticleCatGrandson'];
}

/**
 * 递归调用找到 重子重孙.
 *
 * @param type $cat_id
 */
function getCatGrandson2($cat_id)
{
    $GLOBALS['catGrandson'][] = $cat_id;
    foreach ($GLOBALS['category_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 递归调用找到 重子重孙.
 *
 * @param type $cat_id
 */
function getArticleCatGrandson2($cat_id)
{
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    foreach ($GLOBALS['cat_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getArticleCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 查看某个用户购物车中商品的数量.
 *
 * @param type $user_id
 * @param type $session_id
 *
 * @return type 购买数量
 */
function cart_goods_num($user_id = 0, $session_id = '')
{
//    $where = " session_id = '$session_id' ";
//    $user_id && $where .= " or user_id = $user_id ";
    // 查找购物车数量
//    $cart_count =  M('Cart')->where($where)->sum('goods_num');
    $cart_count = Db::name('cart')->where(function ($query) use ($user_id, $session_id) {
        $query->where('session_id', $session_id);
        if ($user_id) {
            $query->whereOr('user_id', $user_id);
        }
    })->sum('goods_num');
    $cart_count = $cart_count ? $cart_count : 0;

    return $cart_count;
}

/**
 * 获取商品库存.
 *
 * @param type $goods_id 商品id
 * @param type $key 库存 key
 */
function getGoodNum($goods_id, $key)
{
    if (!empty($key)) {
        return M('SpecGoodsPrice')
            ->alias('s')
            ->join('_Goods_ g ', 's.goods_id = g.goods_id', 'LEFT')
            ->where(['g.goods_id' => $goods_id, 'key' => $key, 'is_on_sale' => 1])->getField('s.store_count');
    }

    return M('Goods')->where(['goods_id' => $goods_id, 'is_on_sale' => 1])->getField('store_count');
}

/**
 * 获取缓存或者更新缓存.
 *
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 *
 * @return array or string or bool
 */
function tpCache($config_key, $data = [])
{
    $param = explode('.', $config_key);
    if (empty($data)) {
        //如$config_key=shop_info则获取网站信息数组
        //如$config_key=shop_info.logo则获取网站logo字符串
        $config = F($param[0], '', TEMP_PATH); //直接获取缓存文件
        if (empty($config)) {
            //缓存文件不存在就读取数据库
            $res = D('config')->where('inc_type', $param[0])->select();
            if ($res) {
                foreach ($res as $k => $val) {
                    $config[$val['name']] = $val['value'];
                }
                F($param[0], $config, TEMP_PATH);
            }
        }
        if (count($param) > 1) {
            return $config[$param[1]];
        }

        return $config;
    }
    //更新缓存
    $result = D('config')->where('inc_type', $param[0])->select();
    if ($result) {
        foreach ($result as $val) {
            $temp[$val['name']] = $val['value'];
        }
        foreach ($data as $k => $v) {
            $newArr = ['name' => $k, 'value' => trim($v), 'inc_type' => $param[0]];
            if (!isset($temp[$k])) {
                //新key数据插入数据库
                M('config')->add($newArr);
            } else {
                if ($v != $temp[$k]) {
                    //缓存key存在且值有变更新此项
                    M('config')->where('name', $k)->save($newArr);
                }
            }
        }
        //更新后的数据库记录
        $newRes = D('config')->where('inc_type', $param[0])->select();
        foreach ($newRes as $rs) {
            $newData[$rs['name']] = $rs['value'];
        }
    } else {
        foreach ($data as $k => $v) {
            $newArr[] = ['name' => $k, 'value' => trim($v), 'inc_type' => $param[0]];
        }
        M('config')->insertAll($newArr);
        $newData = $data;
    }

    return F($param[0], $newData, TEMP_PATH);
}

function taskLog($user_id, $task, $reward, $order_sn = '', $reward_electronic = 0, $reward_integral = 0, $type = 1, $status = 0, $reward_coupon_id = 0, $user_task_id = 0, $remark = '')
{
//    $reward_coupon_money = '0.00';
//    $reward_coupon_name = '';
//    if ($reward_coupon_id > 0) {
//        $couponInfo = M('Coupon')->find($reward_coupon_id);
//        if ($couponInfo) {
//            $reward_coupon_money = $couponInfo['money'];
//            $reward_coupon_name = $couponInfo['name'];
//        }
//    }
    $couponName = '';
    $couponMoney = '';
    if ($reward_coupon_id != '0') {
        // 优惠券信息
        $couponIds = explode('-', $reward_coupon_id);
        $coupon = Db::name('coupon')->where(['id' => ['in', $couponIds]])->field('id, name, money')->select();
        foreach ($coupon as $item) {
            $couponName .= $item['name'] . '-';
            $couponMoney .= $item['money'] . '-';
        }
    }

    $task_log = [
        'user_id' => $user_id,
        'task_id' => $task['id'],
        'user_task_id' => $user_task_id,
        'task_title' => $task['title'],
        'task_reward_id' => $reward['reward_id'],
        'task_reward_desc' => $reward['description'],
        'created_at' => time(),
        'reward_electronic' => $reward_electronic,
        'reward_integral' => $reward_integral,
        'reward_coupon_id' => $reward_coupon_id,
        'reward_coupon_money' => rtrim($couponMoney, '-'),
        'reward_coupon_name' => rtrim($couponName, '-'),
        'order_sn' => $order_sn,
        'type' => $type,
        'status' => $status,
        'remark' => $remark
    ];

    // $update_data = array(
    //     // 'user_money'        => ['exp','user_money+'.$user_money],
    //     'pay_points'        => ['exp','pay_points+'.$reward_integral],
    //     // 'distribut_money'   => ['exp','distribut_money+'.$distribut_money],
    //     'user_electronic'   => ['exp','user_electronic+'.$reward_electronic],
    // );
    // if(($reward_electronic+$reward_integral) == 0)
    //     return false;

    // $update = Db::name('users')->where('user_id',$user_id)->update($update_data);
    // if($update){
    M('task_log')->add($task_log);

    return true;
    // }else{
    //     return false;
    // }
}

/**
 * 记录帐户变动.
 *
 * @param int $user_id 用户id
 * @param float $user_money 可用余额变动
 * @param float $pay_points 消费积分变动
 * @param string $desc 变动说明
 * @param float $distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @param float $user_electronic 电子币
 * @param int $type 分类（0：其他，1：佣金结算，2：积分消费，3：下单消费，4：积分收入（包含：5：下单送积分、6：注册积分、7：邀请积分、8：签到积分、9：其他）、10：订单取消、11：电商转入积分、12：积分互转、13：电子币互转、:14：任务获得（包含：15：电子币、16：积分）
 * @param bool $isOneself 是否是登录用户本人
 * @param int $task_id 任务ID
 * @return bool
 * @throws \think\Exception
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 * @throws \think\exception\PDOException
 */
function accountLog($user_id, $user_money = 0.00, $pay_points = 0.00, $desc = '', $distribut_money = 0.00, $order_id = 0, $order_sn = '', $user_electronic = 0.00, $type = 0, $isOneself = true, $task_id = 0)
{
    /* 插入帐户变动记录 */
    $account_log = [
        'user_id' => $user_id,
        'user_money' => $user_money,
        'pay_points' => $pay_points,
        'user_electronic' => $user_electronic,
        'change_time' => time(),
        'desc' => $desc,
        'order_id' => $order_id,
        'order_sn' => $order_sn,
        'type' => $type,
        'task_id' => $task_id,
    ];
    /* 更新用户信息 */
//    $sql = "UPDATE __PREFIX__users SET user_money = user_money + $user_money," .
//        " pay_points = pay_points + $pay_points, distribut_money = distribut_money + $distribut_money WHERE user_id = $user_id";
    $update_data = [
        'user_money' => ['exp', 'user_money+' . $user_money],
        'pay_points' => ['exp', 'pay_points+' . $pay_points],
        'distribut_money' => ['exp', 'distribut_money+' . $distribut_money],
        'user_electronic' => ['exp', 'user_electronic+' . $user_electronic],
    ];
    if (0 == ($user_money + $pay_points + $distribut_money + $user_electronic)) {
        return false;
    }
    $where = ['user_id' => $user_id];
    if (bccomp(0, $user_money, 2) == 1) {
        // 扣减余额
        $where['user_money'] = ['egt', abs($user_money)];
    }
    if (bccomp(0, $pay_points, 2) == 1) {
        // 扣减积分
        $where['pay_points'] = ['egt', abs($pay_points)];
    }
    if (bccomp(0, $distribut_money, 2) == 1) {
        // 扣减累积佣金
        $where['distribut_money'] = ['egt', abs($distribut_money)];
    }
    if (bccomp(0, $user_electronic, 2) == 1) {
        // 扣减电子币
        $where['user_electronic'] = ['egt', abs($user_electronic)];
    }
    $update = Db::name('users')->where($where)->update($update_data);
    if ($update) {
        M('account_log')->add($account_log);
        if ($isOneself) {
            $user = Db::name('users')->where('user_id', $user_id)->find();
            TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
        }
        return true;
    }
    return false;
}

/**
 * 会员升级日志
 * 参数示例.
 *
 * @param string $order_sn 订单编号
 * @param int $user_id 用户ID
 * @param int $new_level 升级的分销等级
 * @param int $old_level 之前的分销等级
 * @param int $type 升级类型
 * @param float $upMoney 升级金额
 *
 * @return bool
 */
function logDistribut($order_sn, $user_id, $new_level, $old_level, $type = 1, $upMoney = 0.00)
{
    $log_info = [
        'user_id' => $user_id,
        'new_level' => $new_level,
        'old_level' => $old_level,
        'order_sn' => $order_sn,
        'type' => $type,
        'add_time' => time(),
        'upgrade_money' => $upMoney,
    ];

    return M('distribut_log')->add($log_info);
}

/**
 * 订单操作日志
 * 参数示例.
 *
 * @param type $order_id 订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id 用户id 默认为管理员
 *
 * @return bool
 */
function logOrder($order_id, $action_note, $status_desc, $user_id = 0)
{
//    $status_desc_arr = ['提交订单', '付款成功', '取消', '等待收货', '完成', '退货'];
//    if (!in_array($status_desc, $status_desc_arr)) return false;

    $order = M('order')->master()->where('order_id', $order_id)->find();
    $action_info = [
        'order_id' => $order_id,
        'action_user' => $user_id,
        'order_status' => $order['order_status'],
        'shipping_status' => $order['shipping_status'],
        'pay_status' => $order['pay_status'],
        'action_note' => $action_note,
        'status_desc' => $status_desc,
        'log_time' => time(),
    ];

    return M('order_action')->add($action_info);
}

/*
 * 获取地区列表
 */
function get_region_list()
{
    return M('region2')->cache(true)->getField('id,name');
}

/*
 * 获取用户地址列表
 */
function get_user_address_list($user_id)
{
    $lists = M('user_address')->where(['user_id' => $user_id])->order('is_default desc')->limit(20)->select();

    return $lists;
}

/*
 * 获取用户地址列表（新）
 */
function get_user_address_list_new($user_id, $default = false, $addressId = 0)
{
    $where = ['user_id' => $user_id];
    if ($default) {
        $where['is_default'] = 1;
    }
    if ($addressId) {
        $where['address_id'] = $addressId;
    }
    $lists = Db::name('user_address ua')
        ->join('region2 r1', 'r1.id = ua.province', 'LEFT')
        ->join('region2 r2', 'r2.id = ua.city', 'LEFT')
        ->join('region2 r3', 'r3.id = ua.district', 'LEFT')
        ->where($where)->limit(20)
        ->field('ua.*, r1.name province_name, city, r2.name city_name, district, r3.name district_name')
        ->order('ua.is_default DESC, ua.address_id DESC')
        ->select();
    if ($addressId && empty($lists)) {
        $lists = Db::name('user_address ua')
            ->join('region2 r1', 'r1.id = ua.province', 'LEFT')
            ->join('region2 r2', 'r2.id = ua.city', 'LEFT')
            ->join('region2 r3', 'r3.id = ua.district', 'LEFT')
            ->where(['user_id' => $user_id])->limit(20)
            ->field('ua.*, r1.name province_name, city, r2.name city_name, district, r3.name district_name')
            ->order('ua.is_default DESC, ua.address_id DESC')
            ->select();
    }

    return $lists;
}

/*
 * 获取指定地址信息
 */
function get_user_address_info($user_id, $address_id)
{
    $data = M('user_address')->where(['user_id' => $user_id, 'address_id' => $address_id])->find();

    return $data;
}

/*
 * 获取用户默认收货地址
 */
function get_user_default_address($user_id)
{
    $data = M('user_address')->where(['user_id' => $user_id, 'is_default' => 1])->find();

    return $data;
}

/**
 * 获取订单状态的 中文描述名称.
 *
 * @param type $order_id 订单id
 * @param type $order 订单数组
 *
 * @return string
 */
function orderStatusDesc($order_id = 0, $order = [])
{
    if (empty($order)) {
        $order = M('Order')->where('order_id', $order_id)->find();
    }

    // 货到付款
    if ('cod' == $order['pay_code']) {
        if (in_array($order['order_status'], [0, 1]) && 0 == $order['shipping_status']) {
            return 'WAITSEND';
        } //'待发货',
    } else { // 非货到付款
        if (0 == $order['pay_status'] && 0 == $order['order_status']) {
            return 'WAITPAY';
        } //'待支付',
        if (1 == $order['pay_status'] && in_array($order['order_status'], [0, 1]) && 0 == $order['shipping_status']) {
            return 'WAITSEND';
        } //'待发货',
        if (1 == $order['pay_status'] && 2 == $order['shipping_status'] && 1 == $order['order_status']) {
            return 'PORTIONSEND';
        } //'部分发货',
    }
    if ((1 == $order['shipping_status']) && (1 == $order['order_status'])) {
        return 'WAITRECEIVE';
    } //'待收货',
    if (2 == $order['order_status']) {
        return 'WAITCCOMMENT';
    } //'待评价',
    if (3 == $order['order_status']) {
        return 'CANCEL';
    } //'已取消',
    if (4 == $order['order_status']) {
        return 'FINISH';
    } //'已完成',
    if (5 == $order['order_status']) {
        return 'CANCELLED';
    } //'已作废',
    if (6 == $order['order_status']) {
        return 'AFTER-SALES';
    } //'售后状态',
    return 'OTHER';
}

/**
 * 获取订单状态的 显示按钮.
 *
 * @param type $order_id 订单id
 * @param type $order 订单数组
 *
 * @return array()
 */
function orderBtn($order_id = 0, $order = [])
{
    if (empty($order)) {
        $order = M('Order')->where('order_id', $order_id)->find();
    }
    /**
     *  订单用户端显示按钮.
     * 去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
     * 取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
     * 确认收货  AND shipping_status=1 AND order_status=0
     * 评价      AND order_status=1
     * 查看物流  if(!empty(物流单号))
     */
    $btn_arr = [
        'pay_btn' => 0, // 去支付按钮
        'cancel_btn' => 0, // 取消按钮
        'receive_btn' => 0, // 确认收货
        'comment_btn' => 0, // 评价按钮
        'shipping_btn' => 0, // 查看物流
        'return_btn' => 0, // 退货按钮 (联系客服)
    ];

    // 货到付款
    if ('cod' == $order['pay_code']) {
        if ((0 == $order['order_status'] || 1 == $order['order_status']) && 0 == $order['shipping_status']) { // 待发货
            $btn_arr['cancel_btn'] = 1; // 取消按钮 (联系客服)
        }
        if (1 == $order['shipping_status'] && 1 == $order['order_status']) { //待收货
            $btn_arr['receive_btn'] = 1;  // 确认收货
        }
    } else {// 非货到付款
        if (0 == $order['pay_status'] && 0 == $order['order_status']) { // 待支付
            $btn_arr['pay_btn'] = 1; // 去支付按钮
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if (1 == $order['pay_status'] && in_array($order['order_status'], [0, 1]) && 0 == $order['shipping_status']) { // 待发货
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if (1 == $order['pay_status'] && 1 == $order['order_status'] && 1 == $order['shipping_status']) { //待收货
            $btn_arr['receive_btn'] = 1;  // 确认收货
//            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    }
    if (2 == $order['order_status']) {
        $btn_arr['comment_btn'] = 1;  // 评价按钮
        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    if (0 != $order['shipping_status'] && in_array($order['order_status'], [1, 2, 4])) {
        $btn_arr['shipping_btn'] = 1; // 查看物流
    }
    if (2 == $order['shipping_status'] && 1 == $order['order_status']) { // 部分发货
//        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }

    if (1 == $order['pay_status'] && 1 == $order['shipping_status'] && 4 == $order['order_status']) { // 已完成(已支付, 已发货 , 已完成)
        $btn_arr['return_btn'] = 1; // 退货按钮
    }

    if (3 == $order['order_status'] && (1 == $order['pay_status'] || 4 == $order['pay_status'])) {
        $btn_arr['cancel_info'] = 1; // 取消订单详情
    }

    return $btn_arr;
}

/**
 * 给订单数组添加属性  包括按钮显示属性 和 订单状态显示属性.
 *
 * @param type $order
 */
function set_btn_order_status($order)
{
    $order_status_arr = C('ORDER_STATUS_DESC');
    $order['order_status_code'] = $order_status_code = orderStatusDesc(0, $order); // 订单状态显示给用户看的

    if ($order_status_code == 'WAITSEND' && $order['order_status'] == 1) {
        $order['order_status_desc'] = '商家已确认，等待发货';
    } else {
        $order['order_status_desc'] = $order_status_arr[$order_status_code];
    }

    $orderBtnArr = orderBtn(0, $order);

    return array_merge($order, $orderBtnArr); // 订单该显示的按钮
}

/**
 * VIP充值返利上级
 * $order_sn 订单号.
 */
function rechargevip_rebate($order)
{
    //获取返利配置
    $tpshop_config = tpCache('basic');
    //检查配置是否开启
    if ($tpshop_config['rechargevip_on_off'] > 0 && $tpshop_config['rechargevip_rebate_on_off'] > 0) {
        //查询充值VIP上级
        $userid = $order['user_id'];
        //更改用户VIP状态
        Db::name('users')->where('user_id', $userid)->save(['is_vip' => 1]);
        $first_leader = Db::name('users')->where('user_id', $userid)->value('first_leader');
        if ($first_leader) {
            //变动上级资金，记录日志
            $msg = '获取线下' . $userid . '充值VIP返利' . $tpshop_config['rechargevip_rebate'];
            accountLog($first_leader, $tpshop_config['rechargevip_rebate'], 0, $msg, 0, 0, $order['order_sn']);
        }
    }
}

/**
 * 支付完成修改订单.
 *
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 *
 * @return bool|void
 */
function update_pay_status($order_sn, $ext = [])
{
    $time = time();

    if (false !== stripos($order_sn, 'recharge')) {
        //用户在线充值
        $order = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) {
            return false;
        } // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('recharge')->where('order_sn', $order_sn)->save(['pay_status' => 1, 'pay_time' => $time]);

        $msg = '会员在线充值';
        if (1 == $order['buy_vip']) {
            rechargevip_rebate($order);
            $msg = '会员充值购买VIP';
        }
        accountLog($order['user_id'], $order['account'], 0, $msg, 0, 0, $order_sn);
    } else {
        // 如果这笔订单已经处理过了
        $count = M('order')->master()->where('order_sn = :order_sn and (pay_status = 0 OR pay_status = 2)')->bind(['order_sn' => $order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if (0 == $count) {
            return false;
        }
        // 找出对应的订单
        $order = M('order')->master()->where('order_sn', $order_sn)->find();
        if (6 == $order['prom_type'] && 0 != $order['order_amount']) {
            $team = new \app\common\logic\team\Team();
            $team->setTeamActivityById($order['prom_id']);
            $team->setOrder($order);
            $team->doOrderPayAfter();
        }

        // 订单优惠券、兑换券处理
        $coupon = new \app\common\logic\CouponLogic();
        $coupon->setOrder($order);
        $coupon->doOrderPayAfter();

        // 双十一任务（随机红包）
        $task = new \app\common\logic\TaskLogic();
        $task->setOrder($order);
        $task->doOrderPayAfter();

        // 加价购
        $task = new \app\common\logic\order\ExtraLogic();
        $task->setOrder($order);
        $task->doOrderPayAfter();

        // 团购
        if (2 == $order['prom_type']) {
            $prom_id = $order['prom_id'];
            $group = M('group_detail')->where('group_id', $prom_id)->order('id desc')->find();
            if (!$group) {
                $group_activity = M('group_buy')->where('id', $prom_id)->find();
                $data = [
                    'group_id' => $prom_id,
                    'status' => 1,
                    'batch' => 1,
                    'order_sn_list' => $order['order_sn'],
                    'order_num' => 1,
                    'time' => time(),
                ];
                if ($group_activity['group_goods_num'] <= $data['order_num']) {
                    $data['status'] = 2;
                }
                M('group_detail')->insert($data);
            } else {
                if (2 == $group['status']) {
                    $data = [
                        'group_id' => $prom_id,
                        'status' => 1,
                        'batch' => $group['batch'] + 1,
                        'order_sn_list' => $order['order_sn'],
                        'order_num' => 1,
                        'time' => time(),
                    ];
                    M('group_detail')->insert($data);
                } else {
                    $data = [];
                    $data['order_sn_list'] = $group['order_sn_list'] . ',' . $order['order_sn'];
                    $data['order_num'] = $group['order_num'] + 1;
                    $data['status'] = 1;
                    $data['time'] = $group['time'];
                    $group_activity = M('group_buy')->where('id', $prom_id)->find();
                    if ($group_activity['group_goods_num'] <= $data['order_num']) {
                        $data['status'] = 2;
                        $data['time'] = time();
                    }
                    M('group_detail')->where('id', $group['id'])->save($data);
                }
            }
            $orderGoodsArr = M('OrderGoods')->master()->where('order_id', $order['order_id'])->select();
            foreach ($orderGoodsArr as $key => $val) {
                if (2 == $val['prom_type']) {
                    M('group_buy')->where('id', $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                    M('group_buy')->where('id', $val['prom_id'])->setInc('order_num');
                }
            }
        }

        //预售订单
        if (4 == $order['prom_type']) {
            $orderGoodsArr = M('OrderGoods')->where(['order_id' => $order['order_id']])->find();
            // 预付款支付 有订金支付 修改支付状态  部分支付
            if ($order['total_amount'] != $order['order_amount'] && 0 == $order['pay_status']) {
                //支付订金
                M('order')->where('order_sn', $order_sn)->save(['order_sn' => date('YmdHis') . mt_rand(1000, 9999), 'pay_status' => 2, 'pay_time' => $time, 'paid_money' => $order['order_amount']]);
                M('goods_activity')->where(['act_id' => $order['prom_id']])->setInc('act_count', $orderGoodsArr['goods_num']);
            } else {
                //全额支付 无订金支付 支付尾款
                M('order')->where('order_sn', $order_sn)->save(['pay_status' => 1, 'pay_time' => $time]);
                $pre_sell = M('goods_activity')->where(['act_id' => $order['prom_id']])->find();
                $ext_info = unserialize($pre_sell['ext_info']);
                //全额支付 活动人数加一
                if (empty($ext_info['deposit'])) {
                    M('goods_activity')->where(['act_id' => $order['prom_id']])->setInc('act_count', $orderGoodsArr['goods_num']);
                }
                //预售全额支付发票生成
                $Invoice = new \app\admin\logic\InvoiceLogic();
                $Invoice->createInvoice($order);
            }
        } else {
            // 修改支付状态  已支付
            $updata = ['pay_status' => 1, 'pay_time' => $time];
            if (isset($ext['transaction_id'])) {
                $updata['transaction_id'] = $ext['transaction_id'];
            }
            M('order')->where('order_sn', $order_sn)->save($updata);
//             if(is_weixin()){
//             	$wx_user = M('wx_user')->find();
//             	$jssdk = new \app\common\logic\JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
//             	$order['goods_name'] = M('order_goods')->where(array('order_id'=>$order['order_id']))->getField('goods_name');
//             	$jssdk->send_template_message($order);//发送微信模板消息提醒
//             }
            //发票生成
            $Invoice = new \app\admin\logic\InvoiceLogic();
            $Invoice->createInvoice($order);
        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if (2 == tpCache('shopping.reduce')) {
            if (6 == $order['prom_type']) {
                $team = \app\common\model\TeamActivity::get($order['prom_id']);
                if (2 != $team['team_type']) {
                    minus_stock($order);
                }
            } else {
                minus_stock($order);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
//        update_user_level($order['user_id']);

        // 记录订单操作日志
        if (array_key_exists('admin_id', $ext)) {
            logOrder($order['order_id'], $ext['note'], '付款成功', $ext['admin_id']);
        } else {
            logOrder($order['order_id'], '订单付款成功', '付款成功', $order['user_id']);
        }
        //分销设置
        M('rebate_log')->where('order_id', $order['order_id'])->save(['status' => 1]);

        // 成为分销商条件 && 分销商升级 BY J
        update_user_distribut($order['user_id'], $order['order_id']);

        // 成为分销商条件
        // $distribut_condition = tpCache('distribut.condition');
        // if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
        //     M('users')->where("user_id", $order['user_id'])->save(array('is_distribut'=>1));
        //虚拟服务类商品支付
        if (5 == $order['prom_type']) {
            $OrderLogic = new \app\common\logic\OrderLogic();
            $OrderLogic->make_virtual_code($order);
        }
        $order['pay_time'] = $time;
        //用户支付, 发送短信给商家
        $res = checkEnableSendSms('4');
        if ($res && 1 == $res['status']) {
            $sender = tpCache('shop_info.mobile');
            if (!empty($sender)) {
                $params = ['order_id' => $order['order_id']];
                sendSms('4', $sender, $params);
            }
        }

        // 如果有微信公众号 则推送一条消息到微信
        $user = Db::name('OauthUsers')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ($user) {
            $wx_content = "您刚成功下了一笔订单，并成功支付。\n订单编号:{$order['order_sn']}\n温馨提示：每周五至周日订单统一在下周一发货配送";
            $wechat = new WechatUtil();
            $wechat->sendMsg($user['openid'], 'text', $wx_content);
        }
        // 发送微信消息模板提醒
        // $wechat = new \app\common\logic\WechatLogic;
        // $wechat->sendTemplateMsgOnPaySuccess($order);
    }
}

/**
 * 订单确认收货.
 *
 * @param $id 订单id
 * @param int $user_id
 *
 * @return array
 */
function confirm_order($id, $user_id = 0)
{
    $where['order_id'] = $id;
    if ($user_id) {
        $where['user_id'] = $user_id;
    }
    $order = M('order')->where($where)->find();
    if (1 != $order['order_status']) {
        return ['status' => -1, 'msg' => '该订单不能收货确认', 'result' => null];
    }
    if (empty($order['pay_time']) || 1 != $order['pay_status']) {
        return ['status' => -1, 'msg' => '商家未确定付款，该订单暂不能确定收货', 'result' => null];
    }
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = time(); // 收货确认时间
    $auto_service_date = tpCache('shopping.auto_service_date') * 24 * 60 * 60;
    $data['end_sale_time'] = time() + $auto_service_date;
    if ('cod' == $order['pay_code']) {
        $data['pay_time'] = time();
    }
    $row = M('order')->where(['order_id' => $id])->save($data);
    if (!$row) {
        return ['status' => -3, 'msg' => '操作失败', 'result' => null];
    }

    order_give($order); // 调用送礼物方法, 给下单这个人赠送相应的礼物
    //分销设置
    M('rebate_log')->where(['order_id' => $id, 'status' => ['NOT IN', ['3', '4', '5']]])->save(['status' => 2, 'confirm' => time()]);

    // 邀请任务 (开始)
//    $orderGoodsArr = M('OrderGoods')->where(['order_id' => $id])->select();
//    $level = [];
//    //1.判断购买的商品是否包含升级专区的商品 zone == 3
//    //且有且只有distribut_id > 0 的商品才更新用户等级
//    foreach ($orderGoodsArr as $v) {
//        $goods_info = M('goods')->field('zone,distribut_id')->where(['goods_id' => $v['goods_id']])->find();
//        if (3 == $goods_info['zone'] && $goods_info['distribut_id'] > 0) {
//            $level[] = $goods_info['distribut_id'];
//        }
//    }
//    if ($level) {
//        $level_list = M('distribut_level')->where('level_id', 'in', $level)->order('order_money')->select();
//        $level = end($level_list);
//        $user_info = M('users')->master()->field('user_id,distribut_level,first_leader')->where('user_id', $order['user_id'])->find() ?: 1;
//        if ($user_info['first_leader'] > 0) {
//            $TaskLogic = new app\common\logic\TaskLogic(2);
//            $TaskLogic->setOrder($order);
//            $TaskLogic->setUser($user_info);
//            $TaskLogic->setDistributId($level['level_id']);
//            $TaskLogic->doInviteAfter();
//        }
//    }
    // 邀请任务 (结束)

    // 销售任务（随机红包）
//    $task2 = new \app\common\logic\TaskLogic(3);
//    $task2->setOrder($order);
//    $task2->doOrderPayAfterSell();

    // 记录订单操作日志
    $action_info = [
        'order_id' => $order['order_id'],
        'action_user' => 0,
        'order_status' => 1,
        'shipping_status' => 1,
        'pay_status' => 1,
        'action_note' => '用户确认收货',
        'status_desc' => '确认收货',
        'log_time' => time(),
    ];
    M('order_action')->add($action_info);

    return ['status' => 1, 'msg' => '操作成功', 'result' => null];
}

/**
 * 下单赠送活动：优惠券，积分.
 *
 * @param $order |订单数组
 */
function order_give($order)
{
    //促销优惠订单商品
    $prom_order_goods = M('order_goods')->where(['order_id' => $order['order_id'], 'prom_type' => 3])->select();
    //获取用户会员等级
//    $user_level = M('users')->where(['user_id' => $order['user_id']])->getField('level');
    foreach ($prom_order_goods as $goods) {
        //查找购买商品送优惠券活动
        $prom_goods = M('prom_goods')->where(['id' => $goods['prom_id'], 'type' => 3])->find();
        if ($prom_goods) {
            //查找购买商品送优惠券模板
            $goods_coupon = M('coupon')->where(['id' => $prom_goods['expression']])->find();
//            if ($goods_coupon && !empty($prom_goods['group'])) {
            if ($goods_coupon) {
                // 用户会员等级是否符合送优惠券活动
//                if (in_array($user_level, explode(',', $prom_goods['group']))) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if (0 == $goods_coupon['createnum'] ||
                    ($goods_coupon['createnum'] > 0 && ($goods_coupon['createnum'] - $goods_coupon['send_num']) > 0)
                ) {
                    $data = ['cid' => $goods_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $goods_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time()];
                    M('coupon_list')->add($data);
                    // 优惠券领取数量加一
                    M('Coupon')->where('id', $goods_coupon['id'])->setInc('send_num');
                }
//                }
            }
        }
    }
    //查找订单满额促销活动
    $prom_order_where = [
        'type' => ['gt', 1],
        'end_time' => ['gt', $order['pay_time']],
        'start_time' => ['lt', $order['pay_time']],
        'money' => ['elt', $order['goods_price']],
    ];
    $prom_orders = M('prom_order')->where($prom_order_where)->order('money desc')->select();
    $prom_order_count = count($prom_orders);
    // 用户会员等级是否符合送优惠券活动
    for ($i = 0; $i < $prom_order_count; ++$i) {
//        if (in_array($user_level, explode(',', $prom_orders[$i]['group']))) {
        $prom_order = $prom_orders[$i];
        if (3 == $prom_order['type']) {
            //查找订单送优惠券模板
            $order_coupon = M('coupon')->where('id', $prom_order['expression'])->find();
            if ($order_coupon) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if (0 == $order_coupon['createnum'] ||
                    ($order_coupon['createnum'] > 0 && ($order_coupon['createnum'] - $order_coupon['send_num']) > 0)
                ) {
                    $data = ['cid' => $order_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $order_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time()];
                    M('coupon_list')->add($data);
                    M('Coupon')->where('id', $order_coupon['id'])->setInc('send_num'); // 优惠券领取数量加一
                }
            }
        }
        //购买商品送积分
        if (2 == $prom_order['type']) {
            accountLog($order['user_id'], 0, $prom_order['expression'], '订单活动赠送积分', 0, 0, '', 0, 5);
        }
        break;
//        }
    }
    $points = M('order_goods')->where('order_id', $order['order_id'])->sum('give_integral * goods_num');
    $points && accountLog($order['user_id'], 0, $points, '下单赠送积分', 0, $order['order_id'], $order['order_sn'], 0, 5);
}

/**
 * 获取商品一二三级分类.
 *
 * @return array
 */
function get_goods_category_tree($isApp = false)
{
    $tree = $arr = $result = [];
    $cat_list = M('goods_category')
        ->alias('c')
        ->field('c.*,p.position_id')
        ->join('__AD_POSITION__ p', 'c.id = p.category_id', 'LEFT')
        // ->cache(true)
        ->where(['is_show' => 1])
        ->order('sort_order desc, id asc')
        ->select(); //所有分类
    if ($cat_list) {
        $abroadCateId = 0;  // 海外购分类
        // 分类广告
        foreach ($cat_list as $ck => $cv) {
            if ($cv['parent_id'] == 0 && strstr($cv['name'], '海外购')) {
                if (!$isApp) {
                    unset($cat_list[$ck]);
                    continue;
                }
                $abroadCateId = $cv['id'];
            }
            $cat_list[$ck]['ad_list'] = null;
            if ($cv['position_id'] > 0) {
                $ad_list = M('Ad')->where('pid', $cv['position_id'])->select();
                $cat_list[$ck]['ad_list'] = $ad_list;
            }
        }
        // 分类等级
        foreach ($cat_list as $val) {
            if (1 == $val['level']) {
                $tree[] = $val;
            }
            if (2 == $val['level']) {
                $arr[$val['parent_id']][] = $val;
            }
            if (3 == $val['level']) {
                if ($abroadCateId != 0 && strstr($val['parent_id_path'], $abroadCateId . '')) {
                    $val['is_abroad'] = 1;
                } else {
                    $val['is_abroad'] = 0;
                }
                $crr[$val['parent_id']][] = $val;
            }
        }
        // 第三级分类
        foreach ($arr as $k => $v) {
            foreach ($v as $kk => $vv) {
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? [] : $crr[$vv['id']];
            }
        }
        // 第二级分类
        foreach ($tree as $val) {
            $val['tmenu'] = empty($arr[$val['id']]) ? [] : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return array_values($result);
}

/**
 * 写入静态页面缓存.
 */
function write_html_cache($html)
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('write_html_cache写入缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) { //不是当前 模块 控制器 方法 直接跳过
            continue;
        }

        //if(!is_dir(RUNTIME_PATH.'html'))
        //mkdir(RUNTIME_PATH.'html');
        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename = $m_c_a_str;
        // 组合参数
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v) {
                $filename .= '_' . $_GET[$v];
            }
        }
        $filename .= '.html';
        \think\Cache::set($filename, $html);
        //file_put_contents($filename, $html);
    }
}

/**
 * 读取静态页面缓存.
 */
function read_html_cache()
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('read_html_cache读取缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) { //不是当前 模块 控制器 方法 直接跳过
            continue;
        }

        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename = $m_c_a_str;
        // 组合参数
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v) {
                $filename .= '_' . $_GET[$v];
            }
        }
        $filename .= '.html';
        $html = \think\Cache::get($filename);
        if ($html) {
            //echo file_get_contents($filename);
            echo \think\Cache::get($filename);
            exit();
        }
    }
}

/**
 * 获取完整地址
 */
function getTotalAddress($province_id, $city_id, $district_id, $twon_id, $address = '')
{
    static $regions = null;
    if (!$regions) {
        $regions = M('region2')->cache(true)->getField('id,name');
    }
    $total_address = $regions[$province_id] ?: '';
    $total_address .= $regions[$city_id] ?: '';
    $total_address .= $regions[$district_id] ?: '';
    $total_address .= $regions[$twon_id] ?: '';
    $total_address .= $address ?: '';

    return $total_address;
}

/**
 * 商品库存操作日志.
 *
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 */
function update_stock_log($muid, $stock = 1, $goods, $order_sn = '')
{
    if (!isset($goods['store_count']) && !$goods['spec_key_name']) {
        $goods['store_count'] = M('Goods')->where('goods_id', $goods['goods_id'])->getField('store_count');
    } elseif ($goods['spec_key_name']) {
        $goods['store_count'] = M('spec_goods_price')->where('goods_id', $goods['goods_id'])->where('key_name', $goods['spec_key_name'])->getField('store_count');
    } else {
        $goods['store_count'] = 0;
    }
    $data['ctime'] = time();
    $data['stock'] = $stock;
    $data['muid'] = $muid;
    $data['goods_id'] = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['g_stock'] = $goods['store_count'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? $goods['key_name'] : $goods['spec_key_name'];
    $data['order_sn'] = $order_sn;
    M('stock_log')->add($data);
}

/**
 * 订单支付时, 获取订单商品名称.
 *
 * @param unknown $order_id
 *
 * @return string|Ambigous <string, unknown>
 */
function getPayBody($order_id)
{
    if (empty($order_id)) {
        return '订单ID参数错误';
    }
    $goodsNames = M('OrderGoods')->where('order_id', $order_id)->column('goods_name');
    $gns = implode($goodsNames, ',');
    $payBody = getSubstr($gns, 0, 18);

    return $payBody;
}

/**
 * 邀请人记录
 * @param $invite
 * @param $userId
 * @param $status 0设置失败 1设置成功 -1设置不成功（用户已设置了推荐人）
 * @param $time
 */
function inviteLog($invite, $userId, $status, $time = null)
{
    M('invite_log')->add([
        'invite_uid' => $invite,
        'user_id' => $userId,
        'status' => $status,
        'create_time' => $time ?? time()
    ]);
}
