<?php

namespace app\common\logic;


class GoodsSearchLogic
{
    /**
     * 商品关键词搜索记录
     * @param $keyword
     * @param int $userId
     */
    public function searchLog($keyword, $userId = 0)
    {
        $keyword = trim($keyword);
        $goodsSearchId = M('goods_search')->where(['keyword' => $keyword])->value('id');
        if ($goodsSearchId) {
            M('goods_search')->where(['id' => $goodsSearchId])->setInc('search_num', 1);
        } else {
            $goodsSearchId = M('goods_search')->add(['keyword' => $keyword]);
        }
        M('goods_search_log')->add([
            'goods_search_id' => $goodsSearchId,
            'user_id' => $userId ?? 0,
            'add_time' => NOW_TIME
        ]);
    }
}
