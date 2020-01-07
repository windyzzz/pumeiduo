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
                break;
            case 2:
                // 商品
                if (!$goodsId || $goodsId <= 0) return json(['status' => 0, 'msg' => '商品ID错误']);
                $url = $baseUrl . '#/goods/goods_details?goods_id=' . $goodsId . '&cart_type=0&invite=' . $this->user_id;
                break;
            case 3:
                // 活动文章
                if (!$articleId || $articleId <= 0) return json(['status' => 0, 'msg' => '文章ID错误']);
                $url = $baseUrl . '#/news/news_particulars?article_id=' . $articleId . '&invite=' . $this->user_id;
                break;
            default:
                return json(['status' => 0, 'msg' => '类型错误']);
        }
        $shareSetting = M('share_setting')->where(['type' => $type, 'is_open' => 1])->find();
        if (empty($shareSetting)) {
            return json(['status' => 0, 'msg' => '分享内容不存在，请管理员到后台添加']);
        }
        $return = [
            'url' => $url,
            'title' => $shareSetting['title'],
            'content' => $shareSetting['content'],
            'image' => $shareSetting['image'] ? $baseUrl . $shareSetting['image'] : ''
        ];
        return json(['status' => 1, 'result' => $return]);
    }


    public function imageShare()
    {

    }
}