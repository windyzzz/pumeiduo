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
use app\admin\model\Ad as AdModel;
use app\admin\model\Goods;
use app\admin\model\Popup as PopupModel;
use think\Db;
use think\Loader;
use think\Page;

class Ad extends Base
{
    public function ad()
    {
        $act = I('get.act', 'add');
        $ad_id = I('get.ad_id/d');
        $is_app_ad = I('get.is_app_ad/d'); //是否APP广告

        $ad_info = [];
        if ($ad_id) {
            $ad_info = D('ad')->where('ad_id', $ad_id)->find();
            $ad_info['start_time'] = date('Y-m-d', $ad_info['start_time']);
            $ad_info['end_time'] = date('Y-m-d', $ad_info['end_time']);
        }
        if ('add' == $act) {
            $ad_info['pid'] = $this->request->param('pid');
        }

        if (1 == $is_app_ad) {
            $cat_list = M('goods_category')->where('parent_id = 0')->select(); // 已经改成联动菜单
            $this->assign('cat_list', $cat_list);

            if ($ad_info && 3 == $ad_info['media_type']) {//如果广告类型是商品,则查找商品的名称
                $ad_info['goods_name'] = M('goods')->where('goods_id', $ad_info['ad_link'])->getField('goods_name');
            } elseif ($ad_info && 4 == $ad_info['media_type']) {//如果广告类型是商品分类,则拆解分类
                $cat_ids = explode('_', $ad_info['ad_link']);
                $ad_info['cat_id1'] = $cat_ids[0];
                $ad_info['cat_id2'] = $cat_ids[1];
                $ad_info['cat_id3'] = $cat_ids[2];
            }
        }
        // APP跳转类型
        $ad_info['goods'] = [
            'goods_id' => 0,
            'goods_name' => 0,
            'original_img' => 0
        ];
        $ad_info['prom'] = [
            'id' => 0,
            'title' => 0
        ];
        $ad_info['goods_category'] = [
            'id' => 0,
            'name' => 0
        ];
        switch ($ad_info['target_type']) {
            case 1:
                $ad_info['goods'] = M('goods')->where(['goods_id' => $ad_info['target_type_id']])->field('goods_id, goods_name, original_img')->find();
                break;
            case 2:
                $ad_info['prom'] = M('prom_goods')->where(['id' => $ad_info['target_type_id']])->field('id, title')->find();
                break;
            case 11:
                $ad_info['goods_category'] = M('goods_category')->where(['id' => $ad_info['target_type_id']])->field('id, name')->find();
                break;
        }
        // 优惠促销方案
        $promList = M('prom_goods')->where([
            'start_time' => ['<', time()],
            'end_time' => ['>', time()],
            'is_end' => 0,
            'is_open' => 1
        ])->field('id, title')->select();
        // 商品分类
        $goodsCategory = M('goods_category')->where(['level' => 3])->field('id, name')->select();

        $position = D('ad_position')->select();
        $this->assign('info', $ad_info);
        $this->assign('act', $act);
        $this->assign('position', $position);
        $this->assign('prom_list', $promList);
        $this->assign('goods_category', $goodsCategory);

        return $this->fetch();
    }

    public function adList()
    {
        delFile(RUNTIME_PATH . 'html'); // 先清除缓存, 否则不好预览

        $Ad = new AdModel();
        $pid = I('pid', 0);

        $order = 'pid DESC, enabled DESC';
        $where = [];
        if ($pid) {
            $where['pid'] = $pid;
            $this->assign('pid', I('pid'));
            $order .= ', orderby DESC';
        }
        $order .= ', ad_id DESC';
        $keywords = I('keywords/s', false, 'trim');
        if ($keywords) {
            $where['ad_name'] = ['like', '%' . $keywords . '%'];
        }
        $count = $Ad->where($where)->count(); // 查询满足要求的总记录数
        $Page = $pager = new Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $res = $Ad->where($where)->order('pid desc')->limit($Page->firstRow . ',' . $Page->listRows)->order($order)->select();
        $list = [];
        if ($res) {
            $media = ['图片', '文字', 'flash'];
            foreach ($res as $val) {
                $val['media_type'] = $media[$val['media_type']];
                $list[] = $val;
            }
        }
        $ad_position_list = M('AdPosition')->getField('position_id,position_name,is_open');
        $this->assign('ad_position_list', $ad_position_list); //广告位
        $show = $Page->show(); // 分页显示输出
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->assign('pager', $pager);

        //判断API模块存在
        if (is_dir(APP_PATH . '/api')) {
            $this->assign('is_exists_api', 1);
        }

        return $this->fetch();
    }

    public function position()
    {
        $act = I('get.act', 'add');
        $position_id = I('get.position_id/d');
        $info = [];
        if ($position_id) {
            $info = D('ad_position')->where('position_id', $position_id)->find();
        }
        $this->assign('info', $info);
        $this->assign('act', $act);

        return $this->fetch();
    }

    public function positionList()
    {
        $count = Db::name('ad_position')->count(); // 查询满足要求的总记录数
        $Page = $pager = new Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $list = Db::name('ad_position')->order('position_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->assign('pager', $Page);

        return $this->fetch();
    }

    public function adHandle()
    {
        $data = I('post.');
        $data['start_time'] = strtotime($data['begin']);
        $data['end_time'] = strtotime($data['end']);
        $media_type = $data['media_type'];
        if (3 == $media_type) {//商品
            $data['ad_link'] = $data['goods_id'];
        } elseif (4 == $media_type) {//分类
            $data['ad_link'] = $data['cat_id1'] . '_' . $data['cat_id2'] . '_' . $data['cat_id3'];
        } else {
            $data['ad_link'] = htmlspecialchars_decode($data['ad_link']);
        }
        switch ($data['target_type']) {
            case 1:
                if (!$data['goods_id'] || $data['goods_id'] == 0) {
                    $this->error('请选择商品');
                }
                $data['target_type_id'] = $data['goods_id'];
                break;
            case 2:
                if (!$data['prom_id'] || $data['prom_id'] == 0) {
                    $this->error('请选择促销优惠');
                }
                $data['target_type_id'] = $data['prom_id'];
                break;
            case 11:
                if (!$data['cate_id'] || $data['cate_id'] == 0) {
                    $this->error('请选择商品分类');
                }
                $data['target_type_id'] = $data['cate_id'];
                break;
            default:
                $data['target_type_id'] = 0;
        }
        unset($data['goods_id']);
        unset($data['goods_name']);
        unset($data['prom_id']);
        switch ($data['act']) {
            case 'add':
                $r = D('ad')->add($data);
                break;
            case 'edit':
                $r = D('ad')->where('ad_id', $data['ad_id'])->save($data);
                break;
            case 'del':
                $r = D('ad')->where('ad_id', $data['del_id'])->delete();
                if ($r) {
                    $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/Ad/adList')]);
                } else {
                    $this->ajaxReturn(['status' => -1, 'msg' => '操作失败']);
                }
                break;
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U('Admin/Ad/adList');
        // 不管是添加还是修改广告 都清除一下缓存
        delFile(RUNTIME_PATH . 'html'); // 先清除缓存, 否则不好预览
        \think\Cache::clear();
        if ($r) {
            $redirect_url = session('ad_request_url');
            $redirect_url && $this->success('操作成功', U('Admin/Ad/editAd', ['request_url' => $redirect_url]));
            $this->success('操作成功', U('Admin/Ad/adList'));
        } else {
            $this->error('操作失败', $referurl);
        }
    }

    public function positionHandle()
    {
        $data = I('post.');
        if ('add' == $data['act']) {
            $r = M('ad_position')->add($data);
        }

        if ('edit' == $data['act']) {
            $r = M('ad_position')->where('position_id', $data['position_id'])->save($data);
        }

        if ('del' == $data['act']) {
            if (M('ad')->where('pid', $data['position_id'])->count() > 0) {
                $this->error('此广告位下还有广告，请先清除', U('Admin/Ad/positionList'));
            } else {
                $r = M('ad_position')->where('position_id', $data['position_id'])->delete();
                if ($r) {
                    exit(json_encode(1));
                }
            }
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U('Admin/Ad/positionList');
        if ($r) {
            $this->success('操作成功', $referurl);
        } else {
            $this->error('操作失败', $referurl);
        }
    }

    /**
     * APP端编辑广告需要选择的商品
     *
     * @return \think\mixed
     */
    public function search_goods()
    {
        $goods_id = I('goods_id/d');
        $brand_id = I('brand_id/d');
        $keywords = I('keywords');
        $goods_id = I('goods_id');
        $cat_id = I('cat_id/d');
        $intro = input('intro'); //推荐/新品

        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();

        $where = ['is_on_sale' => 1,
            'prom_type' => 0,
            'is_virtual' => 0,
            'store_count' => ['gt', 0],
        ];  //搜索条件

        if (!empty($goods_id)) {
            $where['goods_id'] = ['notin', $goods_id];
        }

        if ($cat_id) {
            $this->assign('cat_id', $cat_id);
            $grandson_ids = getCatGrandson($cat_id);
            $where['cat_id'] = ['in', implode(',', $grandson_ids)];
        }

        if ($brand_id) {
            $this->assign('brand_id', $brand_id);
            $where['brand_id'] = $brand_id;
        }
        if ($keywords) {
            $this->assign('keywords', $keywords);
            $where['goods_name|keywords'] = ['like', '%' . $keywords . '%'];
        }
        if ($intro) {
            $where[I('intro')] = 1;
        }
        $Goods = new Goods();
        $count = $Goods->where($where)->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->where($where)->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show(); //分页显示输出
        $this->assign('page', $show); //赋值分页输出
        $this->assign('goodsList', $goodsList);
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);

        return $this->fetch();
    }

    public function changeAdField()
    {
        $field = $this->request->request('field');
        $data[$field] = I('get.value');
        $data['ad_id'] = I('get.ad_id');
        M('ad')->save($data); // 根据条件保存修改的数据
    }

    public function ad_app_home()
    {
        return $this->fetch();
    }

    /**
     * 编辑广告中转方法.
     */
    public function editAd()
    {
        $img_url = I('img_url');
        $pid = I('pid/d', 0);
        \think\Cache::clear();
        $request_url = I('request_url');
        //缓存请求的编辑广告URL
        session('ad_request_url', $request_url);
        $request_url = urldecode(I('request_url'));
        $request_url = urldecode($request_url);
        $request_url = U($request_url, ['edit_ad' => 1, 'img_url' => $img_url, 'pid' => $pid]);

        echo "<script>location.href='" . $request_url . "';</script>";
        exit;
    }

    /**
     * 活动弹窗列表
     * @return mixed
     */
    public function popupList()
    {
        $where = [];
        $selectPopupType = I('popup_type', 0);
        if ($selectPopupType) {
            $where['type'] = $selectPopupType;
        }
        $popup = new PopupModel();
        $count = $popup->count('id');
        $page = new Page($count, 10);
        $popupList = $popup->where($where)->order('sort desc, end_time asc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('page', $page);
        $this->assign('select_popup_type', $selectPopupType);
        $this->assign('popup_type', $popup->getType());
        $this->assign('list', $popupList);
        return $this->fetch('popup_list');
    }

    /**
     * 活动弹窗信息
     * @return mixed
     */
    public function popupInfo()
    {
        $id = I('id', '');
        if (request()->isPost()) {
            $data = I('post.');
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $couponValidate = Loader::validate('Ad');
            if (!$couponValidate->scene('popup')->batch()->check($data)) {
                $msg = '';
                foreach ($couponValidate->getError() as $value) {
                    $msg .= $value . ',';
                }
                $this->error('操作失败，' . rtrim($msg, ','));
            }
            switch ($data['type']) {
                case 9:
                    // 商品
                    $goodsId = M('goods')->where(['goods_sn' => $data['goods_sn']])->value('goods_id');
                    if (empty($goodsId)) {
                        $this->ajaxReturn(['status' => 0, 'msg' => '商品不存在']);
                    }
                    $data['type_id'] = $goodsId;
                    $data['item_id'] = 0;
                    unset($data['goods_sn']);
                    break;
                    break;
            }
            unset($data['id']);
            if ($id) {
                M('popup')->where(['id' => $id])->update($data);
            } else {
                M('popup')->add($data);
            }
            $this->success('操作成功', U('Admin/Ad/popupList'));
        }
        $popup = M('popup')->where(['id' => $id])->find();
        if ($popup) {
            $popup['start_time'] = date('Y-m-d H:i:s', $popup['start_time']);
            $popup['end_time'] = date('Y-m-d H:i:s', $popup['end_time']);
            if ($popup['type'] == 9) {
                $popup['goods_sn'] = M('goods')->where(['goods_id' => $popup['type_id']])->value('goods_sn');
            } else {
                $popup['goods_sn'] = '';
            }
        }
        $this->assign('popup_info', $popup);
        $this->assign('start_time', date('Y-m-d H:i:s', time()));
        $this->assign('end_time', date('Y-m-d H:i:s', strtotime('+1 week')));
        return $this->fetch('popup_info');
    }

    /**
     * 删除活动弹窗
     */
    public function popupDel()
    {
        $id = I('id', '');
        M('popup')->where(['id' => $id])->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }
}