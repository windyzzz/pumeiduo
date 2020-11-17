<?php

namespace app\home\controller\api;

use app\common\model\UserShareImage;
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

    /**
     * 获取二维码分享图与链接
     * @return \think\response\Json
     * @throws \Exception
     */
    public function qrCodeLink()
    {
        // 用户头像
        $headPic = $this->user['head_pic'];
        $headPicPath = simplifyPath($headPic);  // 过滤域名
        $headPicType = 'path';
        if (!$headPicPath) {
            // 网络图片
            $res = download_image($headPic, md5(mt_rand()) . '.jpg', PUBLIC_PATH . 'upload/share/temp/', 1, false);
            if ($res == false) {
                $headPicPath = 'public/images/default_head.png';
            } else {
                $headPicType = 'resource';
                $headPicPath = $res['save_path'] . $res['file_name'];
            }
        }
        // 用户昵称
        $nickname = $this->user['nickname'] ?? $this->user_id;

        $type = I('type', 1);
        $goodsId = I('goods_id', 0);
        $articleId = I('article_id', '');
        switch ($type) {
            case 1:
                // 推荐码
                $url = SITE_URL . '#/register?invite=' . $this->user_id;
                $title = '推荐码分享';
                $content = '推荐码分享';
                $image = '';
                // 二维码
                $qrPath = 'public/images/qrcode/user/user_' . $this->user_id . '_min.jpg';
                if (!file_exists(SITE_URL . '/' . $qrPath)) {
                    $logo = 'public/images/qrcode/qr_logo.png';
                    if (!file_exists($logo)) {
                        $logo = '';
                    }
                    $qrPath = create_qrcode('user', $this->user_id, $goodsId, $logo);
                }
                // 分享背景图
                $shareBg = M('share_bg')->where(['type' => 'user'])->getField('image', true);
                break;
            case 2:
                // 商品
                if (!$goodsId || $goodsId <= 0) return json(['status' => 0, 'msg' => '商品ID错误']);
                $url = SITE_URL . '#/goods/goods_details?goods_id=' . $goodsId . '&cart_type=0&invite=' . $this->user_id;
                $goodsInfo = M('goods')->where(['goods_id' => $goodsId])->field('goods_name, goods_remark, original_img')->find();
                $title = $goodsInfo['goods_name'];
                $content = !empty($goodsInfo['goods_remark']) ? $goodsInfo['goods_remark'] : $goodsInfo['goods_name'];
                $image = SITE_URL . $goodsInfo['original_img'];
                // 二维码
                $qrPath = 'public/images/qrcode/goods/goods_' . $this->user_id . '_' . $goodsId . '.png';
                if (!file_exists(SITE_URL . '/' . $qrPath)) {
                    $logo = 'public/images/qrcode/qr_logo.png';
                    if (!file_exists($logo)) {
                        $logo = '';
                    }
                    $qrPath = create_qrcode('goods', $this->user_id, $goodsId, $logo);
                }
                // 分享背景图
                $shareBg = M('share_bg')->where(['type' => 'goods'])->getField('image', true);
                break;
            case 3:
                // 活动文章
                if (!$articleId || $articleId <= 0) return json(['status' => 0, 'msg' => '文章ID错误']);
                $url = SITE_URL . '#/news/news_particulars?article_id=' . $articleId . '&invite=' . $this->user_id;
                $articleInfo = M('article')->where(['article_id' => $articleId])->field('title, description')->find();
                $title = $articleInfo['title'];
                $content = $articleInfo['description'];
                $image = SITE_URL . tpCache('share.article_logo');
                break;
            default:
                return json(['status' => 0, 'msg' => '类型错误']);
        }
        $userShareData = [];
        if (!empty($shareBg)) {
            foreach ($shareBg as $bg) {
                $bg = substr($bg, 1);
                if (file_exists($bg)) {
                    $pic1Path = PUBLIC_PATH . 'upload/share/temp/';
                    if (!file_exists($pic1Path)) {
                        mkdir($pic1Path, 0755, true);
                    }
                    $pic1Path = $pic1Path . md5(mt_rand()) . '.jpg';
                    copy($bg, $pic1Path);
                } else {
                    continue;
                }
                // 下部分图
                $pic2Path = PUBLIC_PATH . 'upload/share/temp/' . md5(mt_rand()) . '.jpg';
                copy('public/upload/share/pic2.png', $pic2Path);
                // 组合图片
                $res = $this->combinePic($pic1Path, $pic2Path, $nickname, $qrPath, $headPicType, $headPicPath, $this->user['head_pic']);
                if ($res) {
                    unlink($pic2Path);
                }
                $userShareData[] = [
                    'user_id' => $this->user_id,
                    'head_pic' => $headPicType == 'resource' ? $headPicPath : '',
                    'share_pic' => $pic1Path,
                    'add_time' => NOW_TIME
                ];
            }
        }
        if (!empty($userShareData)) {
            // 把之前的本地图片都删除
            $beforeUserShare = M('user_share_image')->where(['user_id' => $this->user_id])->select();
            foreach ($beforeUserShare as $item) {
                if (!empty($item['head_pic']) && file_exists($item['head_pic'])) {
                    unlink($item['head_pic']);
                }
                if (!empty($item['share_pic']) && file_exists($item['share_pic'])) {
                    unlink($item['share_pic']);
                }
            }
            M('user_share_image')->where(['user_id' => $this->user_id])->delete();
            // 增加新记录
            $userShareImage = new UserShareImage();
            $userShareImage->saveAll($userShareData);
        }
        // 输出图片数据
        $userShareList = [];
        foreach ($userShareData as $share) {
            $imgInfo = getimagesize($share['share_pic']);
            if (empty($imgInfo)) {
                continue;
            }
            $userShareList[] = [
                'img' => SITE_URL . substr($share['share_pic'], strrpos($share['share_pic'], '/public')),
                'width' => $imgInfo[0],
                'height' => $imgInfo[1],
                'type' => substr($imgInfo['mime'], strrpos($imgInfo['mime'], '/') + 1),
            ];
        }
        $return = [
            'share_link' => [
                'url' => $url,
                'title' => $title,
                'content' => $content,
                'image' => $image,
            ],
            'share_image' => $userShareList
        ];
        return json(['status' => 1, 'result' => $return]);
    }

    /**
     * 组合图片
     * @param $pic1_path
     * @param $pic2_path
     * @param $nickname
     * @param $qr_path
     * @param $head_pic_type
     * @param $head_pic_path
     * @param $head_pic_resource
     * @return bool
     */
    private function combinePic($pic1_path, $pic2_path, $nickname, $qr_path, $head_pic_type, $head_pic_path, $head_pic_resource)
    {
        /*
         * 上部分图
         */
        $ext1 = pathinfo($pic1_path);
        $pic1 = imagecreatefromstring(file_get_contents($pic1_path));
        $pic1_width = imagesx($pic1);
        $pic1_height = imagesy($pic1);
        // 用户头像
        $head_pic_path = img_radius($head_pic_type, $head_pic_path, $head_pic_resource, 0);     // 圆角处理
        $head_pic = imagecreatefromstring(file_get_contents($head_pic_path));
        $head_width = imagesx($head_pic);       // 头像原本宽
        $head_height = imagesy($head_pic);      // 头像原本高
        $head_pic1_width = 150;                  // 嵌入头像的宽
        $head_pic1_height = 150;                 // 嵌入头像的高
        $from_x = ($pic1_width - $head_pic1_width) / 2;   // 组合之后头像左上角所在坐标点x
        $from_y = 60;                                     // 组合之后头像左上角所在坐标点y
        imagecopyresampled($pic1, $head_pic, $from_x, $from_y, 0, 0, $head_pic1_width, $head_pic1_height, $head_width, $head_height);
        // 用户昵称
        $font = "public/upload/share/simhei.ttf";
        $nickname = '会员 ' . $nickname;
        $color = imagecolorallocate($pic1, 255, 255, 255);
        $fontPos = imagettfbbox(21, 0, $font, $nickname);       // 字体四角位置信息
        $font_x = ($pic1_width - $fontPos[2]) / 2;              // 嵌入字体x轴的位置
        $font_y = 260;                                          // 嵌入字体y轴的位置
        imagettftext($pic1, 21, 0, $font_x, $font_y, $color, $font, $nickname);
        // 输出图片
        switch ($ext1['extension']) {
            case 'jpg':
                imagejpeg($pic1, $pic1_path);
                break;
            case 'png':
                imagepng($pic1, $pic1_path);
                break;
        }
        imagedestroy($pic1);
        $pic1 = imagecreatefromstring(file_get_contents($pic1_path));
        // 背景图
        $bg_width = 810;    // 背景图片宽度
        $bg_height = 1302;   // 背景图片高度
        $background = imagecreatetruecolor($bg_width, $bg_height);  // 背景图片
        $color = imagecolorallocate($background, 255, 255, 255);
        imagefill($background, 0, 0, $color);
//        imageColorTransparent($background, $color);
        imagecopyresampled($background, $pic1, 0, 0, 0, 0, $pic1_width, $pic1_height, $pic1_width, $pic1_height);
        // 输出图片
        switch ($ext1['extension']) {
            case 'jpg':
                imagejpeg($background, $pic1_path);
                break;
            case 'png':
                imagepng($background, $pic1_path);
                break;
        }
        imagedestroy($background);
        /*
         * 下部分图
         */
        $ext2 = pathinfo($pic2_path);
        $pic2 = imagecreatefromstring(file_get_contents($pic2_path));
//        $pic2_width = imagesx($pic2);
//        $pic2_height = imagesy($pic2);
        // 二维码
        $qr = imagecreatefromstring(file_get_contents($qr_path));
        $qr_width = imagesx($qr);       // 二维码原本宽
        $qr_height = imagesy($qr);      // 二维码原本高
        $qr_pic2_width = 168;                  // 嵌入二维码的宽
        $qr_pic2_height = 168;                 // 嵌入二维码的高
        $from_x = 72;                   // 组合之后二维码左上角所在坐标点x
        $from_y = 30;                   // 组合之后二维码左上角所在坐标点y
        imagecopyresampled($pic2, $qr, $from_x, $from_y, 0, 0, $qr_pic2_width, $qr_pic2_height, $qr_width, $qr_height);
        // 输出图片
        switch ($ext2['extension']) {
            case 'jpg':
                imagejpeg($pic2, $pic2_path);
                break;
            case 'png':
                imagepng($pic2, $pic2_path);
                break;
        }
        imagedestroy($qr);
        // logo
        $logo_path = 'public/upload/share/logo.png';
        $logo = imagecreatefromstring(file_get_contents($logo_path));
        $logo_width = imagesx($logo);       // logo原本宽
        $logo_height = imagesy($logo);      // logo原本高
        $logo_pic2_width = 420;                  // 嵌入logo的宽
        $logo_pic2_height = 90;                 // 嵌入logo的高
        $from_x = 339;                   // 组合之后logo左上角所在坐标点x
        $from_y = 96;                   // 组合之后logo左上角所在坐标点y
        imagecopyresampled($pic2, $logo, $from_x, $from_y, 0, 0, $logo_pic2_width, $logo_pic2_height, $logo_width, $logo_height);
        // 输出图片
        switch ($ext2['extension']) {
            case 'jpg':
                imagejpeg($pic2, $pic2_path);
                break;
            case 'png':
                imagepng($pic2, $pic2_path);
                break;
        }
        imagedestroy($logo);
        /*
         * 上下图组合
         */
        $ext1 = pathinfo($pic1_path);
        $pic1 = imagecreatefromstring(file_get_contents($pic1_path));
//        $pic1_width = imagesx($pic1);
        $pic1_height = imagesy($pic1);
        $pic2 = imagecreatefromstring(file_get_contents($pic2_path));
        $pic2_width = imagesx($pic2);
        $pic2_height = imagesy($pic2);
        $from_x = 0;                                    // 组合的下图左上角所在坐标点x
        $from_y = ($pic1_height - $pic2_height);        // 组合的下图左上角所在坐标点y
        imagecopyresampled($pic1, $pic2, $from_x, $from_y, 0, 0, $pic2_width, $pic2_height, $pic2_width, $pic2_height);
        // 输出图片
        switch ($ext1['extension']) {
            case 'jpg':
                imagejpeg($pic1, $pic1_path);
                break;
            case 'png':
                imagepng($pic1, $pic1_path);
                break;
        }
        imagedestroy($pic1);
        imagedestroy($pic2);
//        // 圆角处理
//        img_radius('path', $pic1_path, '', 30);
        return true;
    }
}