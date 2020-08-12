<?php

namespace app\common\logic;


use think\Page;

class Community
{
    /**
     * 文章搜索条件
     * @param $param
     * @return array
     */
    private function articleWhere($param)
    {
        $where = ['ca.status' => 1];
        if (!empty($param['cate_id1'])) {
            $where['ca.cate_id1'] = $param['cate_id1'];
        }
        if (!empty($param['cate_id2'])) {
            $where['ca.cate_id2'] = $param['cate_id2'];
        }
        return $where;
    }

    /**
     * 文章搜索排序
     * @param $param
     * @return string
     */
    private function articleSort($param)
    {
        $sort = '';
        if (!empty($param['sort']) && !empty($param['order'])) {

        }
        $sort .= ' publish_time desc';
        return $sort;
    }

    /**
     * 文章列表
     * @param $param
     * @param int $num
     * @return array
     */
    public function getArticleList($param, $num = 20)
    {
        // 搜索条件
        $where = $this->articleWhere($param);
        // 排序
        $sort = $this->articleSort($param);
        // 查询数据
        $count = M('community_article ca')->where($where)->count();
        $page = new Page($count, $num);
        $articleList = M('community_article ca')
            ->join('users u', 'u.user_id = ca.user_id')
            ->join('goods g', 'g.goods_id = ca.goods_id')
            ->field('ca.*, u.nickname, u.user_name, u.head_pic, g.goods_name, g.original_img, g.shop_price, g.exchange_integral')
            ->where($where)->order($sort)->select();
        return ['total' => $count, 'list' => $articleList];
    }
}