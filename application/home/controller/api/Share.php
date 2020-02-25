<?php

namespace app\home\controller\api;

use think\Url;

class Share extends Base
{
    /**
     * 获取分享链接
     * @return \think\response\Json
     */
    public function linkShare()
    {
        $type = I('type', 0);
        $goodsId = I('goods_id', '');
        $articleId = I('article_id', '');

        Url::root('/');
        $baseUrl = url('/', '', '', true);
        switch ($type) {
            case 1:
                // 推荐码
                $url = $baseUrl . '#/register?invite=' . $this->user_id;
                $title = '推荐码分享';
                $content = '推荐码分享';
                $image = '';
                break;
            case 2:
                // 商品
                if (!$goodsId || $goodsId <= 0) return json(['status' => 0, 'msg' => '商品ID错误']);
                $url = $baseUrl . '#/goods/goods_details?goods_id=' . $goodsId . '&cart_type=0&invite=' . $this->user_id;
                $goodsInfo = M('goods')->where(['goods_id' => $goodsId])->field('goods_name, goods_remark, original_img')->find();
                $title = $goodsInfo['goods_name'];
                $content = !empty($goodsInfo['goods_remark']) ? $goodsInfo['goods_remark'] : $goodsInfo['goods_name'];
                $image = $baseUrl . $goodsInfo['original_img'];
                break;
            case 3:
                // 活动文章
                if (!$articleId || $articleId <= 0) return json(['status' => 0, 'msg' => '文章ID错误']);
                $url = $baseUrl . '#/news/news_particulars?article_id=' . $articleId . '&invite=' . $this->user_id;
                $articleInfo = M('article')->where(['article_id' => $articleId])->field('title, description')->find();
                $title = $articleInfo['title'];
                $content = $articleInfo['description'];
                $image = $baseUrl . tpCache('share.article_logo');
                break;
            default:
                return json(['status' => 0, 'msg' => '类型错误']);
        }
        $return = [
            'url' => $url,
            'title' => $title,
            'content' => $content,
            'image' => $image
        ];
        return json(['status' => 1, 'result' => $return]);
    }
}