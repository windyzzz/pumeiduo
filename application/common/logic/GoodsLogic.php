<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\logic;

use app\common\logic\Pay as PayLogic;
use app\common\logic\supplier\GoodsService;
use app\common\model\Goods;
use app\common\util\TpshopException;
use think\Db;
use think\Model;

/**
 * 分类逻辑定义
 * Class CatsLogic.
 */
class GoodsLogic extends Model
{
    /**
     * @param $goods_id_arr
     * @param $filter_param
     * @param $action
     *
     * @return array|mixed 这里状态一般都为1 result 不是返回数据 就是空
     *                     获取 商品列表页帅选品牌
     */
    public function get_filter_brand($goods_id_arr, $filter_param, $action)
    {
        if (!empty($filter_param['brand_id'])) {
            return [];
        }

        $map['goods_id'] = ['in', $goods_id_arr];
        $map['brand_id'] = ['>', 0];
        $brand_id_arr = M('goods')->where($map)->column('brand_id');
        $list_brand = M('brand')->where('id', 'in', $brand_id_arr)->limit('30')->select();

        foreach ($list_brand as $k => $v) {
            // 帅选参数
            $filter_param['brand_id'] = $v['id'];
            $list_brand[$k]['href'] = urldecode(U("Goods/$action", $filter_param, ''));
        }

        return $list_brand;
    }

    /**
     * @param $goods_id_arr
     * @param $filter_param
     * @param $action
     * @param int $mode 0  返回数组形式  1 直接返回result
     *
     * @return array 这里状态一般都为1 result 不是返回数据 就是空
     *               获取 商品列表页帅选规格
     */
    public function get_filter_spec($goods_id_arr, $filter_param, $action, $mode = 0)
    {
        $goods_id_str = implode(',', $goods_id_arr);
        $goods_id_str = $goods_id_str ? $goods_id_str : '0';
        $spec_key = DB::query("select group_concat(`key` separator  '_') as `key` from __PREFIX__spec_goods_price where goods_id in($goods_id_str)");  //where("goods_id in($goods_id_str)")->select();
        $spec_key = explode('_', $spec_key[0]['key']);
        $spec_key = array_unique($spec_key);
        $spec_key = array_filter($spec_key);

        if (empty($spec_key)) {
            if (1 == $mode) {
                return [];
            }

            return ['status' => 1, 'msg' => '', 'result' => []];
        }
        $spec = M('spec')->where(['search_index' => 1])->getField('id,name');
        $spec_item = M('spec_item')->where(['spec_id' => ['in', array_keys($spec)]])->getField('id,spec_id,item');

        $list_spec = [];
        $old_spec = $filter_param['spec'];
        foreach ($spec_key as $k => $v) {
            if (0 === strpos($old_spec, $spec_item[$v]['spec_id'] . '_') || strpos($old_spec, '@' . $spec_item[$v]['spec_id'] . '_') || '' == $spec_item[$v]['spec_id']) {
                continue;
            }
            $list_spec[$spec_item[$v]['spec_id']]['spec_id'] = $spec_item[$v]['spec_id'];
            $list_spec[$spec_item[$v]['spec_id']]['name'] = $spec[$spec_item[$v]['spec_id']];
            //$list_spec[$spec_item[$v]['spec_id']]['item'][$v] = $spec_item[$v]['item'];

            // 帅选参数
            if (!empty($old_spec)) {
                $filter_param['spec'] = $old_spec . '@' . $spec_item[$v]['spec_id'] . '_' . $v;
            } else {
                $filter_param['spec'] = $spec_item[$v]['spec_id'] . '_' . $v;
            }
            $list_spec[$spec_item[$v]['spec_id']]['item'][] = ['key' => $spec_item[$v]['spec_id'], 'val' => $v, 'item' => $spec_item[$v]['item'], 'href' => urldecode(U("Goods/$action", $filter_param, ''))];
        }

        if (1 == $mode) {
            return $list_spec;
        }

        return ['status' => 1, 'msg' => '', 'result' => $list_spec];
    }

    /**
     * @param array $goods_id_arr
     * @param $filter_param
     * @param $action
     * @param int $mode 0  返回数组形式  1 直接返回result
     *
     * @return array
     *               获取商品列表页帅选属性
     */
    public function get_filter_attr($goods_id_arr = [], $filter_param, $action, $mode = 0)
    {
        $goods_id_str = implode(',', $goods_id_arr);
        $goods_id_str = $goods_id_str ? $goods_id_str : '0';
        $goods_attr = M('goods_attr')->where(['goods_id' => ['in', $goods_id_str], 'attr_value' => ['<>', '']])->select();
        // $goods_attr = M('goods_attr')->where("attr_value != ''")->select();
        $goods_attribute = M('goods_attribute')->where('attr_index = 1')->getField('attr_id,attr_name,attr_index');
        if (empty($goods_attr)) {
            if (1 == $mode) {
                return [];
            }

            return ['status' => 1, 'msg' => '', 'result' => []];
        }
        $list_attr = $attr_value_arr = [];
        $old_attr = $filter_param['attr'];
        foreach ($goods_attr as $k => $v) {
            // 存在的帅选不再显示
            if (0 === strpos($old_attr, $v['attr_id'] . '_') || strpos($old_attr, '@' . $v['attr_id'] . '_')) {
                continue;
            }
            if (0 == $goods_attribute[$v['attr_id']]['attr_index']) {
                continue;
            }
            $v['attr_value'] = trim($v['attr_value']);
            // 如果同一个属性id 的属性值存储过了 就不再存贮
            if (in_array($v['attr_id'] . '_' . $v['attr_value'], (array)$attr_value_arr[$v['attr_id']])) {
                continue;
            }
            $attr_value_arr[$v['attr_id']][] = $v['attr_id'] . '_' . $v['attr_value'];

            $list_attr[$v['attr_id']]['attr_id'] = $v['attr_id'];
            $list_attr[$v['attr_id']]['attr_name'] = $goods_attribute[$v['attr_id']]['attr_name'];

            // 帅选参数
            if (!empty($old_attr)) {
                $filter_param['attr'] = $old_attr . '@' . $v['attr_id'] . '_' . $v['attr_value'];
            } else {
                $filter_param['attr'] = $v['attr_id'] . '_' . $v['attr_value'];
            }

            $list_attr[$v['attr_id']]['attr_value'][] = ['key' => $v['attr_id'], 'val' => $v['attr_value'], 'attr_value' => $v['attr_value'], 'href' => U("Goods/$action", $filter_param, '')];
            //unset($filter_param['attr_id_'.$v['attr_id']]);
        }
        if (1 == $mode) {
            return $list_attr;
        }

        return ['status' => 1, 'msg' => '', 'result' => $list_attr];
    }

    /**
     * 获取某个商品的评论统计
     * c0:全部评论数  c1:好评数 c2:中评数  c3差评数
     * rate1:好评率 rate2:中评率  c3差评率.
     *
     * @param $goods_id
     *
     * @return array
     */
    public function commentStatistics($goods_id)
    {
        $commentWhere = ['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => 0, 'user_id' => ['gt', 0]];
        $c1 = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (4,5)')->count();
        $c2 = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (3)')->count();
        $c3 = M('comment')->where($commentWhere)->where('ceil((deliver_rank + goods_rank + service_rank) / 3) in (0,1,2)')->count();
        $c4 = M('comment')->where($commentWhere)->where("img !='' and img NOT LIKE 'N;%'")->count(); // 晒图
        $c0 = $c1 + $c2 + $c3; // 所有评论
        if ($c0 <= 0) {
            $rate1 = 100;
            $rate2 = 0;
            $rate3 = 0;
        } else {
            $rate1 = ceil($c1 / $c0 * 100); // 好评率
            $rate2 = ceil($c2 / $c0 * 100); // 中评率
            $rate3 = ceil($c3 / $c0 * 100); // 差评率
        }

        return ['c0' => $c0, 'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4, 'rate1' => $rate1, 'rate2' => $rate2, 'rate3' => $rate3];
    }

    /**
     * 商品收藏.
     *
     * @param $user_id |用户id
     * @param $goods_id |商品id
     *
     * @return array
     */
    public function collect_goods($user_id, $goods_id)
    {
        if (!is_numeric($user_id) || $user_id <= 0) {
            return ['status' => -1, 'msg' => '必须登录后才能收藏', 'result' => []];
        }
        $count = Db::name('goods_collect')->where('user_id', $user_id)->where('goods_id', $goods_id)->count();
        if ($count > 0) {
            return ['status' => -3, 'msg' => '商品已收藏', 'result' => []];
        }
        $goods_price = M('Goods')->where('goods_id', $goods_id)->getField('shop_price');
//        Db::name('goods')->where('goods_id', $goods_id)->setInc('collect_sum');
        Db::name('goods_collect')->add(['goods_id' => $goods_id, 'user_id' => $user_id, 'add_time' => time(), 'goods_price' => $goods_price]);

        return ['status' => 1, 'msg' => '收藏成功!请到个人中心查看', 'result' => []];
    }

    /**
     * 批量收藏商品
     * @param $userId
     * @param $goodsIds
     * @return array
     */
    public function collect_goods_arr($userId, $goodsIds)
    {
        // 商品是否有收藏
        $collected = Db::name('goods_collect')->alias('gc')->join('goods g', 'g.goods_id = gc.goods_id')
            ->where(['gc.user_id' => $userId, 'gc.goods_id' => ['in', $goodsIds]])
            ->field('g.goods_name')->select();
        if (!empty($collected)) {
            $collectedGoods = '';
            foreach ($collected as $item) {
                $collectedGoods .= $item['goods_name'] . ',';
            }
            return ['status' => -1, 'msg' => rtrim($collectedGoods, ',') . ' 已收藏', 'result' => ''];
        }
        // 收藏商品
        $goodsPrice = Db::name('goods')->where(['goods_id' => ['in', $goodsIds]])->field('goods_id, shop_price')->select();
        $data = [];
        foreach ($goodsPrice as $item) {
            $data[] = [
                'goods_id' => $item['goods_id'],
                'user_id' => $userId,
                'add_time' => time(),
                'goods_price' => $item['shop_price'],
            ];
        }
        Db::name('goods_collect')->insertAll($data);
//        // 商品被收藏次数+1
//        Db::name('goods')->where(['goods_id' => ['in', $goodsIds]])->setInc('collect_sum');
        return ['status' => 1, 'msg' => '收藏成功！', 'result' => ''];
    }

    /**
     * 获取商品规格
     *
     * @param $goods_id |商品id
     *
     * @return array
     */
    public function get_spec($goods_id)
    {
        //商品规格 价钱 库存表 找出 所有 规格项id
        $keys = M('SpecGoodsPrice')->where('goods_id', $goods_id)->getField("GROUP_CONCAT(`key` ORDER BY store_count desc SEPARATOR '_') ");
        $filter_spec = [];
        if ($keys) {
            $specImage = M('SpecImage')->where(['goods_id' => $goods_id, 'src' => ['<>', '']])->getField('spec_image_id,src'); // 规格对应的 图片表， 例如颜色
            $keys = str_replace('_', ',', $keys);
            $sql = "SELECT a.name,a.order,b.* FROM __PREFIX__spec AS a INNER JOIN __PREFIX__spec_item AS b ON a.id = b.spec_id WHERE b.id IN($keys) ORDER BY b.id";
            $filter_spec2 = \think\Db::query($sql);
            foreach ($filter_spec2 as $key => $val) {
                $filter_spec[$val['name']][] = [
                    'item_id' => $val['id'],
                    'item' => $val['item'],
                    'src' => $specImage[$val['id']],
                ];
            }
        }

        return $filter_spec;
    }

    /**
     * 获取商品规格（新）
     * @param $goods_id
     * @param $itemId
     * @return array
     */
    public function get_spec_new($goods_id, $itemId = null)
    {
        $itemKey = '';
        if ($itemId) {
            $itemKey = Db::name('spec_goods_price')->where(['item_id' => $itemId])->value('key');
            $itemKey = explode('_', $itemKey);
        }
        $keys = Db::name('spec_goods_price')->where('goods_id', $goods_id)->getField("GROUP_CONCAT(`key` ORDER BY store_count desc SEPARATOR '_') ");;
        $keys = array_unique(explode('_', $keys));
        $specItem = Db::name('spec_item')->alias('si')->join('spec s', 's.id = si.spec_id')
            ->where(['si.id' => ['in', $keys]])->field('si.id, si.item, si.spec_id, s.name')->select();
        $specImage = M('SpecImage')->where(['goods_id' => $goods_id, 'src' => ['<>', '']])->getField('spec_image_id,src');
        // 处理数据
        $specData = [];
        foreach ($specItem as $value) {
            if (!empty($itemKey) && in_array($value['id'], $itemKey)) {
                $isDefault = 1;
            } else {
                $isDefault = 0;
            }
            $specData[$value['spec_id']]['type'] = $value['name'];
            $specData[$value['spec_id']]['type_value'][] = [
                'item_id' => $value['id'],
                'item' => $value['item'],
                'src' => $specImage[$value['id']] ?? '',
                'is_default' => $isDefault,
                'can_select' => 1   // 能否被选
            ];
        }
        // 给定默认选中规格
        if ($itemId) {
            $itemKey = implode('_', $itemKey);
        } else {
            foreach ($specData as $k1 => $value) {
                foreach ($value['type_value'] as $k2 => $item) {
                    if ($k2 == 0) {
                        $itemKey .= $item['item_id'] . '_';
                        $specData[$k1]['type_value'][$k2]['is_default'] = 1;
                        break;
                    }
                }
            }
            $itemKey = rtrim($itemKey, '_');
        }
        return ['spec' => array_values($specData), 'default_key' => $itemKey];
    }

    /**
     * 获取供应链商品规格属性
     * @param $goods_id
     * @param null $itemId
     * @return array
     */
    public function get_supply_spec($goods_id, $itemId = null)
    {
        $itemKey = '';
        if ($itemId) {
            $itemKey = Db::name('spec_goods_price')->where(['item_id' => $itemId])->value('key');
            $itemKey = explode('_', $itemKey);
        }
        // 规格信息
        $specGoodsPrice = M('spec_goods_price')->where('goods_id', $goods_id)->select();
        if (empty($specGoodsPrice)) {
            return [];
        }
        // 规格标识
        $goodsSpec = M('supplier_goods_spec')->where(['supplier_id' => 1])->getField('spec_id, name', true);
        // 整合规格信息
        $specData = [];
        foreach ($specGoodsPrice as $specPrice) {
            $spec = explode('_', $specPrice['supplier_goods_spec']);
            $key = explode('_', $specPrice['key']);
            $keyName = explode(',', $specPrice['key_name']);
            // 组合规格
            $specKey = [];
            $count = count($key);
            for ($a = 0; $a < $count; $a++) {
                if (!empty($itemKey) && in_array($key[$a], $itemKey)) {
                    $isDefault = 1;
                } else {
                    $isDefault = 0;
                }
                $specKey[] = [
                    'item_id' => $key[$a],
                    'item' => $keyName[$a],
                    'is_default' => $isDefault,
                    'can_select' => 1,
                ];
            }
            // 组合规格标识
            foreach ($spec as $k => $v) {
                if (!isset($specData[$v])) {
                    if (isset($goodsSpec[$v])) {
                        $type = C('SUPPLIER_GOODS_SPEC')[$goodsSpec[$v]] ?? '规格';
                    } else {
                        $type = '规格';
                    }
                    $specData[$v] = [
                        'type' => $type,
                        'type_value' => []
                    ];
                }
                $specData[$v]['type_value'][$specKey[$k]['item_id']] = $specKey[$k];
            }
        }
        foreach ($specData as &$spec) {
            $spec['type_value'] = array_values($spec['type_value']);
        }
        // 给定默认选中规格
        if ($itemId) {
            $itemKey = implode('_', $itemKey);
        } else {
            foreach ($specData as $k1 => $value) {
                foreach ($value['type_value'] as $k2 => $item) {
                    if ($k2 == 0) {
                        $itemKey .= $item['item_id'] . '_';
                        $specData[$k1]['type_value'][$k2]['is_default'] = 1;
                        break;
                    }
                }
            }
            $itemKey = rtrim($itemKey, '_');
        }
        return ['spec' => array_values($specData), 'default_key' => $itemKey];
    }

    /**
     * 获取商品规格价格
     * @param $goods_id
     * @return mixed
     */
    public function get_spec_price($goods_id)
    {
        return M('spec_goods_price')->where('goods_id', $goods_id)->getField('key,item_id,price,store_count,spec_img'); // 规格 对应 价格 库存表
    }

    /**
     * 获取相关分类.
     *
     * @param $cat_id |分类id
     *
     * @return array|false|mixed|\PDOStatement|string|\think\Collection
     */
    public function get_siblings_cate($cat_id)
    {
        if (empty($cat_id)) {
            return [];
        }
        $cate_info = M('goods_category')->where('id', $cat_id)->find();
        $siblings_cate = M('goods_category')->where(['id' => ['<>', $cat_id], 'parent_id' => $cate_info['parent_id']])
            ->getField('parent_id_path,id,name,mobile_name,parent_id,parent_id_path,level,sort_order,is_show,image,is_hot,cat_group,commission_rate');

        return empty($siblings_cate) ? [] : $siblings_cate;
    }

    /**
     * 看了又看.
     */
    public function get_look_see($goods = [], $userId = null)
    {
        $where = [
            'is_on_sale' => 1,
            'zone' => 1
        ];
        if ($userId) {
            $where['gv.user_id'] = $userId;
        } else {
            $where['gv.user_id'] = cookie('user_id');
        }
        if (!empty($goods)) {
            $where['g.goods_id'] = ['not in', $goods['goods_id']];
        }

        $goods_list = [];
        if ($where['gv.user_id']) {
            $take_goods_list = M('goods')
                ->field('g.goods_id, g.cat_id, g.goods_name, g.goods_remark, g.original_img, g.shop_price, g.exchange_integral, g.sale_type')
                ->alias('g')
                ->join('__GOODS_VISIT__ gv', 'gv.goods_id = g.goods_id', 'LEFT')
                ->where($where)
                ->order('gv.visit_id desc')
                ->limit(20)
                ->select();
            shuffle($take_goods_list);
            if (count($take_goods_list) > 4) {
                for ($i = 0; $i < 4; ++$i) {
                    $goods_list[] = $take_goods_list[$i];
                }
                foreach ($goods_list as $k => $v) {
                    // 缩略图
                    $goods_list[$k]['original_img_new'] = getFullPath($v['original_img']);
                    // 处理显示金额
                    if ($v['exchange_integral'] != 0) {
                        $goods_list[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
                    } else {
                        $goods_list[$k]['exchange_price'] = $v['shop_price'];
                    }
                }
            }
        }
        if ($goods_list) {
            return $goods_list;
        }

        $countcus = Db::name('goods')->count();
        $min = Db::name('goods')->min('goods_id');
        $num = 4;
        if ($countcus < $num) {
            $num = $countcus;
        }
        $i = 1;
        $flag = 0;
        $ary = [];
        while ($i <= $num) {
            $rundnum = rand($min, $countcus); //抽取随机数
            if ($flag != $rundnum) {
                //过滤重复
                if (!in_array($rundnum, $ary)) {
                    $ary[] = $rundnum;
                    $flag = $rundnum;
                } else {
                    --$i;
                }
                ++$i;
            }
        }

        $where = [
            'is_on_sale' => 1,
            'zone' => 1
        ];
        if (!empty($goods)) {
            $where['goods_id'] = ['not in', $goods['goods_id']];
        }
        $goods_list = M('goods')
            ->field('goods_id, cat_id, goods_name, goods_remark, original_img, shop_price, exchange_integral, sale_type')
            ->where($where)
            ->where('goods_id', 'in', $ary)
            ->select();
        foreach ($goods_list as $k => $v) {
            // 缩略图
            $goods_list[$k]['original_img_new'] = getFullPath($v['original_img']);
            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $goods_list[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
            } else {
                $goods_list[$k]['exchange_price'] = $v['shop_price'];
            }
        }
        return $goods_list;
    }

    /**
     * 猜你喜欢
     * @param $filterGoods
     * @param $userId
     * @return mixed
     */
    public function get_look_see_v2($filterGoods, $userId = null)
    {
        $goodsList = M('goods g')->field('g.goods_id, g.cat_id, g.goods_name, g.goods_remark, g.original_img, g.shop_price, g.exchange_integral, g.sale_type');
        $where = [];
        if (!empty($filterGoods)) {
            $where = [
                'g.goods_id' => ['NEQ', $filterGoods['goods_id']],
                'g.cat_id' => $filterGoods['cat_id']
            ];
        }
        if ($userId) {
            $goodsList = $goodsList->join('goods_visit gv', 'gv.goods_id = g.goods_id', 'LEFT')->group('g.goods_id');
        }
        $count = $goodsList->where($where)->count();
        if ($count == 0) {
            $count = $goodsList->count();
            $offset = rand(0, $count);
            $goodsList = $goodsList->limit($offset, 4)->select();
        } else {
            if ($count < 4) {
                $offset = 0;
            } else {
                $offset = rand(0, $count - 4);
            }
            $goodsList = $goodsList->where($where)->limit($offset, 4)->select();
        }
        foreach ($goodsList as $k => $v) {
            // 缩略图
            $goodsList[$k]['original_img_new'] = getFullPath($v['original_img']);
            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $goodsList[$k]['exchange_price'] = bcdiv(bcsub(bcmul($v['shop_price'], 100), bcmul($v['exchange_integral'], 100)), 100, 2);
            } else {
                $goodsList[$k]['exchange_price'] = $v['shop_price'];
            }
        }
        return $goodsList;
    }

    /**
     * 筛选的价格期间.
     *
     * @param $goods_id_arr |帅选的分类id
     * @param $filter_param
     * @param $action
     * @param int $c 分几段 默认分5 段
     *
     * @return array
     */
    public function get_filter_price($goods_id_arr, $filter_param, $action, $c = 5)
    {
        if (!empty($filter_param['price'])) {
            return [];
        }

        $goods_id_str = implode(',', $goods_id_arr);
        $goods_id_str = $goods_id_str ? $goods_id_str : '0';
        $priceList = M('goods')->where('goods_id', 'in', $goods_id_str)->getField('shop_price', true);  //where("goods_id in($goods_id_str)")->select();
        rsort($priceList);
        $max_price = (int)$priceList[0];

        $psize = ceil($max_price / $c); // 每一段累积的价钱
        $parr = [];
        for ($i = 0; $i < $c; ++$i) {
            $start = $i * $psize;
            $end = $start + $psize;

            // 如果没有这个价格范围的商品则不列出来
            $in = false;
            foreach ($priceList as $k => $v) {
                if ($v > $start && $v < $end) {
                    $in = true;
                }
            }
            if (false == $in) {
                continue;
            }

            $filter_param['price'] = "{$start}-{$end}";
            if (0 == $i) {
                $parr[] = ['value' => "{$end}元以下", 'href' => urldecode(U("Goods/$action", $filter_param, ''))];
            } elseif ($i == ($c - 1) && ($max_price > $end)) {
                $parr[] = ['value' => "{$end}元以上", 'href' => urldecode(U("Goods/$action", $filter_param, ''))];
            } else {
                $parr[] = ['value' => "{$start}-{$end}元", 'href' => urldecode(U("Goods/$action", $filter_param, ''))];
            }
        }

        return $parr;
    }

    /**
     * 筛选条件菜单.
     *
     * @param $filter_param
     * @param $action
     *
     * @return array
     */
    public function get_filter_menu($filter_param, $action)
    {
        $menu_list = [];
        // 品牌
        if (!empty($filter_param['brand_id'])) {
            $brand_list = M('brand')->getField('id,name');
            $brand_id = explode('_', $filter_param['brand_id']);
            $brand['text'] = '品牌:';
            foreach ($brand_id as $k => $v) {
                $brand['text'] .= $brand_list[$v] . ',';
            }
            $brand['text'] = substr($brand['text'], 0, -1);
            $tmp = $filter_param;
            unset($tmp['brand_id']); // 当前的参数不再带入
            $brand['href'] = urldecode(U("Goods/$action", $tmp, ''));
            $menu_list[] = $brand;
        }
        // 规格
        if (!empty($filter_param['spec'])) {
            $spec = M('spec')->getField('id,name');
            $spec_item = M('spec_item')->getField('id,item');
            $spec_group = explode('@', $filter_param['spec']);
            foreach ($spec_group as $k => $v) {
                $spec_group2 = explode('_', $v);
                $spec_menu['text'] = $spec[$spec_group2[0]] . ':';
                array_shift($spec_group2); // 弹出第一个规格名称
                foreach ($spec_group2 as $k2 => $v2) {
                    $spec_menu['text'] .= $spec_item[$v2] . ',';
                }
                $spec_menu['text'] = substr($spec_menu['text'], 0, -1);

                $tmp = $spec_group;
                $tmp2 = $filter_param;
                unset($tmp[$k]);
                $tmp2['spec'] = implode('@', $tmp); // 当前的参数不再带入
                $spec_menu['href'] = urldecode(U("Goods/$action", $tmp2, ''));
                $menu_list[] = $spec_menu;
            }
        }
        // 属性
        if (!empty($filter_param['attr'])) {
            $goods_attribute = M('goods_attribute')->getField('attr_id,attr_name');
            $attr_group = explode('@', $filter_param['attr']);
            foreach ($attr_group as $k => $v) {
                $attr_group2 = explode('_', $v);
                $attr_menu['text'] = $goods_attribute[$attr_group2[0]] . ':';
                array_shift($attr_group2); // 弹出第一个规格名称
                foreach ($attr_group2 as $k2 => $v2) {
                    $attr_menu['text'] .= $v2 . ',';
                }
                $attr_menu['text'] = substr($attr_menu['text'], 0, -1);

                $tmp = $attr_group;
                $tmp2 = $filter_param;
                unset($tmp[$k]);
                $tmp2['attr'] = implode('@', $tmp); // 当前的参数不再带入
                $attr_menu['href'] = urldecode(U("Goods/$action", $tmp2, ''));
                $menu_list[] = $attr_menu;
            }
        }
        // 价格
        if (!empty($filter_param['price'])) {
            $price_menu['text'] = '价格:' . $filter_param['price'];
            unset($filter_param['price']);
            $price_menu['href'] = urldecode(U("Goods/$action", $filter_param, ''));
            $menu_list[] = $price_menu;
        }

        return $menu_list;
    }

    /**
     * 传入当前分类 如果当前是 2级 找一级
     * 如果当前是 3级 找2 级 和 一级.
     *
     * @param  $goodsCate
     */
    public function get_goods_cate(&$goodsCate)
    {
        if (empty($goodsCate)) {
            return [];
        }
        $cateAll = get_goods_category_tree();
        if (1 == $goodsCate['level']) {
            $cateArr = $cateAll[$goodsCate['id']]['tmenu'];
            $goodsCate['parent_name'] = $goodsCate['name'];
            $goodsCate['select_id'] = 0;
        } elseif (2 == $goodsCate['level']) {
            $cateArr = $cateAll[$goodsCate['parent_id']]['tmenu'];
            $goodsCate['parent_name'] = $cateAll[$goodsCate['parent_id']]['name']; //顶级分类名称
            $goodsCate['open_id'] = $goodsCate['id']; //默认展开分类
            $goodsCate['select_id'] = 0;
        } else {
            $parent = M('GoodsCategory')->where('id', $goodsCate['parent_id'])->order('`sort_order` desc')->find(); //父类
            $cateArr = $cateAll[$parent['parent_id']]['tmenu'];
            $goodsCate['parent_name'] = $cateAll[$parent['parent_id']]['name']; //顶级分类名称
            $goodsCate['open_id'] = $parent['id'];
            $goodsCate['select_id'] = $goodsCate['id']; //默认选中分类
        }
        return $cateArr;
    }

    /**
     * @param  $brand_id |帅选品牌id
     * @param  $price |帅选价格
     *
     * @return array|mixed
     */
    public function getGoodsIdByBrandPrice($brand_id, $price)
    {
        if (empty($brand_id) && empty($price)) {
            return [];
        }
        $brand_select_goods = $price_select_goods = [];
        if ($brand_id) { // 品牌查询
            $brand_id_arr = explode('_', $brand_id);
            $brand_select_goods = M('goods')->whereIn('brand_id', $brand_id_arr, 'or')->getField('goods_id', true);
        }
        if ($price) {// 价格查询
            $price = explode('-', $price);
            $price[0] = intval($price[0]);
            $price[1] = intval($price[1]);
            $price_where = " shop_price >= $price[0] and shop_price <= $price[1] ";
            $price_select_goods = M('goods')->where($price_where)->getField('goods_id', true);
        }
        if ($brand_select_goods && $price_select_goods) {
            $arr = array_intersect($brand_select_goods, $price_select_goods);
        } else {
            $arr = array_merge($brand_select_goods, $price_select_goods);
        }

        return $arr ? $arr : [];
    }

    /**
     * 根据规格 查找 商品id.
     *
     * @param $spec |规格
     *
     * @return array|\type
     */
    public function getGoodsIdBySpec($spec)
    {
        if (empty($spec)) {
            return [];
        }

        $spec_group = explode('@', $spec);
        $where = ' where 1=1 ';
        foreach ($spec_group as $k => $v) {
            $spec_group2 = explode('_', $v);
            array_shift($spec_group2);
            $like = [];
            foreach ($spec_group2 as $k2 => $v2) {
                $v2 = addslashes($v2);
                $like[] = " key2  like '%\_$v2\_%' ";
            }
            $where .= ' and (' . implode('or', $like) . ') ';
        }
        $sql = "select * from (select *,concat('_',`key`,'_') as key2 from __PREFIX__spec_goods_price as a) b  $where";
        $result = Db::query($sql);
        $arr = get_arr_column($result, 'goods_id');  // 只获取商品id 那一列
        return $arr ? $arr : array_unique($arr);
    }

    /**
     * @param $attr |属性
     *
     * @return array|mixed
     *                     根据属性 查找 商品id
     *                     59_直板_翻盖
     *                     80_BT4.0_BT4.1
     */
    public function getGoodsIdByAttr($attr)
    {
        if (empty($attr)) {
            return [];
        }

        $attr_group = explode('@', $attr);
        $attr_id = $attr_value = [];
        foreach ($attr_group as $k => $v) {
            $attr_group2 = explode('_', $v);
            $attr_id[] = array_shift($attr_group2);
            $attr_value = array_merge($attr_value, $attr_group2);
        }
        $c = count($attr_id) - 1;
        if ($c > 0) {
            $arr = Db::name('goods_attr')
                ->where(['attr_id' => ['in', $attr_id], 'attr_value' => ['in', $attr_value]])
                ->group('goods_id')
                ->having("count(goods_id) > $c")
                ->getField('goods_id', true);
        } else {
            $arr = M('goods_attr')
                ->where(['attr_id' => ['in', $attr_id], 'attr_value' => ['in', $attr_value]])
                ->getField('goods_id', true); // 如果只有一个条件不再进行分组查询
        }

        return $arr ? $arr : array_unique($arr);
    }

    /**
     * 获取地址
     *
     * @return array
     */
    public function getRegionList()
    {
        $res = S('getRegionList');
        if (!empty($res)) {
            return $res;
        }
        $parent_region = M('region2')->field('id,name')->where(['level' => 1])->cache(true)->select();
        $ip_location = [];
        $city_location = [];
        foreach ($parent_region as $key => $val) {
            $c = M('region2')->field('id,name')->where(['parent_id' => $parent_region[$key]['id']])->order('id asc')->cache(true)->select();
            $ip_location[$parent_region[$key]['name']] = ['id' => $parent_region[$key]['id'], 'root' => 0, 'djd' => 1, 'c' => $c[0]['id']];
            $city_location[$parent_region[$key]['id']] = $c;
        }
        $res = [
            'ip_location' => $ip_location,
            'city_location' => $city_location,
        ];
        S('getRegionList', $res);

        return $res;
    }

    /**
     * 寻找Region_id的父级id.
     *
     * @param $cid
     *
     * @return array
     */
    public function getParentRegionList($cid)
    {
        //$pids = '';
        $pids = [];
        $parent_id = M('region2')->where(['id' => $cid])->getField('parent_id');
        if (0 != $parent_id) {
            //$pids .= $parent_id;
            array_push($pids, $parent_id);
            $npids = $this->getParentRegionList($parent_id);
            if (!empty($npids)) {
                //$pids .= ','.$npids;
                $pids = array_merge($pids, $npids);
            }
        }

        return $pids;
    }

    /**
     * 检查多个商品是否可配送
     *
     * @param $goodsArr
     * @param $region_id
     * @param $userAddress
     *
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function checkGoodsListShipping($goodsArr, $region_id, $userAddress = [])
    {
        $Goods = new Goods();
        $freightLogic = new FreightLogic();
        $freightLogic->setRegionId($region_id);
        $goods_ids = get_arr_column($goodsArr, 'goods_id');
        $goodsList = $Goods->field('goods_id, template_id, is_free_shipping, is_supply, supplier_goods_id')->where('goods_id', 'IN', $goods_ids)->cache(true)->select();
        $goodsService = new GoodsService();
        foreach ($goodsList as $goodsKey => $goodsVal) {
            /*
             * 商品运费地区限制
             */
            $freightLogic->setGoodsModel($goodsVal);
            $goodsList[$goodsKey]['shipping_able'] = $freightLogic->checkShipping();
            if ($goodsVal['is_supply'] == 1 && !empty($userAddress)) {
                /*
                 * 供应链商品
                 */
                // 地区购买限制的库存和最低购买数量
                $province = M('region2')->where(['id' => $userAddress['province']])->value('ml_region_id');
                $city = M('region2')->where(['id' => $userAddress['city']])->value('ml_region_id');
                $district = M('region2')->where(['id' => $userAddress['district']])->value('ml_region_id');
                $town = M('region2')->where(['id' => $userAddress['twon']])->value('ml_region_id') ?? 0;
                $goodsData = [
                    'goods_id' => $goodsVal['supplier_goods_id'],
                    'spec_key' => '',
                    'goods_num' => 1
                ];
                $res = $goodsService->checkGoodsRegion([$goodsData], $province, $city, $district, $town);
                if ($res['status'] == 0) {
                    throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['msg' => $res['msg']]);
                }
                if (!empty($res['data'])) {
                    $data = $res['data'][0];
                    $goodsList[$goodsKey]['shipping_able'] = isset($data['isAreaRestrict']) && $data['isAreaRestrict'] == true ? 1 : 0;
                }
            }
        }

        return $goodsList;
    }

    /**
     * 根据配送地址获取多个商品的运费.
     *
     * @param $goodsArr
     * @param $region_id
     * @param string $orderPromAmount 订单优惠金额
     * @return string
     */
    public function getFreight($goodsArr, $region_id, $orderPromAmount)
    {
        $Goods = new Goods();
        $freightLogic = new FreightLogic();
        $freightLogic->setRegionId($region_id);
        $goods_ids = get_arr_column($goodsArr, 'goods_id');
        $goodsList = $Goods->field('goods_id,volume,weight,template_id,is_free_shipping')->where('goods_id', 'IN', $goods_ids)->select();
        $goodsList = collection($goodsList)->toArray();
        $goodsNum = 0;
        foreach ($goodsArr as $cartKey => $cartVal) {
            if (isset($cartVal['re_id']) && $cartVal['re_id'] > 0) {
                // 跳过兑换券商品
                continue;
            }
            foreach ($goodsList as $goodsKey => $goodsVal) {
                if ($cartVal['goods_id'] == $goodsVal['goods_id']) {
                    $goodsArr[$cartKey]['volume'] = $goodsVal['volume'];
                    $goodsArr[$cartKey]['weight'] = $goodsVal['weight'];
                    $goodsArr[$cartKey]['template_id'] = $goodsVal['template_id'];
                    $goodsArr[$cartKey]['is_free_shipping'] = $goodsVal['is_free_shipping'];
                }
            }
            $goodsNum += $cartVal['goods_num'];
        }
        $eachOrderPromAmount = bcdiv($orderPromAmount, $goodsNum, 2);   // 每个商品的优惠金额
        $template_list = [];
        foreach ($goodsArr as $cartKey => $cartVal) {
            if (isset($cartVal['re_id']) && $cartVal['re_id'] > 0) {
                // 跳过兑换券商品
                continue;
            }
            $template_list[$cartVal['template_id']][] = $cartVal;
        }
        $freight = 0;               // 启用商城免运费设置的运费
        $outSettingFreight = 0;     // 不启用商城免运费设置的运费
        $freightGoodsPrice = 0;     // 启用商城免运费设置的商品价格
        foreach ($template_list as $templateVal => $goodsArr) {
            $temp['template_id'] = $templateVal;
            $temp['is_free_shipping'] = 1;
            foreach ($goodsArr as $goodsKey => $goodsVal) {
                $temp['member_goods_price'] = bcadd($temp['member_goods_price'], bcmul($goodsVal['member_goods_price'], $goodsVal['goods_num'], 2), 2);
                $temp['total_volume'] += $goodsVal['volume'] * $goodsVal['goods_num'];
                $temp['total_weight'] += $goodsVal['weight'] * $goodsVal['goods_num'];
                $temp['goods_num'] += $goodsVal['goods_num'];
                if ($goodsVal['is_free_shipping'] == 0) {
                    $temp['is_free_shipping'] = 0;
                }
            }
            $temp['each_order_prom_amount'] = $eachOrderPromAmount;
            $freightLogic->setGoodsModel($temp);
            $freightLogic->setGoodsNum($temp['goods_num']);
            $freightLogic->doCalculation();
            $freight = bcadd($freight, $freightLogic->getFreight(), 2);
            $outSettingFreight = bcadd($outSettingFreight, $freightLogic->getOutSettingFreight(), 2);
            $freightGoodsPrice = bcadd($freightGoodsPrice, $freightLogic->getFreightGoodsPrice(), 2);
            unset($temp);
        }
        $freightFree = tpCache('shopping.freight_free'); // 全场满多少免运费
        if ($freightGoodsPrice >= $freightFree) {
            $freight = 0;
        }
        return bcadd($freight, $outSettingFreight, 2);
    }

    /**
     *网站自营,入驻商家,货到付款,仅看有货,促销商品
     *
     * @param $sel |筛选条件
     * @param array $cat_id |分类ID
     *
     * @return mixed
     */
    public function getFilterSelected($sel, $cat_id = [1])
    {
        $where = ' 1 = 1 ';
        $Goods = M('goods')->where('cat_id', 'in', implode(',', $cat_id));
        //查看全部
        if ('selall' == $sel) {
            $where .= '';
        }
        //促销商品
        if ('prom_type' == $sel) {
            $where .= ' and prom_type = 3';
        }
        //看有货
        if ('store_count' == $sel) {
            $where .= ' and store_count > 0';
        }
        //看包邮
        if ('free_post' == $sel) {
            $where .= ' and is_free_shipping=1';
        }
        //看全部
        if ('all' == $sel) {
            $arrid = $Goods->getField('goods_id', true);
        } else {
            $arrid = $Goods->where($where)->getField('goods_id', true);
        }

        return $arrid;
    }

    /**
     * 用户浏览记录.
     *
     * @author lxl
     * @time  17-4-20
     */
    public function add_visit_log($user_id, $goods)
    {
        $record = M('goods_visit')->where(['user_id' => $user_id, 'goods_id' => $goods['goods_id']])->find();
        if ($record) {
            M('goods_visit')->where(['user_id' => $user_id, 'goods_id' => $goods['goods_id']])->save(['visittime' => time()]);
        } else {
            $visit = ['user_id' => $user_id, 'goods_id' => $goods['goods_id'], 'visittime' => time(), 'cat_id' => $goods['cat_id'], 'extend_cat_id' => $goods['extend_cat_id']];
            M('goods_visit')->add($visit);
        }
    }

    /**
     * 删除用户浏览记录.
     *
     * @author lxl
     * @time  17-4-20
     */
    public function del_visit_log($ids)
    {
        M('goods_visit')->where(['visit_id' => ['in', $ids]])->delete();

        return true;
    }

    /**
     * 在有价格阶梯的情况下，根据商品数量，获取商品价格
     *
     * @param $goods_num |购买的商品数
     * @param $goods_price |商品默认单价
     * @param $price_ladder |价格阶梯数组
     *
     * @return mixed
     */
    public function getGoodsPriceByLadder($goods_num, $goods_price, $price_ladder)
    {
        $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
        $price_ladder_count = count($price_ladder);
        for ($i = 0; $i < $price_ladder_count; ++$i) {
            if (0 == $i && $goods_num < $price_ladder[$i]['amount']) {
                return $goods_price;
            }
            if ($goods_num >= $price_ladder[$i]['amount'] && $goods_num < $price_ladder[$i + 1]['amount']) {
                return $price_ladder[$i]['price'];
            }
            if ($i == ($price_ladder_count - 1)) {
                return $price_ladder[$i]['price'];
            }
        }
    }

    /**
     * 获取商品数据
     * @param $filter_goods_id
     * @param $sort
     * @param $page
     * @param null $user
     * @param bool $isApp
     * @return array
     */
    public function getGoodsList($filter_goods_id, $sort, $page, $user = null, $isApp = false)
    {
        $where = [];
        if (!$isApp) {
            $where = [
                'is_abroad' => 0,
                'is_supply' => 0
            ];
        }
        $sort['sort'] = 'desc';
        if (!isset($sort['goods_id'])) {
            $sort['goods_id'] = 'desc';
        }
        // 商品列表
        $goodsList = Db::name('goods')->where('goods_id', 'in', $filter_goods_id)->where($where)
            ->field('goods_id, cat_id, extend_cat_id, goods_sn, goods_name, goods_type, brand_id, store_count, comment_count, goods_remark,
                market_price, shop_price, cost_price, give_integral, exchange_integral, original_img, limit_buy_num, trade_type,
                is_on_sale, is_free_shipping, is_recommend, is_new, is_hot, sale_type, is_supply')
            ->order($sort)->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        // 商品规格属性
        $goodsItem = Db::name('spec_goods_price')->where(['goods_id' => ['in', $filter_goods_id]])->group('goods_id')->getField('goods_id, item_id', true);
        // 用户收藏
        if ($user) {
            $goodsCollect = $this->getCollectGoods($user['user_id']);
        }
        // 商品标签
        $goodsTab = M('GoodsTab')->where(['goods_id' => ['in', $filter_goods_id], 'status' => 1])->select();
        // 秒杀商品
        $flashSale = Db::name('flash_sale fs')
            ->join('spec_goods_price sgp', 'sgp.item_id = fs.item_id', 'LEFT')
            ->where(['fs.goods_id' => ['IN', $filter_goods_id], 'fs.start_time' => ['<=', time()], 'fs.end_time' => ['>=', time()], 'fs.is_end' => 0])
            ->where(['fs.source' => ['LIKE', $isApp ? '%' . 3 . '%' : '%' . 1 . '%']])
            ->field('fs.goods_id, sgp.key spec_key, fs.price, fs.goods_num, fs.buy_limit, fs.start_time, fs.end_time, fs.can_integral')->select();
        // 团购商品
        $groupBuy = Db::name('group_buy gb')
            ->join('spec_goods_price sgp', 'sgp.item_id = gb.item_id', 'LEFT')
            ->where(['gb.goods_id' => ['IN', $filter_goods_id], 'gb.start_time' => ['<=', time()], 'gb.end_time' => ['>=', time()], 'gb.is_end' => 0])
            ->field('gb.goods_id, gb.price, sgp.key spec_key, gb.price, gb.group_goods_num, gb.goods_num, gb.buy_limit, gb.start_time, gb.end_time, gb.can_integral')->select();
        // 促销商品
        $promGoods = Db::name('prom_goods')->alias('pg')->join('goods_tao_grade gtg', 'gtg.promo_id = pg.id')
            ->where(['gtg.goods_id' => ['IN', $filter_goods_id], 'pg.is_end' => 0, 'pg.is_open' => 1, 'pg.start_time' => ['<=', time()], 'pg.end_time' => ['>=', time()]])
            ->field('pg.id prom_id, pg.title, pg.type, pg.expression, gtg.goods_id')->order('expression desc');
        if ($user) {
            $promGoods = $promGoods->where(['pg.group' => ['LIKE', '%' . $user['distribut_level'] . '%']]);
        }
        $promGoods = $promGoods->select();
        // 订单满减优惠
        $orderPromTitle = Db::name('order_prom')
            ->where(['type' => ['in', '0, 1'], 'is_open' => 1, 'is_end' => 0, 'start_time' => ['<=', time()], 'end_time' => ['>=', time()]])
            ->order('discount_price desc')->value('title');
        // 循环处理数据
        foreach ($goodsList as $k => $v) {
            $goodsList[$k]['goods_type'] = 'normal';
            // 处理显示金额
            if ($v['exchange_integral'] != 0) {
                $goodsList[$k]['exchange_price'] = bcsub($v['shop_price'], $v['exchange_integral'], 2);
            } else {
                $goodsList[$k]['exchange_price'] = $v['shop_price'];
            }
            if ($v['is_supply'] == 0) {
                // 处理商品缩略图丢失情况
                if (!file_exists(ltrim($v['original_img'], '/'))) {
                    $goodsImages = M('goods_images')->where(['goods_id' => $v['goods_id']])->select();
                    foreach ($goodsImages as $image) {
                        if (file_exists(ltrim($image['image_url'], '/'))) {
                            $v['original_img'] = $image['image_url'];
                            $goodsList[$k]['original_img'] = $image['image_url'];
                            M('goods')->where(['goods_id' => $v['goods_id']])->update(['original_img' => $image['image_url']]);
                            $logData = [
                                'old_original_img' => $v['original_img'],
                                'new_original_img' => $image['image_url'],
                            ];
                            $this->goodsErrorLog($v['goods_id'], '缩略图文件丢失', $logData);
                            break;
                        }
                    }
                }
            }
            // 缩略图
            $goodsList[$k]['original_img_new'] = getFullPath($v['original_img']);
            // 商品规格属性
            if (isset($goodsItem[$v['goods_id']])) {
                $goodsList[$k]['item_id'] = $goodsItem[$v['goods_id']];
            } else {
                $goodsList[$k]['item_id'] = '0';
            }
            // 是否收藏
            $goodsList[$k]['is_enshrine'] = 0;
            if (!empty($goodsCollect)) {
                foreach ($goodsCollect as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['is_enshrine'] = 1;
                        break;
                    }
                }
            }
            // 商品标签
            $goodsList[$k]['tabs'] = [];
            if (!empty($goodsTab)) {
                foreach ($goodsTab as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['tabs'][] = [
                            'tab_id' => $value['tab_id'],
                            'title' => $value['title'],
                            'status' => $value['status']
                        ];
                    }
                }
            }
            // 商品标识
            $goodsList[$k]['tags'] = [];
            // 第一类，活动类（优先级：“秒杀” > “团购” > “套组” > “自营”）
//            $goodsList[$k]['tags'][0] = ['type' => 'activity', 'title' => '自营'];
            if ($v['sale_type'] == 2) {
                $goodsList[$k]['tags'][0] = ['type' => 'activity', 'title' => '套组'];
            }
            if (!empty($groupBuy)) {
                foreach ($groupBuy as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['goods_nature'] = 'group_buy';
                        if ($value['can_integral'] == 0) {
                            $goodsList[$k]['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $goodsList[$k]['exchange_price'] = bcsub($value['price'], $goodsList[$k]['exchange_integral'], 2);
                        $goodsList[$k]['tags'][0]['type'] = 'activity';
                        $goodsList[$k]['tags'][0]['title'] = '团购';
                        break;
                    }
                }
            }
            if (!empty($flashSale)) {
                foreach ($flashSale as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['goods_nature'] = 'flash_sale';
                        if ($value['can_integral'] == 0) {
                            $goodsList[$k]['exchange_integral'] = '0';    // 不能使用积分兑换
                        }
                        $goodsList[$k]['exchange_price'] = bcsub($value['price'], $goodsList[$k]['exchange_integral'], 2);
                        $goodsList[$k]['tags'][0]['type'] = 'activity';
                        $goodsList[$k]['tags'][0]['title'] = '秒杀';
                        break;
                    }
                }
            }
            // 第二类，促销类
            if (!empty($promGoods)) {
                foreach ($promGoods as $value) {
                    if ($v['goods_id'] == $value['goods_id']) {
                        $goodsList[$k]['goods_nature'] = 'promotion';
                        switch ($value['type']) {
                            case 0:
                                // 打折
                                $goodsList[$k]['exchange_price'] = bcdiv(bcmul($goodsList[$k]['exchange_price'], $value['expression'], 2), 100, 2);
                                break;
                            case 1:
                                // 减价
                                $goodsList[$k]['exchange_price'] = bcsub($goodsList[$k]['exchange_price'], $value['expression'], 2);
                                break;
                        }
                        $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $value['title']];
                        break;
                    }
                }
                if (!isset($goodsList[$k]['tags'][1]) && !empty($orderPromTitle)) {
                    $goodsList[$k]['tags'][] = ['type' => 'promotion', 'title' => $orderPromTitle];
                }
            }
            // 第三类，默认
            $goodsList[$k]['tags'][] = ['type' => 'default', 'title' => '品牌直营'];
        }
        return ['goods_list' => $goodsList, 'goods_images' => isset($goods_images) ?? []];
    }

    /**
     * 获取用户收藏商品列表
     * @param $userId
     * @return array
     */
    public function getCollectGoods($userId)
    {
        $collect = M('goods_collect')->where(['user_id' => $userId])->select();
        return $collect ? $collect : [];
    }

    /**
     * 是否收藏商品
     *
     * @param type $user_id
     * @param type $goods_id
     *
     * @return type
     */
    public function isCollectGoods($user_id, $goods_id)
    {
        $collect = M('goods_collect')->where(['user_id' => $user_id, 'goods_id' => $goods_id])->find();

        return $collect ? 1 : 0;
    }

    /**
     * 获取促销商品数据.
     *
     * @return mixed
     */
    public function getPromotionGoods()
    {
        $goods_where = ['g.is_on_sale' => 1];
        $promotion_goods = M('goods')
            ->alias('g')
            ->field('g.goods_id,g.goods_name,f.price AS shop_price,f.end_time')
            ->join('__FLASH_SALE__ f', 'g.goods_id = f.goods_id')
            ->where($goods_where)
            ->limit(3)
            ->select();

        return $promotion_goods;
    }

    /**
     * 获取精品商品数据.
     *
     * @return mixed
     */
    public function getRecommendGoods($p = 1)
    {
        $goods_where = ['is_recommend' => 1, 'is_on_sale' => 1];
//        $goods_where['goods_name'] = array("exp", " NOT REGEXP '华为|荣耀|小米|合约机|三星|魅族' ");//临时屏蔽,苹果APP审核过了之后注释
        $favourite_goods = M('goods')
            ->field('goods_id,goods_name,shop_price,cat_id')
            ->where($goods_where)
            ->order('sort DESC')
            ->page($p, 10)
            ->cache(true, 3600)
            ->select();

        return $favourite_goods;
    }

    /**
     * 获取新品商品数据.
     *
     * @return mixed
     */
    public function getNewGoods()
    {
        $goods_where = ['is_new' => 1, 'is_on_sale' => 1];
        $orderBy = ['sort' => 'desc'];
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();

        return $new_goods;
    }

    /**
     * 获取热销商品数据.
     *
     * @return mixed
     */
    public function getHotGood($is_brand = 0)
    {
        $goods_where = ['is_hot' => 1, 'is_on_sale' => 1];
        if ($is_brand) {
            $goods_where['brand_id'] = ['<>', 0];
        }
        $orderBy = ['sort' => 'desc'];
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price,market_price,is_virtual')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();

        return $new_goods;
    }

    /**
     * 获取首页轮播图片.
     *
     * @return mixed
     */
    public function getHomeAdv()
    {
        $start_time = strtotime(date('Y-m-d H:00:00'));
        $end_time = strtotime(date('Y-m-d H:00:00'));
        $adv = M('ad')->field(['ad_link', 'ad_name', 'ad_code'])
            ->where("pid=9 and enabled=1 and start_time< $start_time and end_time > $end_time")
            ->order('orderby desc')->cache(true, 3600)
            ->limit(5)->select();
        //广告地址转换
        foreach ($adv as $k => $v) {
            if (!strstr($v['ad_link'], 'http')) {
                $adv[$k]['ad_link'] = SITE_URL . $v['ad_link'];
            }
            $adv[$k]['ad_code'] = SITE_URL . $v['ad_code'];
        }

        return $adv;
    }

    /**
     * 获取首页轮播图片.
     *
     * @return mixed
     */
    public function getAppHomeAdv($isBanner = true)
    {
        $start_time = strtotime(date('Y-m-d H:00:00'));
        $end_time = strtotime(date('Y-m-d H:00:00'));
        if ($isBanner) {
            $where = ['pid' => 500];
        } else {
            $where = 'pid > 500 AND pid < 520';
        }

        $adv = M('ad')->field(['ad_link', 'ad_name', 'ad_code', 'media_type,pid'])
            ->where(" enabled=1 and start_time< $start_time and end_time > $end_time")->where($where)
            ->order('orderby desc')//->fetchSql(true)//->cache(true,3600)
            ->limit(20)->select();

        return $adv;
    }

    /**
     * 获取秒杀商品
     *
     * @return mixed
     */
    public function getFlashSaleGoods($count, $page = 1, $start_time = 0, $end_time = 0)
    {
        //$where['f.status'] = 1;
        $where['f.start_time'] = ['egt', $start_time ?: time()];
        if ($end_time) {
            $where['f.end_time'] = ['elt', $end_time];
        }

        $flash_sale_goods = M('flash_sale')->alias('f')
            ->field('f.end_time,f.goods_name,f.price,f.goods_id,f.price,g.shop_price,f.item_id,100*(FORMAT(f.buy_num/f.goods_num,2)) as percent')
            ->join('__GOODS__ g', 'g.goods_id = f.goods_id')
            ->where($where)
            //->order('f.start_time', 'asc')
            ->page($page, $count)
            ->select();

        return $flash_sale_goods;
    }

    /**
     * 找相似
     */
    public function getSimilar($goods_id, $p, $count)
    {
        $goods = M('goods')->field('cat_id')->where('goods_id', $goods_id)->find();
        if (empty($goods)) {
            return [];
        }

        $where = ['goods_id' => ['<>', $goods_id], 'cat_id' => $goods['cat_id']];
        $goods_list = M('goods')->field('goods_id,goods_name,shop_price,is_virtual')
            ->where($where)->page($p, $count)->select();

        return $goods_list;
    }

    /**
     * 积分商城.
     */
    public function integralMall($rank, $user_id, $p = 1)
    {
        $ranktype = '';
        if ('num' == $rank) {
            $ranktype = 'sales_sum'; //以兑换量（购买量）排序
        } elseif ('integral' == $rank) {
            $ranktype = 'exchange_integral'; //以需要积分排序
        }

        $point_rate = tpCache('shopping.point_rate');
        $goods_where['is_on_sale'] = 1; //是否上架
        $goods_where['is_virtual'] = 0; //是否虚拟商品
        //积分兑换筛选
        $exchange_integral_where_array = [['gt', 0]];
        //我能兑换
        if ('exchange' == $rank && !empty($user_id)) {
            //获取用户积分
            $user_pay_points = intval(M('users')->where(['user_id' => $user_id])->getField('pay_points'));
            if (false !== $user_pay_points) {
                array_push($exchange_integral_where_array, ['lt', $user_pay_points]);
            }
        }
        $goods_where['exchange_integral'] = $exchange_integral_where_array;  //拼装条件
        $goods_list_count = M('goods')->where($goods_where)->count();   //总数
        $goods_list = M('goods')->field('goods_id,goods_name,shop_price,market_price,exchange_integral,is_virtual')
            ->where($goods_where)->order($ranktype, 'desc')->page($p, 15)->select();

        $result = [
            'goods_list' => $goods_list,
            'goods_list_count' => $goods_list_count,
            'point_rate' => $point_rate,
        ];

        return $result;
    }

    /**
     *  获取排好序的品牌列表.
     */
    public function getSortBrands()
    {
        $brandList = M('Brand')->select();
        $brandIdArr = M('Brand')->where('name in (select `name` from `' . C('database.prefix') . 'brand` group by name having COUNT(id) > 1)')->getField('id,cat_id');
        $goodsCategoryArr = M('goodsCategory')->where('level = 1')->getField('id,name');
        $nameList = [];
        foreach ($brandList as $k => $v) {
            $name = getFirstCharter($v['name']) . '  --   ' . $v['name']; // 前面加上拼音首字母

            if (array_key_exists($v[id], $brandIdArr) && $v['cat_id']) { // 如果有双重品牌的 则加上分类名称
                $name .= ' ( ' . $goodsCategoryArr[$v['cat_id']] . ' ) ';
            }
            $nameList[] = $v['name'] = $name;
            $brandList[$k] = $v;
        }
        array_multisort($nameList, SORT_STRING, SORT_ASC, $brandList);

        return $brandList;
    }

    /**
     * 获取活动简要信息.
     *
     * @param array $goods
     * @param FlashSaleLogic|GroupBuyLogic|PromGoodsLogic $goodsPromLogic
     *
     * @return array
     */
    public function getActivitySimpleInfo($goods, $goodsPromLogic)
    {
        //1.商品促销
        $activity = $this->getGoodsPromSimpleInfo($goods, $goodsPromLogic);

        //2.订单促销
        $activity_order = $this->getOrderPromSimpleInfo($goods);

        //3.数据合并
        if ($activity['data'] || $activity_order) {
            empty($activity['data']) && $activity['data'] = [];
            $activity['data'] = array_merge($activity['data'], $activity_order);
        }

        $activity['server_current_time'] = time(); //服务器时间
        return $activity;
    }

    /**
     * 获取商品促销简单信息.
     *
     * @param array $goods
     * @param FlashSaleLogic|GroupBuyLogic|PromGoodsLogic $goodsPromLogic
     *
     * @return array
     */
    public function getGoodsPromSimpleInfo($goods, $goodsPromLogic)
    {
        //prom_type: 0默认 1抢购 2团购 3优惠促销 4预售(不考虑)
        $activity['prom_type'] = 0;

        $goodsPromFactory = new \app\common\logic\GoodsPromFactory();
        if (!$goodsPromFactory->checkPromType($goods['prom_type'])
            || !$goodsPromLogic || !$goodsPromLogic->checkActivityIsAble()) {
            return $activity;
        }

        // 1抢购 2团购
        $prom = $goodsPromLogic->getPromModel()->getData();
        if (in_array($goods['prom_type'], [1, 2])) {
            $info = $goodsPromLogic->getActivityGoodsInfo();
            $activity = [
                'prom_type' => $goods['prom_type'],
                'prom_price' => $prom['price'],
                'prom_store_count' => $info['store_count'],
                'virtual_num' => $info['virtual_num'],
            ];
            if ($prom['start_time']) {
                $activity['prom_start_time'] = $prom['start_time'];
            }
            if ($prom['end_time']) {
                $activity['prom_end_time'] = $prom['end_time'];
            }

            return $activity;
        }

        // 3优惠促销
        // type:0直接打折,1减价优惠,2固定金额出售,3买就赠优惠券
        if (0 == $prom['type']) {
            $expression = round($prom['expression'] / 10, 2);
            $activityData[] = ['title' => '折扣', 'content' => "指定商品立打{$expression}折"];
        } elseif (1 == $prom['type']) {
            $activityData[] = ['title' => '直减', 'content' => "指定商品立减{$prom['expression']}元"];
        } elseif (2 == $prom['type']) {
            $activityData[] = ['title' => '促销', 'content' => "促销价{$prom['expression']}元"];
        } elseif (3 == $prom['type']) {
            $couponLogic = new \app\common\logic\CouponLogic();
            $money = $couponLogic->getSendValidCouponMoney($prom['expression'], $goods['goods_id'], $goods['store_id'], $goods['cat_id']);
            if (false !== $money) {
                $activityData[] = ['title' => '送券', 'content' => "买就送代金券{$money}元"];
            }
        }
        if ($activityData) {
            $activityInfo = $goodsPromLogic->getActivityGoodsInfo();
            $activity = [
                'prom_type' => $goods['prom_type'],
                'prom_price' => $activityInfo['shop_price'],
                'data' => $activityData,
            ];
            if ($prom['start_time']) {
                $activity['prom_start_time'] = $prom['start_time'];
            }
            if ($prom['end_time']) {
                $activity['prom_end_time'] = $prom['end_time'];
            }
        }

        return $activity;
    }

    /**
     * 获取.
     *
     * @param type $user_level
     * @param type $cur_time
     * @param type $goods
     *
     * @return string|array
     */
    public function getOrderPromSimpleInfo($goods)
    {
        $cur_time = time();

        $data = [];
        $po = M('prom_order')->where(['start_time' => ['<=', $cur_time], 'end_time' => ['>', $cur_time], 'is_close' => 0])->select();
        if (!empty($po)) {
            foreach ($po as $p) {
                //type:0满额打折,1满额优惠金额,2满额送积分,3满额送优惠券
                if (0 == $p['type']) {
                    $data[] = ['title' => '折扣', 'content' => "满{$p['money']}元打{$p['expression']}折"];
                } elseif (1 == $p['type']) {
                    $data[] = ['title' => '优惠', 'content' => "满{$p['money']}元优惠{$p['expression']}元"];
                } elseif (2 == $p['type']) {
                    //积分暂不支持?
                } elseif (3 == $p['type']) {
                    $couponLogic = new \app\common\logic\CouponLogic();
                    $money = $couponLogic->getSendValidCouponMoney($p['expression'], $goods['goods_id'], $goods['cat_id']);
                    if (false !== $money) {
                        $data[] = ['title' => '送券', 'content' => "满{$p['money']}元送{$money}元优惠券"];
                    }
                }
            }
        }

        return $data;
    }

    /**
     *  获取排好序的分类列表.
     */
    public function getSortCategory()
    {
        $categoryList = M('GoodsCategory')->getField('id,name,parent_id,level');
        $nameList = [];
        foreach ($categoryList as $k => $v) {
            //$str_pad = str_pad('',($v[level] * 5),'-',STR_PAD_LEFT);
            $name = getFirstCharter($v['name']) . ' ' . $v['name']; // 前面加上拼音首字母
            //$name = getFirstCharter($v['name']) .' '. $v['name'].' '.$v['level']; // 前面加上拼音首字母
            /*
            // 找他老爸
            $parent_id = $v['parent_id'];
            if($parent_id)
                $name .= '--'.$categoryList[$parent_id]['name'];
            // 找他 爷爷
            $parent_id = $categoryList[$v['parent_id']]['parent_id'];
            if($parent_id)
                $name .= '--'.$categoryList[$parent_id]['name'];
            */
            $nameList[] = $v['name'] = $name;
            $categoryList[$k] = $v;
        }
        array_multisort($nameList, SORT_STRING, SORT_ASC, $categoryList);

        return $categoryList;
    }

    /**
     * 算出多个商品的积分的最大使用数量 BY J.
     *
     * @param $goodsArr
     *
     * @return false|\PDOStatement|string|Floot
     */
    public function countIntegral($goodsArr)
    {
        $Goods = new Goods();
        $goods_ids = get_arr_column($goodsArr, 'goods_id');
        $max_discount_integral = 0;
        $goodsList = $Goods->field('goods_id,zone,distribut_id')->where('goods_id', 'IN', $goods_ids)->cache(true)->select();
        // 如果是分销商的升级商品，则不能使用积分
        foreach ($goodsList as $goodsKey => $goodsVal) {
            if (3 == $goodsVal['zone'] && $goodsVal['distribut_id'] > 0) {
                $max_discount_integral = bcadd($max_discount_integral, 0, 2);
            } elseif ($goodsArr[$goodsKey]['member_goods_price'] > 0) {
                $max_discount_integral = bcadd($goodsArr[$goodsKey]['member_goods_price'], 0, 2);
            } else {
                $max_discount_integral = bcadd($goodsArr[$goodsKey]['use_integral'], 0, 2);
            }
        }

        return $max_discount_integral;
    }

    /**
     * 商品错误处理记录
     * @param $goodsId
     * @param $desc
     * @param array $data
     */
    public function goodsErrorLog($goodsId, $desc, $data = [])
    {
        M('goods_error_log')->add([
            'goods_id' => $goodsId,
            'desc' => $desc,
            'log_data' => !empty($data) ? json_encode($data) : '',
            'add_time' => NOW_TIME
        ]);
    }

    /**
     * 获取订单商品数据
     * @param $cartLogic
     * @param $goodsId
     * @param $itemId
     * @param $goodsNum
     * @param $payType
     * @param $cartIds
     * @param $isApp
     * @param $userId
     * @param $passAuth
     * @return array
     */
    public function getOrderGoodsData(CartLogic $cartLogic, $goodsId, $itemId, $goodsNum, $payType, $cartIds, $isApp, $userId, $passAuth = false)
    {
        if (!empty($goodsId) && empty(trim($cartIds))) {
            /*
             * 单个商品下单
             */
            $cartLogic->setGoodsModel($goodsId);
            $cartLogic->setSpecGoodsPriceModel($itemId);
            $cartLogic->setGoodsBuyNum($goodsNum);
            $cartLogic->setType($payType);
            $cartLogic->setCartType(0);
            try {
                $buyGoods = $cartLogic->buyNow($isApp, $passAuth);
            } catch (TpshopException $tpE) {
                $error = $tpE->getErrorArr();
                return ['status' => 0, 'msg' => $error['msg']];
            }
            return ['status' => 1, 'result' => [$buyGoods]];
        } elseif (empty($goodsId) && !empty(trim($cartIds))) {
            /*
             * 购物车下单
             */
            $cartIds = explode(',', $cartIds);
            foreach ($cartIds as $k => $v) {
                $data = [];
                $data['id'] = $v;
                $data['selected'] = 1;
                $cartIds[$k] = $data;
            }
            $result = $cartLogic->AsyncUpdateCarts($cartIds);
            if (1 != $result['status']) {
                return ['status' => 0, 'msg' => $result['msg']];
            }
            if (0 == $cartLogic->getUserCartOrderCount()) {
                return ['status' => 0, 'msg' => '你的购物车没有选中商品'];
            }
            $cartList = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
            $vipGoods = [];
            foreach ($cartList as $key => $cart) {
                if ($cart['prom_type'] == 0) {
                    if ($cart['goods']['least_buy_num'] != 0 && $cart['goods']['least_buy_num'] > $cart['goods_num']) {
                        return ['status' => 0, 'msg' => $cart['goods']['goods_name'] . '至少购买' . $cart['goods']['least_buy_num'] . '件'];
                    }
                }
                if ($cart['goods']['zone'] == 3 && $cart['goods']['distribut_id'] != 0) {
                    $vipGoods[] = $cart['goods']['goods_id'];
                }
                if ($cart['prom_type'] == 3) {
                    // 商品促销优惠
                    $cartList[$key]['member_goods_price'] = bcsub($cart['goods_price'], $cart['use_integral'], 2);
                }
            }
            if (count($vipGoods) > 0 && M('user_pre_distribute_log')->where(['user_id' => $userId, 'status' => 0])->find()) {
                return ['status' => 0, 'msg' => '你已经购买过升级套餐，请耐心等待审核结果'];
            }
            if (count($vipGoods) > 1) {
                return ['status' => 0, 'msg' => '不能一次购买两种或以上升级套餐'];
            }
            return ['status' => 1, 'result' => $cartList];
        } else {
            /*
             * 单个商品 + 购物车 下单
             */
            return ['status' => 0, 'msg' => '暂不支持此下单方式'];
        }
    }

    /**
     * 根据地址获取商品信息
     * @param $goodsId
     * @param $itemId
     * @param $districtId
     * @return array
     */
    public function addressGoodsInfo($goodsId, $itemId, $districtId)
    {
        $specGoodsInfo = M('spec_goods_price')->where(['goods_id' => $goodsId, 'item_id' => $itemId])->find();
        if (!empty($specGoodsInfo)) {
            $returnData = [
                'store_count' => $specGoodsInfo['store_count']
            ];
        } else {
            $goodsInfo = M('goods')->where(['goods_id' => $goodsId])->find();
            $returnData = [
                'store_count' => $goodsInfo['store_count']
            ];
        }
        return $returnData;
    }

    /**
     * 地址商品信息
     * @param $user
     * @param $goodsId
     * @param int $itemId
     * @param string $addressId
     * @param int $goodsNum
     * @param bool $isSupply 是否是供应链商品
     * @return array
     * @throws TpshopException
     */
    public function addressGoods($user, $goodsId, $itemId = 0, $addressId = '', $goodsNum = 1, $isSupply = false)
    {
        if (empty($addressId)) {
            // 用户默认地址
            $userAddress = get_user_address_list_new($user['user_id'], true);
        } else {
            $userAddress = get_user_address_list_new($user['user_id'], false, $addressId);
        }
        if (!empty($userAddress)) {
            $userAddress = $userAddress[0];
            $userAddress['town_name'] = $userAddress['town_name'] ?? '';
            $userAddress['is_illegal'] = 0;     // 非法地址
            $userAddress['out_range'] = 0;      // 超出配送范围
            $userAddress['limit_tips'] = '';    // 限制的提示
            unset($userAddress['zipcode']);
            unset($userAddress['is_pickup']);
            // 地址标签
            $addressTab = (new UsersLogic())->getAddressTab($user['user_id']);
            if (!empty($addressTab)) {
                if (empty($userAddress['tabs'])) {
                    unset($userAddress['tabs']);
                    $userAddress['tabs'][] = [
                        'tab_id' => 0,
                        'name' => '默认',
                        'is_selected' => 1
                    ];
                } else {
                    $tabs = explode(',', $userAddress['tabs']);
                    unset($userAddress['tabs']);
                    foreach ($addressTab as $item) {
                        if (in_array($item['tab_id'], $tabs)) {
                            $userAddress['tabs'][] = [
                                'tab_id' => $item['tab_id'],
                                'name' => $item['name'],
                                'is_selected' => 1
                            ];
                        }
                    }
                    $userAddress['tabs'][] = [
                        'tab_id' => 0,
                        'name' => '默认',
                        'is_selected' => 1
                    ];
                }
            } else {
                unset($userAddress['tabs']);
                $userAddress['tabs'][] = [
                    'tab_id' => 0,
                    'name' => '默认',
                    'is_selected' => 1
                ];
            }
            // 判断用户地址是否合法
            $userAddress = (new UsersLogic())->checkAddressIllegal($userAddress);
            if ($userAddress['is_illegal'] == 1) {
                // 不合法
                $return['store_count'] = '0';
                $return['user_address'] = $userAddress;
            } else {
                // 合法
                /*
                 * 商品运费地区限制
                 */
                $cartLogic = new CartLogic();
                $cartLogic->setUserId($user['user_id']);
                // 获取订单商品数据
                $res = $this->getOrderGoodsData($cartLogic, $goodsId, $itemId, 1, 1, '', 1, $user['user_id'], true);
                if ($res['status'] != 1) {
                    throw new TpshopException('地址商品信息', 0, ['status' => 0, 'msg' => $res['msg']]);
                } else {
                    $cartList = $res['result'];
                }
                $payLogic = new PayLogic();
                $payLogic->payCart($cartList);
                // 配送物流
                if (!empty($userAddress)) {
                    $res = $payLogic->delivery($userAddress['district']);
                    if (isset($res['status']) && $res['status'] == -1) {
                        $userAddress['out_range'] = 1;
                    }
                }
                if ($isSupply) {
                    /*
                     * 供应链商品
                     */
                    // 获取最新库存信息
                    $supplierGoodsId = M('goods')->where(['goods_id' => $goodsId])->value('supplier_goods_id');
                    if ($itemId > 0) {
                        $key = M('spec_goods_price')->where(['item_id' => $itemId])->value('key') ?? '';
                    } else {
                        $key = M('spec_goods_price')->where(['goods_id' => $goodsId])->value('key') ?? '';
                    }
                    $goodsData = [
                        'goods_id' => $supplierGoodsId,
                        'key' => $key
                    ];
                    $goodsService = new GoodsService();
                    $res = $goodsService->getGoodsCount([$goodsData]);
                    if ($res['status'] == 0) {
                        throw new TpshopException('获取供应链商品库存信息失败', 0, ['msg' => $res['msg']]);
                    }
                    $data = $res['data'][0];
                    $return['store_count'] = $data['store_count'] . '';
                    // 地区购买限制的库存和最低购买数量
                    $province = M('region2')->where(['id' => $userAddress['province']])->value('ml_region_id');
                    $city = M('region2')->where(['id' => $userAddress['city']])->value('ml_region_id');
                    $district = M('region2')->where(['id' => $userAddress['district']])->value('ml_region_id');
                    $town = M('region2')->where(['id' => $userAddress['twon']])->value('ml_region_id') ?? 0;
                    $goodsData = [
                        'goods_id' => $supplierGoodsId,
                        'spec_key' => $key,
                        'goods_num' => $goodsNum
                    ];
                    $res = $goodsService->checkGoodsRegion([$goodsData], $province, $city, $district, $town);
                    if ($res['status'] == 0) {
                        throw new TpshopException('获取供应链商品地区购买限制失败', 0, ['msg' => $res['msg']]);
                    }
                    if (!empty($res['data'])) {
                        $data = $res['data'][0];
                        $return['store_count'] = $data['goods_count'] <= 0 ? '0' : $data['goods_count'];
                        $return['buy_least'] = isset($data['buy_num']) ? $data['buy_num'] : '0';
                        $userAddress['out_range'] = isset($data['isAreaRestrict']) && $data['isAreaRestrict'] == true ? 1 : 0;
                        $return['store_count'] = isset($data['isNoStock']) && $data['isNoStock'] == true ? '0' : $return['store_count'];
                    }
                }
            }
            if ($userAddress['is_illegal'] == 1) {
                $userAddress['limit_tips'] = '当前地址信息不完整，请添加街道后补充完整地址信息再提交订单';
            } elseif ($userAddress['out_range'] == 1) {
                $userAddress['limit_tips'] = '当前地址不在配送范围内，请重新选择';
            }
            $return['user_address'] = $userAddress;
        } else {
            $return = [
                'user_address' => []
            ];
        }
        return $return;
    }

    /**
     * 生成商品口令
     * @param $goodsInfo
     * @return string
     */
    public function createGoodsPwd($goodsInfo)
    {
        $password = 'PMD_' . get_rand_str(12, 0, 1);
        $password = '复制内容“' . $password . '”打开乐活优选APP【' . $goodsInfo['goods_name'] . '】';
        if (M('goods_password')->where(['password' => $password])->find()) {
            return $this->createGoodsPwd($goodsInfo);
        }
        return $password;
    }
}
