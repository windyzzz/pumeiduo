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
use app\admin\logic\SearchWordLogic;
use app\admin\model\Goods as GoodsModel;
use app\common\logic\OssLogic;
use think\AjaxPage;
use think\Db;
use think\Exception;
use think\Loader;
use think\Page;

class Goods extends Base
{

    public function importGoods()
    {

    }

    /**
     *  商品分类列表.
     */
    public function categoryList()
    {
        $GoodsLogic = new GoodsLogic();
        $cat_list = $GoodsLogic->goods_cat_list();
//        echo '<pre>';
//        print_r($cat_list);
//        echo '</pre>';
//        exit();
        $this->assign('cat_list', $cat_list);

        return $this->fetch();
    }

    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'),
     * select * from tp_goods_category where id = 393
     * select * from tp_goods_category where parent_id = 393.
     * update tp_goods_category  set parent_id_path = concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
     * insert into `tp_goods_category` (`parent_id`,`name`) values
     * ('393','时尚饰品'),
     */
    public function addEditCategory()
    {
        $GoodsLogic = new GoodsLogic();
        if (IS_GET) {
            $goods_category_info = D('GoodsCategory')->where('id=' . I('GET.id', 0))->find();
            $this->assign('goods_category_info', $goods_category_info);

            $all_type = M('goods_category')->where('level<3')->getField('id,name,parent_id'); //上级分类数据集，限制3级分类，那么只拿前两级作为上级选择
            if (!empty($all_type)) {
                $parent_id = empty($goods_category_info) ? I('parent_id', 0) : $goods_category_info['parent_id'];
                $all_type = $GoodsLogic->getCatTree($all_type);
                $cat_select = $GoodsLogic->exportTree($all_type, 0, $parent_id);
                $this->assign('cat_select', $cat_select);
            }

            //$cat_list = M('goods_category')->where("parent_id = 0")->select();
            //$this->assign('cat_list',$cat_list);
            return $this->fetch('_category');
            exit;
        }

        $GoodsCategory = D('GoodsCategory');

        $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if (1 == I('is_ajax')) {
            // 数据验证
            $validate = \think\Loader::validate('GoodsCategory');
            if (!$validate->batch()->check(input('post.'))) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = [
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                ];
                $this->ajaxReturn($return_arr);
            } else {
                $GoodsCategory->data(input('post.'), true); // 收集数据
                $GoodsCategory->parent_id = I('parent_id');

                //查找同级分类是否有重复分类
                $par_id = ($GoodsCategory->parent_id > 0) ? $GoodsCategory->parent_id : 0;
                $sameCateWhere = ['parent_id' => $par_id, 'name' => $GoodsCategory['name']];
                $GoodsCategory->id && $sameCateWhere['id'] = ['<>', $GoodsCategory->id];
                $same_cate = M('GoodsCategory')->where($sameCateWhere)->find();
                if ($same_cate) {
                    $return_arr = ['status' => 0, 'msg' => '同级已有相同分类存在', 'data' => ''];
                    $this->ajaxReturn($return_arr);
                }

                if ($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id) {
                    //  编辑
                    $return_arr = ['status' => 0, 'msg' => '上级分类不能为自己', 'data' => ''];
                    $this->ajaxReturn($return_arr);
                }
                /*if($GoodsCategory->commission_rate > 100)
                {
                    //  编辑
                    $return_arr = array('status' => -1,'msg'   => '分佣比例不得超过100%','data'  => '');
                    $this->ajaxReturn($return_arr);
                }*/

                if (2 == $type) {
                    $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库
                    $GoodsLogic->refresh_cat(I('id'));
                } else {
                    $GoodsCategory->save(); // 写入数据到数据库
                    $insert_id = $GoodsCategory->getLastInsID();
                    $GoodsLogic->refresh_cat($insert_id);
                }
                $return_arr = [
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => ['url' => U('Admin/Goods/categoryList')],
                ];
                $this->ajaxReturn($return_arr);
            }
        }
    }

    /**
     * 获取商品分类 的帅选规格 复选框.
     */
    public function ajaxGetSpecList()
    {
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsCategory')->where('id = ' . $_REQUEST['category_id'])->getField('filter_spec');
        $filter_spec_arr = explode(',', $filter_spec);
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'], $filter_spec_arr);
        $str = $str ? $str : '没有可帅选的商品规格';
        exit($str);
    }

    /**
     * 获取商品分类 的帅选属性 复选框.
     */
    public function ajaxGetAttrList()
    {
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsCategory')->where('id = ' . $_REQUEST['category_id'])->getField('filter_attr');
        $filter_attr_arr = explode(',', $filter_attr);
        $str = $GoodsLogic->GetAttrCheckboxList($_REQUEST['type_id'], $filter_attr_arr);
        $str = $str ? $str : '没有可帅选的商品属性';
        exit($str);
    }

    /**
     * 删除分类.
     */
    public function delGoodsCategory()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！', 'data' => '']);
        // 判断子分类
        $count = Db::name('goods_category')->where("parent_id = {$ids}")->count('id');
        $count > 0 && $this->ajaxReturn(['status' => -1, 'msg' => '该分类下还有分类不得删除!']);
        // 判断是否存在商品
        $goods_count = Db::name('Goods')->where("cat_id = {$ids}")->count('1');
        $goods_count > 0 && $this->ajaxReturn(['status' => -1, 'msg' => '该分类下有商品不得删除!']);
        // 删除分类
        DB::name('goods_category')->where('id', $ids)->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/Goods/categoryList')]);
    }

    public function getGoodsInfo()
    {
        $goods_id = I('goods_id', 0);

        $result = M('goods')->field('goods_id,goods_name,shop_price,exchange_integral,store_count')->where('goods_id', $goods_id)->find();
        if ($result) {
            return json(['msg' => 'ok', 'status' => 1, 'result' => $result]);
        }
        return json(['msg' => 'err', 'status' => 0, 'result' => null]);
    }

    /**
     *  商品列表.
     */
    public function goodsList()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);

        return $this->fetch();
    }

    public function export_goods()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . ' = 1';
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        ('' !== I('is_on_sale')) && $where = "$where and is_on_sale = " . I('is_on_sale');

        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        $sale_type = I('sale_type');
        if ($sale_type) {
            $where = "$where and sale_type = " . I('sale_type');
        }
        $goods_nature = I('goods_nature');
        if ($goods_nature) {
            switch ($goods_nature) {
                case 1:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 2:
                    $where = "$where and is_abroad = 1 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 3:
                    $where = "$where and is_abroad = 0 and is_supply = 1 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 4:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 1 and is_abroad2 = 0";
                    break;
                case 5:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 1";
                    break;
            }
        }

        $is_area_show = I('is_area_show');
        switch ($is_area_show) {
            case 1:
                $where .= ' and is_area_show = 1';
                break;
            case 3:
                $where .= ' and applet_on_sale = 1';
                if (in_array($goods_nature, [0, 4])) {
                    $where .= ' and applet_on_sale = 1 or is_agent = 1';
                }
                break;
        }

        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= ' and cat_id in(' . implode(',', $grandson_ids) . ') '; // 初始化搜索条件
        }
        $ids = I('ids');
        $map = [];
        if ($ids) {
            $map['goods_id'] = ['in', $ids];
        }

        $onTimeBegin = I('on_time_begin');
        $onTimeEnd = I('on_time_end');
        if ($onTimeBegin && $onTimeEnd) {
            $onTimeBegin = strtotime($onTimeBegin);
            $onTimeEnd = strtotime($onTimeEnd);
            $where .= ' and on_time BETWEEN ' . $onTimeBegin . ' and ' . $onTimeEnd;
        }

        $outTimeBegin = I('out_time_begin');
        $outTimeEnd = I('out_time_end');
        if ($outTimeBegin && $outTimeEnd) {
            $outTimeBegin = strtotime($outTimeBegin);
            $outTimeEnd = strtotime($outTimeEnd);
            $where .= ' and out_time BETWEEN ' . $outTimeBegin . ' and ' . $outTimeEnd;
        }

        $goodsList = M('Goods')->where($where)->where($map)->select();

        $GoodsLogic = new GoodsLogic();
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">货号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">商品分类1</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">商品分类2</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品分类3</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品名称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品种类</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">成本价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">本店售价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">商品不含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">现金金额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">现金不含税价</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分兑换</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">零售价pv</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分价pv</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">交易条件选择</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">供应商</td>';
        $strTable .= '</tr>';
        if (is_array($goodsList)) {
            foreach ($goodsList as $k => $val) {
                $goodsNature = '圃美多';
                if ($val['is_abroad'] == 1) {
                    $goodsNature = '韩国购';
                } elseif ($val['is_supply'] == 1) {
                    $goodsNature = '供应链';
                } elseif ($val['is_agent'] == 1) {
                    $goodsNature = '代理商';
                }
                $level_cat = $GoodsLogic->find_parent_cat($val['cat_id']); // 获取分类默认选中的下拉框
                $first_cat = M('GoodsCategory')->where('id', $level_cat[1])->getField('name');
                $secend_cat = M('GoodsCategory')->where('id', $level_cat[2])->getField('name');
                $third_cat = M('GoodsCategory')->where('id', $level_cat[3])->getField('name');
                $suppliers = M('Suppliers')->where('suppliers_id', $val['suppliers_id'])->getField('suppliers_name');
                $price = $val['shop_price'] - $val['exchange_integral'];
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px; vnd.ms-excel.numberformat:@;">' . $val['goods_sn'] . '</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;">' . $first_cat . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $secend_cat . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $third_cat . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $goodsNature . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['goods_name'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['cost_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['shop_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['stax_price'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $price . '</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['ctax_price'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['exchange_integral'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['retail_pv'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['integral_pv'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . trade_type($val['trade_type']) . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">' . $suppliers . ' </td>';
                $strTable .= '</tr>';
            }
            unset($goodsList);
        }

        $strTable .= '</table>';
        downloadExcel($strTable, 'goods_list');
        exit();
    }

    /**
     * 订单导出（显示缩略图）
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function export_goods_v2()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . ' = 1';
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        ('' !== I('is_on_sale')) && $where = "$where and is_on_sale = " . I('is_on_sale');

        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        $sale_type = I('sale_type');
        if ($sale_type) {
            $where = "$where and sale_type = " . I('sale_type');
        }
        $goods_nature = I('goods_nature');
        if ($goods_nature) {
            switch ($goods_nature) {
                case 1:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 2:
                    $where = "$where and is_abroad = 1 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 3:
                    $where = "$where and is_abroad = 0 and is_supply = 1 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 4:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 1 and is_abroad2 = 0";
                    break;
                case 5:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 1";
                    break;
            }
        }

        $is_area_show = I('is_area_show');
        switch ($is_area_show) {
            case 1:
                $where .= ' and is_area_show = 1';
                break;
            case 3:
                $where .= ' and applet_on_sale = 1';
                break;
        }

        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= ' and cat_id in(' . implode(',', $grandson_ids) . ') '; // 初始化搜索条件
        }
        $ids = I('ids');
        $map = [];
        if ($ids) {
            $map['goods_id'] = ['in', $ids];
        }

        $onTimeBegin = I('on_time_begin');
        $onTimeEnd = I('on_time_end');
        if ($onTimeBegin && $onTimeEnd) {
            $onTimeBegin = strtotime($onTimeBegin);
            $onTimeEnd = strtotime($onTimeEnd);
            $where .= ' and on_time BETWEEN ' . $onTimeBegin . ' and ' . $onTimeEnd;
        }

        $outTimeBegin = I('out_time_begin');
        $outTimeEnd = I('out_time_end');
        if ($outTimeBegin && $outTimeEnd) {
            $outTimeBegin = strtotime($outTimeBegin);
            $outTimeEnd = strtotime($outTimeEnd);
            $where .= ' and out_time BETWEEN ' . $outTimeBegin . ' and ' . $outTimeEnd;
        }

        $goodsList = M('Goods')->where($where)->where($map)->select();
        if (is_array($goodsList)) {
            $GoodsLogic = new GoodsLogic();
            // 分类信息
            $cateInfo = $GoodsLogic->get_parent_cate();
            // 供应商信息
            $suppliers = M('suppliers')->getField('suppliers_id, suppliers_name');
            foreach ($goodsList as $k => &$val) {
                $goodsNature = '圃美多';
                if ($val['is_abroad'] == 1) {
                    $goodsNature = '韩国购';
                } elseif ($val['is_supply'] == 1) {
                    $goodsNature = '供应链';
                } elseif ($val['is_agent'] == 1) {
                    $goodsNature = '代理商';
                } elseif ($val['is_abroad2'] == 1) {
                    $goodsNature = '京畿道直邮';
                }
                $val['goods_nature'] = $goodsNature;
                if (isset($cateInfo[$val['cat_id']])) {
                    switch ($cateInfo[$val['cat_id']]['level']) {
                        case 3:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                            $val['second_cat'] = $cateInfo[$val['cat_id']]['level_2']['name'];
                            $val['third_cat'] = $cateInfo[$val['cat_id']]['name'];
                            break;
                        case 2:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                            $val['second_cat'] = $cateInfo[$val['cat_id']]['name'];
                            $val['third_cat'] = '';
                            break;
                        case 1:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['name'];
                            $val['second_cat'] = '';
                            $val['third_cat'] = '';
                            break;
                    }
                } else {
                    $val['first_cat'] = '';
                    $val['second_cat'] = '';
                    $val['third_cat'] = '';
                }
                $val['exchange_price'] = $val['shop_price'] - $val['exchange_integral'];
                $val['trade_type'] = trade_type($val['trade_type']);
                $val['supplier'] = $suppliers[$val['suppliers_id']] ?? '';
                $val['is_on_sale2'] = $val['is_on_sale2'] == 1 ? '上架' : '下架';
                $val['is_on_sale'] = $val['is_on_sale'] == 1 ? '上架' : '下架';
            }
        }
        $expCellName = [
            ['goods_sn', '货号', 20, 1],
            ['first_cat', '商品分类1', 20, 1],
            ['second_cat', '商品分类2', 20, 1],
            ['third_cat', '商品分类3', 20, 1],
            ['goods_name', '商品名称', 50, 1],
            ['goods_nature', '商品种类', 20, 1],
            ['cost_price', '成本价', 20, 0],
            ['shop_price', '本店售价', 20, 0],
            ['stax_price', '商品不含税价', 20, 0],
            ['exchange_price', '现金金额', 20, 0],
            ['ctax_price', '现金不含税价', 20, 0],
            ['exchange_integral', '积分兑换', 20, 0],
            ['retail_pv', '零售价pv', 20, 0],
            ['integral_pv', '积分价pv', 20, 0],
            ['trade_type', '交易条件选择', 20, 1],
            ['supplier', '供应商', 50, 1],
            ['original_img', '缩略图', 20, 1],
            ['is_on_sale2', '子系统上架状态', 20, 1],
            ['is_on_sale', '真正上架状态', 20, 1],
        ];
        exportExcel('产品列表', $expCellName, $goodsList, 'goods', true);
    }

    /**
     * 订单导出（不显示缩略图）
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function export_goods_v3()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . ' = 1';
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        ('' !== I('is_on_sale')) && $where = "$where and is_on_sale = " . I('is_on_sale');

        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        $sale_type = I('sale_type');
        if ($sale_type) {
            $where = "$where and sale_type = " . I('sale_type');
        }
        $goods_nature = I('goods_nature');
        if ($goods_nature) {
            switch ($goods_nature) {
                case 1:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 2:
                    $where = "$where and is_abroad = 1 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 3:
                    $where = "$where and is_abroad = 0 and is_supply = 1 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 4:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 1 and is_abroad2 = 0";
                    break;
                case 5:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 1";
                    break;
            }
        }

        $is_area_show = I('is_area_show');
        switch ($is_area_show) {
            case 1:
                $where .= ' and is_area_show = 1';
                break;
            case 3:
                $where .= ' and applet_on_sale = 1';
                break;
        }

        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= ' and cat_id in(' . implode(',', $grandson_ids) . ') '; // 初始化搜索条件
        }
        $ids = I('ids');
        $map = [];
        if ($ids) {
            $map['goods_id'] = ['in', $ids];
        }

        $onTimeBegin = I('on_time_begin');
        $onTimeEnd = I('on_time_end');
        if ($onTimeBegin && $onTimeEnd) {
            $onTimeBegin = strtotime($onTimeBegin);
            $onTimeEnd = strtotime($onTimeEnd);
            $where .= ' and on_time BETWEEN ' . $onTimeBegin . ' and ' . $onTimeEnd;
        }

        $outTimeBegin = I('out_time_begin');
        $outTimeEnd = I('out_time_end');
        if ($outTimeBegin && $outTimeEnd) {
            $outTimeBegin = strtotime($outTimeBegin);
            $outTimeEnd = strtotime($outTimeEnd);
            $where .= ' and out_time BETWEEN ' . $outTimeBegin . ' and ' . $outTimeEnd;
        }

        $goodsList = M('Goods')->where($where)->where($map)->select();
        if (is_array($goodsList)) {
            $GoodsLogic = new GoodsLogic();
            // 分类信息
            $cateInfo = $GoodsLogic->get_parent_cate();
            // 供应商信息
            $suppliers = M('suppliers')->getField('suppliers_id, suppliers_name');
            foreach ($goodsList as $k => &$val) {
                $goodsNature = '圃美多';
                if ($val['is_abroad'] == 1) {
                    $goodsNature = '韩国购';
                } elseif ($val['is_supply'] == 1) {
                    $goodsNature = '供应链';
                } elseif ($val['is_agent'] == 1) {
                    $goodsNature = '代理商';
                }
                $val['goods_nature'] = $goodsNature;
                if (isset($cateInfo[$val['cat_id']])) {
                    switch ($cateInfo[$val['cat_id']]['level']) {
                        case 3:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                            $val['second_cat'] = $cateInfo[$val['cat_id']]['level_2']['name'];
                            $val['third_cat'] = $cateInfo[$val['cat_id']]['name'];
                            break;
                        case 2:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['level_1']['name'];
                            $val['second_cat'] = $cateInfo[$val['cat_id']]['name'];
                            $val['third_cat'] = '';
                            break;
                        case 1:
                            $val['first_cat'] = $cateInfo[$val['cat_id']]['name'];
                            $val['second_cat'] = '';
                            $val['third_cat'] = '';
                            break;
                    }
                } else {
                    $val['first_cat'] = '';
                    $val['second_cat'] = '';
                    $val['third_cat'] = '';
                }
                $val['exchange_price'] = $val['shop_price'] - $val['exchange_integral'];
                $val['trade_type'] = trade_type($val['trade_type']);
                $val['supplier'] = $suppliers[$val['suppliers_id']] ?? '';
                $val['is_on_sale2'] = $val['is_on_sale2'] == 1 ? '上架' : '下架';
                $val['is_on_sale'] = $val['is_on_sale'] == 1 ? '上架' : '下架';
            }
        }
        $expCellName = [
            ['goods_sn', '货号', 20, 1],
            ['first_cat', '商品分类1', 20, 1],
            ['second_cat', '商品分类2', 20, 1],
            ['third_cat', '商品分类3', 20, 1],
            ['goods_name', '商品名称', 50, 1],
            ['goods_nature', '商品种类', 20, 1],
            ['cost_price', '成本价', 20, 0],
            ['shop_price', '本店售价', 20, 0],
            ['stax_price', '商品不含税价', 20, 0],
            ['exchange_price', '现金金额', 20, 0],
            ['ctax_price', '现金不含税价', 20, 0],
            ['exchange_integral', '积分兑换', 20, 0],
            ['retail_pv', '零售价pv', 20, 0],
            ['integral_pv', '积分价pv', 20, 0],
            ['trade_type', '交易条件选择', 20, 1],
            ['supplier', '供应商', 50, 1],
            ['original_img', '缩略图', 20, 1],
            ['is_on_sale2', '子系统上架状态', 20, 1],
            ['is_on_sale', '真正上架状态', 20, 1],
        ];
        exportExcel('产品列表', $expCellName, $goodsList, 'goods');
    }

    /**
     *  商品列表.
     */
    public function ajaxGoodsList()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . ' = 1';
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        ('' !== I('is_on_sale')) && $where = "$where and is_on_sale = " . I('is_on_sale');

        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        $sale_type = I('sale_type');
        if ($sale_type) {
            $where = "$where and  sale_type = " . I('sale_type');
        }
        $goods_nature = I('goods_nature');
        if ($goods_nature) {
            switch ($goods_nature) {
                case 1:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 2:
                    $where = "$where and is_abroad = 1 and is_supply = 0 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 3:
                    $where = "$where and is_abroad = 0 and is_supply = 1 and is_agent = 0 and is_abroad2 = 0";
                    break;
                case 4:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 1 and is_abroad2 = 0";
                    break;
                case 5:
                    $where = "$where and is_abroad = 0 and is_supply = 0 and is_agent = 0 and is_abroad2 = 1";
                    break;
            }
        }

        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= ' and cat_id in(' . implode(',', $grandson_ids) . ') '; // 初始化搜索条件
        }

        $is_area_show = I('is_area_show');
        switch ($is_area_show) {
            case 1:
                $where .= ' and is_area_show = 1';
                break;
            case 3:
                if (in_array($goods_nature, [0, 4])) {
                    $where .= ' and (applet_on_sale = 1 or is_agent = 1)';
                } else {
                    $where .= ' and applet_on_sale = 1';
                }
                break;
        }

        $goodsType = I('goods_type');
        switch ($goodsType) {
            case 1:
                $where .= ' and is_abroad = 0';
                break;
            case 2:
                $where .= ' and is_abroad = 1';
                break;
        }

        $onTimeBegin = I('on_time_begin');
        $onTimeEnd = I('on_time_end');
        if ($onTimeBegin && $onTimeEnd) {
            $onTimeBegin = strtotime($onTimeBegin);
            $onTimeEnd = strtotime($onTimeEnd);
            $where .= ' and on_time BETWEEN ' . $onTimeBegin . ' and ' . $onTimeEnd;
        }

        $outTimeBegin = I('out_time_begin');
        $outTimeEnd = I('out_time_end');
        if ($outTimeBegin && $outTimeEnd) {
            $outTimeBegin = strtotime($outTimeBegin);
            $outTimeEnd = strtotime($outTimeEnd);
            $where .= ' and out_time BETWEEN ' . $outTimeBegin . ' and ' . $outTimeEnd;
        }

        $count = M('Goods')->where($where)->count();
        $Page = new AjaxPage($count, 20);
        /**  搜索条件下 分页赋值
         * foreach($condition as $key=>$val) {
         * $Page->parameter[$key]   =   urlencode($val);
         * }
         */
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}, " . "sort desc, goods_id desc";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');

        $freight_template = Db::name('freight_template')->where('')->select();

        $this->assign('freight_template', $freight_template);
        $this->assign('catList', $catList);
        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show); // 赋值分页输出
        return $this->fetch();
    }

    public function stock_list()
    {
        $model = M('stock_log');
        $map = [];
        $mtype = I('mtype');
        if (1 == $mtype) {
            $map['stock'] = ['gt', 0];
        }
        if (-1 == $mtype) {
            $map['stock'] = ['lt', 0];
        }
        $goods_name = I('goods_name');
        if ($goods_name) {
            $map['goods_name'] = ['like', "%$goods_name%"];
        }
        $ctime = urldecode(I('ctime'));
        if ($ctime) {
            $gap = explode(' - ', $ctime);
            $this->assign('start_time', $gap[0]);
            $this->assign('end_time', $gap[1]);
            $this->assign('ctime', $gap[0] . ' - ' . $gap[1]);
            $map['ctime'] = [['gt', strtotime($gap[0])], ['lt', strtotime($gap[1])]];
        }
        $count = $model->where($map)->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('pager', $Page);
        $this->assign('page', $show); // 赋值分页输出
        $stock_list = $model->where($map)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('stock_list', $stock_list);

        return $this->fetch();
    }

    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\Goods();
        $goods_id = I('goods_id');
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新

        //ajax提交验证
        if ((1 == I('is_ajax')) && IS_POST) {
            $goodsInfo = M('goods')->where(['goods_id' => $goods_id])->field('is_abroad, is_supply, video')->find();
            // 数据验证
            $is_distribut = input('is_distribut');
            $virtual_indate = input('post.virtual_indate'); //虚拟商品有效期
            $return_url = $is_distribut > 0 ? U('admin/Distribut/goods_list') : U('admin/Goods/goodsList');
            $data = input('post.');
            $data['goods_type'] = $data['is_supply'] == 1 ? 0 : $data['goods_type'];
            $validate = \think\Loader::validate('Goods');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = [
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                ];
                $this->ajaxReturn($return_arr);
            }
            if ($data['is_agent'] == 1) {
                if ($goodsInfo['is_abroad'] == 1) {
                    $this->ajaxReturn(['status' => -1, 'msg' => '韩国购商品不能设为代理商商品']);
                }
                if ($goodsInfo['is_supply'] == 1) {
                    $this->ajaxReturn(['status' => -1, 'msg' => '供应链商品不能设为代理商商品']);
                }
            }
            $data['virtual_indate'] = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            $data['exchange_integral'] = (1 == $data['is_virtual']) ? 0 : $data['exchange_integral'];
            $data['video'] = $data['video_path'];
            //积分按系统默认比例 BY J
            if (1 == $data['exchange_integral_type'] && 1 != $data['is_virtual']) {
                $data['exchange_integral'] = $data['shop_price'] * tpCache('shopping.point_use_percent') / 100;
            } elseif (0 == $data['exchange_integral_type']) {
                $data['exchange_integral'] = 0;
            }

            if (1 == $data['is_commission']) {
                $data['commission'] = tpCache('distribut.default_rate');
            } elseif (0 == $data['is_commission']) {
                $data['commission'] = 0;
            }

            if (!empty($data['video_path'])) {
                if ($goodsInfo['video'] != $data['video_path']) {
                    // 处理视频封面图
                    $data['video'] = $data['video_path'];
                    $videoCover = getVideoCoverImages($data['video'], 'upload/goods/video_cover/temp/');
                    $data['video_cover'] = $videoCover['path'];
                    $data['video_axis'] = $videoCover['axis'];
                    unset($data['video_path']);
                }
            }

            $goods_item = I('item/a');
            $specStock = Db::name('spec_goods_price')->where('goods_id = ' . $goods_id)->getField('key,store_count,item_id,store_count,item_sn');
            if ($goods_item) {
                $store_count_item = 0;
                $is_can = true;
                foreach ($goods_item as $k => $v) {
                    // 批量添加数据
                    $v['store_count'] = trim($v['store_count']); // 记录商品总库存
                    $store_count_item = $store_count_item + $v['store_count'];
                    if ($data['trade_type'] == 1) {//非一键待发  不可以改库存
                        if ($v['store_count'] != $v['store_count']) {
                            $return_arr = [
                                'msg' => '仓库自发,不可以修改规格库存',
                                'status' => 0
                            ];
                            $is_can = false;
                            break;
                        }
                        if (empty($specStock[$k])) {
                            $return_arr = [
                                'msg' => '仓库自发,不可以新增规格',
                                'status' => 0
                            ];
                            $is_can = false;
                            break;
                        }
                        if (count($goods_item) != count($specStock)) {
                            $return_arr = [
                                'msg' => '仓库自发,不可以删除规格',
                                'status' => 0
                            ];
                            $is_can = false;
                            break;
                        }
                    }

                }
                if (!$is_can) {
                    $this->ajaxReturn($return_arr);
                }
                if ($store_count_item != $data['store_count']) {
                    $return_arr = [
                        'msg' => '子规格库存要等于主库存数',
                        'status' => 0
                    ];
                    $this->ajaxReturn($return_arr);
                }
            }

            if ($data['applet_on_sale2'] == 1) {
                if (M('goods')->where(['goods_id' => $goods_id])->value('prom_type')) {
                    $return_arr = [
                        'msg' => '小程序不能设置活动商品',
                        'status' => 0
                    ];
                    $this->ajaxReturn($return_arr);
                }
                if ($data['applet_on_time'] == 0 || $data['applet_out_time'] == 0) {
                    $return_arr = [
                        'msg' => '请设置小程序上下架时间',
                        'status' => 0
                    ];
                    $this->ajaxReturn($return_arr);
                }
                $data['applet_on_time'] = strtotime($data['applet_on_time']);
                $data['applet_out_time'] = strtotime($data['applet_out_time']);
            } else {
                $data['applet_on_time'] = 0;
                $data['applet_out_time'] = 0;
            }

            $Goods->data($data, true); // 收集数据
//            $Goods->on_time = time(); // 上架时间

            // 查看是否选择了韩国购分类
            $catId = I('cat_id');
            if (M('goods_category')->where(['id' => $catId, 'name' => ['LIKE', '%韩国购%']])->value('id')) {
                // 查看商品是否是韩国购商品
                if (!M('goods')->where(['goods_id' => $goods_id, 'is_abroad' => 1])->value('goods_id')) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '请选择韩国购商品']);
                }
            }

            I('cat_id_2') && ($Goods->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($Goods->cat_id = I('cat_id_3'));

            I('extend_cat_id_2') && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->spec_type = $Goods->goods_type;
            $price_ladder = [];
            if ($Goods->ladder_amount[0] > 0) {
                foreach ($Goods->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($Goods->ladder_amount[$key]);
                    $price_ladder[$key]['price'] = floatval($Goods->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $Goods->shop_price) {
                    $return_arr = [
                        'msg' => '价格阶梯最大金额不能大于商品原价！',
                        'status' => 0,
                        'data' => ['url' => $return_url],
                    ];
                    $this->ajaxReturn($return_arr);
                }
                if ($price_ladder[0]['amount'] <= 0 || $price_ladder[0]['price'] <= 0) {
                    $return_arr = [
                        'msg' => '您没有输入有效的价格阶梯！',
                        'status' => 0,
                        'data' => ['url' => $return_url],
                    ];
                    $this->ajaxReturn($return_arr);
                }
                $Goods->price_ladder = serialize($price_ladder);
            } else {
                $Goods->price_ladder = '';
            }

            $spec_item = I('item/a');
            if (2 == $type) {
                $goods_stock = M('goods')->where(['goods_id' => $goods_id])->getField('store_count');
                if (empty($spec_item) && $goods_stock != I('store_count')) {
                    update_stock_log(session('admin_id'), I('store_count') - $goods_stock, ['goods_id' => $goods_id, 'goods_name' => I('goods_name')]);
                }
                $Goods->isUpdate(true)->save(); // 写入数据到数据库
                // 修改商品后购物车的商品价格也修改一下
                M('cart')->where("goods_id = $goods_id and spec_key = ''")->where('type', 2)->save([
                    'market_price' => I('market_price'), //市场价
                    'goods_price' => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price'), // 会员折扣价
                ]);

                M('cart')->where("goods_id = $goods_id and spec_key = ''")->where('type', 1)->save([
                    'market_price' => I('market_price'), //市场价
                    'goods_price' => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price') - I('exchange_integral'), // 会员折扣价
                    'use_integral' => I('exchange_integral'), // 会员折扣价
                ]);
            } else {
                $Goods->save(); // 写入数据到数据库
                $goods_id = $insert_id = $Goods->getLastInsID();
                if (empty($spec_item)) {
                    update_stock_log(session('admin_id'), I('store_count'), ['goods_id' => $goods_id, 'goods_name' => I('goods_name')]); //库存日志
                }
            }
            $Goods->afterSave($goods_id);
            $GoodsLogic->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $GoodsLogic->saveGoodsTabs($goods_id, $data['tabs']);
            if (isset($data['goodsSpec'])) {
                // 处理商品规格属性
                $GoodsLogic->saveGoodsSpec($data['goodsSpec']);
            }
            $return_arr = [
                'status' => 1,
                'msg' => '操作成功',
                'data' => ['url' => $return_url],
            ];
            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = Db::name('Goods')->where('goods_id=' . I('GET.id', 0))->find();
        $goodsInfoData = [];
        if ($goodsInfo['price_ladder']) {
            $goodsInfoData['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }

        //积分类型输出 BY J
        if ($goodsInfo && 0 == $goodsInfo['exchange_integral']) {
            $goodsInfoData['exchange_integral_type'] = 0;
        } elseif (empty($goodsInfo) || ($goodsInfo['shop_price'] * tpCache('shopping.point_use_percent') / 100 == $goodsInfo['exchange_integral'])) {
            $goodsInfoData['exchange_integral_type'] = 1;
        } else {
            $goodsInfoData['exchange_integral_type'] = 2;
        }

        if ($goodsInfo && 0 == $goodsInfo['commission']) {
            $goodsInfoData['is_commission'] = 0;
        } elseif (empty($goodsInfo) || ($goodsInfo['commission'] == tpCache('distribut.default_rate'))) {
            $goodsInfoData['is_commission'] = 1;
        } else {
            $goodsInfoData['is_commission'] = 2;
        }

        $goodsInfo = $goodsInfo ?: [];
        $goodsInfo = array_merge($goodsInfo, $goodsInfoData);
        $level_cat = $GoodsLogic->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        $level_cat2 = $GoodsLogic->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = Db::name('goods_category')->where('parent_id = 0')->select(); // 已经改成联动菜单
//        $brandList = $GoodsLogic->getSortBrands($goodsInfo['cat_id']);   //获取三级分类下的全部品牌
        $brandList = $GoodsLogic->getSortBrands();
        $goodsType = Db::name('GoodsType')->select();
        $suppliersList = Db::name('suppliers')->select();
        $tabsList = Db::name('tabs')->getField('id,name,color');
        if (I('id')) {
            $goodsTabList = Db::name('goods_tab')->where('goods_id', 'eq', I('id'))->getField('tab_id,title,status');
        }
        foreach ($tabsList as $k => $v) {
            $tabsList[$k]['title'] = '';
            $tabsList[$k]['status'] = 0;
            if (isset($goodsTabList)) {
                $tabsList[$k]['status'] = $goodsTabList[$v['id']]['status'];
                $tabsList[$k]['title'] = $goodsTabList[$v['id']]['title'];
            }
        }

        // 代理商商品
        if ($goodsInfo['is_agent'] == 1 || $goodsInfo['applet_on_sale'] == 1) {
            $goodsInfo['applet_on_time'] = date('Y-m-d H:i:s', $goodsInfo['applet_on_time']);
            $goodsInfo['applet_out_time'] = date('Y-m-d H:i:s', $goodsInfo['applet_out_time']);
        }

        $distributList = Db::name('distribut_level')->select();
        $freight_template = Db::name('freight_template')->where('')->select();
        $this->assign('freight_template', $freight_template);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('tabsList', $tabsList);
        $this->assign('distributList', $distributList);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $this->assign('video_url', $goodsInfo['video'] ? \plugins\Oss::url($goodsInfo['video']) : '');  // 视频url

        $goodsImages = M('GoodsImages')->where('goods_id =' . I('GET.id', 0))->select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $goodsSeries = M('GoodsSeries')
            ->alias('gs')
            ->field('gs.*,g.store_count,g.goods_name,g.original_img as goods_images,g.shop_price as goods_price,sg.spec_img,CONCAT_WS(",",goods_name,sg.key_name) as spec_name,sg.price as spec_goods_price,sg.key')
            ->join('__GOODS__ g', 'g.goods_id = gs.g_id')
            ->join('__SPEC_GOODS_PRICE__ sg', 'sg.item_id = gs.item_id', 'left')
            ->where('gs.goods_id =' . I('GET.id', 0))
            ->select();
        foreach ($goodsSeries as $k => $v) {
            $goodsSeries[$k]['spec_image'] = '';
            if ($v['item_id'] > 0) {
                $goodsSeries[$k]['spec_image'] = M('spec_image')->where('spec_image_id', $v['key'])->where('goods_id', $v['g_id'])->getField('src');
            }
        }
        $this->assign('goodsSeries', $goodsSeries);

        if ($goodsInfo['is_supply'] == 1) {
            $goodsSpec = M('spec_goods_price')->where(['goods_id' => I('GET.id', 0)])->select();
            $this->assign('goodsSpec', $goodsSpec);
        }

        return $this->fetch('_goods');
    }

    public function getCategoryBindList()
    {
        $cart_id = I('cart_id/d', 0);
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands($cart_id);
        $this->ajaxReturn(['status' => 1, 'result' => $brandList]);
    }

    /**
     * 商品类型  用于设置商品的属性.
     */
    public function goodsTypeList()
    {
        $model = M('GoodsType');
        $count = $model->count();
        $Page = $pager = new Page($count, 14);
        $show = $Page->show();
        $goodsTypeList = $model->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('pager', $pager);
        $this->assign('show', $show);
        $this->assign('goodsTypeList', $goodsTypeList);

        return $this->fetch('goodsTypeList');
    }

    /**
     * 添加修改编辑  商品属性类型.
     */
    public function addEditGoodsType()
    {
        $id = $this->request->param('id', 0);
        $model = M('GoodsType');
        if (IS_POST) {
            $data = $this->request->post();
            if ($id) {
                DB::name('GoodsType')->update($data);
            } else {
                DB::name('GoodsType')->insert($data);
            }

            $this->success('操作成功!!!', U('Admin/Goods/goodsTypeList'));
            exit;
        }
        $goodsType = $model->find($id);
        $this->assign('goodsType', $goodsType);

        return $this->fetch('_goodsType');
    }

    /**
     * 商品属性列表.
     */
    public function goodsAttributeList()
    {
        $goodsTypeList = M('GoodsType')->select();
        $this->assign('goodsTypeList', $goodsTypeList);

        return $this->fetch();
    }

    /**
     *  商品属性列表.
     */
    public function ajaxGoodsAttributeList()
    {
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件
        I('type_id') && $where = "$where and type_id = " . I('type_id');
        // 关键词搜索
        $model = M('GoodsAttribute');
        $count = $model->where($where)->count();
        $Page = new AjaxPage($count, 13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $goodsTypeList = M('GoodsType')->getField('id,name');
        $attr_input_type = [0 => '手工录入', 1 => ' 从列表中选择', 2 => ' 多行文本框'];
        $this->assign('attr_input_type', $attr_input_type);
        $this->assign('goodsTypeList', $goodsTypeList);
        $this->assign('goodsAttributeList', $goodsAttributeList);
        $this->assign('page', $show); // 赋值分页输出
        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品属性.
     */
    public function addEditGoodsAttribute()
    {
        $model = D('GoodsAttribute');
        $type = I('attr_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        $attr_values = str_replace('_', '', I('attr_values')); // 替换特殊字符
        $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符
        $attr_values = trim($attr_values);

        $post_data = input('post.');
        $post_data['attr_values'] = $attr_values;

        if ((1 == I('is_ajax')) && IS_POST) {//ajax提交验证
            // 数据验证
            $validate = \think\Loader::validate('GoodsAttribute');
            if (!$validate->batch()->check($post_data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = [
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                ];
                $this->ajaxReturn($return_arr);
            } else {
                $model->data($post_data, true); // 收集数据

                if (2 == $type) {
                    $model->isUpdate(true)->save(); // 写入数据到数据库
                } else {
                    $model->save(); // 写入数据到数据库
                    $insert_id = $model->getLastInsID();
                }
                $return_arr = [
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => ['url' => U('Admin/Goods/goodsAttributeList')],
                ];
                $this->ajaxReturn($return_arr);
            }
        }
        // 点击过来编辑时
        $attr_id = I('attr_id/d', 0);
        $goodsTypeList = M('GoodsType')->select();
        $goodsAttribute = $model->find($attr_id);
        $this->assign('goodsTypeList', $goodsTypeList);
        $this->assign('goodsAttribute', $goodsAttribute);

        return $this->fetch('_goodsAttribute');
    }

    /**
     * 更改指定表的指定字段.
     */
    public function updateField()
    {
        $primary = [
            'goods' => 'goods_id',
            'goods_category' => 'id',
            'brand' => 'id',
            'goods_attribute' => 'attr_id',
            'ad' => 'ad_id',
        ];
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];
        $model->save();
        $return_arr = [
            'status' => 1,
            'msg' => '操作成功',
            'data' => ['url' => U('Admin/Goods/goodsAttributeList')],
        ];
        $this->ajaxReturn($return_arr);
    }

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型.
     */
    public function ajaxGetAttrInput()
    {
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'], $_REQUEST['type_id']);
        exit($str);
    }

    /**
     * 删除商品
     */
    public function delGoods()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！', 'data' => '']);
        $goods_ids = rtrim($ids, ',');
        // 判断此商品是否有订单
        $ordergoods_count = Db::name('OrderGoods')->whereIn('goods_id', $goods_ids)->group('goods_id')->getField('goods_id', true);
        if ($ordergoods_count) {
            $goods_count_ids = implode(',', $ordergoods_count);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$goods_count_ids}】的商品有订单,不得删除!", 'data' => '']);
        }
        // 商品团购
        $groupBuy_goods = M('group_buy')->whereIn('goods_id', $goods_ids)->group('goods_id')->getField('goods_id', true);
        if ($groupBuy_goods) {
            $groupBuy_goods_ids = implode(',', $groupBuy_goods);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$groupBuy_goods_ids}】的商品有团购,不得删除!", 'data' => '']);
        }

        //删除用户收藏商品记录
        M('GoodsCollect')->whereIn('goods_id', $goods_ids)->delete();

        // 删除此商品
        M('Goods')->whereIn('goods_id', $goods_ids)->delete();  //商品表
        M('cart')->whereIn('goods_id', $goods_ids)->delete();  // 购物车
        M('comment')->whereIn('goods_id', $goods_ids)->delete();  //商品评论
        M('goods_consult')->whereIn('goods_id', $goods_ids)->delete();  //商品咨询
        M('goods_images')->whereIn('goods_id', $goods_ids)->delete();  //商品相册
        M('spec_goods_price')->whereIn('goods_id', $goods_ids)->delete();  //商品规格
        M('spec_image')->whereIn('goods_id', $goods_ids)->delete();  //商品规格图片
        M('goods_attr')->whereIn('goods_id', $goods_ids)->delete();  //商品属性
        M('goods_collect')->whereIn('goods_id', $goods_ids)->delete();  //商品收藏
        M('goods_tab')->whereIn('goods_id', $goods_ids)->delete();  //商品标签

        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/goods/goodsList')]);
    }

    /**
     * 删除商品类型.
     */
    public function delGoodsType()
    {
        // 判断 商品规格
        $id = $this->request->param('id');
        $count = M('Spec')->where("type_id = {$id}")->count('1');
        $count > 0 && $this->error('该类型下有商品规格不得删除!', U('Admin/Goods/goodsTypeList'));
        // 判断 商品属性
        $count = M('GoodsAttribute')->where("type_id = {$id}")->count('1');
        $count > 0 && $this->error('该类型下有商品属性不得删除!', U('Admin/Goods/goodsTypeList'));
        // 删除分类
        M('GoodsType')->where("id = {$id}")->delete();
        $this->success('操作成功!!!', U('Admin/Goods/goodsTypeList'));
    }

    /**
     * 删除商品属性.
     */
    public function delGoodsAttribute()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        $attrBute_ids = rtrim($ids, ',');
        // 判断 有无商品使用该属性
        $count_ids = Db::name('GoodsAttr')->whereIn('attr_id', $attrBute_ids)->group('attr_id')->getField('attr_id', true);
        if ($count_ids) {
            $count_ids = implode(',', $count_ids);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$count_ids}】的属性有商品正在使用,不得删除!"]);
        }
        // 删除 属性
        M('GoodsAttribute')->whereIn('attr_id', $attrBute_ids)->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功!', 'url' => U('Admin/Goods/goodsAttributeList')]);
    }

    /**
     * 删除商品规格
     */
    public function delGoodsSpec()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        $aspec_ids = rtrim($ids, ',');
        // 判断 商品规格项
        $count_ids = M('SpecItem')->whereIn('spec_id', $aspec_ids)->group('spec_id')->getField('spec_id', true);
        if ($count_ids) {
            $count_ids = implode(',', $count_ids);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$count_ids}】规格，清空规格项后才可以删除!"]);
        }
        // 删除分类
        M('Spec')->whereIn('id', $aspec_ids)->delete();
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功!!!', 'url' => U('Admin/Goods/specList')]);
    }

    /**
     * 品牌列表.
     */
    public function brandList()
    {
        $ossLogic = new OssLogic();
        if (IS_POST) {
            $param = I('post.');
            // 配置
            foreach ($param as $k => $v) {
                switch ($k) {
                    case 'banner':
                        if (strstr($v['url'], 'aliyuncs.com')) {
                            // 原图
                            $v['url'] = M('brand_config')->where(['type' => 'official'])->value('url');
                        } else {
                            // 新图
                            $filePath = PUBLIC_PATH . substr($v['url'], strrpos($v['url'], '/public/') + 8);
                            $fileName = substr($v['url'], strrpos($v['url'], '/') + 1);
                            $object = 'image/' . date('Y/m/d/H/') . $fileName;
                            $return_url = $ossLogic->uploadFile($filePath, $object);
                            if (!$return_url) {
                                $this->error('图片上传错误');
                            } else {
                                // 图片信息
                                $imageInfo = getimagesize($filePath);
                                $v['url'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                                unlink($filePath);
                            }
                        }
                        $data = [
                            'type' => $k,
                            'name' => isset($v['name']) ? $v['name'] : '',
                            'url' => isset($v['url']) ? $v['url'] : '',
                            'content' => isset($v['content']) ? $v['content'] : '',
                        ];
                        $config = M('brand_config')->where(['type' => $k])->find();
                        if (!empty($config)) {
                            M('brand_config')->where(['id' => $config['id']])->update($data);
                        } else {
                            M('brand_config')->add($data);
                        }
                        break;
                }
            }
            $this->success('操作成功', U('Goods/brandList'));
        }
        // 配置
        $brandConfig = M('brand_config')->select();
        $config = [];
        foreach ($brandConfig as $val) {
            if ($val['type'] == 'banner' && !empty($val['url'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $ossLogic::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            }
            $config[$val['type']] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }
        // 品牌列表
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : '';
        $count = Db::name('Brand')->where($where)->count();
        $Page = new Page($count, 10);
        $brandList = Db::name('Brand')->where($where)->order('sort desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($brandList as &$brand) {
            if (!empty($brand['logo'])) {
                $logo = explode(',', $brand['logo']);
                $brand['logo'] = $ossLogic::url(substr($logo[0], strrpos($logo[0], 'img:') + 4));
            }
            if (!empty($brand['banner'])) {
                $banner = explode(',', $brand['banner']);
                $brand['banner'] = $ossLogic::url(substr($banner[0], strrpos($banner[0], 'img:') + 4));
            }
        }

        $this->assign('config', $config);
        $this->assign('page', $Page);
        $this->assign('brandList', $brandList);
        return $this->fetch('brandList');
    }

    /**
     * 添加修改编辑  商品品牌.
     */
    public function addEditBrand()
    {
        $ossLogic = new OssLogic();
        $id = I('id');
        if (IS_POST) {
            $data = I('post.');
            $brandValidate = Loader::validate('Brand');
            if (!$brandValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '操作失败', 'result' => $brandValidate->getError()];
                $this->ajaxReturn($return);
            }
            if (!empty($data['logo'])) {
                if (strstr($data['logo'], 'aliyuncs.com')) {
                    // 原图
                    $data['logo'] = M('brand')->where(['id' => $id])->value('logo');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($data['logo'], strrpos($data['logo'], '/public/') + 8);
                    $fileName = substr($data['logo'], strrpos($data['logo'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $ossLogic->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $data['logo'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
            }
            if (!empty($data['banner'])) {
                if (strstr($data['banner'], 'aliyuncs.com')) {
                    // 原图
                    $data['banner'] = M('brand')->where(['id' => $id])->value('banner');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($data['banner'], strrpos($data['banner'], '/public/') + 8);
                    $fileName = substr($data['banner'], strrpos($data['banner'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $ossLogic->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $data['banner'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
            }
            if ($id) {
                Db::name('Brand')->where('id', $id)->update($data);
            } else {
                Db::name('Brand')->insert($data);
            }
            // 发送到仓储系统
            include_once "plugins/Tb.php";
            $TbLogic = new \Tb();
            $TbLogic->add_tb(3, 2, 0, 0);

            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result' => '']);
        }
        $brand = M('Brand')->find($id);
        if (!empty($brand['logo'])) {
            $logo = explode(',', $brand['logo']);
            $brand['logo'] = $ossLogic::url(substr($logo[0], strrpos($logo[0], 'img:') + 4));
        }
        if (!empty($brand['banner'])) {
            $banner = explode(',', $brand['banner']);
            $brand['banner'] = $ossLogic::url(substr($banner[0], strrpos($banner[0], 'img:') + 4));
        }

        $this->assign('brand', $brand);
        return $this->fetch('_brand');
    }

    /**
     * 删除品牌.
     */
    public function delBrand()
    {
        $id = I('id', '');
        empty($id) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        // 判断此品牌是否有商品在使用
        $goods_count = Db::name('Goods')->where('brand_id', $id)->group('brand_id')->count();
        if ($goods_count) {
            $this->ajaxReturn(['status' => -1, 'msg' => '该品牌有商品在用不得删除!', 'data' => '']);
        }
        $res = Db::name('Brand')->where('id', $id)->delete();
        if ($res) {
            // 发送到仓储系统
            include_once "plugins/Tb.php";
            $TbLogic = new \Tb();
            $TbLogic->add_tb(3, 2, 0, 0);

            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '操作失败', 'data' => '']);
    }

    /**
     * 商品规格列表.
     */
    public function specList()
    {
        $goodsTypeList = M('GoodsType')->select();
        $this->assign('goodsTypeList', $goodsTypeList);

        return $this->fetch();
    }

    /**
     *  商品规格列表.
     */
    public function ajaxSpecList()
    {
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件
        I('type_id') && $where = "$where and type_id = " . I('type_id');
        // 关键词搜索
        $model = D('spec');
        $count = $model->where($where)->count();
        $Page = new AjaxPage($count, 13);
        $show = $Page->show();
        $specList = $model->where($where)->order('`type_id` desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $GoodsLogic = new GoodsLogic();
        foreach ($specList as $k => $v) {       // 获取规格项
            $arr = $GoodsLogic->getSpecItem($v['id']);
            $specList[$k]['spec_item'] = implode(' , ', $arr);
        }

        $this->assign('specList', $specList);
        $this->assign('page', $show); // 赋值分页输出
        $goodsTypeList = M('GoodsType')->select(); // 规格分类
        $goodsTypeList = convert_arr_key($goodsTypeList, 'id');
        $this->assign('goodsTypeList', $goodsTypeList);

        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品规格
     */
    public function addEditSpec()
    {
        $model = D('spec');
        $id = I('id/d', 0);
        if ((1 == I('is_ajax')) && IS_POST) {//ajax提交验证
            // 数据验证
            $validate = \think\Loader::validate('Spec');
            $post_data = I('post.');
            $scene = $id > 0 ? 'edit' : 'add';
            if (!$validate->scene($scene)->batch()->check($post_data)) {  //验证数据
                $error = $validate->getError();
                $error_msg = array_values($error);
                $this->ajaxReturn(['status' => -1, 'msg' => $error_msg[0], 'data' => $error]);
            }
            $model->data($post_data, true); // 收集数据
            if ('edit' == $scene) {
                $model->isUpdate(true)->save(); // 写入数据到数据库
                $model->afterSave(I('id'));
            } else {
                $model->save(); // 写入数据到数据库
                $insert_id = $model->getLastInsID();
                $model->afterSave($insert_id);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U('Admin/Goods/specList')]);
        }
        // 点击过来编辑时
        $spec = DB::name('spec')->find($id);
        $GoodsLogic = new GoodsLogic();
        $items = $GoodsLogic->getSpecItem($id);
        $spec[items] = implode(PHP_EOL, $items);
        $this->assign('spec', $spec);

        $goodsTypeList = M('GoodsType')->select();
        $this->assign('goodsTypeList', $goodsTypeList);

        return $this->fetch('_spec');
    }

    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框.
     */
    public function ajaxGetSpecSelect()
    {
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;
        $GoodsLogic = new GoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('Spec')->where('type_id = ' . I('get.spec_type/d'))->order('`order` desc')->select();
        foreach ($specList as $k => $v) {
            $specList[$k]['spec_item'] = M('SpecItem')->where('spec_id = ' . $v['id'])->order('id')->getField('id,item');
        } // 获取规格项

        $items_id = M('SpecGoodsPrice')->where('goods_id = ' . $goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);

        // 获取商品规格图片
        if ($goods_id) {
            $specImageList = M('SpecImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');
        }
        $this->assign('specImageList', $specImageList);

        $this->assign('items_ids', $items_ids);
        $this->assign('specList', $specList);

        return $this->fetch('ajax_spec_select');
    }

    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框.
     */
    public function ajaxGetSpecInput()
    {
        $GoodsLogic = new GoodsLogic();
        $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
        $str = $GoodsLogic->getSpecInput($goods_id, I('post.spec_arr/a', [[]]));
        exit($str);
    }

    /**
     * 删除商品相册图.
     */
    public function del_goods_images()
    {
        $path = I('filename', '');
        M('goods_images')->where("image_url = '$path'")->delete();
    }

    /**
     * 初始化商品关键词搜索.
     */
    public function initGoodsSearchWord()
    {
        $searchWordLogic = new SearchWordLogic();
        $successNum = $searchWordLogic->initGoodsSearchWord();
        $this->success('成功初始化' . $successNum . '个搜索关键词');
    }

    /**
     * 初始化地址json文件.
     */
    public function initLocationJsonJs()
    {
        $goodsLogic = new GoodsLogic();
        $region_list = $goodsLogic->getRegionList(); //获取配送地址列表
        file_put_contents(ROOT_PATH . 'public/js/locationJson.js', 'var locationJsonInfoDyr = ' . json_encode($region_list, JSON_UNESCAPED_UNICODE) . ';');
        $this->success('初始化地区json.js成功。文件位置为' . ROOT_PATH . 'public/js/locationJson.js');
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
     * 获取分类信息
     */
    public function ajaxCategoryList()
    {
        $level = I('level', 1);

        $where = [];
        if ($level != 0) {
            $where['level'] = $level;
        }
        $category = M('goods_category')->where($where)->field('id, name title')->select();
        $this->ajaxReturn(['status' => 1, 'result' => $category]);
    }

    /**
     * 批量删除商品图片
     */
    public function delGoodsImages()
    {
        $imageIds = I('img_ids', '');
        if (!$imageIds) $this->ajaxReturn(['status' => 0, 'msg' => '删除失败']);
        Db::startTrans();
        $imageIds = explode(',', $imageIds);
        M('goods_images')->where(['img_id' => ['IN', $imageIds]])->delete();
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    /**
     * 小程序上架处理
     */
    public function appletOnSale()
    {
        $goodsId = I('goods_id');
        $onSale = I('on_sale');
        $data = [
            'applet_on_sale' => $onSale,
            'applet_on_sale2' => $onSale,
        ];
        switch ($onSale) {
            case 0:
                $data['applet_out_time'] = NOW_TIME;
                break;
            case 1:
                $goods = M('goods')->where(['goods_id' => $goodsId])->find();
                if ($goods['is_on_sale'] != 1) {
                    $return_arr = [
                        'msg' => '商品未真正上架',
                        'status' => 0
                    ];
                    $this->ajaxReturn($return_arr);
                }
                if ($goods['store_count'] == 0) {
                    $return_arr = [
                        'msg' => '商品库存不足',
                        'status' => 0
                    ];
                    $this->ajaxReturn($return_arr);
                }
                if ($goods['prom_type']) {
                    $return_arr = [
                        'msg' => '小程序不能设置活动商品',
                        'status' => 0
                    ];
                    $this->ajaxReturn($return_arr);
                }
                $data['applet_on_time'] = NOW_TIME;
                $data['applet_out_time'] = strtotime('+12 month');
                break;
        }
        M('goods')->where(['goods_id' => $goodsId])->update($data);
        $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
    }

    /**
     * 商品种类提示
     * @return mixed
     */
    public function categoryTips()
    {
        if (IS_POST) {
            $param = I('post.');
            foreach ($param as $k => $v) {
                $data = [
                    'type' => $k,
                    'title' => isset($v['title']) ? $v['title'] : '',
                    'content' => isset($v['content']) ? $v['content'] : '',
                ];
                $categoryTips = M('goods_category_tips')->where(['type' => $k])->find();
                if (!empty($categoryTips)) {
                    M('goods_category_tips')->where(['id' => $categoryTips['id']])->update($data);
                } else {
                    M('goods_category_tips')->add($data);
                }
            }
            $this->success('操作成功', U('Admin/Goods/categoryTips'));
        }
        $categoryTips = M('goods_category_tips')->select();
        $tips = [];
        foreach ($categoryTips as $val) {
            $tips[$val['type']] = [
                'title' => $val['title'],
                'content' => $val['content']
            ];
        }
        $this->assign('tips', $tips);
        return $this->fetch('category_tips');
    }

    /**
     * 导入excel更新商品信息
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public function uploadEditGoods()
    {
        $goodsFile = request()->file('goodsFile');
        if (empty($goodsFile)) {
            $this->error('请先上传文件', U('Admin/Goods/goodsList'));
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        $path = ROOT_PATH . 'public/upload/goods/excel';
        $file_name = date('YmdHis');
        $info = $goodsFile->validate(['size' => 200000000, 'ext' => 'csv,xls,xlsx'])->move($path, $file_name);
        if ($info) {
            //上传成功 获取上传文件信息
            $file = $info->getPathName();
            if (file_exists($file)) {
                $goodsFile = iconv("utf-8", "gb2312", $file);   //转码
                include_once "plugins/PHPExcel.php";
                $objRead = new \PHPExcel_Reader_Excel2007();   //建立reader对象
                if (!$objRead->canRead($goodsFile)) {
                    $objRead = new \PHPExcel_Reader_Excel5();
                    if (!$objRead->canRead($goodsFile)) {
                        die('No Excel!');
                    }
                }

                $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
                $obj = $objRead->load($file);  //建立excel对象
                $currSheet = $obj->getSheet(0);   //获取指定的sheet表
                $columnH = $currSheet->getHighestColumn();   //取得最大的列号
                $columnCnt = array_search($columnH, $cellName);
                $rowCnt = $currSheet->getHighestRow();   //获取总行数

                $data = array();
                for ($_row = 1; $_row <= $rowCnt; $_row++) {  //读取内容
                    for ($_column = 0; $_column <= $columnCnt; $_column++) {
                        $cellId = $cellName[$_column] . $_row;
                        $cellValue = $currSheet->getCell($cellId)->getValue();
                        //$cellValue = $currSheet->getCell($cellId)->getCalculatedValue();  #获取公式计算的值
                        if ($cellValue instanceof \PHPExcel_RichText) {   //富文本转换字符串
                            $cellValue = $cellValue->__toString();
                        }
                        $data[$_row][$cellName[$_column]] = $cellValue;
                    }
                }
                Db::startTrans();
                try {
                    foreach ($data as $k => $v) {
                        if ($k == 1 || $k == 2) continue;
                        $goodsId = M('goods')->where(['goods_sn' => trim($v['A'])])->value('goods_id');
                        if (!$goodsId) throw new Exception('商品编号：' . $v['A'] . '不存在');
                        // 商品主体表更新数据
                        $goodsUpdateData = [];
                        if (!empty($v['C'])) $goodsUpdateData['shop_price'] = trim($v['C']);
                        if (!empty($v['D'])) $goodsUpdateData['exchange_integral'] = trim($v['D']);
                        if (!empty($v['E'])) $goodsUpdateData['ctax_price'] = trim($v['E']);
                        if (!empty($v['F'])) $goodsUpdateData['stax_price'] = trim($v['F']);
                        if (!empty($v['G'])) $goodsUpdateData['cost_price'] = trim($v['G']);
                        if (!empty($v['H'])) $goodsUpdateData['retail_pv'] = trim($v['H']);
                        if (!empty($v['I'])) $goodsUpdateData['integral_pv'] = trim($v['I']);
                        if (!empty($v['J'])) $goodsUpdateData['give_integral'] = trim($v['J']);
                        if (!empty($v['K'])) $goodsUpdateData['buying_price'] = trim($v['K']);
                        if (!empty($v['L'])) $goodsUpdateData['retail_price'] = trim($v['L']);
                        if (!empty($v['M'])) $goodsUpdateData['buying_price_pv'] = trim($v['M']);
                        if (!empty($v['N'])) $goodsUpdateData['retail_price_pv'] = trim($v['N']);
                        if (!empty($goodsUpdateData)) {
                            M('goods')->where(['goods_id' => $goodsId])->update($goodsUpdateData);
                        }
                        if (!empty($v['B'])) {
                            $itemId = M('spec_goods_price')->where(['goods_id' => $goodsId, 'key' => trim($v['B'])])->value('item_id');
                            if (!$itemId) throw new Exception('商品编号：' . $v['A'] . '的规格' . $v['B'] . '不存在');
                            // 商品规格表更新数据
                            $specUpdateData = [];
                            if (!empty($v['O'])) $specUpdateData['price'] = $v['O'];
                            M('spec_goods_price')->where(['item_id' => $itemId])->update($specUpdateData);
                        }
                    }
                    Db::commit();
                    $this->success('导出处理成功', U('Admin/Goods/goodsList'));
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error('导入失败——' . $e->getMessage(), U('Admin/Goods/goodsList'));
                }
            }
        } else {
            $this->error($goodsFile->getError(), U('Admin/Goods/goodsList'));
        }
    }

    /**
     * 免运费处理
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function freeShipping()
    {
        $goodsId = I('goods_id');
        $isFreeShipping = I('is_free_shipping', 0);
        M('goods')->where(['goods_id' => $goodsId])->update(['is_free_shipping' => $isFreeShipping]);
        if ($isFreeShipping == 0) {
            $freight_template = Db::name('freight_template')->where('')->select();
            if (!empty($freight_template))
                M('goods')->where(['goods_id' => $goodsId])->update(['template_id' => $freight_template[0]['template_id']]);
            $select = '<select name="template_id"><option value="0">请选择运费模板</option>';
            foreach ($freight_template as $k => $item) {
                $select .= '<option value="' . $item['template_id'] . '"';
                if ($k == 0) {
                    $select .= ' selected="selected"';
                }
                $select .= '>' . $item['template_name'] . '</option>';
            }
            $select .= '</select><input type="hidden" value="' . $goodsId . '">';
            $this->ajaxReturn(['status' => 2, 'msg' => '处理成功', 'res' => $select]);
        } else {
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
        }
    }

    /**
     * 更改运费模板
     */
    public function changeFreightTemplate()
    {
        $goodsId = I('goods_id');
        $templateId = I('template_id', 0);
        if ($templateId == 0 && M('goods')->where(['goods_id' => $goodsId])->value('is_free_shipping') == 0) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择运费模板']);
        }
        M('goods')->where(['goods_id' => $goodsId])->update(['template_id' => $templateId]);
        $this->ajaxReturn(['status' => 1, 'msg' => '修改运费模板成功']);
    }

    /**
     * ajax获取商品分类
     */
    public function ajaxGetCategory()
    {
        $level = I('level', 0);
        $where = [];
        if ($level) {
            $where['level'] = $level;
        }
        $category = M('goods_category')->where($where)->select();
        $this->ajaxReturn(['status' => 1, 'res' => $category]);
    }

    /**
     * 删除主推商品
     */
    public function delRecommendGoods(){
        $id = I('id', '');
        empty($id) && $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        $res = Db::name('goods_recommend')->where('id', $id)->delete();
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => -1, 'msg' => '操作失败', 'data' => '']);
    }

    /**
     * 主推产品列表
     * @return mixed
     */
    public function recommendGoodsList()
    {
        $ossLogic = new OssLogic();
        $count = M('goods_recommend')->count();
        $page = new Page($count, 10);
        $goodsList = M('goods_recommend gr')->join('goods g', 'g.goods_id = gr.goods_id')
            ->order('gr.sort DESC')->field('gr.*, g.goods_name, g.shop_price, g.exchange_integral')->select();
        foreach ($goodsList as &$goods) {
            if (!empty($goods['image'])) {
                $image = explode(',', $goods['image']);
                $goods['image'] = $ossLogic::url(substr($image[0], strrpos($image[0], 'img:') + 4));
            }
            if (!empty($goods['video'])) {
                $goods['video'] = $ossLogic::url($goods['video']);
            }
            $goods['exchange_price'] = bcsub($goods['shop_price'], $goods['exchange_integral'], 2);
        }
        $this->assign('page', $page);
        $this->assign('goods_list', $goodsList);
        return $this->fetch('recommend_goods_list');
    }

    /**
     * 添加编辑主推产品
     * @return mixed
     */
    public function addEditRecommendGoods()
    {
        $ossLogic = new OssLogic();
        $id = I('id');
        if (IS_POST) {
            $param = I('post.', []);
            if (empty($param['image']) && empty($param['video'])) {
                $this->ajaxReturn(['status' => 0, 'msg' => '请上传主推图或者主推视频']);
            }
            // 主推图
            if (!empty($param['image'])) {
                if (strstr($param['image'], 'aliyuncs.com')) {
                    // 原图
                    $param['image'] = M('goods_recommend')->where(['id' => $id])->value('image');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($param['image'], strrpos($param['image'], '/public/') + 8);
                    $fileName = substr($param['image'], strrpos($param['image'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $ossLogic->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $param['image'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
                $param['video'] = '';
                $param['video_cover'] = '';
                $param['video_axis'] = 1;
            }
            // 主推视频
            if (!empty($param['video'])) {
                if (strstr($param['video'], 'aliyuncs.com')) {
                    // 原本的视频
                    $param['video'] = substr($param['video'], strrpos($param['video'], 'video'));
                } else {
                    // 处理视频封面图
                    $videoCover = getVideoCoverImages($param['video'], 'upload/goods/video_cover/temp/');
                    $param['video_cover'] = $videoCover['path'];
                    $param['video_axis'] = $videoCover['axis'];
                }
                $param['image'] = '';
            }
            if ($id) {
                M('goods_recommend')->where(['id' => $id])->update($param);
            } else {
                M('goods_recommend')->add($param);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '处理成功']);
        }
        if ($id) {
            $goods = M('goods_recommend')->where(['id' => $id])->find();
            if (!empty($goods['image'])) {
                $image = explode(',', $goods['image']);
                $goods['image'] = $ossLogic::url(substr($image[0], strrpos($image[0], 'img:') + 4));
            }
            if (!empty($goods['video'])) {
                $goods['video'] = $ossLogic::url($goods['video']);
            }
            $goods['goods_name'] = M('goods')->where(['goods_id' => $goods['goods_id']])->value('goods_name');
            $this->assign('goods', $goods);
        }
        return $this->fetch('recommend_goods_addEdit');
    }

    /**
     * 商品设置
     * @return mixed
     */
    public function goodsConfig()
    {
        $ossLogic = new OssLogic();
        if (IS_POST) {
            $param = I('post.');
            // 配置
            foreach ($param as $k => $v) {
                if (strstr($v['url'], 'aliyuncs.com')) {
                    // 原图
                    $v['url'] = M('goods_config')->where(['type' => $k])->value('url');
                } else {
                    // 新图
                    $filePath = PUBLIC_PATH . substr($v['url'], strrpos($v['url'], '/public/') + 8);
                    $fileName = substr($v['url'], strrpos($v['url'], '/') + 1);
                    $object = 'image/' . date('Y/m/d/H/') . $fileName;
                    $return_url = $ossLogic->uploadFile($filePath, $object);
                    if (!$return_url) {
                        $this->error('图片上传错误');
                    } else {
                        // 图片信息
                        $imageInfo = getimagesize($filePath);
                        $v['url'] = 'img:' . $object . ',width:' . $imageInfo[0] . ',height:' . $imageInfo[1] . ',type:' . substr($imageInfo['mime'], strrpos($imageInfo['mime'], '/') + 1);
                        unlink($filePath);
                    }
                }
                $data = [
                    'type' => $k,
                    'name' => isset($v['name']) ? $v['name'] : '',
                    'url' => isset($v['url']) ? $v['url'] : '',
                    'content' => isset($v['content']) ? $v['content'] : '',
                ];
                $config = M('goods_config')->where(['type' => $k])->find();
                if (!empty($config)) {
                    M('goods_config')->where(['id' => $config['id']])->update($data);
                } else {
                    M('goods_config')->add($data);
                }
            }
            $this->success('操作成功', U('Goods/goodsConfig'));
        }
        // 配置
        $goodsConfig = M('goods_config')->select();
        $config = [];
        foreach ($goodsConfig as $val) {
            if (!empty($val['url'])) {
                $url = explode(',', $val['url']);
                $val['url'] = $ossLogic::url(substr($url[0], strrpos($url[0], 'img:') + 4));
            }
            $config[$val['type']] = [
                'name' => $val['name'],
                'url' => $val['url'],
                'content' => $val['content']
            ];
        }

        $this->assign('config', $config);
        return $this->fetch('goods_config');
    }
}
