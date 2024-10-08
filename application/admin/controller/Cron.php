<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

use app\common\logic\OrderLogic;
use app\common\logic\OssLogic;
use app\common\logic\PushLogic;
use app\common\logic\SmsLogic;
use app\common\logic\Token as TokenLogic;
use app\common\logic\wechat\WechatUtil;
use app\common\model\Users as UsersModel;
use think\Controller;
use think\Db;

// 自动任务调度类
class Cron extends Controller
{

    /**
     * 自动上下架
     */
    function auto_on_out()
    {
        /*
         * 普通商品
         */
        // 上架
        M('goods')->where(array('is_area_show' => 1, 'is_on_sale' => 0, 'is_on_sale2' => 1, 'store_count' => array('gt', 0), 'on_time' => array('elt', NOW_TIME), 'out_time' => array('gt', NOW_TIME)))->data(array('is_on_sale' => 1))->save();
        // 下架
        M('goods')->where(array('is_area_show' => 1, 'is_on_sale' => 1, 'out_time' => array('elt', NOW_TIME)))->data(array('is_on_sale' => 0))->save();
        M('goods')->where(array('is_area_show|is_on_sale2|store_count' => 0))->data(array('is_on_sale' => 0))->save();
        /*
         * 代理商商品
         */
        // 上架
        M('goods')->where(array('is_on_sale' => 1, 'applet_on_sale' => 0, 'applet_on_sale2' => 1, 'store_count' => array('gt', 0), 'applet_on_time' => array('elt', NOW_TIME), 'applet_out_time' => array('gt', NOW_TIME)))->data(array('applet_on_sale' => 1))->save();
        // 下架
        M('goods')->where(array('is_on_sale' => 1, 'applet_on_sale' => 1, 'applet_out_time' => array('elt', NOW_TIME)))->data(array('applet_on_sale' => 0))->save();
        M('goods')->where(array('is_on_sale|is_on_sale2|applet_on_sale|applet_on_sale2|store_count' => 0))->data(array('applet_on_sale' => 0))->save();
    }

    // 检查错误订单积分 上次检查 2018-12-14 15:43
    public function orderFix()
    {
        $order_list = M('order')->field('order_id,order_sn,order_amount,integral,integral_money')->select();
        $error_order = [];
        foreach ($order_list as $v) {
            $order_goods_integral = 0;
            $order_goods = M('order_goods')->field('goods_num,use_integral')->where('order_id', $v['order_id'])->select();
            foreach ($order_goods as $gv) {
                $order_goods_integral += $gv['goods_num'] * $gv['use_integral'];
            }
            if ($order_goods_integral != $v['integral']) {
                $error_order[] = $v['order_id'];
//                M('order')->where('order_id',$v['order_id'])->update(['integral'=>$order_goods_integral,'integral_money'=>$order_goods_integral]);
            }
        }
        dump($error_order);
        exit;
    }

    /**
     * 同步图文素材管理.
     */
    public function tongbuNews()
    {
        $wx_user = Db::name('wx_user')->find();
        $wechatObj = new WechatUtil($wx_user);

        $num = 20;
        $offset = 0;
        $data = $wechatObj->getMaterialList('news', $offset, $num);
        if (!$data) {
            echo $wechatObj->getError();
            exit;
        }
        if ($data) {
            $insert_arr = [];
            $insert_m_arr = [];

            $list = $data['item'];
            foreach ($list as $k => $v) {
                if (!M('wx_material')->where('media_id', $v['media_id'])->find()) {
                    $insert_m_arr[$k]['media_id'] = $v['media_id'];
                    $insert_m_arr[$k]['type'] = 'news';
                    // $insert_m_arr[$k]['data'] = json_encode($v['content']);
                    $insert_m_arr[$k]['data'] = '';
                    $insert_m_arr[$k]['update_time'] = $v['update_time'];
                    $material_id = M('wx_material')->add($insert_m_arr[$k]);
                }
                if ($material_id && !M('wx_news')->where('thumb_media_id', $v['content']['news_item'][0]['thumb_media_id'])->find()) {
                    $insert_arr[$k]['material_id'] = $material_id;
                    $insert_arr[$k]['update_time'] = $v['update_time'];
                    $insert_arr[$k]['title'] = $v['content']['news_item'][0]['title'];
                    $insert_arr[$k]['author'] = $v['content']['news_item'][0]['author'];
                    // $insert_arr[$k]['content'] = $v['content']['news_item'][0]['content'];
                    $insert_arr[$k]['content'] = '';
                    $insert_arr[$k]['digest'] = $v['content']['news_item'][0]['digest'];
                    $insert_arr[$k]['thumb_url'] = $v['content']['news_item'][0]['thumb_url'];
                    $insert_arr[$k]['thumb_media_id'] = $v['content']['news_item'][0]['thumb_media_id'];
                    $insert_arr[$k]['content_source_url'] = $v['content']['news_item'][0]['content_source_url'];
                    $insert_arr[$k]['show_cover_pic'] = $v['content']['news_item'][0]['show_cover_pic'];
                    M('wx_news')->add($insert_arr[$k]);
                }
            }
        }
    }

    public function accountLogFix()
    {
        $rows = Db::query("SELECT * FROM tp_account_log WHERE user_id IN
(SELECT user_id FROM tp_account_log WHERE `desc` = '电商转入商城' AND user_id >= 23964  GROUP BY user_id,pay_points,user_electronic  HAVING COUNT(*) > 1 ORDER BY user_id)
AND pay_points IN
(SELECT pay_points FROM tp_account_log WHERE `desc` = '电商转入商城' AND user_id >= 23964  GROUP BY user_id,pay_points,user_electronic  HAVING COUNT(*) > 1 ORDER BY user_id)
AND user_electronic IN
(SELECT user_electronic FROM tp_account_log WHERE `desc` = '电商转入商城' AND user_id >= 23964  GROUP BY user_id,pay_points,user_electronic  HAVING COUNT(*) > 1 ORDER BY user_id)
AND log_id NOT IN
(SELECT MIN(log_id) FROM tp_account_log WHERE `desc` = '电商转入商城' AND user_id >= 23964  GROUP BY user_id,pay_points,user_electronic  HAVING COUNT(*) > 1 ORDER BY user_id)
");
        foreach ($rows as $key => $value) {
            $update = [];
            $update['pay_points'] = ['exp', 'pay_points+' . -$value['pay_points']];
            $update['user_electronic'] = ['exp', 'user_electronic+' . -$value['user_electronic']];
            $str = M('users')->where('user_id', $value['user_id'])->update($update);
            // $str = M('users')->where('user_id',$value['user_id'])->fetchSql(1)->update($update);
            // file_put_contents('account_sql.log', $str."\r\n", FILE_APPEND | LOCK_EX);
        }
    }

    public function accountFenLei()
    {
        M('account_log')->where('desc', 'LIKE', '%退款到用户余额%')->update(['type' => 19]);
        M('account_log')->where('desc', 'LIKE', '%客服调整%')->update(['type' => 0]);
        M('account_log')->where('desc', 'LIKE', '%提现已完成%')->update(['type' => 20]);
        M('account_log')->where('desc', 'LIKE', '%管理员处理用户提现申请%')->update(['type' => 20]);
        M('account_log')->where('desc', 'LIKE', '%用户申请商品退款%')->update(['type' => 19]);
        M('account_log')->where('desc', 'LIKE', '%退货积分追回%')->update(['type' => 19]);
        M('account_log')->where('desc', 'LIKE', '%用户申请订单退款%')->update(['type' => 19]);
        M('account_log')->where('desc', 'LIKE', '%用户取消订单退款%')->update(['type' => 10]);
        M('account_log')->where('desc', 'LIKE', '%会员注册赠送积分%')->update(['type' => 6]);
        M('account_log')->where('desc', 'LIKE', '%订单活动赠送积分%')->update(['type' => 5]);
        M('account_log')->where('desc', 'LIKE', '%下单赠送积分%')->update(['type' => 5]);
        M('account_log')->where('desc', 'LIKE', '%佣金分成%')->update(['type' => 1]);
        M('account_log')->where('desc', 'LIKE', '%订单取消%')->update(['type' => 10]);
        M('account_log')->where('desc', 'LIKE', '%【双十一活动】购买双十一标签商品赠送%')->update(['type' => 18]);
        M('account_log')->where('desc', 'LIKE', '%返回双十一奖励%')->update(['type' => 10]);
        M('account_log')->where('desc', 'LIKE', '%签到%')->update(['type' => 8]);
        M('account_log')->where('desc', 'LIKE', '%邀请用户奖励积分%')->update(['type' => 7]);
        M('account_log')->where('desc', 'LIKE', '%会员注册赠送积分%')->update(['type' => 6]);
        M('account_log')->where('desc', 'LIKE', '%用户余额转电子币%')->update(['type' => 13]);
        M('account_log')->where('desc', 'LIKE', '%电子币充值（余额转）%')->update(['type' => 13]);
        M('account_log')->where('desc', 'LIKE', '%转出电子币给用户%')->update(['type' => 13]);
        M('account_log')->where('desc', 'LIKE', '%来自用户%')->update(['type' => 13]);
        M('account_log')->where('desc', 'LIKE', '%转出积分给用户%')->update(['type' => 12]);
        M('account_log')->where('desc', 'LIKE', '%转入积分From用户%')->update(['type' => 12]);
        M('account_log')->where('desc', 'LIKE', '%用户领取任务奖励%')->where('user_electronic', 'gt', 0)->update(['type' => 15]);
        M('account_log')->where('desc', 'LIKE', '%用户领取任务奖励%')->where('pay_points', 'gt', 0)->update(['type' => 16]);
        M('account_log')->where('desc', 'LIKE', '%下单消费%')->update(['type' => 3]);
    }

    //套组组合
    public function downloadTaozuzuhe()
    {
        $start_time = 1541001600;
        $end_time = 1543593600;
        $goods_list = M('Goods')->field('goods_id,goods_sn')->where('sale_type', 2)->select();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">套组代码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">是否产生</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">平台</td>';
        $strTable .= '</tr>';
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $is_create = M('orderGoods')
                    ->alias('og')
                    ->join('__ORDER__ o', 'o.order_id = og.order_id', 'left')
                    ->where('og.goods_id', $val['goods_id'])
                    ->where('og.is_send', 1)
                    ->where('o.pay_status', 1)
                    ->where('o.add_time', 'lt', $end_time)
                    ->where('o.add_time', 'gt', $start_time)
                    ->find() ? 'Y' : 'N';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $is_create . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'Taozuzuhe');
        exit();
    }

    //套组构成
    public function downloadTaozuzuheGoucheng()
    {
        $goods_list = M('Goods')
            ->field('g.goods_id,g.goods_sn,gsg.goods_sn as g_goods_sn,gs.g_number')
            ->alias('g')
            ->join('__GOODS_SERIES__ gs', 'gs.goods_id = g.goods_id', 'left')
            ->join('__GOODS__ gsg', 'gsg.goods_id = gs.g_id', 'left')
            ->where('g.sale_type', 2)
            ->select();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">套组代码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">包含产品</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">包含数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">平台</td>';
        $strTable .= '</tr>';
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['g_goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['g_number'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'TaozuzuheGoucheng');
        exit();
    }

    //库存管理
    public function downloadStoreManager()
    {
        $start_time = 1541001600;
        $end_time = 1543593600;
        $goods_list = M('StockLog')
            ->alias('s')
            ->field('s.*,g.goods_sn')
            ->join('__GOODS__ g', 'g.goods_id = s.goods_id', 'left')
            ->where('s.ctime', 'between', [$start_time, $end_time])
            ->order('id desc')
//        ->limit(500)
            ->select();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">产品码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">事由</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:160px;">订单号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">平台</td>';
        $strTable .= '</tr>';
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $str = '';
                if ($val['order_sn'] && $val['stock'] < 0) {
                    $str = '商品下单--出库';
                } elseif ($val['order_sn'] && $val['stock'] > 0) {
                    $str = '商品取消订单--入库';
                } elseif (!$val['order_sn'] && $val['stock'] > 0) {
                    $str = '手动调整--入库';
                } else {
                    $str = '手动调整--出库';
                }
                $day = date('Y-m-d H:i', $val['ctime']);
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $str . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['stock'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        // 补充12月份出库记录
        $order_sns = M('Order')->where('shipping_status', 1)->whereBetween('add_time', [1541001600, 1543593600])->getField('order_sn', true);
        $goods_list = M('StockLog')
            ->alias('s')
            ->field('s.*,g.goods_sn')
            ->join('__GOODS__ g', 'g.goods_id = s.goods_id', 'left')
            ->where('s.ctime', 'egt', 1543593600)
            ->where('s.order_sn', 'in', $order_sns)
            ->order('id desc')
//        ->limit(500)
            ->select();

        if ($goods_list) {
            foreach ($goods_list as $k => $val) {
                if ($val['order_sn'] && $val['stock'] < 0) {
                    $str = '商品下单--出库';
                } elseif ($val['order_sn'] && $val['stock'] > 0) {
                    $str = '商品取消订单--入库';
                } elseif (!$val['order_sn'] && $val['stock'] > 0) {
                    $str = '手动调整--入库';
                } else {
                    $str = '手动调整--出库';
                }
                $day = date('Y-m-d H:i', $val['ctime']);
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $str . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['stock'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        $strTable .= '</table>';
        downloadExcel($strTable, 'downloadStoreManager');
        exit();
    }

    //销售明细
    public function downloadSaleDetail()
    {
        $start_time = 1541001600;
        $end_time = 1543593600;
        $order_ids = M('Order')->where('shipping_status', 1)->whereBetween('add_time', [$start_time, $end_time])->getField('order_id', true);

        $goods_list = M('Order')
            ->where('shipping_status', 1)
            ->where('order_id', 'in', $order_ids)
            // ->limit(50)
            ->order('order_id desc')
            ->select();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">销售日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:160px;">订单号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">会员号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">现金</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">刷卡</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">电子币</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">优惠券</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">运费</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">总额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">备注</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">平台</td>';
        $strTable .= '</tr>';
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $use_integral = $val['integral'];
                $total_amount = $val['order_amount'] + $use_integral + $val['coupon_price'] + $val['user_electronic'] + $val['shipping_price'];
                $day = date('Y-m-d H:i:s', $val['pay_time']);

                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['order_amount'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">0</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_electronic'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $use_integral . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['coupon_price'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['shipping_price'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_amount . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        // 补充退款单的记录
        $goods_list = M('order_action')
            ->alias('oa')
            ->join('__ORDER__ o', 'o.order_id = oa.order_id', 'RIGHT')
            ->whereBetween('oa.log_time', [$start_time, $end_time])
            ->where('oa.order_status', 3)
            ->where('o.add_time', 'lt', $start_time)
            ->select();

        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $use_integral = $val['integral'];
                $total_amount = $val['order_amount'] + $use_integral + $val['coupon_price'] + $val['user_electronic'] + $val['shipping_price'];
                $day = date('Y-m-d H:i:s', $val['pay_time']);

                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['order_amount'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">0</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_electronic'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $use_integral . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['coupon_price'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['shipping_price'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_amount . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">（退款单）</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        // 补充退货退款订单
        $goods_list = M('return_goods')->alias('rg')->join('__ORDER__ o', 'o.order_id=rg.order_id')
            ->whereBetween('rg.refund_time', [$start_time, $end_time])
            ->select();
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $day = date('Y-m-d H:i:s', $val['addtime']);
                $total_amount = $val['refund_money'] + $val['refund_integral'];
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$val['refund_money'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">0</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$val['refund_electronic'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$val['refund_integral'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;"></td>';
                $strTable .= '<td style="text-align:center;font-size:12px;"></td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$total_amount . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">（有退货退款商品）</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        $strTable .= '</table>';
        downloadExcel($strTable, 'downloadSaleDetail');
        exit();
    }

    //产品销售明细
    public function downloadProductSaleDetail()
    {
        $start_time = 1541001600;
        $end_time = 1543593600;
        $order_ids = M('Order')->where('shipping_status', 1)->whereBetween('add_time', [$start_time, $end_time])->getField('order_id', true);

        $goods_list = M('OrderGoods')
            ->alias('og')
            ->field('og.*,g.stax_price,g.ctax_price,o.order_sn,o.pay_time')
            ->join('__GOODS__ g', 'g.goods_id = og.goods_id', 'left')
            ->join('__ORDER__ o', 'o.order_id = og.order_id', 'left')
            ->where('og.is_send', 1)
            // ->where('og.use_integral',0)
            ->where('o.order_id', 'in', $order_ids)
            ->order('o.order_id desc')
            // ->limit(50)
            ->select();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">产品码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:180px;">产品名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">数量</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">单价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">销售额（含税）</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">销售额（不含税）</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">税额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:180px;">订单号码</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">日期</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">备注</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">平台</td>';
        $strTable .= '</tr>';
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $total_price = $val['goods_price'] * $val['goods_num'];
                $total_ctax_price = $val['ctax_price'] * $val['goods_num'];
                $ctax = $total_price - $total_ctax_price;
                $day = date('Y-m-d H:i:s', $val['pay_time']);
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_num'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_price'] . '</td>';

                $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_price . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_ctax_price . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $ctax . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        // 补充退款单的记录
        $goods_list = M('order_action')
            ->alias('oa')
            ->join('__ORDER__ o', 'o.order_id = oa.order_id', 'RIGHT')
            ->join('__ORDER_GOODS__ og', 'og.order_id = oa.order_id', 'LEFT')
            ->whereBetween('oa.log_time', [$start_time, $end_time])
            ->where('oa.order_status', 3)
            ->where('o.add_time', 'lt', $start_time)
            ->select();
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $total_price = $val['goods_price'] * $val['goods_num'];
                $total_ctax_price = $val['ctax_price'] * $val['goods_num'];
                $ctax = $total_price - $total_ctax_price;
                $day = date('Y-m-d H:i:s', $val['pay_time']);
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_num'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_price'] . '</td>';

                $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_price . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_ctax_price . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $ctax . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">(退款单)</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        // 补充退货退款订单
        $goods_list = M('return_goods')->alias('rg')
            ->join('__ORDER__ o', 'o.order_id=rg.order_id')
            ->join('__ORDER_GOODS__ og', 'og.order_id = o.order_id', 'LEFT')
            ->join('__GOODS__ g', 'g.goods_id = og.goods_id', 'left')
            ->whereBetween('rg.refund_time', [$start_time, $end_time])
            // ->where('o.add_time','lt',$start_time)
            ->select();

        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $total_price = $val['goods_price'] * $val['goods_num'];
                $total_ctax_price = $val['ctax_price'] * $val['goods_num'];
                $ctax = $total_price - $total_ctax_price;
                $day = date('Y-m-d H:i:s', $val['addtime']);
                if ($val['add_time'] > $start_time) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['goods_sn'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_name'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_num'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_price'] . '</td>';

                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_price . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $total_ctax_price . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $ctax . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $day . '</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;"></td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                    $strTable .= '</tr>';
                }

                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$val['goods_num'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$val['goods_price'] . '</td>';

                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$total_price . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$total_ctax_price . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . -$ctax . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $val['order_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;' . $day . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">(退货退款单)</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }

        $strTable .= '</table>';
        downloadExcel($strTable, 'downloadProductSaleDetail');
        exit();
    }

    //产品明细
    public function downloadProductDetail()
    {
        $goods_list = M('Goods')
            ->limit(50)
            ->select();
        $GoodsLogic = new \app\admin\logic\GoodsLogic();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:180px;">商品货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:180px;">产品名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">大分类名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">中分类名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:60px;">小分类名</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">平台</td>';
        $strTable .= '</tr>';
        if (is_array($goods_list)) {
            foreach ($goods_list as $k => $val) {
                $level_cat = $GoodsLogic->find_parent_cat($val['cat_id']);
                $first_cat = M('goodsCategory')->where('id', $level_cat[1])->getField('name');
                $second_cat = M('goodsCategory')->where('id', $level_cat[2])->getField('name');
                $third_cat = M('goodsCategory')->where('id', $level_cat[3])->getField('name');
                $strTable .= '<tr>';

                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['goods_name'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $first_cat . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $second_cat . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $third_cat . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">圃美多商城</td>';
                $strTable .= '</tr>';
            }
            unset($goods_list);
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'downloadProductDetail');
        exit();
    }

    public function testCode()
    {
        // $order_list = M('Order')
        // ->where('add_time','gt',1537286400)
        // ->where('add_time','lt',1537372800)
        // ->where('order_status','in',[1,2,4])
        // ->where('pay_status',1)
        // ->select();
        // foreach ($order_list as $k => $v) {
        //     $total_money = 0;
        //     $order_goods_list = M('OrderGoods')->where('order_id',$v['order_id'])->select();
        //     foreach ($order_goods_list as $key => $value) {
        //         if($value['use_integral'] > 0)
        //         {
        //             $total_money += ($value['goods_price'] - $value['use_integral']) * $value['goods_num'];
        //         }else{
        //             $total_money += $value['goods_price'] * $value['goods_num'];
        //         }
        //     }
        //     if($total_money != $v['goods_price'])
        //     {
        //         echo $v['order_id'];
        //         echo "<BR>";
        //         echo $v['goods_price'].','.$total_money;
        //     }
        // }
        // $list = M('task_log')->where('task_id','in',[2,3])->select();
        // foreach ($list as $key => $value) {
        //     $task_reward_desc = M('taskReward')->where('reward_id',$value['task_reward_id'])->getField('description');
        //     M('task_log')->where('id',$value['id'])->update(['task_reward_desc'=>$task_reward_desc]);
        // }

        echo 'testCode123--追回123456' . date('Y-m-d : H:i:s');
    }

    public function bucongOrderLog()
    {
        $order_list = M('Order')->field('order_id,confirm_time')->where('confirm_time', 'gt', 0)->select();

        foreach ($order_list as $k => $v) {
            // 记录订单操作日志
            $action_info = [
                'order_id' => $v['order_id'],
                'action_user' => 0,
                'order_status' => 1,
                'shipping_status' => 1,
                'pay_status' => 1,
                'action_note' => '用户确认收货',
                'status_desc' => '确认收货',
                'log_time' => $v['confirm_time'],
            ];
            M('order_action')->add($action_info);
        }
    }

    public function tongbusucai()
    {
        $ids = M('wx_material')->group('media_id')->having('COUNT(*) >= 1')->order('id asc')->select();
        $ids = get_arr_column($ids, 'id');
        M('wx_material')->where('id', 'not in', $ids)->delete();
        $ids = M('wx_news')->group('thumb_media_id')->having('COUNT(*) >= 1')->order('id asc')->select();
        $ids = get_arr_column($ids, 'id');
        M('wx_news')->where('id', 'not in', $ids)->delete();
    }

    public function zhuihuiorder()
    {
        $order_list = M('order')->where('user_id', 16679)->select();

        foreach ($order_list as $k => $v) {
            $this->rebateLog($v);
        }
    }

    public function rebateLog($order)
    {
        $order_ids = [];

        if ($order['order_id'] <= 0) {
            return false;
        }

        $rebate_info = [
            'buy_user_id' => $order['user_id'],
            'nickname' => $order['user_id'],
            'order_sn' => $order['order_sn'],
            'order_id' => $order['order_id'],
            'goods_price' => $order['goods_price'],
            'create_time' => time(),
            'status' => 0,
        ];

        // $user_distributs = M('users')
        //     ->field('first_leader,second_leader,third_leader')
        //     ->where(array("user_id"=>$order['user_id']))
        //     ->find();
        $invite_uid = M('Users')->where('user_id', $order['user_id'])->getField('invite_uid');

        $first_leader = $this->_getShopUid($invite_uid, 2);

        $second_leader = $this->_getShopUid($invite_uid, 2, [$first_leader]);

        $third_leader = $this->_getShopUid($invite_uid, 2, [$first_leader, $second_leader]);

        $shop_uid = $this->_getShopUid($invite_uid, 3);

        $first_exisit = M('rebate_log')->where('order_id', $order['order_id'])->where('user_id', $first_leader)->where('level', 1)->find();
        $second_exisit = M('rebate_log')->where('order_id', $order['order_id'])->where('user_id', $second_leader)->where('level', 2)->find();
        $third_exisit = M('rebate_log')->where('order_id', $order['order_id'])->where('user_id', $third_leader)->where('level', 3)->find();
        $shop_exisit = M('rebate_log')->where('order_id', $order['order_id'])->where('user_id', $shop_uid)->where('level', 0)->find();

        // 计算用于分成的总金额

        $order_goods = M('OrderGoods')
            ->field('goods_id,goods_num,final_price')
            ->where('order_id', $order['order_id'])
            ->select();

        $distribut_total_money = 0;
        foreach ($order_goods as $ov) {
            $goods_commission = M('Goods')->where('goods_id', $ov['goods_id'])->getField('commission');
            $distribut_total_money += ($ov['final_price'] * $ov['goods_num']) * $goods_commission / 100;
        }

        if ($distribut_total_money > 0) {
            $rebate_info['order_money'] = $distribut_total_money;
            $status = 0;
            if (1 == $order['pay_status']) {
                $status = 1;
                if (2 == $order['order_status']) {
                    $status = 2;
                } elseif (3 == $order['order_status']) {
                    $status = 4;
                }
            } else {
                $status = 0;
                if (3 == $order['order_status']) {
                    $status = 4;
                }
            }

            // $status = $order['order_status'];
            //普通分销提成
            if ($first_leader > 0 && !$first_exisit) {
                $data = [];
                $data['user_id'] = $first_leader;
                $data['money'] = $distribut_total_money * tpCache('distribut.first_rate') / 100;
                $data['level'] = 1;
                $data['status'] = $status;
                $data = array_merge($rebate_info, $data);
                $order_ids[] = M('rebate_log')->add($data);
            }

            if ($second_leader > 0 && !$second_exisit) {
                $data = [];
                $data['user_id'] = $second_leader;
                $data['money'] = $distribut_total_money * tpCache('distribut.second_rate') / 100;
                $data['level'] = 2;
                $data['status'] = $status;
                $data = array_merge($rebate_info, $data);
                $order_ids[] = M('rebate_log')->add($data);
            }

            if ($third_leader > 0 && !$third_exisit) {
                $data = [];
                $data['user_id'] = $third_leader;
                $data['money'] = $distribut_total_money * tpCache('distribut.third_rate') / 100;
                $data['level'] = 3;
                $data['status'] = $status;
                $data = array_merge($rebate_info, $data);
                $order_ids[] = M('rebate_log')->add($data);
            }

            //商铺分销提成

            // $shop_uid = $this->_getShopUid($invite_uid);
            if ($shop_uid > 0 && !$shop_exisit) {
                $data = [];
                $data['user_id'] = $shop_uid;
                $data['money'] = $distribut_total_money * tpCache('distribut.shop_rate') / 100;
                $data['level'] = 0;
                $data['type'] = 1;
                $data['status'] = $status;
                $data = array_merge($rebate_info, $data);
                $order_ids[] = M('rebate_log')->add($data);
            }
        }
        if ($order_ids) {
            $order_ids = implode(',', $order_ids);
            echo $order_ids . ',';
        }
    }

    //获取最近的商店分销商ID
    private function _getShopUid($uid, $level, $where = '')
    {
        if ($uid < 1) {
            return 0;
        }

        $shop_id = 0;
        //等级id大于2为商铺代理
        $user_info = M('users')->field('distribut_level,user_id,invite_uid')
            ->where('user_id', $uid)
            ->find();
        $res = true;
        if ($where) {
            $res = !in_array($user_info['user_id'], $where);
        }

        if ($user_info['distribut_level'] >= $level && $res) {
            return $user_info['user_id'];
        }
        $shop_id = $this->_getShopUid($user_info['invite_uid'], $level, $where);

        return $shop_id;
    }

    public function test()
    {
        $data = M('users')->field('user_id,first_leader,second_leader')->select();
        $o = 0;
        foreach ($data as $k => $v) {
            if ($v['first_leader'] > 0) {
                $first_leader = M('users')->field('user_id,first_leader,second_leader')->where('user_id', $v['first_leader'])->find();
                if ($v['second_leader'] != $first_leader['first_leader']) {
                    M('users')->where('first_leader', $v['first_leader'])->update(['second_leader' => $first_leader['first_leader'], 'third_leader' => $first_leader['second_leader']]);
                    // echo $o;
                    // echo "<BR>";
                    // $o++;
                }
            }
        }
        echo 'string123';
    }

    public function test2()
    {
        $data = M('users')->field('user_id,first_leader,second_leader,third_leader')->select();
        $o = 0;
        foreach ($data as $k => $v) {
            if ($v['first_leader'] > 0) {
                $first_leader = M('users')->field('user_id,first_leader,second_leader')->where('user_id', $v['first_leader'])->find();
                if ($v['second_leader'] != $first_leader['first_leader'] || $v['third_leader'] != $first_leader['second_leader']) {
                    M('users')->where('first_leader', $v['first_leader'])->update(['second_leader' => $first_leader['first_leader'], 'third_leader' => $first_leader['second_leader']]);
                    // echo $v['user_id'];
                    // echo "<BR>";
                    ++$o;
                }
            }
        }
        echo "$o";
    }

    public function commission()
    {
        $yesterday = strtotime('2018-11-22 00:00:00');
        // $today = strtotime('2018-08-24 23:59:59');
        $now = time();
        $days = $now - $yesterday;
        $days = $days / 24 / 60 / 60;
        $days = (int)$days;
        for ($i = 0; $i < $days; ++$i) {
            $time = $yesterday + $i * 24 * 60 * 60;
            $day = date('Y-m-d H:i:s', $time);
            $time1 = $time + 24 * 60 * 60;
            // echo $day;
            // echo "<BR>";
            $this->commissionLog($time, $time1);
        }
        exit;
    }

    // 自动年度分成汇总
    public function commissionLogYear()
    {
        $lastYear = '2018-12-31';
        $lastYearLastDay = strtotime($lastYear);
        $lastYearFirstDay = strtotime(date('Y-01-01', strtotime($lastYear)));
        $lastYearRecrod = M('CommissionLog')->where('create_time', $lastYearLastDay)->where('type', 'y')->find();
        if (!$lastYearRecrod) {
            $where = [
                'create_time' => ['between', [$lastYearFirstDay, $lastYearLastDay]],
                'type' => 'm',
            ];
            $data = M('commission_log')
                ->where($where)
                ->select();
            if ($data) {
                $total_amount = $real_amount = $order_num = $shop_free = $sale_free = 0;
                $nomal_oid = $special_oid = '';
                foreach ($data as $k => $v) {
                    $total_amount += $v['total_amount'];
                    $real_amount += $v['real_amount'];
                    $order_num += $v['order_num'];
                    $shop_free += $v['shop_free'];
                    $sale_free += $v['sale_free'];
                    if ($v['nomal_oid']) {
                        $nomal_oid .= ',' . $v['nomal_oid'];
                    }
                    if ($v['special_oid']) {
                        $special_oid .= ',' . $v['special_oid'];
                    }
                }
                if ($nomal_oid) {
                    $nomal_oid = substr($nomal_oid, 1);
                }
                if ($special_oid) {
                    $special_oid = substr($special_oid, 1);
                }

                $insert_data = [
                    'total_amount' => $total_amount ?: 0, //应发总金额
                    'status' => 1,
                    'create_time' => $lastYearLastDay,
                    'order_num' => $order_num,
                    'sale_free' => $sale_free ?: 0,
                    'shop_free' => $shop_free ?: 0,
                    'real_amount' => $real_amount, //实发总金额
                    'nomal_oid' => $nomal_oid,
                    'special_oid' => $special_oid,
                    'type' => 'y',
                ];
                M('CommissionLog')->add($insert_data);
            }
        }
    }

    // 自动月度分成汇总
    public function commissionLogMonth()
    {
        $today = strtotime(date('Y-m-d'));
        $monthFirstDay = strtotime(date('Y-m-01'));

        $lastMonthLastDay = strtotime(date('Y-m-01') . ' -1 day'); // 上个月最后一天
        $lastMonthFirstDay = strtotime(date('Y-m-01', $lastMonthLastDay));  // 上个月第一天

        $lastMonthRecrod = M('CommissionLog')->where('create_time', $lastMonthLastDay)->where('type', 'm')->find();

        if ($monthFirstDay == $today || !$lastMonthRecrod) {
            $where = [
                'create_time' => ['between', [$lastMonthFirstDay, $lastMonthLastDay]],
                'type' => 'd',
            ];
            $data = M('commission_log')
                ->where($where)
                ->select();
            if ($data) {
                $total_amount = $real_amount = $order_num = $shop_free = $sale_free = 0;
                $nomal_oid = $special_oid = '';
                foreach ($data as $k => $v) {
                    $total_amount += $v['total_amount'];
                    $real_amount += $v['real_amount'];
                    $order_num += $v['order_num'];
                    $shop_free += $v['shop_free'];
                    $sale_free += $v['sale_free'];
                    if ($v['nomal_oid']) {
                        $nomal_oid .= ',' . $v['nomal_oid'];
                    }
                    if ($v['special_oid']) {
                        $special_oid .= ',' . $v['special_oid'];
                    }
                }
                if ($nomal_oid) {
                    $nomal_oid = substr($nomal_oid, 1);
                }
                if ($special_oid) {
                    $special_oid = substr($special_oid, 1);
                }

                $insert_data = [
                    'total_amount' => $total_amount ?: 0, //应发总金额
                    'status' => 1,
                    'create_time' => $lastMonthLastDay,
                    'order_num' => $order_num,
                    'sale_free' => $sale_free ?: 0,
                    'shop_free' => $shop_free ?: 0,
                    'real_amount' => $real_amount, //实发总金额
                    'nomal_oid' => $nomal_oid,
                    'special_oid' => $special_oid,
                    'type' => 'm',
                ];
                M('CommissionLog')->add($insert_data);
            }
        }
    }

    // 自动日度分成汇总
    public function commissionLog()
    {
        $auto_service_date = tpCache('shopping.auto_service_date') * 24 * 60 * 60;

        $today = strtotime(date('Y-m-d'));

        $yesterday = strtotime(date('Y-m-d', strtotime('-1 day')));

        $where_yesterday = $yesterday - $auto_service_date;
        $where_today = $today - $auto_service_date;

        if (M('commission_log')->where('create_time', $yesterday)->find()) {
            return;
        }

        $where = [
            'status' => ['in', [3, 5]],
            'confirm' => ['between', [$where_yesterday, $where_today]],
        ];

        $order_num = 0;

        $data = M('RebateLog')
            ->field('order_money as total_money, SUM(money) as money, order_id')
            ->where($where)
            ->where('type', 0)
            ->group('order_id')
            ->select();

        $total_amount = 0;
        $total_sale_amount = 0;
        $nomal_oid = '';

        if ($data) {
            foreach ($data as $v) {
                $total_amount += $v['total_money'];
                $total_sale_amount += $v['money'];
                $nomal_oid .= $v['order_id'] . ',';
                ++$order_num;
            }
            $nomal_oid = substr($nomal_oid, 0, -1);
        }

        // 店铺提成
        $shop_free = M('RebateLog')
            ->field('money')
            ->where($where)
            ->where('type', 1)
            ->group('order_id')
            ->select();

        $total_shop_free = 0;
        if ($shop_free) {
            foreach ($shop_free as $v) {
                $total_shop_free += $v['money'];
            }
        }

        // 分销提成
        $sale_free = $total_sale_amount;

        $where = [
            'type' => 2,
            'status' => 4,
            'change_time' => ['between', [$yesterday, $today]],
        ];

        // 开始记录换货订单
        $special_oid = [];
        $returnData = M('ReturnGoods')->field('order_id')->where($where)->select();

        if ($returnData) {
            foreach ($returnData as $sk => $sv) {
                $special_oid[] = $sv['order_id'];
            }
            $specialData = M('RebateLog')
                ->field('order_money as total_money, SUM(money) as money, order_id')
                ->where('order_id', 'IN', $special_oid)
                ->group('order_id')
                ->select();

            foreach ($specialData as $v) {
                $total_amount += $v['total_money'];
                $total_sale_amount += $v['money'];
                ++$order_num;
            }

            // 店铺提成
            $shop_free = M('RebateLog')
                ->field('money')
                ->where('order_id', 'IN', $special_oid)
                ->where('type', 1)
                ->group('order_id')
                ->select();
            foreach ($shop_free as $v) {
                $total_shop_free += $v['money'];
            }
        }

        //汇总的同时把换货成功的订单也分成给用户
        foreach ($special_oid as $skey => $svalue) {
            $order_sn = M('Order')->where('order_id', $svalue)->getField('order_sn');
            $distributLogic = new \app\common\logic\DistributLogic();
            $distributLogic->confirmOrder($order_sn, $svalue); // 确认分成
        }

        $special_oid = implode(',', $special_oid);
        $real_amount = $sale_free + $total_shop_free;

        $insert_data[] = [
            'total_amount' => $total_amount ?: 0, //应发总金额
            'status' => 1,
            'create_time' => $yesterday,
            'order_num' => $order_num,
            'sale_free' => $sale_free ?: 0,
            'shop_free' => $total_shop_free ?: 0,
            'real_amount' => $real_amount, //实发总金额
            'nomal_oid' => $nomal_oid,
            'special_oid' => $special_oid,
        ];

        foreach ($insert_data as $v) {
            M('CommissionLog')->add($v);
            $order_id = $v['nomal_oid'] . ',' . $v['special_oid'];
            if ('' !== $order_id) {
                $order_id = explode(',', $order_id);
                M('RebateLog')->where('order_id', 'IN', $order_id)->update(['status' => 5, 'statistics_time' => time()]);
            }
        }
        $this->commissionLogMonth();
        $this->commissionLogYear();
    }

    //自动团购退单
    public function autoGroupCancel()
    {
        $time = time();
        $gourp_list = M('group_buy')->where('end_time', 'elt', $time)->select();
        foreach ($gourp_list as $k => $v) {
            $group = M('group_detail')->where('group_id', $v['id'])->where('status', 'in', [1, 2])->order('batch desc')->find();
            if ($group && 1 == $group['status']) {
                $order_sn = explode(',', $group['order_sn_list']);
                $list = M('Order')->where('order_sn', 'in', $order_sn)->select();
                if ($list) {
                    $orderLogic = new \app\common\logic\OrderLogic();
                    foreach ($list as $k => $v) {
                        $orderLogic->cancel_order($v['user_id'], $v['order_id'], '团购结束,系统自动取消订单');
                    }
                }
                M('group_detail')->where('group_id', $v['id'])->update(['status' => 3]);
            }
        }
    }

    //自动取消订单
    public function autoCancelOrder()
    {
        $time = 60 * 60;

        $list = M('Order')->field('UNIX_TIMESTAMP() - add_time as test,add_time,UNIX_TIMESTAMP() as now,order_id,user_id,prepare_pay_time')
            ->where('parent_id', 0)
            ->where('pay_status', 0)
            ->where('order_status', 0)
            ->having("test > {$time}")
            ->limit(1000)
            ->select();
        if ($list) {
            $orderLogic = new \app\common\logic\OrderLogic();
            foreach ($list as $k => $v) {
                if (0 == $v['prepare_pay_time'] || $v['prepare_pay_time'] + $time < time()) {
                    $orderLogic->cancel_order($v['user_id'], $v['order_id'], '系统自动取消订单');
                }
            }
        }
    }

    // 自动分成
    public function autoConfirm()
    {
        // 多少天后自动分销记录自动分成
        $switch = tpCache('distribut.switch');
        if (1 == $switch && file_exists(APP_PATH . 'common/logic/DistributLogic.php')) {
            $distributLogic = new \app\common\logic\DistributLogic();
            $distributLogic->auto_confirm(); // 自动确认分成
        }
    }

    function auto_confirm_ceshi()
    {
        // 多少天后自动分销记录自动分成
        $switch = tpCache('distribut.switch');
        if (1 == $switch && file_exists(APP_PATH . 'common/logic/DistributLogic.php')) {
            $distributLogic = new \app\common\logic\DistributLogic();
            $distributLogic->auto_confirm_test(); // 自动确认分成
        }
    }

    // 自动收货
    public function autoReceipt()
    {
        // 发货后满多少天自动收货确认
        $auto_confirm_date = tpCache('shopping.auto_confirm_date');
        $auto_confirm_date = $auto_confirm_date * (60 * 60 * 24); // 7天的时间戳
        $time = time() - $auto_confirm_date; // 比如7天以前的可用自动确认收货
        $order_id_arr = M('order')->where("order_status = 1 and shipping_status = 1 and shipping_time < $time and parent_id = 0")->getField('order_id', true);
        foreach ($order_id_arr as $k => $v) {
            confirm_order($v);
        }
    }

    /**
     * 导入报单系统的用户与账户信息.
     */
    public function autoCustomsInfo()
    {
        $list = Db::connect(config('database.other_db'))->table('g_butt')->where(['status' => 0])->limit(1000)->select();
        if ($list) {
            foreach ($list as $k => $v) {
                if (M('butt')->where(['id' => $v['id']])->find()) {
                    Db::connect(config('database.other_db'))->execute('update g_butt set status = 1 where status=0 and id=' . $v['id']);
                } else {
                    $v['status'] = 0;
                    $bbutt = M('butt')->add($v);
                    if ($bbutt) {
                        Db::connect(config('database.other_db'))->execute('update g_butt set status = 1 where status=0 and id=' . $v['id']);
                    }
                }
            }
        } else {
            $this->autoCustomsNow();
        }
    }

    /**
     * 导入报单系统的用户与账户信息.
     */
    public function autoCustomsNow()
    {
        $list = M('butt')->where('status=0')->order('type asc')->limit(1000)->select();

        if ($list) {
            $smsLogic = new SmsLogic();
            foreach ($list as $k => $v) {
                Db::startTrans();
                $buttdata = json_decode($v['data'], true);
                if (1 == $v['type']) {  //导入新会员
                    $data = [
                        'user_name' => $buttdata['user_name'],
                        'password' => $buttdata['password'],
                        'paypwd' => $buttdata['paypwd'],
                        'real_name' => $buttdata['true_name'],
                        'mobile' => $buttdata['mobile'],
                        'reg_time' => $buttdata['reg_time'],
                        'is_zhixiao' => 1,
                        'distribut_level' => 3,
                        'is_distribut' => 1,
                        'type' => 2
                    ];
                    if ($buttdata['referee_user_name']) {
                        $referee_users = M('users')->where(['user_name' => $buttdata['referee_user_name'], 'is_lock' => 0, 'is_cancel' => 0])->field('user_id,first_leader,second_leader')->find();
                        if ($referee_users) {
                            $data['first_leader'] = $referee_users['user_id'];
                            $data['second_leader'] = $referee_users['first_leader'];
                            $data['third_leader'] = $referee_users['second_leader'];
                            $data['invite_uid'] = $referee_users['user_id'];
                            $data['invite_time'] = NOW_TIME;
                        } else {
                            Db::rollback();
                            continue;
                        }
                    }
                    $bool = M('users')->add($data);
                    // 发送短信通知用户
                    $param = [
                        'user_id' => $bool,
                        'user_name' => $buttdata['user_name']
                    ];
                    $smsLogic->sendSms(9, $buttdata['mobile'], $param);
                } elseif (2 == $v['type']) {
                    $user = M('users')->where(array('user_name' => $buttdata['user_name'], 'is_lock' => 0))->field('user_id')->find();
                    if ($user) {
                        $bool = accountLog($user['user_id'], 0, $buttdata['xiaofei_money'], '电商转入商城', 0, 0, '', $buttdata['customs_money'], 13);
                    } else {
                        $bool = false;
                    }
                }
                $bbutt = M('butt')->where(['id' => $v['id'], 'status' => 0])->data(['status' => 1])->save();
                if ($bool && $bbutt) {
                    Db::commit();
                } else {
                    Db::rollback();
                }
            }
        }
    }

    /**
     * 登录奖励使用过期处理
     */
    public function autoInvalidProfit()
    {
        $task = M('task')->where(['id' => 4])->find();
        if (time() >= $task['use_end_time']) {
            Db::startTrans();
            // 所有未使用的记录
            $taskLog = M('task_log')->where(['task_id' => 4, 'status' => 1, 'type' => 1, 'finished_at' => 0])->limit(200)->order('id DESC')->select();
            // 更新用户记录
            $logIds = [];
            foreach ($taskLog as $log) {
                $payPoints = $log['reward_integral'] != 0 ? -$log['reward_integral'] : 0;
                $userElectronic = $log['reward_electronic'] != 0 ? -$log['reward_electronic'] : 0;
                $res = accountLog($log['user_id'], 0, $payPoints, '登录奖励使用过期', 0, 0, 0, $userElectronic, 18, false, 4);
                if ($res) {
                    $logIds[] = $log['id'];
                }
            }
            // 更新记录（未使用）
            M('task_log')->where(['id' => ['IN', $logIds]])->update([
                'status' => -1
            ]);
            Db::commit();
        }
    }

    /**
     * 消息推送
     */
    public function autoPushMessage()
    {
        $where = [
            'push_time' => ['<=', time()],
            'status' => 0
        ];
        $pushList = M('push')->where($where)->field('id, type, type_id, item_id, title, desc, distribute_level')->order('push_time asc')->select();
        if (!empty($pushList)) {
            $pushIds = [];
            $pushLogic = new PushLogic();
            foreach ($pushList as $push) {
                // 标题内容数据
                $contentData = [
                    'title' => htmlspecialchars_decode($push['title']),
                    'desc' => htmlspecialchars_decode($push['desc'])
                ];
                // 点击处理数据
                $extraData = [
                    'type' => $push['type'],
                    'value' => [
                        'need_login' => 0,
                        'message_url' => '',
                        'goods_id' => '',
                        'item_id' => '',
                        'cate_id' => '',
                        'cate_name' => ''
                    ]
                ];
                switch ($push['type']) {
                    case 1:
                        // 系统消息列表页
                    case 3:
                        // 领券中心页
                    case 7:
                        // SVIP专享
                    case 9:
                        // 我的礼券
                        $extraData['value']['need_login'] = 1;
                        break;
                    case 2:
                        // 活动消息详情页
                        $extraData['value']['message_url'] = SITE_URL . '/#/news/app_news_particulars?article_id=' . $push['type_id'];
                        break;
                    case 4:
                        // 商品详情页
                        $extraData['value']['goods_id'] = $push['type_id'];
                        $extraData['value']['item_id'] = $push['item_id'];
                        break;
                    case 12:
                        // 商品分类
                        $extraData['value']['cate_id'] = $push['type_id'];
                        $extraData['value']['cate_name'] = M('goods_category')->where(['id' => $push['type_id']])->value('name') ?? '';
                }
                // 标签
                $all = 0;
                $pushTags = [];
                switch ($push['distribute_level']) {
                    case 0:
                        $all = 1;   // 全部人发送
                        break;
                    case 1:
                        $pushTags[] = 'member';
                        break;
                    case 2:
                        $pushTags[] = 'vip';
                        break;
                    case 3:
                        $pushTags[] = 'svip';
                        break;
                }
//                $all = 0;
//                $pushTags = [];
                $alias = '';
                // 发送消息
                $res = $pushLogic->push($contentData, $extraData, $all, [], $pushTags, $alias);
                if ($res['status'] !== 1) {
                    // 更新消息发送状态
                    M('push')->where(['id' => $push['id']])->update(['status' => -1]);
                    // 错误日志记录
                    M('push_log')->add([
                        'push_id' => $push['id'],
                        'user_push_ids' => '',
                        'user_push_tags' => implode(',', $pushTags),
                        'error_msg' => $res['msg'],
                        'error_response' => isset($res['result']) ? serialize($res['result']) : '',
                        'create_time' => time()
                    ]);
                } else {
                    $pushIds[] = $push['id'];
                }
            }
            // 更新消息发送状态
            M('push')->where(['id' => ['in', $pushIds]])->update(['status' => 1]);
        }
    }

    /**
     * 自动发送订单pv到代理商系统记录
     */
    public function autoSendOrderPv()
    {
        $where = [
            'order_status' => ['IN', [2, 4, 6]],    // 已收货 已完成 售后
            'pay_status' => 1,                      // 已支付
            'shipping_status' => 1,                 // 已发货
            'order_pv' => ['>', 0],
            'pv_tb' => 0,
            'pv_send' => 0,
            'pv_user_id' => ['NEQ', 0],
            'end_sale_time' => ['<=', time()],      // 售后期结束
            'is_agent' => 0
        ];
        $orderIds = M('order')->where($where)->getField('order_id', true);
        // 查看订单商品是否正在申请售后（未处理完成）
        foreach ($orderIds as $k => $orderId) {
            if (M('return_goods')->where(['order_id' => $orderId, 'status' => ['IN', [0, 1]]])->value('id')) {
                unset($orderIds[$k]);
            }
        }
        // 通知代理商系统记录
        include_once "plugins/Tb.php";
        $TbLogic = new \Tb();
        foreach ($orderIds as $orderId) {
            $TbLogic->add_tb(1, 11, $orderId, 0);
        }
        // 更新记录状态
        M('order')->where(['order_id' => ['in', $orderIds]])->update(['pv_tb' => 1]);
    }

    /**
     * 查询计算订单商品价格，普通会员升级到VIP
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function autoUpgradeVip()
    {
        // 普通用户升级成为VIP所需购买的金额
        $vipBuyDate = tpCache('distribut.vip_buy_date');
        $vipBuyMoney = tpCache('distribut.vip_buy_money');
        if (isset($vipBuyDate) && $vipBuyDate != '' && isset($vipBuyMoney) && $vipBuyMoney > 0) {
            $where = [
                'order_status' => ['IN', [2, 4, 6]],    // 已收货 已完成 售后
                'pay_status' => 1,                      // 已支付
                'shipping_status' => 1,                 // 已发货
                'end_sale_time' => ['<=', time()],      // 售后期结束
                'add_time' => ['>=', strtotime($vipBuyDate)]
            ];
            // 订单列表
            $orderList = M('order o')->join('users u', 'u.user_id = o.user_id')
                ->where($where)->where(['u.distribut_level' => 1])->field('o.order_id, o.user_id')->order('o.user_id')->select();
            // 用户购买的金额
            $userOrderGoodsPrice = [];
            foreach ($orderList as $order) {
                $orderGoodsPrice = M('order_goods')->where([
                    'order_id' => $order['order_id'],
                    'is_gift' => 0,
                    're_id' => 0
                ])->sum('(goods_price - use_integral) * goods_num');
                if (!isset($userOrderGoodsPrice[$order['user_id']])) {
                    $userOrderGoodsPrice[$order['user_id']] = [
                        'goods_price' => $orderGoodsPrice
                    ];
                } else {
                    $userOrderGoodsPrice[$order['user_id']]['goods_price'] = bcadd($userOrderGoodsPrice[$order['user_id']]['goods_price'], $orderGoodsPrice, 2);
                }
            }
            foreach ($userOrderGoodsPrice as $userId => $orderGoodsPrice) {
                if ($orderGoodsPrice['goods_price'] >= $vipBuyMoney) {
                    $userOldLevel = M('users')->where(['user_id' => $userId])->value('distribut_level');
                    if ($userOldLevel > 1) {
                        continue;
                    }
                    // 升级用户
                    $update['is_distribut'] = 1;
                    $update['distribut_level'] = 2;
                    M('users')->where('user_id', $userId)->save($update);
                    // 升级返还奖励
                    if (tpCache('distribut.buy_get_electronic') > 0) {
                        accountLog($userId, 0, 0, '累积消费升级返消费币', 0, 0, '', tpCache('distribut.buy_get_electronic'), 14, false);
                    }
                    $user = Db::name('users')->where('user_id', $userId)->find();
                    // 更新用户推送tags
                    $res = (new PushLogic())->bindPushTag($user);
                    if ($res['status'] == 2) {
                        $user = Db::name('users')->where('user_id', $userId)->find();
                    }
                    // 更新缓存
                    TokenLogic::updateValue('user', $user['token'], $user, $user['time_out']);
                    // 升级记录
                    logDistribut('', $userId, $update['distribut_level'], $userOldLevel, 3, $vipBuyMoney);
                    // 推荐任务
                    $task = new \app\common\logic\TaskLogic(2);
                    $task->setUserId($userId);
                    $task->inviteProfit(true);
                }
            }
        }
    }

    /**
     * 更新促销活动
     */
    public function updatePromGoods()
    {
        /*
         * 进行中的活动
         */
        $promIds = M('prom_goods')->where([
            'is_open' => 1,
            'end_time' => ['>', NOW_TIME]
        ])->getField('id', true);
        if (!empty($promIds)) {
            M('prom_goods')->where(['id' => ['IN', $promIds]])->update(['is_end' => 0]);
        }
        /*
         * 已经结束的活动
         */
        $promIds = M('prom_goods')->where([
            'end_time' => ['<=', NOW_TIME]
        ])->getField('id', true);
        if (!empty($promIds)) {
            M('prom_goods')->where(['id' => ['IN', $promIds]])->update(['is_end' => 1]);
            M('goods')->where(['prom_type' => 3, 'prom_id' => ['IN', $promIds]])->update(['prom_type' => 0, 'prom_id' => 0]);
            M('spec_goods_price')->where(['prom_type' => 3, 'prom_id' => ['IN', $promIds]])->update(['prom_type' => 0, 'prom_id' => 0]);
        }
    }

    /**
     * 更新秒杀活动
     */
    public function updateFlashSale()
    {
        /*
         * 进行中的活动
         */
        $promIds = M('flash_sale')->where([
            'end_time' => ['>', NOW_TIME]
        ])->getField('id', true);
        if (!empty($promIds)) {
            M('flash_sale')->where(['id' => ['IN', $promIds]])->update(['is_end' => 0]);
        }
        /*
         * 已经结束的活动
         */
        $promIds = M('flash_sale')->where([
            'end_time' => ['<=', NOW_TIME]
        ])->getField('id', true);
        if (!empty($promIds)) {
            M('prom_goods')->where(['id' => ['IN', $promIds]])->update(['is_end' => 1]);
            M('goods')->where(['prom_type' => 1, 'prom_id' => ['IN', $promIds]])->update(['prom_type' => 0, 'prom_id' => 0]);
            M('spec_goods_price')->where(['prom_type' => 1, 'prom_id' => ['IN', $promIds]])->update(['prom_type' => 0, 'prom_id' => 0]);
        }
    }

    /**
     * 更新团购活动
     */
    public function updateGroupBuy()
    {
        /*
         * 进行中的活动
         */
        $promIds = M('flash_sale')->where([
            'end_time' => ['>', NOW_TIME]
        ])->getField('id', true);
        if (!empty($promIds)) {
            M('flash_sale')->where(['id' => ['IN', $promIds]])->update(['is_end' => 0]);
        }
        /*
         * 已经结束的活动
         */
        $promIds = M('flash_sale')->where([
            'end_time' => ['<=', NOW_TIME]
        ])->getField('id', true);
        if (!empty($promIds)) {
            M('prom_goods')->where(['id' => ['IN', $promIds]])->update(['is_end' => 1]);
            M('goods')->where(['prom_type' => 1, 'prom_id' => ['IN', $promIds]])->update(['prom_type' => 0, 'prom_id' => 0]);
            M('spec_goods_price')->where(['prom_type' => 1, 'prom_id' => ['IN', $promIds]])->update(['prom_type' => 0, 'prom_id' => 0]);
        }
    }

    /**
     * 发送订单数据到供应链系统
     */
    public function supplierOrderSend()
    {
        $orderIds = M('order o1')->join('order o2', 'o2.parent_id = o1.order_id')->where([
            'o1.pay_status' => 1,
            'o2.order_type' => 3,
            'o2.supplier_order_status' => ['IN', [0, 2]],
            'o1.add_time' => ['<=', time() - 600],   // 10分钟前的订单
            'o1.order_id' => ['NOT IN', ['18339']]
        ])->getField('o1.order_id', true);
        $orderLogic = new OrderLogic();
        foreach ($orderIds as $orderId) {
            $orderLogic->supplierOrderSend($orderId, NOW_TIME);
        }
    }

    /**
     * 更新社区文章图片
     */
    public function updateCommunityArticleImage()
    {
        $article = M('community_article')
            ->where(['image' => ['NEQ', ''], 'get_image_info' => 0])
            ->field('id, image')->limit(5)->select();
        foreach ($article as $value) {
            $upData['image'] = '';
            // 获取图片信息
            $value['image'] = explode(';', $value['image']);
            foreach ($value['image'] as $item) {
                $item = explode(',', $item);
                $image = substr($item[0], strrpos($item[0], 'url:') + 4);
                $imageInfo = getImageInfo($image);
                $upData['image'] .= $item[0] . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ';';
            }
            $upData['image'] = rtrim($upData['image'], ';');
            $upData['get_image_info'] = 1;
            // 更新文章数据
            M('community_article')->where(['id' => $value['id']])->update($upData);
        }
    }

    /**
     * 更新社区文章视频
     */
    public function updateCommunityArticleVideo()
    {
        $article = M('community_article')
            ->where(['video' => ['NEQ', ''], 'video_cover' => ''])
            ->field('id, video')->limit(5)->select();
        foreach ($article as $value) {
            // 获取视频封面图
            $videoCover = getVideoCoverImages($value['video'], 'upload/community/video_cover/temp/');
            $upData = [
                'video_cover' => $videoCover['path'],
                'video_axis' => $videoCover['axis']
            ];
            // 更新文章数据
            M('community_article')->where(['id' => $value['id']])->update($upData);
        }
    }

    /**
     * 发布社区文章
     */
    public function communityArticlePublish()
    {
        $where = [
            'status' => 2,
            'publish_time' => ['ELT', NOW_TIME]
        ];
        $articleIds = M('community_article')->where($where)->getField('id', true);
        if (!empty($articleIds)) {
            M('community_article')->where(['id' => ['IN', $articleIds]])->update(['status' => 1]);
        }
    }

    /**
     * 删除分享海报图
     */
    public function clearShareImage()
    {
        $beforeUserShare = M('user_share_image')->where(['add_time' => ['<=', time() - 3600]])->select();
        $ids = [];
        foreach ($beforeUserShare as $item) {
            if (!empty($item['head_pic']) && file_exists($item['head_pic'])) {
                unlink($item['head_pic']);
            }
            if (!empty($item['share_pic']) && file_exists($item['share_pic'])) {
                unlink($item['share_pic']);
            }
            $ids[] = $item['id'];
        }
        M('user_share_image')->where(['id' => ['IN', $ids]])->delete();
    }

    /**
     * 发布商学院文章
     */
    public function schoolArticlePublish()
    {
        $where = [
            'status' => 2,
            'publish_time' => ['ELT', NOW_TIME]
        ];
        $articleIds = M('school_article')->where($where)->getField('id', true);
        if (!empty($articleIds)) {
            M('school_article')->where(['id' => ['IN', $articleIds]])->update(['status' => 1]);
        }
    }

    /**
     * 商学院文章内容视频、音频素材上传到OSS服务器
     */
    public function schoolArticleTempResource()
    {
        $tempResource = M('school_article_temp_resource')->where(['status' => ['IN', [0, -1]]])->select();
        if (!empty($tempResource)) {
            set_time_limit(0);
            Db::startTrans();
            $ossLogic = new OssLogic();
            $articleIds = [];
            $ossPath = [];
            foreach ($tempResource as $resource) {
                // 上传到OSS服务器
                $filePath = PUBLIC_PATH . substr($resource['local_path'], strrpos($resource['local_path'], '/public/') + 8);
                $fileName = substr($resource['local_path'], strrpos($resource['local_path'], '/') + 1);
                $ext = substr($resource['local_path'], strrpos($resource['local_path'], '.') + 1);
                if (in_array($ext, ['mp3', 'wma', 'wav'])) {
                    $dir = 'audio';
                } else {
                    $dir = 'video';
                }
                $object = $dir . '/' . date('Y/m/d/H/') . $fileName;
                $return_url = $ossLogic->uploadFile($filePath, $object);
                if (!$return_url) {
                    $upData = ['status' => -1];
                } else {
                    $upData = [
                        'oss_path' => OssLogic::url($object),
                        'status' => 1
                    ];
                    if (!in_array($resource['article_id'], $articleIds)) {
                        $articleIds[] = $resource['article_id'];
                    }
                    $ossPath[$resource['article_id']][$resource['local_path']] = $upData['oss_path'];
                }
                M('school_article_temp_resource')->where(['id' => $resource['id']])->update($upData);
            }
            // 更新文章内容
            foreach ($articleIds as $articleId) {
                $content = M('school_article')->where(['id' => $articleId])->value('content');
                foreach ($ossPath[$articleId] as $localPath => $path) {
                    $content = str_replace($localPath, $path, $content);
                    unlink(PUBLIC_PATH . substr($localPath, strrpos($localPath, 'public') + 7));
                }
                M('school_article')->where(['id' => $articleId])->update(['content' => $content]);
            }
            Db::commit();
        }
    }

//    /**
//     * 更新用户头像
//     */
//    public function updateUserHeadPic()
//    {
//        $userIds = M('users')->where(['head_pic' => ['LIKE', '%mall.pumeiduo.com/index.php%']])->getField('user_id', true);
//        M('users')->where(['user_id' => ['IN', $userIds]])->update(['head_pic' => 'https://mall.pumeiduo.com/public/images/default_head.png']);
//    }

    /**
     * 文件导出
     */
    public function exportFile()
    {
        $exportFile = M('export_file')->where(['status' => 0])->find();
        if (!empty($exportFile)) {
            M('export_file')->where(['id' => $exportFile['id']])->update(['status' => 2]);
            $table = $exportFile['table'];
            $join = $exportFile['join'] ? json_decode($exportFile['join'], true) : [];
            $condition = $exportFile['condition'] ? json_decode($exportFile['condition'], true) : [];
            $group = $exportFile['group'];
            $orderArr = $exportFile['order'] ? json_decode($exportFile['order'], true) : [];
            $order = '';
            foreach ($orderArr as $k => $v) {
                $order .= $k . ' ' . $v . ',';
            }
            $order = rtrim($order, ',');
            $limitArr = $exportFile['limit'] ? json_decode($exportFile['limit'], true) : [];
            $offset = 0;
            $length = '';
            foreach ($limitArr as $k => $v) {
                switch ($k) {
                    case 'offset':
                        $offset = $v;
                        break;
                    case 'length':
                        $length = $v;
                        break;
                }
            }
            $field = $exportFile['field'];
            $ext = $exportFile['ext'] ? json_decode($exportFile['ext'], true) : [];
            switch ($exportFile['type']) {
                case 'order':
                    $res = $this->exportOrder($table, $join, $condition, $field, $group, $order, $offset, $length, $exportFile['name'], $exportFile['path']);
                    break;
                case 'school_user_course':
                    $res = $this->exportSchoolUserCourse($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFile['name'], $exportFile['path']);
                    break;
                case 'school_user_course_ext':
                    $res = $this->exportSchoolUserCourseExt($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFile['name'], $exportFile['path']);
                    break;
                case 'school_user_graduate':
                    $res = $this->exportSchoolUserGraduate($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFile['name'], $exportFile['path']);
                    break;
                case 'module_user_list':
                    $res = $this->exportModuleUserList($field, $exportFile['name'], $exportFile['path']);
                    break;
                case 'school_resource_article':
                    $res = $this->exportSchoolResourceArticle($exportFile['name'], $exportFile['path']);
                    break;
                case 'school_user_course_all':
                    $res = $this->exportSchoolUserCourseAll($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFile['name'], $exportFile['path']);
                    break;
            }
            if (isset($res) && $res === true) {
                M('export_file')->where(['id' => $exportFile['id']])->update([
                    'status' => 1,
                    'url' => SITE_URL . '/' . $exportFile['path'] . $exportFile['name']
                ]);
            }
        }
        exit();
    }

    private function exportOrder($table, $join, $condition, $field, $group, $order, $offset, $length, $exportFileName, $exportFilePath)
    {
        // 订单数据
        $orderList = M($table)->join($join)->where($condition)->field($field)->group($group)->order($order)->limit($offset, $length)->select();
        // 等级列表
        $levelList = M('distribut_level')->getField('level_id, level_name');
        // 会员升级记录
        $distributeLog = M('distribut_log')->where(['new_level' => 2])->group('user_id')->getField('user_id, add_time');
        // 订单来源
        $orderSource = ['1' => '微信', '2' => 'PC', '3' => 'APP', '4' => '管理后台'];
        // 地区数据
        $region = get_region_list();
        // 表头
        $headList = [
            '订单编号', '下单日期', '支付日期', '父级ID', '会员ID', '注册时间', '会员等级', '升级VIP时间', '收货人', '收货地址', '电话',
            '应付金额', '商品金额', '优惠券折扣', '积分折扣', '现金折扣', '优惠促销', '运费', '总pv值', '乐活豆',
            '订单状态', '支付状态', '发货状态', '订单来源', '支付方式', '订单总销售额', '订单总去税',
            '商品总数', '商品编号', '商品数量', '商品名称', '商品规格', '商品单价', '交易条件', '商品销售额', '商品去税'
        ];
        // 表数据
        $dataList = [];
        $orderStatus = C('ORDER_STATUS');
        $payStatus = C('PAY_STATUS');
        $shippingStatus = C('SHIPPING_STATUS');
        foreach ($orderList as $key => $order) {
            // 支付时间
            $payTime = $order['pay_time'] ? date('Y-m-d H:i:s', $order['pay_time']) : '';
            // 用户注册时间
            $regTime = $order['reg_time'] ? date('Y-m-d H:i:s', $order['reg_time']) : '';
            // 用户等级
            $level = $order['distribut_level'] ? $levelList[$order['distribut_level']] : '普通会员';
            // 升级VIP时间
            $levelUpTime = $distributeLog[$order['user_id']] ? date('Y-m-d H:i:s', $distributeLog[$order['user_id']]) : '';
            $dataList[$key] = [
                "\t" . $order['order_sn'],
                $order['add_time'],
                $payTime,
                $order['first_leader'],
                $order['user_id'],
                $regTime,
                $level,
                $levelUpTime,
                $order['consignee'],
                "{$region[$order['province']]},{$region[$order['city']]},{$region[$order['district']]},{$region[$order['twon']]}{$order['address']}",
                "\t" . $order['mobile'],
                $order['order_amount'],
                $order['goods_price'],
                $order['coupon_price'],
                $order['integral_money'],
                $order['user_electronic'],
                $order['order_prom_amount'],
                $order['shipping_price'],
                $order['order_pv'],
                $order['school_credit'],
                $orderStatus[$order['order_status']],
                $payStatus[$order['pay_status']],
                $shippingStatus[$order['shipping_status']],
                $orderSource[$order['source']],
                $order['pay_name'],
            ];
            // 订单商品
            $orderGoods = D('order_goods og')->join('goods g', 'g.goods_id = og.goods_id')
                ->where('og.order_id=' . $order['order_id'])
                ->field('og.trade_type, og.goods_num, og.spec_key_name, og.member_goods_price, g.goods_sn, g.goods_name, g.is_abroad')->select();
            $goods_amount = 0;
            $goods_sn = [];
            $goods_num = [];
            $goods_name = [];
            $goods_attr = [];
            $goods_price = [];
            $goods_trade = [];
            $totalSalesAmount = 0;      // 订单总销售额
            $totalTaxOut = 0;           // 订单总去税
            $goodsSalesAmount = [];     // 商品销售额
            $goodsTaxOut = [];          // 商品去税
            $goodsTradeType = '';
            foreach ($orderGoods as $goods) {
                $goods_amount += $goods['goods_num'];
                $goods_sn[] = $goods['goods_sn'];
                $goods_num[] = $goods['goods_num'];
                $goods_name[] = $goods['goods_name'];
                $goods_attr[] = $goods['spec_key_name'];
                $goods_price[] = $goods['member_goods_price'];
//                switch ($order['order_type']) {
//                    case 2:
//                        $goodsTradeType = '韩国购';
//                        break;
//                    default:
//                        $goodsTradeType = trade_type($goods['trade_type']);
//                }
                $totalSalesAmount = bcadd($totalSalesAmount, bcmul($goods['goods_num'], $goods['member_goods_price'], 2), 2);
                $goodsSalesAmount[] = bcmul($goods['goods_num'], $goods['member_goods_price'], 2);
                if ($goods['is_abroad'] == 1) {
                    $goodsTradeType = '韩国购';
                    $goodsTaxOut[] = bcdiv(bcmul(bcmul($goods['goods_num'], $goods['member_goods_price'], 2), 0.25, 2), 1.13, 2);
                } else {
                    $goodsTradeType = trade_type($goods['trade_type']);
                    $goodsTaxOut[] = bcdiv(bcmul($goods['goods_num'], $goods['member_goods_price'], 2), 1.13, 2);
                }
                $goods_trade[] = $goodsTradeType;
            }
            if ($goodsTradeType == '韩国购') {
                $totalTaxOut = bcdiv(bcmul($totalSalesAmount, 0.25, 2), 1.13, 2);
            } else {
                $totalTaxOut = bcdiv($totalSalesAmount, 1.13, 2);
            }
            $dataList[$key][] = $totalSalesAmount;
            $dataList[$key][] = $totalTaxOut;
            $dataList[$key][] = $goods_amount;
            $dataList[$key][] = $goods_sn;
            $dataList[$key][] = $goods_num;
            $dataList[$key][] = $goods_name;
            $dataList[$key][] = $goods_attr;
            $dataList[$key][] = $goods_price;
            $dataList[$key][] = $goods_trade;
            $dataList[$key][] = $goodsSalesAmount;
            $dataList[$key][] = $goodsTaxOut;
        }
        toCsvExcel($dataList, $headList, $exportFileName, $exportFilePath);
        return true;
    }

    private function exportSchoolUserCourse($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFileName, $exportFilePath)
    {
        // 用户数据
        $userList = M($table)->join($join)->where($condition)->field($field)->group($group)->order($order)->limit($offset, $length);
        $whereOr = $ext['where_or'] ?? [];
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $userList = $userList->select();
        // 用户首次进入商学院的时间
        $userFirstVisit = M('user_school_config')->where(['type' => 'first_visit'])->getField('user_id, add_time', true);
        // 用户学习课程数量
        $learnTimeFrom = $ext['learn_time_from'] ?? '';
        $learnTimeTo = $ext['learn_time_to'] ?? '';
        $userCourseCount = $this->checkUserCourseCount([], false, $learnTimeFrom, $learnTimeTo);
        // APP等级列表
        $appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $svipLevel = M('svip_level')->getField('app_level, name', true);
        // 表数据
        $dataList = [];
        foreach ($userList as $user) {
            // APP等级
            $user['app_grade_name'] = $appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $svipLevel[$user['svip_level']] : '';
            $dataList[] = [
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['svip_real_name'] ?? $user['real_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                isset($userCourseCount[$user['user_id']]) ? $userCourseCount[$user['user_id']] : 0,
                $user['school_credit'],
                isset($userFirstVisit[$user['user_id']]) ? date('Y-m-d H:i:s', $userFirstVisit[$user['user_id']]) : '',
                $user['svip_activate_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_activate_time']) : '',
                $user['svip_upgrade_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_upgrade_time']) : '',
                $user['svip_referee_number'] ?? 0,
                $user['grade_referee_num1'] ?? 0,
                $user['grade_referee_num2'] ?? 0,
                $user['grade_referee_num3'] ?? 0,
                $user['grade_referee_num4'] ?? 0,
                $user['referee_user_name'] ?? '',
                $user['referee_real_name'] ?? '',
                $user['network_parent_user_name'] ?? '',
                $user['network_parent_real_name'] ?? '',
                $user['customs_user_name'] ?? '',
                $user['customs_real_name'] ?? '',
            ];
        }
        // 表头
        $headList = [
            '用户ID', '用户昵称', '用户名', '真实姓名', 'APP等级', '代理商等级', '代理商职级', '课程数量', '乐活豆数量', '首次进入商学院',
            '211系统激活时间', '211系统升级代理商时间', '推荐总人数', '推荐游客人数', '推荐优享会员人数', '推荐尊享会员人数', '推荐代理商人数',
            '推荐人用户名', '推荐人真实姓名', '服务人用户名', '服务人真实姓名', '服务中心用户名', '服务中心真实姓名',
        ];
        toCsvExcel($dataList, $headList, $exportFileName, $exportFilePath);
        return true;
    }

    private function exportSchoolUserCourseExt($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFileName, $exportFilePath)
    {
        // 用户数据
        $userList = M($table)->join($join)->where($condition)->field($field)->group($group)->order($order)->limit($offset, $length);
        $whereOr = $ext['where_or'] ?? [];
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $userList = $userList->select();
        // 用户首次进入商学院的时间
        $userFirstVisit = M('user_school_config')->where(['type' => 'first_visit'])->getField('user_id, add_time', true);
        // 用户学习课程数量
        $userCourseCount = $this->checkUserCourseCount();
        // APP等级列表
        $appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $svipLevel = M('svip_level')->getField('app_level, name', true);
        // 商学院模块分类信息
        $schoolModuleClass = M('school_class sc')->join('school s', 's.id = sc.module_id')->getField('sc.id, sc.name class_name, s.name module_name', true);
        $learnTimeFrom = $ext['learn_time_from'] ?? '';
        $learnTimeTo = $ext['learn_time_to'] ?? '';
        // 表数据
        $dataList = [];
        foreach ($userList as $user) {
            if ($learnTimeFrom && $learnTimeTo && $user['finish_time'] && ($user['finish_time'] < $learnTimeFrom || $user['finish_time'] > $learnTimeTo)) {
                continue;
            }
            // APP等级
            $user['app_grade_name'] = $appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $svipLevel[$user['svip_level']] : '';
            // 课程相关信息
            $learnType = '';
            switch ($user['learn_type']) {
                case 1:
                    $learnType = '必修';
                    break;
                case 2:
                    $learnType = '选修';
                    break;
            }
            $status = '';
            switch ($user['status']) {
                case 1:
                    $status = '已发布';
                    break;
                case 2:
                    $status = '预发布';
                    break;
                case 3:
                    $status = '不发布';
                    break;
            }
            $learnStatusDesc = '';
            if (is_numeric($user['learn_status'])) {
                switch ($user['learn_status']) {
                    case 0:
                        $learnStatusDesc = '未完成';
                        break;
                    case 1:
                        $learnStatusDesc = '已完成';
                        break;
                }
            }
            $questionnaireStatusDesc = '';
            if (is_numeric($user['is_questionnaire'])) {
                switch ($user['is_questionnaire']) {
                    case 0:
                        $questionnaireStatusDesc = '未完成';
                        break;
                    case 1:
                        $questionnaireStatusDesc = '已完成';
                        break;
                }
            }
            $dataList[] = [
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['svip_real_name'] ?? $user['real_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                isset($userCourseCount[$user['user_id']]) ? $userCourseCount[$user['user_id']] : 0,
                $user['school_credit'],
                isset($userFirstVisit[$user['user_id']]) ? date('Y-m-d H:i:s', $userFirstVisit[$user['user_id']]) : '',
                $user['svip_activate_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_activate_time']) : '',
                $user['svip_upgrade_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_upgrade_time']) : '',
                $user['svip_referee_number'] ?? 0,
                $user['grade_referee_num1'] ?? 0,
                $user['grade_referee_num2'] ?? 0,
                $user['grade_referee_num3'] ?? 0,
                $user['grade_referee_num4'] ?? 0,
                $user['referee_user_name'] ?? '',
                $user['referee_real_name'] ?? '',
                $user['network_parent_user_name'] ?? '',
                $user['network_parent_real_name'] ?? '',
                $user['customs_user_name'] ?? '',
                $user['customs_real_name'] ?? '',
                $user['article_id'] ?? '',
                $user['title'] ?? '',
                $learnType,
                $user['class_id'] ? $schoolModuleClass[$user['class_id']]['module_name'] : '',
                $user['class_id'] ? $schoolModuleClass[$user['class_id']]['class_name'] : '',
                $status,
                $user['publish_time'] ? date('Y-m-d H:i:s', $user['publish_time']) : '',
                $user['add_time'] ? date('Y-m-d H:i:s', $user['add_time']) : '',
                $user['finish_time'] ? date('Y-m-d H:i:s', $user['finish_time']) : '',
                $learnStatusDesc,
                $questionnaireStatusDesc
            ];
        }
        // 表头
        $headList = [
            '用户ID', '用户昵称', '用户名', 'APP等级', '真实姓名', '代理商等级', '代理商职级', '课程数量', '乐活豆数量', '首次进入商学院',
            '211系统激活时间', '211系统升级代理商时间', '推荐总人数', '推荐游客人数', '推荐优享会员人数', '推荐尊享会员人数', '推荐代理商人数',
            '推荐人用户名', '推荐人真实姓名', '服务人用户名', '服务人真实姓名', '服务中心用户名', '服务中心真实姓名',
            '文章ID', '标题', '学习类型', '所属模块', '所属分类', '发布状态', '发布时间', '学习开始时间', '学习完成时间', '学习状态', '是否完成调查问卷'
        ];
        toCsvExcel($dataList, $headList, $exportFileName, $exportFilePath);
        return true;
    }

    private function exportSchoolUserGraduate($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFileName, $exportFilePath)
    {
        $moduleName = M('school')->where(['id' => $ext['module_id']])->value('name');
        $className = M('school_class')->where(['id' => $ext['class_id']])->value('name');
        // 模块分类下的课程列表
        $totalCourseNum = M('school_article')->where(['class_id' => $ext['class_id'], 'learn_type' => ['IN', [1, 2]], 'status' => 1])->count('id');
        // APP等级列表
        $appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $svipLevel = M('svip_level')->getField('app_level, name', true);
        // 用户数据
        $userList = M($table)->join($join)->where($condition)->field($field)->group($group)->order($order)->limit($offset, $length);
        $whereOr = $ext['where_or'] ?? [];
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $userList = $userList->select();
        // 用户学习课程数量
        $learnTimeFrom = $ext['learn_time_from'] ?? '';
        $learnTimeTo = $ext['learn_time_to'] ?? '';
        $userCourseCount = $this->checkUserCourseCount([], true, $learnTimeFrom, $learnTimeTo);
        // 表数据
        $dataList = [];
        foreach ($userList as $user) {
            // APP等级
            $user['app_grade_name'] = $appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $svipLevel[$user['svip_level']] : '';
            $userLearnedCourseNum = isset($userCourseCount[$user['user_id']]) ? $userCourseCount[$user['user_id']] : 0;
            $dataList[] = [
                $moduleName,
                $className,
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                $totalCourseNum,
                $userLearnedCourseNum,
                $user['school_credit'],
                $userLearnedCourseNum == $totalCourseNum ? '已结业' : '未结业',
                $user['svip_activate_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_activate_time']) : '',
                $user['svip_upgrade_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_upgrade_time']) : '',
                $user['svip_referee_number'] ?? 0,
                $user['grade_referee_num1'] ?? 0,
                $user['grade_referee_num2'] ?? 0,
                $user['grade_referee_num3'] ?? 0,
                $user['grade_referee_num4'] ?? 0,
                $user['referee_user_name'] ?? '',
                $user['referee_real_name'] ?? '',
                $user['network_parent_user_name'] ?? '',
                $user['network_parent_real_name'] ?? '',
                $user['customs_user_name'] ?? '',
                $user['customs_real_name'] ?? '',
            ];
        }
        // 表头
        $headList = [
            '所属模块', '所属分类', '用户ID', '用户昵称', '用户名', 'APP等级', '代理商等级', '代理商职级', '总课程数量', '已学习完成课程数量',
            '乐活豆数量', '是否已结业', '211系统激活时间', '211系统升级代理商时间', '推荐总人数', '推荐游客人数', '推荐优享会员人数', '推荐尊享会员人数', '推荐代理商人数',
            '推荐人用户名', '推荐人真实姓名', '服务人用户名', '服务人真实姓名', '服务中心用户名', '服务中心真实姓名',
        ];
        toCsvExcel($dataList, $headList, $exportFileName, $exportFilePath);
        return true;
    }

    private function exportModuleUserList($field, $exportFileName, $exportFilePath)
    {
        // APP等级列表
        $appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $svipLevel = M('svip_level')->getField('app_level, name', true);
        // 用户数据
        $field = json_decode($field, true);
        $userList = DB::query($field['sql']);
        // 用户学习课程数量
        $userCourseCount = $this->checkUserCourseCount([], true);
        // 表数据
        $dataList = [];
        foreach ($userList as $key => $user) {
            $user['is_graduate'] = 0;       // 是否已结业
            $user['module_num'] = 1;        // 学习模块数量
            // APP等级
            $user['app_grade_name'] = $appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $svipLevel[$user['svip_level']] : '';
            // 用户已学习完成课程数量
            $userData = [
                'user_id' => $user['user_id'],
                'app_grade' => $user['distribut_level'],
                'svip_grade' => $user['svip_grade'],
                'svip_level' => $user['svip_level'],
            ];
            $userLearnedCourseNum = isset($userCourseCount[$user['user_id']]) ? $userCourseCount[$user['user_id']] : 0;
            // 是否已结业
            if ($userLearnedCourseNum == $field['total_course_num']) {
                $user['is_graduate'] = 1;
            }
            // 用户已学习的模块数量
            if (I('module_id', -1) == -1) {
                $res = $this->checkUserModuleNum($userData);
                $user['module_num'] = $res['module_num'];
            }
            $dataList[$key] = [
                $field['module_name'],
                $field['total_course_num'],
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['svip_real_name'] ?? $user['real_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                $user['school_credit'],
                $userLearnedCourseNum
            ];
            if ($field['module_id'] != -1) {
                $dataList[$key][] = $user['is_graduate'] == 1 ? '已结业' : '未结业';
            } else {
                $dataList[$key][] = $user['module_num'];
            }
        }
        // 表头
        $headList = [
            '模块', '课程总数', '用户ID', '用户昵称', '用户名', '真实姓名', 'APP等级', '代理商等级', '代理商职级', '乐活豆数量', '已学习完成课程数量'
        ];
        if ($field['module_id'] != -1) {
            $headList[] = '是否已结业';
        } else {
            $headList[] = '参与学习的模块数量';
        }
        toCsvExcel($dataList, $headList, $exportFileName, $exportFilePath);
        return true;
    }

    private function exportSchoolResourceArticle($exportFileName, $exportFilePath)
    {
        $ossLogic = new OssLogic();
        // APP等级列表
        $appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $svipLevel = M('svip_level')->getField('app_level, name', true);
        // 文章列表
        $articleList = M('school_article sa')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
            ->where(['s.type' => 'module6', 'sa.delete_time' => 0])->order('sc.sort DESC')
            ->field('sc.name class_name, sa.*')
            ->select();
        $articleIds = [];
        foreach ($articleList as $article) {
            $articleIds[] = $article['id'];
        }
        // 素材列表
        $resourceList = M('school_article_resource')->where(['article_id' => ['IN', $articleIds]])->field('article_id, image, video')->select();
        $markClassIds = [];
        $markArticleIds = [];
        // 表数据
        $dataList = [];
        foreach ($articleList as $article) {
            // 分类归类
            if (!in_array($article['class_id'], $markClassIds)) {
                $dataList[] = [
                    '素材专区',
                    $article['class_name']
                ];
                $markClassIds[] = $article['class_id'];
            }
            // 文章状态
            $articleStatus = '';
            switch ($article['status']) {
                case 1:
                    $articleStatus = '发布中';
                    break;
                case 2:
                    $articleStatus = '预发布';
                    break;
                case 3:
                    $articleStatus = '不发布';
                    break;
            }
            // APP访问等级
            if ($article['app_grade'] == 0) {
                $articleAppGrade = '所有人';
            } else {
                $article['app_grade'] = explode(',', $article['app_grade']);
                $articleAppGrade = '';
                foreach ($article['app_grade'] as $item) {
                    $articleAppGrade .= $appGrade[$item] . ',';
                }
                $articleAppGrade = rtrim($articleAppGrade, ',');
            }
            // 代理商访问等级
            if ($article['distribute_grade'] == 0) {
                $articleDistributeGrade = '所有人';
            } else {
                $article['distribute_grade'] = explode(',', $article['distribute_grade']);
                $articleDistributeGrade = '';
                foreach ($article['distribute_grade'] as $item) {
                    $articleDistributeGrade .= $svipGrade[$item] . ',';
                }
                $articleDistributeGrade = rtrim($articleDistributeGrade, ',');
            }
            // 代理商访问职级
            if ($article['distribute_level'] == 0) {
                $articleDistributeLevel = '所有人';
            } else {
                $article['distribute_level'] = explode(',', $article['distribute_level']);
                $articleDistributeLevel = '';
                foreach ($article['distribute_level'] as $item) {
                    $articleDistributeLevel .= $svipLevel[$item] . ',';
                }
                $articleDistributeLevel = rtrim($articleDistributeLevel, ',');
            }
            // 附件
            $resourceFile = '';
            if (!empty($article['file'])) {
                $file = explode(',', $article['file']);
                $resourceFile = $ossLogic::url(substr($file[0], strrpos($file[0], 'url:') + 4));
            }
            // 素材下载人数
            $downloadNum = M('user_school_article')->where(['article_id' => $article['id']])->count('user_id');
            // 素材列表处理
            foreach ($resourceList as $resource) {
                if ($resource['article_id'] == $article['id']) {
                    $resourceImage = '';
                    $resourceVideo = '';
                    if (!empty($resource['image'])) {
                        $image = explode(',', $resource['image']);
                        $resourceImage = $ossLogic::url(substr($image[0], strrpos($image[0], 'img:') + 4));
                    } elseif (!empty($resource['video'])) {
                        $resourceVideo = $ossLogic::url($resource['video']);
                    }
                    if (!in_array($resource['article_id'], $markArticleIds)) {
                        $dataList[] = [
                            '',
                            '',
                            $article['id'],
                            $article['content'],
                            $articleAppGrade,
                            $articleDistributeGrade,
                            $articleDistributeLevel,
                            $article['sort'],
                            $articleStatus,
                            date('Y-m-d H:i:s', $article['add_time']),
                            date('Y-m-d H:i:s', $article['publish_time']),
                            $article['up_time'] ? date('Y-m-d H:i:s', $article['up_time']) : '',
                            $article['integral'],
                            $downloadNum,
                            $resourceFile,
                            $resourceImage,
                            $resourceVideo,
                        ];
                        $markArticleIds[] = $resource['article_id'];
                    } else {
                        $dataList[] = [
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                            $resourceImage,
                            $resourceVideo,
                        ];
                    }
                }
            }
        }
        // 表头
        $headList = [
            '模块', '分类', '素材文章ID', '内容', 'APP访问等级', '代理商访问等级', '代理商访问职级', '排序', '状态', '添加时间', '发布时间', '更新时间', '下载需要的积分', '下载人数', '附件', '图片素材', '视频素材'
        ];
        toCsvExcel($dataList, $headList, $exportFileName, $exportFilePath);
        return true;
    }

    private function exportSchoolUserCourseAll($table, $join, $condition, $field, $group, $order, $offset, $length, $ext, $exportFileName, $exportFilePath)
    {
        // 用户列表
        $usersModel = new UsersModel();
        $userList = $usersModel->alias('u')->join($join)->where($condition)
            ->field($field)->group($group)->order($order)->limit($offset, $length);
        $whereOr = $ext['where_or'] ?? [];
        if (!empty($whereOr)) {
            $userList = $userList->where(function ($query) use ($whereOr) {
                $query->whereOr($whereOr);
            });
        }
        $userList = $userList->select();
        // 用户首次进入商学院的时间
        $userFirstVisit = Db::name('user_school_config')->where(['type' => 'first_visit'])->getField('user_id, add_time', true);
        // 学习课程列表
        $courseList = M('school_article sa')->join('school_class sc', 'sc.id = sa.class_id')->join('school s', 's.id = sc.module_id')
            ->where(['sa.learn_type' => ['IN', [1, 2]], 'sa.status' => 1])
            ->field('s.name module_name, sc.name class_name, sa.id article_id, sa.title')->order('s.id ASC, sc.id ASC')->select();
        // 用户参与的课程列表
        $userArticleList = M('user_school_article')->where(['is_learn' => 1])->field('user_id, article_id')->select();
        $userArticle = [];
        foreach ($userArticleList as $ua) {
            if (!isset($userArticle[$ua['user_id']])) {
                $userArticle[$ua['user_id']] = [$ua['article_id']];
            } else {
                $userArticle[$ua['user_id']][] = $ua['article_id'];
            }
        }
        // APP等级列表
        $appGrade = M('distribut_level')->getField('level_id, level_name', true);
        // 代理商等级列表
        $svipGrade = M('svip_grade')->getField('app_level, name', true);
        // 代理商职级列表
        $svipLevel = M('svip_level')->getField('app_level, name', true);
        // 表数据
        $dataList = [
            [
                '', '', '', '', '', '', '', '', '', '',
                '', '', '', '', '', '', '',
                '', '', '', '', '', '',
            ],
            [
                '用户ID', '用户昵称', '用户名', '真实姓名', 'APP等级', '代理商等级', '代理商职级', '课程数量', '乐活豆数量', '首次进入商学院',
                '211系统激活时间', '211系统升级代理商时间', '推荐总人数', '推荐游客人数', '推荐优享会员人数', '推荐尊享会员人数', '推荐代理商人数',
                '推荐人用户名', '推荐人真实姓名', '服务人用户名', '服务人真实姓名', '服务中心用户名', '服务中心真实姓名',
            ]
        ];
        foreach ($courseList as $course) {
            $dataList[0][] = $course['class_name'];
            $dataList[1][] = $course['title'];
        }
        // 用户学习课程数量
        $learnTimeFrom = $ext['learn_time_from'] ?? '';
        $learnTimeTo = $ext['learn_time_to'] ?? '';
        $userCourseCount = $this->checkUserCourseCount([], [], false, $learnTimeFrom, $learnTimeTo);
        foreach ($userList as $k => $user) {
            // APP等级
            $user['app_grade_name'] = $appGrade[$user['distribut_level']];
            // 代理商等级
            $user['svip_grade_name'] = $user['distribut_level'] == 3 ? $svipGrade[$user['svip_grade']] : '';
            // 代理商等级
            $user['svip_level_name'] = $user['distribut_level'] == 3 ? $svipLevel[$user['svip_level']] : '';
            $dataList[$k + 2] = [
                $user['user_id'],
                $user['nickname'],
                $user['user_name'],
                $user['svip_real_name'] ?? $user['real_name'],
                $user['app_grade_name'],
                $user['svip_grade_name'],
                $user['svip_level_name'],
                isset($userCourseCount[$user['user_id']]) ? $userCourseCount[$user['user_id']] : 0,
                $user['school_credit'],
                isset($userFirstVisit[$user['user_id']]) ? date('Y-m-d H:i:s', $userFirstVisit[$user['user_id']]) : '',
                $user['svip_activate_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_activate_time']) : '',
                $user['svip_upgrade_time'] != 0 ? date('Y-m-d H:i:s', $user['svip_upgrade_time']) : '',
                $user['svip_referee_number'] ?? 0,
                $user['grade_referee_num1'] ?? 0,
                $user['grade_referee_num2'] ?? 0,
                $user['grade_referee_num3'] ?? 0,
                $user['grade_referee_num4'] ?? 0,
                $user['referee_user_name'] ?? '',
                $user['referee_real_name'] ?? '',
                $user['network_parent_user_name'] ?? '',
                $user['network_parent_real_name'] ?? '',
                $user['customs_user_name'] ?? '',
                $user['customs_real_name'] ?? '',
            ];
            foreach ($courseList as $course) {
                $isLearn = 0;
                if (isset($userArticle[$user['user_id']])) {
                    // 用户参与的学习课程
                    foreach ($userArticle[$user['user_id']] as $articleId) {
                        if ($articleId == $course['article_id']) {
                            $isLearn = 1;
                            break;
                        }
                    }
                }
                $dataList[$k + 2][] = $isLearn == 1 ? '√' : '';
            }
        }
        // 表头
        $headList = [
            '', '', '', '', '', '', '', '', '', '',
            '', '', '', '', '', '', '',
            '', '', '', '', '', '',
        ];
        foreach ($courseList as $course) {
            $headList[] = $course['module_name'];
        }
        toCsvExcel($dataList, $headList, $exportFileName, $exportFilePath);
        return true;
    }

    /**
     * 获取用户学习的模块数量
     * @param $user
     * @return array
     */
    private function checkUserModuleNum($user)
    {
        $moduleNum = M('user_school_article usa')
            ->join('school_article sa', 'sa.id = usa.article_id')
            ->join('school_class sc', 'sc.id = sa.class_id')
            ->where(['usa.user_id' => $user['user_id']])
            ->group('sc.module_id')
            ->count('usa.id');
        return ['module_num' => $moduleNum];
    }

    /**
     * 查看用户学习课程数量
     * @param $userIds
     * @param bool $isLearned
     * @param string $timeFrom
     * @param string $timeTo
     * @return mixed
     */
    private function checkUserCourseCount($userIds = [], $isLearned = false, $timeFrom = '', $timeTo = '')
    {
        $where = ['sa.learn_type' => ['IN', [1, 2]], 'sa.status' => 1];
        if (!empty($userIds)) $where['usa.user_id'] = ['IN', $userIds];
        if ($isLearned) $where['usa.status'] = 1;
        if ($timeFrom && $timeTo) $where['usa.finish_time'] = ['BETWEEN', [$timeFrom, $timeTo]];
        // 用户学习课程记录总数
        $userCourseCount = M('user_school_article usa')->join('school_article sa', 'sa.id = usa.article_id')
            ->where($where)
            ->group('usa.user_id')->getField('usa.user_id, count(usa.article_id) as count', true);
        return $userCourseCount;
    }
}
