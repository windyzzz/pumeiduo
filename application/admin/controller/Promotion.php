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

use app\admin\logic\GoodsLogic;
use app\admin\logic\PromotionLogic;
use app\admin\model\FlashSale;
use app\admin\model\Goods;
use app\admin\model\GroupBuy;
use app\admin\validate\Gift as Giftvalidate;
use app\common\model\OrderProm as OrderPromModel;
use app\common\model\OrderPromGoods as OrderPromGoodsModel;
use app\common\model\PromGoods;
use app\common\model\Gift2;
use think\Db;
use think\Loader;
use think\Page;

class Promotion extends Base
{
    public $service;

    public function __construct(PromotionLogic $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * 商品活动列表
     */
    public function gift2()
    {
        $Gift = new Gift2();
        $count = $Gift->where('')->count();
        $Page = new Page($count, 10);
        $prom_list = $Gift->where('')->limit($Page->firstRow . ',' . $Page->listRows)->order('id desc')->select();
        $this->assign('page', $Page);
        $this->assign('prom_list', $prom_list);
        return $this->fetch();
    }


    public function gift2_info()
    {

        $prom_id = I('id');
        $info = M('gift2')->where(array('id' => $prom_id))->find();

        if ($prom_id > 0) {

            $GoodsTao = M('gift2_goods')->where(array('promo_id' => $prom_id))->group('goods_id,item_id')->select();
            $prom_goods = [];
            foreach ($GoodsTao as $k => $v) {
                $prom_goods[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
                $prom_goods[$k]['stock'] = $v['stock'];
                if ($v['item_id']) {
                    $prom_goods[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
                }
            }
            $this->assign('prom_goods', $prom_goods);

            $GoodsTao = M('gift2_goods')->where(array('promo_id' => $prom_id))->group('buy_goods_id,buy_item_id')->select();
            $buy_goods = [];
            foreach ($GoodsTao as $k => $v) {
                $buy_goods[$k] = M('Goods')->where('goods_id=' . $v['buy_goods_id'])->find();
                $buy_goods[$k]['stock'] = $v['buy_stock'];
                if ($v['buy_item_id']) {
                    $buy_goods[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['buy_item_id']])->find();
                }
            }
            $this->assign('buy_goods', $buy_goods);

            $info['start_time'] = date('Y-m-d', $info['start_time']);
            $info['end_time'] = date('Y-m-d', $info['end_time']);
        } else {
            $info['start_time'] = date('Y-m-d');
            $info['end_time'] = date('Y-m-d', time() + 3600 * 60 * 24);
        }

        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));

        $this->initEditor();
        return $this->fetch();
    }

    public function gift2_save()
    {
        $prom_id = I('id/d');
        $data = I('post.');
        $title = input('title');

        /*$promGoodsValidate = Loader::validate('PromGoods');
        if(!$promGoodsValidate->batch()->check($data)){
            $return = ['status' => 0,'msg' =>'操作失败',
                'result'    => $promGoodsValidate->getError(),
                'token'       =>  \think\Request::instance()->token(),
            ];
            $this->ajaxReturn($return);
        }*/

        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($prom_id) {
            M('gift2')->where(['id' => $prom_id])->save($data);
            $last_id = $prom_id;
            adminLog("管理员修改了商品促销 " . $title);
        } else {
            $last_id = M('gift2')->add($data);
            adminLog("管理员添加了商品促销 " . $title);
        }

        M('gift2_goods')->where(array('promo_id' => $last_id))->delete();

        $buyGoods = $data['goods2'];
        $promGoods = $data['goods'];
        if ($buyGoods) {
            foreach ($buyGoods as $k => $v) {
                if ($promGoods) {
                    $vval = explode('_', $k);
                    foreach ($promGoods as $goodsKey => $goodsVal) {
                        $dfd = explode('_', $goodsKey);
                        $tao_goods = array(
                            'buy_goods_id' => $vval[0],
                            'buy_item_id' => $vval[1],
                            'buy_stock' => $v['stock'],
                            'goods_id' => $dfd[0],
                            'item_id' => $dfd[1],
                            'promo_id' => $last_id,
                            'stock' => $goodsVal['stock']
                        );
                        M('gift2_goods')->data($tao_goods)->add();
                    }
                }
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '编辑促销活动成功', 'result']);
    }

    public function gift2_del()
    {
        $prom_id = I('id');

        M('gift2_goods')->where("promo_id=$prom_id")->delete();
        M('gift2')->where("id=$prom_id")->delete();
        $this->success('删除活动成功', U('Promotion/gift2'));
    }

    public function flash_sale_log()
    {
        $id = I('id');

        $detail = M('order')
            ->alias('oi')
            ->join('__ORDER_GOODS__ og', 'og.order_id=oi.order_id', 'LEFT')
            ->where([
                'oi.order_status' => ['in', [0, 1, 2, 4, 6]],
                'oi.pay_status' => ['egt', 1],
                'og.prom_type' => 1,
                'og.prom_id' => $id,
            ])
            ->order('oi.order_id desc')
            ->select();

        $is_send_desc = [
            '0' => '未发货',
            '1' => '已发货',
            '2' => '已换货',
            '3' => '已退货',
        ];

        return view('', compact('detail', 'is_send_desc'));
    }

    public function index()
    {
        return $this->fetch();
    }

    /**
     * 赠品活动列表.
     */
    public function gift()
    {
        $list = $this->service->getGiftList();

        $count = $this->service->getGiftCount();

        $page = new Page($count, 20);

        return view('', compact('list', 'page'));
    }

    /**
     * 赠品活动新增页面.
     */
    public function gift_add()
    {
        $cat_list = Db::name('goods_category')->where('parent_id = 0')->select();
        $this->assign('cat_list', $cat_list);

        return view();
    }

    /**
     * 赠品活动编辑页面.
     *
     * @return \think\response\View
     */
    public function gift_edit()
    {
        $id = I('id');

        $info = $this->service->getGiftById($id);


        $info['type'] = 0 != $info && $info['cat_id'] ? 1 : 0;

        $info['category'] = $info && $info['cat_id'] ? explode(',', $info['cat_id']) : array();
        $info['category2'] = $info && $info['cat_id'] ? explode(',', $info['cat_id_2']) : array();
        $info['category3'] = $info && $info['cat_id'] ? explode(',', $info['cat_id_3']) : array();

        $cat_list = Db::name('goods_category')->where('parent_id = 0')->select();
        $this->assign('cat_list', $cat_list);

        return view('', compact('info'));
    }

    /**
     * 赠品活动详情页.
     *
     * @return \think\response\View
     */
    public function gift_info()
    {
        $id = I('id');

        $detail = $this->service->getGiftLogById($id);

        return view('', compact('detail'));
    }

    /**
     * 新增赠品活动.
     *
     * @param Giftvalidate $validate
     *
     * @return \think\response\Json
     */
    public function gift_store(Giftvalidate $validate)
    {
        $data = I('post.');
        if (!$validate->check($data)) {
            return json(['status' => 0, 'msg' => $validate->getError(), 'result' => null]);
        }

        $result = $this->service->giftStore($data);

        if (!$result) {
            return json(['status' => 0, 'msg' => '新增赠品活动失败.', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '新增赠品活动成功.', 'result' => null]);
    }

    /**
     * 编辑赠品活动.
     *
     * @param Giftvalidate $validate
     *
     * @return \think\response\Json
     */
    public function gift_update(Giftvalidate $validate)
    {
        $data = I('post.');
        if (!$validate->check($data)) {
            return json(['status' => 0, 'msg' => $validate->getError(), 'result' => null]);
        }

        $result = $this->service->giftUpdate($data);

        if (!$result) {
            return json(['status' => 0, 'msg' => '编辑赠品活动失败.错误信息:' . $this->service->error, 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '编辑赠品活动成功.', 'result' => null]);
    }

    /**
     * 删除赠品活动.
     *
     * @return \think\response\Json
     */
    public function gift_delete()
    {
        $id = I('id');

        $result = $this->service->giftDelete($id);

        if (!$result) {
            return json(['status' => 0, 'msg' => '删除赠品活动失败.', 'result' => null]);
        }

        return json(['status' => 1, 'msg' => '删除赠品活动成功.', 'result' => null]);
    }

    /**
     * 商品活动列表.
     */
    public function prom_goods_list()
    {
        $PromGoods = new PromGoods();
        $count = $PromGoods->count();
        $Page = new Page($count, 10);
        $prom_list = $PromGoods->order('start_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page', $Page);
        $this->assign('prom_list', $prom_list);

        return $this->fetch();
    }

    public function prom_goods_info()
    {
        $level = M('distribut_level')->select();
        $this->assign('level', $level);
        $prom_id = I('id');
        $info = M('prom_goods')->where(array('id' => $prom_id))->find();
        if ($prom_id > 0) {
            $GoodsTao = M('GoodsTaoGrade')->where(array('promo_id' => $prom_id))->select();
            foreach ($GoodsTao as $k => $v) {
                $prom_goods[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
                $prom_goods[$k]['stock'] = $v['stock'];
                if ($v['item_id']) {
                    $prom_goods[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
                }
            }
            $this->assign('prom_goods', $prom_goods);
            $info['start_time'] = date('Y-m-d H:i:s', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i:s', $info['end_time']);
            $info['group'] = array_filter(explode(',', $info['group']));
        } else {
            $info['start_time'] = date('Y-m-d H:i:s');
            $info['end_time'] = date('Y-m-d H:i:s', time() + 3600 * 60 * 24);
            $info['group'] = array();
        }
        $coupon_list = M('coupon')->where(['type' => 0, 'status' => 1, 'use_start_time' => ['lt', time()], 'use_end_time' => ['gt', time()]])->select();
        $this->assign('coupon_list', $coupon_list);
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));
        $this->initEditor();

        return $this->fetch();
    }

    public function prom_goods_save()
    {
        $prom_id = I('id/d');

        $data = I('post.');
        $title = input('title');

        /*$promGoodsValidate = Loader::validate('PromGoods');
        if(!$promGoodsValidate->batch()->check($data)){
            $return = ['status' => 0,'msg' =>'操作失败',
                'result'    => $promGoodsValidate->getError(),
                'token'       =>  \think\Request::instance()->token(),
            ];
            $this->ajaxReturn($return);
        }*/
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);

        switch ($data['type']) {
            case 4: // 满打折
                $data['goods_num'] = explode('/', $data['expression'])[0];
                $data['expression'] = explode('/', $data['expression'])[1];
                break;
            case 5: // 满减价
                $data['goods_price'] = explode('/', $data['expression'])[0];
                $data['expression'] = explode('/', $data['expression'])[1];
                break;
        }

        if ($data['group']) {
            $data['group'] = implode(',', $data['group']);
        } else {
            $data['group'] = 1;
        }

        if ($prom_id) {
            M('goods')->where(['prom_type' => 3, 'prom_id' => $prom_id])->save(['prom_id' => 0, 'prom_type' => 0]);
            M('prom_goods')->where(['id' => $prom_id])->save($data);
            $last_id = $prom_id;
            adminLog("管理员修改了商品促销 " . $title);
        } else {
            $last_id = M('prom_goods')->add($data);
            adminLog("管理员添加了商品促销 " . $title);
        }

        M('goods_tao_grade')->where(array('promo_id' => $last_id))->delete();
        $promGoods = $data['goods'];
//        $tao_goods = array();
        if ($promGoods) {
            foreach ($promGoods as $goodsKey => $goodsVal) {
                $dfd = explode('_', $goodsKey);
                $tao_goods = array(
                    'goods_id' => $dfd[0],
                    'item_id' => $goodsVal['item_id'][0],
                    'promo_id' => $last_id,
                    'stock' => 1
                );
                M('goods_tao_grade')->data($tao_goods)->add();
                M('goods')->where(['goods_id' => $dfd[0]])->update(['prom_type' => 3, 'prom_id' => $last_id]);
            }

        }
        $this->ajaxReturn(['status' => 1, 'msg' => '编辑促销活动成功', 'result']);
    }

    public function prom_goods_del()
    {
        $prom_id = I('id');
        $order_goods = M('order_goods')->where("prom_type = 3 and prom_id = $prom_id")->find();
        if (!empty($order_goods)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '该活动有订单参与不能删除!']);
        }
        M('goods')->where("prom_id=$prom_id and prom_type=3")->save(['prom_id' => 0, 'prom_type' => 0]);
        Db::name('spec_goods_price')->where(['prom_type' => 3, 'prom_id' => $prom_id])->save(['prom_id' => 0, 'prom_type' => 0]);
        M('prom_goods')->where("id=$prom_id")->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除活动成功']);
    }

    /**
     * 活动列表.
     */
    public function prom_order_list()
    {
        $parse_type = ['0' => '满额打折', '1' => '满额优惠金额', '2' => '满额送积分', '3' => '满额送优惠券'];
        $level = M('user_level')->select();
        if ($level) {
            foreach ($level as $v) {
                $lv[$v['level_id']] = $v['level_name'];
            }
        }
        $count = M('prom_order')->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $prom_list = M('prom_order')->limit($Page->firstRow . ',' . $Page->listRows)->select();
//        if ($res) {  //获得适用范围（用户等级）
//            foreach ($res as $val) {
//                if (!empty($val['group']) && !empty($lv)) {
//                    $val['group'] = explode(',', $val['group']);
//                    foreach ($val['group'] as $v) {
//                        $val['group_name'] .= $lv[$v] . ',';
//                    }
//                }
//                $prom_list[] = $val;
//            }
//        }
        $this->assign('pager', $Page); // 赋值分页输出
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('parse_type', $parse_type);
        $this->assign('prom_list', $prom_list);

        return $this->fetch();
    }

    public function prom_order_info()
    {
        $this->assign('min_date', date('Y-m-d'));
        $level = M('user_level')->select();
        $this->assign('level', $level);
        $prom_id = I('id');
        $info['start_time'] = date('Y-m-d');
        $info['end_time'] = date('Y-m-d', time() + 3600 * 24 * 60);
        if ($prom_id > 0) {
            $info = M('prom_order')->where("id=$prom_id")->find();
            $info['start_time'] = date('Y-m-d H:i:s', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i:s', $info['end_time']);
        }
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));
        $this->initEditor();

        return $this->fetch();
    }

    public function prom_order_save()
    {
        $prom_id = I('id');
        $data = I('post.');
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        $data['group'] = $data['group'] ? implode(',', $data['group']) : '';
        if ($prom_id) {
            M('prom_order')->where("id=$prom_id")->save($data);
            adminLog('管理员修改了商品促销 ' . I('name'));
        } else {
            M('prom_order')->add($data);
            adminLog('管理员添加了商品促销 ' . I('name'));
        }
        $this->success('编辑促销活动成功', U('Promotion/prom_order_list'));
    }

    public function prom_order_del()
    {
        $prom_id = I('id');
        $order = M('order')->where("order_prom_id = $prom_id")->find();
        if (!empty($order)) {
            $this->error('该活动有订单参与不能删除!');
        }

        M('prom_order')->where("id=$prom_id")->delete();
        $this->success('删除活动成功', U('Promotion/prom_order_list'));
    }

    public function group_buy_list()
    {
        $GroupBuy = new GroupBuy();
        $count = $GroupBuy->where('')->count();
        $Page = new Page($count, 10);
        $list = $GroupBuy->where('')->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);
        $this->assign('page', $Page);

        return $this->fetch();
    }

    public function group_buy()
    {
        $act = I('GET.act', 'add');
        $groupbuy_id = I('get.id/d');
        $group_info = [];
        $group_info['start_time'] = date('Y-m-d');
        $group_info['end_time'] = date('Y-m-d', time() + 3600 * 365);
        if ($groupbuy_id) {
            $GroupBy = new GroupBuy();
            $group_info = $GroupBy->with('specGoodsPrice,goods')->find($groupbuy_id);
            $group_info['start_time'] = date('Y-m-d H:i', $group_info['start_time']);
            $group_info['end_time'] = date('Y-m-d H:i', $group_info['end_time']);
            $act = 'edit';
        }
        $this->assign('min_date', date('Y-m-d'));
        $this->assign('info', $group_info);
        $this->assign('act', $act);

        return $this->fetch();
    }

    public function group_buy_detail()
    {
        $act = I('GET.act', 'add');
        $groupbuy_id = I('get.id/d');
        $group_info = [];
        $group_info['start_time'] = date('Y-m-d');
        $group_info['end_time'] = date('Y-m-d', time() + 3600 * 365);
        if ($groupbuy_id) {
            $GroupBy = new GroupBuy();
            $group_info = $GroupBy->with('specGoodsPrice,goods,groupDetail')->find($groupbuy_id);
            // dump($group_info->toArray());
            // exit;
            $group_info['start_time'] = date('Y-m-d H:i', $group_info['start_time']);
            $group_info['end_time'] = date('Y-m-d H:i', $group_info['end_time']);
            $act = 'edit';
        }
        $this->assign('min_date', date('Y-m-d'));
        $this->assign('info', $group_info);
        $this->assign('act', $act);

        return $this->fetch();
    }

    public function groupbuyHandle()
    {
        $data = I('post.');
        $data['can_integral'] = isset($data['can_integral']) ? 1 : 0;
        $data['groupbuy_intro'] = htmlspecialchars(stripslashes($this->request->param('groupbuy_intro')));
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($data['can_integral'] == 1) {
            // 验证秒杀积分
            $goodsIntegral = Db::name('goods')->where(['goods_id' => $data['goods_id']])->value('exchange_integral');
            if ($goodsIntegral >= $data['price']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '可兑换商品的积分多于团购价格，请不要勾选使用积分']);
            }
        }
        if ('del' == $data['act']) {
            $spec_goods = Db::name('spec_goods_price')->where(['prom_type' => 2, 'prom_id' => $data['id']])->find();
            //有活动商品规格
            if ($spec_goods) {
                Db::name('spec_goods_price')->where(['prom_type' => 2, 'prom_id' => $data['id']])->save(['prom_id' => 0, 'prom_type' => 0]);
                //商品下的规格是否都没有活动
                $goods_spec_num = Db::name('spec_goods_price')->where(['prom_type' => 2, 'goods_id' => $spec_goods['goods_id']])->find();
                if (empty($goods_spec_num)) {
                    //商品下的规格都没有活动,把商品回复普通商品
                    Db::name('goods')->where(['goods_id' => $spec_goods['goods_id']])->save(['prom_id' => 0, 'prom_type' => 0]);
                }
            } else {
                //没有商品规格
                Db::name('goods')->where(['prom_type' => 2, 'prom_id' => $data['id']])->save(['prom_id' => 0, 'prom_type' => 0]);
            }
            $r = D('group_buy')->where(['id' => $data['id']])->delete();
            if ($r) {
                exit(json_encode(1));
            }
        }
        $groupBuyValidate = Loader::validate('GroupBuy');
        if ($data['item_id'] > 0) {
            $spec_goods_price = Db::name('spec_goods_price')->where(['item_id' => $data['item_id']])->find();
            $data['goods_price'] = $spec_goods_price['price'];
            $data['store_count'] = $spec_goods_price['store_count'];
        } else {
            $goods = Db::name('goods')->where(['goods_id' => $data['goods_id']])->find();
            $data['goods_price'] = $goods['shop_price'];
            $data['store_count'] = $goods['store_count'];
        }
        if (!$groupBuyValidate->batch()->check($data)) {
            $msg = '';
            foreach ($groupBuyValidate->getError() as $error) {
                $msg .= $error . '，';
            }
            $return = ['status' => 0, 'msg' => rtrim($msg, '，'), 'result' => $groupBuyValidate->getError()];
            $this->ajaxReturn($return);
        }
        $data['rebate'] = number_format($data['price'] / $data['goods_price'] * 10, 1);
        if ($data['item_id'] > 0 && M('spec_goods_price')->where(['item_id' => $data['item_id']])->value('key') == '') {
            $data['item_id'] = 0;
        }
        if ('add' == $data['act']) {
            $r = Db::name('group_buy')->insertGetId($data);
            if ($data['item_id'] > 0 && M('spec_goods_price')->where(['item_id' => $data['item_id']])->value('key') != '') {
                //设置商品一种规格为活动
                Db::name('spec_goods_price')->where('item_id', $data['item_id'])->update(['prom_id' => $r, 'prom_type' => 2]);
                Db::name('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => 0, 'prom_type' => 2]);
            } else {
                Db::name('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => $r, 'prom_type' => 2]);
            }
        }
        if ('edit' == $data['act']) {
            $r = Db::name('group_buy')->where(['id' => $data['id']])->update($data);
            if ($data['item_id'] > 0 && M('spec_goods_price')->where(['item_id' => $data['item_id']])->value('key') != '') {
                //设置商品一种规格为活动
                Db::name('spec_goods_price')->where(['prom_type' => 2, 'prom_id' => $data['id']])->update(['prom_id' => 0, 'prom_type' => 0]);
                Db::name('spec_goods_price')->where('item_id', $data['item_id'])->update(['prom_id' => $data['id'], 'prom_type' => 2]);
                M('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => 0, 'prom_type' => 2]);
            } else {
                M('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => $data['id'], 'prom_type' => 2]);
            }
        }
        if (false !== $r) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result' => '']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => '']);
        }
    }

    public function get_goods()
    {
        $prom_id = I('id/d');
        $Goods = new Goods();
        $prom_where = ['prom_id' => $prom_id, 'prom_type' => 3];
        $count = $Goods->where($prom_where)->count('goods_id');
        $Page = new Page($count, 10);
        $goodsList = $Goods->with('specGoodsPrice')->where($prom_where)->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('goodsList', $goodsList);

        return $this->fetch();
    }

    public function search_goods()
    {
        $goods_id = input('goods_id');
        $intro = input('intro');
        $cat_id = input('cat_id');
        $brand_id = input('brand_id');
        $keywords = input('keywords');
        $prom_id = input('prom_id');
        $tpl = input('tpl', 'search_goods');
        $where = ['store_count' => ['gt', 0], 'is_virtual' => 0, 'is_area_show' => 1];
        $prom_type = input('prom_type/d');
        $coupon_use_type = input('coupon_use_type', '');
        if ($goods_id) {
            $where['goods_id'] = ['notin', trim($goods_id, ',')];
        }
        if ($intro) {
            $where[$intro] = 1;
        }
        if ($cat_id) {
            $grandson_ids = getCatGrandson($cat_id);
            $where['cat_id'] = ['in', implode(',', $grandson_ids)];
        }
        if ($brand_id) {
            $where['brand_id'] = $brand_id;
        }
        if ($keywords) {
            $where['goods_name|keywords'] = ['like', '%' . $keywords . '%'];
        }
        $Goods = new Goods();
        $count = $Goods->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if (3 == $prom_type) {
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => $prom_type])->whereor('prom_id', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            } elseif (in_array($prom_type, [1, 2, 6])) {
                //抢购，团购
                $query->where('prom_type', 'in', [0, $prom_type])->where('prom_type', 0);
            } elseif (7 == $prom_type) {
                // 订单合购
                $query->where('prom_type', 'not in', [$prom_type]);
            } else {
                $query->where('prom_type', 0);
            }
        })->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->with('specGoodsPrice')->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if (3 == $prom_type) {
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => $prom_type])->whereor('prom_id', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            } elseif (in_array($prom_type, [1, 2, 6])) {
                //抢购，团购
                $query->where('prom_type', 'in', [0, $prom_type])->where('prom_id', 0);
            } elseif (7 == $prom_type) {
                // 订单合购
                $query->where('prom_type', 'not in', [$prom_type]);
            } else {
                $query->where('prom_type', 0);
            }
        })->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $types = I('types', 1);
        $this->assign('types', $types);

        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);
        $this->assign('coupon_use_type', $coupon_use_type);

        return $this->fetch($tpl);
    }

    //限时抢购
    public function flash_sale()
    {
        $condition = [];
        $FlashSale = new FlashSale();
        $count = $FlashSale->where($condition)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $prom_list = $FlashSale->append(['status_desc'])->where($condition)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('prom_list', $prom_list);
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    public function flash_sale_info()
    {
        if (IS_POST) {
            $data = I('post.');
            if (empty($data['source'])) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请选择展示地方']);
            }
            Db::startTrans();
            switch ($data['action']) {
                case 1:
                    $data['source'] = implode(',', $data['source']);
                    $data['can_integral'] = isset($data['can_integral']) ? 1 : 0;
                    $data['start_time'] = strtotime($data['start_time']);
                    $data['end_time'] = strtotime($data['end_time']);
                    $flashSaleValidate = Loader::validate('FlashSale');
                    if (!$flashSaleValidate->batch()->check($data)) {
                        $msg = '';
                        foreach ($flashSaleValidate->getError() as $item) {
                            $msg .= $item . '，';
                        }
                        $return = ['status' => 0, 'msg' => rtrim($msg, '，')];
                        $this->ajaxReturn($return);
                    }
                    if ($data['can_integral'] == 1) {
                        // 验证秒杀积分
                        $goodsIntegral = Db::name('goods')->where(['goods_id' => $data['goods_id']])->value('exchange_integral');
                        if ($goodsIntegral >= $data['price']) {
                            $this->ajaxReturn(['status' => 0, 'msg' => '可兑换商品的积分多于秒杀价格，请不要勾选使用积分']);
                        }
                    }
                    if ($data['item_id'] > 0 && M('spec_goods_price')->where(['item_id' => $data['item_id']])->value('key') == '') {
                        $data['item_id'] = 0;
                    }
                    if (empty($data['id'])) {
                        $flashSaleInsertId = Db::name('flash_sale')->insertGetId($data);
                        if ($data['item_id'] > 0 && M('spec_goods_price')->where(['item_id' => $data['item_id']])->value('key') != '') {
                            //设置商品一种规格为活动
                            Db::name('spec_goods_price')->where('item_id', $data['item_id'])->update(['prom_id' => $flashSaleInsertId, 'prom_type' => 1]);
                            Db::name('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => 0, 'prom_type' => 1]);
                        } else {
                            Db::name('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => $flashSaleInsertId, 'prom_type' => 1]);
                        }
                        adminLog('管理员添加抢购活动 ' . $data['title']);
                        if (false !== $flashSaleInsertId) {
                            Db::commit();
                            $this->ajaxReturn(['status' => 1, 'msg' => '添加抢购活动成功', 'result' => '']);
                        } else {
                            $this->ajaxReturn(['status' => 0, 'msg' => '添加抢购活动失败', 'result' => '']);
                        }
                    } else {
                        $r = M('flash_sale')->where('id=' . $data['id'])->save($data);
                        M('goods')->where(['prom_type' => 1, 'prom_id' => $data['id']])->save(['prom_id' => 0, 'prom_type' => 0]);
                        if ($data['item_id'] > 0 && M('spec_goods_price')->where(['item_id' => $data['item_id']])->value('key') != '') {
                            //设置商品一种规格为活动
                            Db::name('spec_goods_price')->where(['prom_type' => 1, 'prom_id' => $data['item_id']])->update(['prom_id' => 0, 'prom_type' => 0]);
                            Db::name('spec_goods_price')->where('item_id', $data['item_id'])->update(['prom_id' => $data['id'], 'prom_type' => 1]);
                            M('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => 0, 'prom_type' => 1]);
                        } else {
                            M('goods')->where('goods_id', $data['goods_id'])->save(['prom_id' => $data['id'], 'prom_type' => 1]);
                        }
                        adminLog('管理员编辑抢购活动 ' . $data['title']);
                        if (false !== $r) {
                            Db::commit();
                            $this->ajaxReturn(['status' => 1, 'msg' => '编辑抢购活动成功', 'result' => '']);
                        } else {
                            $this->ajaxReturn(['status' => 0, 'msg' => '编辑抢购活动失败', 'result' => '']);
                        }
                    }
                    break;
                case 2:
                    M('flash_sale')->where('id=' . $data['id'])->save([
                        'end_time' => time()
                    ]);
                    adminLog('管理员紧急下架抢购活动 ' . $data['title']);
                    Db::commit();
                    $this->ajaxReturn(['status' => 1, 'msg' => '编辑抢购活动成功', 'result' => '']);
                    break;
                case 3:
                    M('flash_sale')->where('id=' . $data['id'])->save([
                        'end_time' => strtotime($data['end_time'])
                    ]);
                    adminLog('管理员继续上架抢购活动 ' . $data['title']);
                    Db::commit();
                    $this->ajaxReturn(['status' => 1, 'msg' => '编辑抢购活动成功', 'result' => '']);
            }
        }
        $id = I('id');
        $now_time = date('H');
        if (0 == $now_time % 2) {
            $flash_now_time = $now_time;
        } else {
            $flash_now_time = $now_time - 1;
        }
        $flash_sale_time = strtotime(date('Y-m-d') . ' ' . $flash_now_time . ':00:00');
        $info['start_time'] = date('Y-m-d H:i:s', $flash_sale_time);
        $info['end_time'] = date('Y-m-d H:i:s', $flash_sale_time + 7200);
        if ($id > 0) {
            $FlashSale = new FlashSale();
            $info = $FlashSale->with('specGoodsPrice,goods')->find($id);
            $info['start_time'] = date('Y-m-d H:i', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i', $info['end_time']);
            $info['source'] = explode(',', $info['source']);
        }
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));

        return $this->fetch();
    }

    public function flash_sale_del()
    {
        $id = I('del_id/d');
        if ($id) {
            $spec_goods = Db::name('spec_goods_price')->where(['prom_type' => 1, 'prom_id' => $id])->find();
            //有活动商品规格
            if ($spec_goods) {
                Db::name('spec_goods_price')->where(['prom_type' => 1, 'prom_id' => $id])->save(['prom_id' => 0, 'prom_type' => 0]);
                //商品下的规格是否都没有活动
                $goods_spec_num = Db::name('spec_goods_price')->where(['prom_type' => 1, 'goods_id' => $spec_goods['goods_id']])->find();
                if (empty($goods_spec_num)) {
                    //商品下的规格都没有活动,把商品回复普通商品
                    Db::name('goods')->where(['goods_id' => $spec_goods['goods_id']])->save(['prom_id' => 0, 'prom_type' => 0]);
                }
            } else {
                //没有商品规格
                Db::name('goods')->where(['prom_type' => 1, 'prom_id' => $id])->save(['prom_id' => 0, 'prom_type' => 0]);
            }
            M('flash_sale')->where(['id' => $id])->delete();
            exit(json_encode(1));
        }
        exit(json_encode(0));
    }

    private function initEditor()
    {
        $this->assign('URL_upload', U('Admin/Ueditor/imageUp', ['savepath' => 'promotion']));
        $this->assign('URL_fileUp', U('Admin/Ueditor/fileUp', ['savepath' => 'promotion']));
        $this->assign('URL_scrawlUp', U('Admin/Ueditor/scrawlUp', ['savepath' => 'promotion']));
        $this->assign('URL_getRemoteImage', U('Admin/Ueditor/getRemoteImage', ['savepath' => 'promotion']));
        $this->assign('URL_imageManager', U('Admin/Ueditor/imageManager', ['savepath' => 'promotion']));
        $this->assign('URL_imageUp', U('Admin/Ueditor/imageUp', ['savepath' => 'promotion']));
        $this->assign('URL_getMovie', U('Admin/Ueditor/getMovie', ['savepath' => 'promotion']));
        $this->assign('URL_Home', '');
    }

    /**
     * 商品预售列表.
     */
    public function pre_sell_list()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 预售商品商品详情页.
     */
    public function pre_sell_info()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 预售商品删除处理.
     */
    public function pre_sell_del()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 预售活动成功
     */
    public function pre_sell_success()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 预售活动失败.
     */
    public function pre_sell_fail()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * 订单优惠促销列表
     * @return mixed
     */
    public function order_prom_list()
    {
        $orderProm = new OrderPromModel();
        $count = $orderProm->count();
        $page = new Page($count, 10);
        $prom_list = $orderProm->limit($page->firstRow . ',' . $page->listRows)->order('id desc')->select();

        $this->assign('page', $page);
        $this->assign('prom_list', $prom_list);

        return $this->fetch();
    }

    /**
     * 订单优惠促销详情
     * @return mixed
     */
    public function order_prom_info()
    {
        $orderPromId = I('id', '');
        if (!$orderPromId) {
            return $this->fetch();
        }
        $promInfo = Db::name('order_prom')->where(['id' => $orderPromId])->find();
        // 活动购买商品
//        $buyGoods = Db::name('order_prom_goods')->where(['order_prom_id' => $orderPromId, 'type' => 1])->select();
//        $buy_goods = [];
//        foreach ($buyGoods as $k => $v) {
//            $buy_goods[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
//            $buy_goods[$k]['goods_num'] = $v['goods_num'];
//            if ($v['item_id']) {
//                $buy_goods[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
//            }
//        }
        // 赠送商品
        $giftGoods = Db::name('order_prom_goods')->where(['order_prom_id' => $orderPromId, 'type' => 2])->select();
        $gift_goods = [];
        foreach ($giftGoods as $k => $v) {
            $gift_goods[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
            $gift_goods[$k]['goods_num'] = $v['goods_num'];
            if ($v['item_id']) {
                $gift_goods[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
            }
        }
        $promInfo['start_time'] = date('Y-m-d H:i:s', $promInfo['start_time']);
        $promInfo['end_time'] = date('Y-m-d H:i:s', $promInfo['end_time']);
        $this->assign('gift_goods', $gift_goods);
//        $this->assign('buy_goods', $buy_goods);
        $this->assign('info', $promInfo);
        return $this->fetch();
    }

    /**
     * 订单促销可赠送商品
     * @return mixed
     */
    public function order_search_goods()
    {
        // 要过滤的商品ID
        $goodsIds1 = Db::name('gift')->getField('goods_id', true);
        $goodsIds2 = Db::name('gift2_goods')->getField('goods_id', true);
        $goodsIds = array_unique(array_merge($goodsIds1, $goodsIds2));
        $where = [
            'goods_id' => ['not in', $goodsIds],
            'is_on_sale' => 1
        ];
        $Goods = new Goods();
        $count = $Goods->where($where)->count();
        $Page = new Page($count, 10);
        // 获取商品信息
        $goodsList = $Goods->with('specGoodsPrice')->where($where)->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $types = I('types', 1);
        $this->assign('types', $types);

        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);

        return $this->fetch('search_goods');
    }

    /**
     * 新增/编辑订单促销活动
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function order_prom_save()
    {
        $data = I('post.');
        $orderPromId = $data['id'];
        switch ($data['type']) {
            case 0:
            case 2:
                if (!isset($data['gift_goods'])) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '请选择赠送商品']);
                }
                break;
            case 1:
                unset($data['gift_goods']);
                break;
        }
        // 验证
        $orderPromValidate = Loader::validate('OrderProm');
        if (!$orderPromValidate->batch()->check($data)) {
            $msg = '';
            foreach ($orderPromValidate->getError() as $item) {
                $msg .= $item . '，';
            }
            $return = ['status' => 0, 'msg' => rtrim($msg, '，')];
            $this->ajaxReturn($return);
        }

//        $buyGoods = $data['buy_goods'];
//        unset($data['buy_goods']);
        $giftGoods = isset($data['gift_goods']) ? $data['gift_goods'] : [];

        // 订单优惠数据
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($orderPromId) {
            // 编辑
            Db::name('order_prom')->where(['id' => $orderPromId])->update($data);
            Db::name('order_prom_goods')->where(['order_prom_id' => $orderPromId])->delete();   // 删除旧商品数据
        } else {
            // 新增
            $orderPromId = Db::name('order_prom')->add($data);
        }
        // 参与活动商品
//        $goodsIds = [];
//        $buyGoodsData = [];
//        foreach ($buyGoods as $key => $value) {
//            $goods_item = explode('_', $key);
//            $goodsIds[] = $goods_item[0];
//            $buyGoodsData[] = [
//                'order_prom_id' => $orderPromId,
//                'type' => 1,
//                'goods_id' => $goods_item[0],
//                'item_id' => isset($goods_item[1]) ? $goods_item[1] : 0
//            ];
//        }
        $orderPromGoods = new OrderPromGoodsModel();
//        $orderPromGoods->saveAll($buyGoodsData);
//        // 更新商品活动信息
//        Db::name('goods')->where(['goods_id' => ['in', array_unique($goodsIds)]])->update(['prom_id' => $orderPromId, 'prom_type' => 7]);
        // 赠送商品
        if (!empty($giftGoods)) {
            $giftGoodsData = [];
            foreach ($giftGoods as $key => $value) {
                $goods_item = explode('_', $key);
                $giftGoodsData[] = [
                    'order_prom_id' => $orderPromId,
                    'type' => 2,
                    'goods_id' => $goods_item[0],
                    'item_id' => isset($goods_item[1]) ? $goods_item[1] : 0,
                    'goods_num' => $value['goods_num']
                ];
            }
            $orderPromGoods->saveAll($giftGoodsData);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
    }

    /**
     * 删除订单促销活动
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function order_prom_del()
    {
        $orderPromId = I('id');
        $order_goods = M('order_goods')->where(['prom_type' => 7, 'prom_id' => $orderPromId, 'is_send' => 0])->find();
        if (!empty($order_goods)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '该活动有订单参与不能删除!']);
        }
        Db::name('order_prom')->where(['id' => $orderPromId])->delete();
        // 活动相关商品
        $goodsIds = Db::name('order_prom_goods')->where(['order_prom_id' => $orderPromId, 'type' => 1])->getField('goods_id', true);
        Db::name('goods')->where(['goods_id' => ['in', $goodsIds]])->save(['prom_id' => 0, 'prom_type' => 0]);

        $this->ajaxReturn(['status' => 1, 'msg' => '删除活动成功']);
    }
}
