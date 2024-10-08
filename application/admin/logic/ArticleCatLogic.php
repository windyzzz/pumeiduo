<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\logic;

use think\Db;
use think\Model;

/**
 * 分类逻辑定义
 * Class CatsLogic.
 */
class ArticleCatLogic extends Model
{
    /**
     * 获得指定分类下的子分类的数组.
     *
     * @param int  $cat_id   分类的ID
     * @param int  $selected 当前选中分类的ID
     * @param bool $re_type  返回的类型: 值为真时返回下拉列表,否则返回数组
     * @param int  $level    限定返回的级数。为0时返回所有级数
     *
     * @return mix
     */
    public function article_cat_list($cat_id = 0, $selected = 0, $re_type = true, $level = 0)
    {
        static $res = null;

        if (null === $res) {
            $data = false; //read_static_cache('art_cat_pid_releate');
            if (false === $data) {
                $cat_type = I('cat_type/d');
                $where = [];
                if ('' != $cat_type) {
                    $where['c.cat_type'] = $cat_type;
                }
                $res = DB::name('article_cat')
                    ->field('c.*,count(s.cat_id) as has_children')
                    ->alias('c')
                    ->join('__ARTICLE_CAT__ s', 's.parent_id = c.cat_id', 'LEFT')
                    ->where($where)
                    ->group('c.cat_id')
                    ->order('parent_id,sort_order')
                    ->select();
            //write_static_cache('art_cat_pid_releate', $res);
            } else {
                $res = $data;
            }
        }

        if (true == empty($res)) {
            return $re_type ? '' : [];
        }

        $options = $this->article_cat_options($cat_id, $res); // 获得指定分类下的子分类的数组

        /* 截取到指定的缩减级别 */
        if ($level > 0) {
            if (0 == $cat_id) {
                $end_level = $level;
            } else {
                $first_item = reset($options); // 获取第一个元素
                $end_level = $first_item['level'] + $level;
            }

            /* 保留level小于end_level的部分 */
            foreach ($options as $key => $val) {
                if ($val['level'] >= $end_level) {
                    unset($options[$key]);
                }
            }
        }

        $pre_key = 0;
        foreach ($options as $key => $value) {
            $options[$key]['has_children'] = 1;
            if ($pre_key > 0) {
                if ($options[$pre_key]['cat_id'] == $options[$key]['parent_id']) {
                    $options[$pre_key]['has_children'] = 1;
                }
            }
            $pre_key = $key;
        }

        if (true == $re_type) {
            $select = '';
            foreach ($options as $var) {
                $select .= '<option value="'.$var['cat_id'].'" ';
                //$select .= ' cat_type="' . $var['cat_type'] . '" ';
                $select .= ($selected == $var['cat_id']) ? "selected='ture'" : '';
                $select .= '>';
                if ($var['level'] > 0) {
                    $select .= str_repeat('&nbsp;', $var['level'] * 4);
                }
                $select .= htmlspecialchars(addslashes($var['cat_name'])).'</option>';
            }

            return $select;
        }

        foreach ($options as $key => $value) {
            ///$options[$key]['url'] = build_uri('article_cat', array('acid' => $value['cat_id']), $value['cat_name']);
        }

        return $options;
    }

    /**
     * 过滤和排序所有文章分类，返回一个带有缩进级别的数组.
     *
     * @param int   $cat_id 上级分类ID
     * @param array $arr    含有所有分类的数组
     * @param int   $level  级别
     */
    public function article_cat_options($spec_cat_id, $arr)
    {
        static $cat_options = [];

        if (isset($cat_options[$spec_cat_id])) {
            return $cat_options[$spec_cat_id];
        }

        if (!isset($cat_options[0])) {
            $level = $last_cat_id = 0;
            $options = $cat_id_array = $level_array = [];
            while (!empty($arr)) {
                foreach ($arr as $key => $value) {
                    $cat_id = $value['cat_id'];
                    if (0 == $level && 0 == $last_cat_id) {
                        if ($value['parent_id'] > 0) {
                            break;
                        }

                        $options[$cat_id] = $value;
                        $options[$cat_id]['level'] = $level;
                        $options[$cat_id]['id'] = $cat_id;
                        $options[$cat_id]['name'] = $value['cat_name'];
                        unset($arr[$key]);

                        if (0 == $value['has_children']) {
                            continue;
                        }
                        $last_cat_id = $cat_id;
                        $cat_id_array = [$cat_id];
                        $level_array[$last_cat_id] = ++$level;
                        continue;
                    }

                    if ($value['parent_id'] == $last_cat_id) {
                        $options[$cat_id] = $value;
                        $options[$cat_id]['level'] = $level;
                        $options[$cat_id]['id'] = $cat_id;
                        $options[$cat_id]['name'] = $value['cat_name'];
                        unset($arr[$key]);

                        if ($value['has_children'] > 0) {
                            if (end($cat_id_array) != $last_cat_id) {
                                $cat_id_array[] = $last_cat_id;
                            }
                            $last_cat_id = $cat_id;
                            $cat_id_array[] = $cat_id;
                            $level_array[$last_cat_id] = ++$level;
                        }
                    } elseif ($value['parent_id'] > $last_cat_id) {
                        break;
                    }
                }

                $count = count($cat_id_array);
                if ($count > 1) {
                    $last_cat_id = array_pop($cat_id_array);
                } elseif (1 == $count) {
                    if ($last_cat_id != end($cat_id_array)) {
                        $last_cat_id = end($cat_id_array);
                    } else {
                        $level = 0;
                        $last_cat_id = 0;
                        $cat_id_array = [];
                        continue;
                    }
                }

                if ($last_cat_id && isset($level_array[$last_cat_id])) {
                    $level = $level_array[$last_cat_id];
                } else {
                    $level = 0;
                    break;
                }
            }
            $cat_options[0] = $options;
        } else {
            $options = $cat_options[0];
        }

        if (!$spec_cat_id) {
            return $options;
        }

        if (empty($options[$spec_cat_id])) {
            return [];
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options as $key => $value) {
            if ($key != $spec_cat_id) {
                unset($options[$key]);
            } else {
                break;
            }
        }

        $spec_cat_id_array = [];
        foreach ($options as $key => $value) {
            if (($spec_cat_id_level == $value['level'] && $value['cat_id'] != $spec_cat_id) ||
                    ($spec_cat_id_level > $value['level'])) {
                break;
            }

            $spec_cat_id_array[$key] = $value;
        }
        $cat_options[$spec_cat_id] = $spec_cat_id_array;

        return $spec_cat_id_array;
    }
}
