<?php

namespace app\admin\controller;


use think\Page;

class GoodsSearch extends Base
{
    /**
     * 商品搜索词
     * @return \think\response\View
     */
    public function index()
    {
        $search_time_begin = I('search_time_begin');
        $search_time_end = I('search_time_end');
        if ($search_time_begin && $search_time_end) {
            $where['add_time'] = ['BETWEEN', [strtotime($search_time_begin), strtotime($search_time_end)]];
            $count = M('goods_search_log l')->join('goods_search s', 's.id = l.goods_search_id')->where($where)->group('goods_search_id')->count('goods_search_id');
            $page = new Page($count, 20);
            $list = M('goods_search_log l')->join('goods_search s', 's.id = l.goods_search_id')->where($where)->group('goods_search_id')->order('search_num DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
        } else {
            $count = M('goods_search')->count();
            $page = new Page($count, 20);
            $list = M('goods_search')->order('search_num DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
        }
        return view('goods/goods_search_list', compact('page', 'list', 'search_time_begin', 'search_time_end'));
    }

    /**
     * 商品搜索词记录
     * @return \think\response\View
     */
    public function log()
    {
        $id = I('id', 0);
        $where = ['goods_search_id' => $id];
        $search_time_begin = I('search_time_begin');
        $search_time_end = I('search_time_end');
        if ($search_time_begin && $search_time_end) {
            $where['add_time'] = ['BETWEEN', [strtotime($search_time_begin), strtotime($search_time_end)]];
        }
        $count = M('goods_search_log')->where($where)->count();
        $page = new Page($count, 20);
        $list = M('goods_search_log l')->join('users u', 'u.user_id = l.user_id', 'LEFT')->where($where)->order('add_time DESC')->limit($page->firstRow . ',' . $page->listRows)->field('l.*, u.nickname, u.user_name')->select();
        return view('goods/goods_search_log', compact('page', 'list', 'id', 'search_time_begin', 'search_time_end'));
    }
}
