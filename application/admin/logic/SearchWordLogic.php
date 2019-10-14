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

use app\common\util\ChineseSpell;
use think\db;
use think\Model;

/**
 * 秒杀逻辑定义
 * Class CatsLogic.
 */
class SearchWordLogic extends Model
{
    public function initGoodsSearchWord()
    {
        header('Content-Type:text/html;charset=utf-8');
        Db::query('TRUNCATE table '.C('database.prefix').'search_word');
        $goodsKeyWords = Db::name('goods')->field('keywords')->where('keywords', '<>', '')->distinct('keywords')->select();
        $chineseSpell = new ChineseSpell();
        $successNum = 0;
        foreach ($goodsKeyWords as $key => $val) {
            $string = str_replace(',', ' ', trim($val['keywords']));
            $string = str_replace('|', ' ', $string);
            $string = str_replace('，', ' ', $string);
            $search_add_data['keywords'] = $string;
            $keywords = strstr($string, ' ', true) ? strstr($string, ' ', true) : $string;
            $keywords_array = explode(' ', $string);
            foreach ($keywords_array as $k => $v) {
                if ('' != $v) {
                    $goods_where_like[] = '%'.$v.'%';
                }
            }
            $keywords_u8 = iconv('UTF-8', 'gb2312', $keywords);
            $py_full = $chineseSpell->getChineseSpells($keywords_u8);
            $py_long = pinyin_long($keywords);
            $search_add_data['pinyin_full'] = empty($py_full) ? $keywords : $py_full;
            $search_add_data['pinyin_simple'] = empty($py_long) ? $keywords : strtolower(pinyin_long($keywords));
            $search_add_data['search_num'] = 100;
            $goods_num = Db::name('goods')->where('goods_name|keywords', 'like', $goods_where_like)->count();
            $search_add_data['goods_num'] = $goods_num;
            $success = Db::name('search_word')->insert($search_add_data);
            if (false !== $success) {
                ++$successNum;
            }
            unset($goods_where_like);
        }

        return $successNum;
    }

    /**
     * 获取全拼
     *
     * @param $keyWord
     *
     * @return string
     */
    public function getPinyinFull($keyWord)
    {
        $chineseSpell = new ChineseSpell();
        $keywords_u8 = iconv('UTF-8', 'gb2312', $keyWord);
        $py_full = $chineseSpell->getChineseSpells($keywords_u8);

        return $py_full;
    }

    /**
     * 获取全拼
     *
     * @param $keyWord
     *
     * @return string
     */
    public function getPinyinSimple($keyWord)
    {
        return strtolower(pinyin_long($keyWord));
    }

    /**
     * 前台搜索关键词
     * 返回查询数组.
     *
     * @param $q|关键词
     *
     * @return array
     */
    public function getSearchWordWhere($q)
    {
        //引入
        $where = [];
        if (file_exists(PLUGIN_PATH.'coreseek/sphinxapi.php')) {
            require_once PLUGIN_PATH.'coreseek/sphinxapi.php';
            $cl = new \SphinxClient();
            $cl->SetServer(C('SPHINX_HOST').'', intval(C('SPHINX_PORT')));
            $cl->SetConnectTimeout(10);
            $cl->SetArrayResult(true);
            $cl->SetMatchMode(SPH_MATCH_ANY);
            $res = $cl->Query($q, 'mysql');
            if ($res) {
                $goods_id_array = [];
                if (array_key_exists('matches', $res)) {
                    foreach ($res['matches'] as $key => $value) {
                        $goods_id_array[] = $value['id'];
                    }
                }
                if (!empty($goods_id_array)) {
                    $where['goods_id'] = ['in', $goods_id_array];
                } else {
                    $where['goods_id'] = 0;
                }
            } else {
                $q_arr = explode(' ', $q);
                foreach ($q_arr as $key => $value) {
                    $q_arr[$key] = '%'.$value.'%';
                }
                $where['goods_name'] = ['like', $q_arr];
            }
        } else {
            $q_arr = explode(' ', $q);
            foreach ($q_arr as $key => $value) {
                $q_arr[$key] = '%'.$value.'%';
            }
            $where['goods_name'] = ['like', '%'.$q.'%'];
        }

        return $where;
    }
}
