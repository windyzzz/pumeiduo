<?php

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use app\admin\model\Goods as GoodsModel;
use app\admin\model\PromActivityItem;
use app\common\model\CateActivity;
use app\common\model\CateActivityGoods;
use think\Db;
use think\Loader;
use think\Page;

class Activity extends Base
{
    /**
     * 分类主题活动列表
     * @return mixed
     */
    public function cate_activity_list()
    {
        $activity = new CateActivity();
        $count = $activity->count();
        $Page = new Page($count, 10);
        $activity_list = $activity->order('start_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page', $Page);
        $this->assign('activity_list', $activity_list);
        return $this->fetch();
    }

    /**
     * 分类主题活动信息
     * @return mixed
     */
    public function cate_activity_info()
    {
        $level = M('distribut_level')->select();
        $this->assign('level', $level);
        $activity_id = I('id');
        if ($activity_id > 0) {
            $info = M('cate_activity')->where(array('id' => $activity_id))->find();
            $activity_goods = M('cate_activity_goods')->where(array('cate_act_id' => $activity_id))->select();
            foreach ($activity_goods as $k => $v) {
                $activity_goods[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
                if ($v['item_id']) {
                    $activity_goods[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
                }
            }
            $this->assign('activity_goods', $activity_goods);
            $info['start_time'] = date('Y-m-d H:i:s', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i:s', $info['end_time']);
        } else {
            $info['start_time'] = date('Y-m-d H:i:s');
            $info['end_time'] = date('Y-m-d H:i:s', time() + 3600 * 60 * 24);
        }
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * 新建/更新分类主题活动信息
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function cate_activity_save()
    {
        $data = I('post.');
        $activityId = $data['id'];
        // 验证
        $orderPromValidate = Loader::validate('CateActivity');
        if (!$orderPromValidate->batch()->check($data)) {
            $msg = '';
            foreach ($orderPromValidate->getError() as $item) {
                $msg .= $item . '，';
            }
            $return = ['status' => 0, 'msg' => rtrim($msg, '，')];
            $this->ajaxReturn($return);
        }
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($activityId) {
            // 编辑
            Db::name('cate_activity')->where(['id' => $activityId])->update($data);
            Db::name('cate_activity_goods')->where(['cate_act_id' => $activityId])->delete();   // 删除旧商品数据
        } else {
            // 新增
            $activityId = Db::name('cate_activity')->add($data);
        }
        // 活动商品
        $actGoods = [];
        foreach ($data['goods'] as $value) {
            if (isset($value['item_id'])) {
                foreach ($value['item_id'] as $item) {
                    $actGoods[] = [
                        'cate_act_id' => $activityId,
                        'goods_id' => $value['goods_id'],
                        'item_id' => $item
                    ];
                }
            } else {
                $actGoods[] = [
                    'cate_act_id' => $activityId,
                    'goods_id' => $value['goods_id'],
                    'item_id' => 0
                ];
            }
        }
        $cateActGoods = new CateActivityGoods();
        $cateActGoods->saveAll($actGoods);
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
    }

    /**
     * 删除活动
     */
    public function cate_activity_del()
    {
        $activityId = I('id');
        $cateActivity = Db::name('cate_activity')->where(['id' => $activityId])->find();
        if (empty($activityId)) {
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
        }
        if ($cateActivity['is_open'] == 1) {
            $this->ajaxReturn(['status' => 0, 'msg' => '活动正在开启']);
        }
        Db::name('cate_activity')->where(['id' => $activityId])->delete();
        Db::name('cate_activity_goods')->where(['cate_act_id' => $activityId])->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
    }

    /**
     * 商品搜索
     * @return mixed
     */
    public function search_goods()
    {
        $goods_id = input('goods_id');
        $intro = input('intro');
        $cat_id = input('cat_id');
        $brand_id = input('brand_id');
        $keywords = input('keywords');
        $tpl = input('tpl', 'search_goods');
        $where = ['store_count' => ['gt', 0], 'is_virtual' => 0, 'is_area_show' => 1];
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
        $Goods = new GoodsModel();
        $count = $Goods->where($where)->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->where($where)->with('specGoodsPrice')->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $types = I('types', 1);
        $this->assign('types', $types);

        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);

        return $this->fetch($tpl);
    }

    /**
     * 促销活动配置
     * @return mixed
     */
    public function config()
    {
        if (IS_POST) {
            $data = I('post.');
            if (!empty($data['index_banner']['img'])) {
                $imgInfo = getimagesize(PUBLIC_PATH . substr($data['index_banner']['img'], strrpos($data['index_banner']['img'], 'public') + 7));
                if (empty($imgInfo)) {
                    $this->error('上传首页banner发生错误');
                }
                $data['index_banner'] = json_encode([
                    'img' => $data['index_banner']['img'],
                    'width' => $imgInfo[0],
                    'height' => $imgInfo[1],
                    'type' => substr($imgInfo['mime'], strrpos($imgInfo['mime'], '/') + 1),
                ]);
            }
            if (!empty($data['inside_banner']['img'])) {
                $imgInfo = getimagesize(PUBLIC_PATH . substr($data['inside_banner']['img'], strrpos($data['inside_banner']['img'], 'public') + 7));
                if (empty($imgInfo)) {
                    $this->error('上传内页banner发生错误');
                }
                $data['inside_banner'] = json_encode([
                    'img' => $data['inside_banner']['img'],
                    'width' => $imgInfo[0],
                    'height' => $imgInfo[1],
                    'type' => substr($imgInfo['mime'], strrpos($imgInfo['mime'], '/') + 1),
                ]);
            }
            M('prom_activity_config')->where('1=1')->delete();
            M('prom_activity_config')->add($data);
            $this->success('配置成功');
        }
        $config = M('prom_activity_config')->find();
        if (!empty($config['index_banner'])) {
            $config['index_banner'] = json_decode($config['index_banner'], true);
        }
        if (!empty($config['inside_banner'])) {
            $config['inside_banner'] = json_decode($config['inside_banner'], true);
        }
        $this->assign('config', $config);
        return $this->fetch();
    }

    /**
     * 板块1
     * @return mixed
     */
    public function module1()
    {
        $res = $this->getModule(1);
        $this->assign('module_type', 1);
        $this->assign('activity', $res['activity']);
        $this->assign('activity_item', $res['activity_item']);
        return $this->fetch('module');
    }

    /**
     * 板块2
     * @return mixed
     */
    public function module2()
    {
        $res = $this->getModule(2);
        $activityItem = [];
        foreach ($res['activity_item'] as $k => $v) {
            $activityItem[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
            if ($v['item_id']) {
                $activityItem[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
            }
        }
        $this->assign('module_type', 2);
        $this->assign('activity', $res['activity']);
        $this->assign('activity_item', $activityItem);
        return $this->fetch('module');
    }

    /**
     * 板块3
     * @return mixed
     */
    public function module3()
    {
        $res = $this->getModule(3);
        $activityItem = [];
        foreach ($res['activity_item'] as $k => $v) {
            $activityItem[$k] = M('Goods')->where('goods_id=' . $v['goods_id'])->find();
            if ($v['item_id']) {
                $activityItem[$k]['SpecGoodsPrice'] = M('SpecGoodsPrice')->where(['item_id' => $v['item_id']])->find();
            }
        }
        $this->assign('module_type', 3);
        $this->assign('activity', $res['activity']);
        $this->assign('activity_item', $activityItem);
        return $this->fetch('module');
    }

    private function getModule($type)
    {
        $promActivity = M('prom_activity')->where(['module_type' => $type])->find();
        if (empty($promActivity)) return ['activity' => [], 'activity_item' => []];
        $activityItem = M('prom_activity_item')->where(['activity_id' => $promActivity['id']])->select();
        return ['activity' => $promActivity, 'activity_item' => $activityItem];
    }

    public function saveModule()
    {
        $data = I('post.');
        $moduleType = $data['module_type'];
        $activityId = $data['activity_id'];
        $actData = [
            'module_type' => $moduleType,
            'title' => $data['title'],
            'is_open' => $data['is_open']
        ];
        if ($activityId) {
            M('prom_activity')->where(['id' => $activityId])->update($actData);
        } else {
            $activityId = M('prom_activity')->add($actData);
        }
        M('prom_activity_item')->where(['activity_id' => $activityId])->delete();
        switch ($moduleType) {
            case 1:
                $itemData = [];
                if (isset($data['item']['coupon_id'])) {
                    foreach ($data['item']['coupon_id'] as $item) {
                        $itemData[] = [
                            'activity_id' => $activityId,
                            'coupon_id' => $item
                        ];
                    }
                }
                (new PromActivityItem())->saveAll($itemData);
                break;
            case 2:
            case 3:
                $itemData = [];
                if (isset($data['item'])) {
                    foreach ($data['item'] as $item) {
                        $itemData[] = [
                            'activity_id' => $activityId,
                            'goods_id' => $item['goods_id'],
                            'item_id' => $item['item_id'] ?? 0
                        ];
                    }
                }
                (new PromActivityItem())->saveAll($itemData);
                break;
        }
        $this->success('设置成功', U('Admin/Activity/module' . $moduleType));
    }
}