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

use app\common\model\FreightTemplate;
use think\Db;
use think\Loader;
use think\Page;

class Freight extends Base
{
    public function index()
    {
        $FreightTemplate = new FreightTemplate();
        $count = $FreightTemplate->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $template_list = $FreightTemplate->append(['type_desc'])->with('freightConfig')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($template_list as $key => $val) {
            foreach ($val['freightConfig'] as $k => $v) {
                $freight_region = M('freight_region')
                    ->alias('f')
                    ->join('region2 r', 'r.id = f.region_id')
                    ->where(array('f.config_id' => $v['config_id']))
                    ->select();
                $template_list[$key]['freightConfig'][$k]['freightRegion'] = $freight_region;
            }
        }
        $this->assign('page', $show);
        $this->assign('template_list', $template_list);
        return $this->fetch();
    }

    public function info()
    {
        $template_id = input('template_id');
        if ($template_id) {
            $FreightTemplate = new FreightTemplate();
            $freightTemplate = $FreightTemplate->with('freightConfig')->where(['template_id' => $template_id])->find();
            if (empty($freightTemplate)) {
                $this->error('非法操作');
            }
            foreach ($freightTemplate['freightConfig'] as $k => $v) {
                $freight_region = M('freight_region')
                    ->alias('f')
                    ->join('region2 r', 'r.id = f.region_id')
                    ->where(array('f.config_id' => $v['config_id']))
                    ->select();
                $freightTemplate['freightConfig'][$k]['freightRegion'] = $freight_region;
                switch ($v['discount_type']) {
                    case 1:
                        $freightTemplate['freightConfig'][$k]['discount_condition'] = number_format($v['discount_condition']);
                        break;
                }
            }
            $this->assign('freightTemplate', $freightTemplate);
        }
        return $this->fetch();
    }

    /**
     *  保存运费模板
     *
     * @throws \think\Exception
     */
    public function save()
    {
        $template_id = input('template_id/d');
        $template_name = input('template_name/s');
        $type = input('type/d');
        $is_enable_default = input('is_enable_default/d');
        $is_out_setting = input('is_out_setting/d');
        $config_list = input('config_list/a', []);
        $data = input('post.');
        $freightTemplateValidate = Loader::validate('FreightTemplate');
        if (!$freightTemplateValidate->batch()->check($data)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => $freightTemplateValidate->getError()]);
        }
        Db::startTrans();
        if (empty($template_id)) {
            //添加模板
            $freightTemplate = new FreightTemplate();
        } else {
            //更新模板
            $freightTemplate = FreightTemplate::get(['template_id' => $template_id]);
        }
        $freightTemplate['template_name'] = $template_name;
        $freightTemplate['type'] = $type;
        $freightTemplate['is_enable_default'] = $is_enable_default ?? 0;
        $freightTemplate['is_out_setting'] = $is_out_setting ?? 0;
        $freightTemplate->save();
        $config_list_count = count($config_list);
        $config_id_arr = Db::name('freight_config')->where(['template_id' => $template_id])->getField('config_id', true);
        $update_config_id_arr = [];
        if ($config_list_count > 0) {
            for ($i = 0; $i < $config_list_count; ++$i) {
                // 是否有设置优惠运费
                switch ($config_list[$i]['discount_type']) {
                    case 0:
                        // 不设置
                        $config_list[$i]['discount_condition'] = 0;
                        $config_list[$i]['discount_money'] = 0;
                        break;
                    case 1:
                        // 按数量
                        $config_list[$i]['discount_condition'] = number_format($config_list[$i]['discount_condition']);
                        if ($config_list[$i]['discount_condition'] == 0) {
                            Db::rollback();
                            $this->ajaxReturn(['status' => 0, 'msg' => '请设置优惠条件', 'result' => '']);
                        }
                        if ($config_list[$i]['discount_money'] > number_format($config_list[$i]['first_money'], 2)) {
                            Db::rollback();
                            $this->ajaxReturn(['status' => 0, 'msg' => '设置优惠运费不能大于普通运费', 'result' => '']);
                        }
                }
                $freight_config_data = [
                    'first_unit' => $config_list[$i]['first_unit'],
                    'first_money' => $config_list[$i]['first_money'],
                    'continue_unit' => $config_list[$i]['continue_unit'],
                    'continue_money' => $config_list[$i]['continue_money'],
                    'discount_type' => $config_list[$i]['discount_type'],
                    'discount_condition' => $config_list[$i]['discount_condition'],
                    'discount_money' => $config_list[$i]['discount_money'],
                    'template_id' => $freightTemplate['template_id'],
                    'is_default' => $config_list[$i]['is_default'],
                ];
                if (empty($config_list[$i]['config_id'])) {
                    //新增配送区域
                    $config_id = Db::name('freight_config')->insertGetId($freight_config_data);
                    if (!empty($config_list[$i]['area_ids'])) {
                        $area_id_arr = explode(',', $config_list[$i]['area_ids']);
                        if (false !== $config_id) {
                            foreach ($area_id_arr as $areaKey => $areaVal) {
                                Db::name('freight_region')->add(['template_id' => $freightTemplate['template_id'], 'config_id' => $config_id, 'region_id' => $areaVal]);
                            }
                        }
                    }
                } else {
                    //更新配送区域
                    array_push($update_config_id_arr, $config_list[$i]['config_id']);
                    $config_result = Db::name('freight_config')->where(['config_id' => $config_list[$i]['config_id']])->save($freight_config_data);
                    if (false !== $config_result) {
                        Db::name('freight_region')->where(['config_id' => $config_list[$i]['config_id']])->delete();
                        if (!empty($config_list[$i]['area_ids'])) {
                            $area_id_arr = explode(',', $config_list[$i]['area_ids']);
                            foreach ($area_id_arr as $areaKey => $areaVal) {
                                Db::name('freight_region')->add(['template_id' => $freightTemplate['template_id'], 'config_id' => $config_list[$i]['config_id'], 'region_id' => $areaVal]);
                            }
                        }
                    }
                }
            }
        }
        $delete_config_id_arr = array_diff($config_id_arr, $update_config_id_arr);
        if (count($delete_config_id_arr) > 0) {
            Db::name('freight_region')->where(['config_id' => ['IN', $delete_config_id_arr]])->delete();
            Db::name('freight_config')->where(['config_id' => ['IN', $delete_config_id_arr]])->delete();
        }
        $this->checkFreightTemplate($freightTemplate->template_id);
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功', 'result' => '']);
    }

    /**
     * 删除运费模板
     *
     * @throws \think\Exception
     */
    public function delete()
    {
        $template_id = input('template_id');
        $action = input('action');
        if (empty($template_id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'result' => '']);
        }
        if ('confirm' != $action) {
            $goods_count = Db::name('goods')->where(['template_id' => $template_id])->count();
            if ($goods_count > 0) {
                $this->ajaxReturn(['status' => -1, 'msg' => '已有' . $goods_count . '种商品使用该运费模板，确定删除该模板吗？继续删除将把使用该运费模板的商品设置成包邮。', 'result' => '']);
            }
        }
        Db::name('goods')->where(['template_id' => $template_id])->update(['template_id' => 0, 'is_free_shipping' => 1]);
        Db::name('freight_region')->where(['template_id' => $template_id])->delete();
        Db::name('freight_config')->where(['template_id' => $template_id])->delete();
        $delete = Db::name('freight_template')->where(['template_id' => $template_id])->delete();
        if (false !== $delete) {
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功', 'result' => '']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '删除失败', 'result' => '']);
        }
    }

    public function area()
    {
        $province_list = Db::name('region2')->where(['parent_id' => 0, 'level' => 1])->select();
        $this->assign('province_list', $province_list);

        return $this->fetch();
    }

    /**
     * 检查模板，如果模板下没有配送区域配置，就删除该模板
     *
     * @param $template_id
     */
    private function checkFreightTemplate($template_id)
    {
        $freight_config = Db::name('freight_config')->where(['template_id' => $template_id])->find();
        if (empty($freight_config)) {
            Db::name('freight_template')->where('template_id', $template_id)->delete();
        }
    }
}
