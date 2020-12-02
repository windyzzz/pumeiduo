<?php

namespace app\home\controller\api;


use app\common\logic\supplier\GoodsService;
use think\Loader;

class Test
{


    public function checkSupplierGoods()
    {
        // 用户地址信息
        $userId = I('user_id');
        $userAddress = M('user_address')->where(['user_id' => $userId])->find();
        $province = M('region2')->where(['id' => $userAddress['province']])->value('ml_region_id');
        $city = M('region2')->where(['id' => $userAddress['city']])->value('ml_region_id');
        $district = M('region2')->where(['id' => $userAddress['district']])->value('ml_region_id');
        $town = M('region2')->where(['id' => $userAddress['twon']])->value('ml_region_id') ?? 0;
        // 商品信息
        $goodsId = I('goods_id');
        $goodsInfo = M('goods')->where(['goods_id' => $goodsId])->find();
        $specGoods = M('spec_goods_price')->where(['goods_id' => $goodsId])->find();
        $goodsData[] = [
            'goods_id' => $goodsInfo['supplier_goods_id'],
            'spec_key' => !empty($specGoods) ? $specGoods['key'] : '',
            'goods_num' => 1,
        ];
        $res = (new GoodsService())->checkGoodsRegion($goodsData, $province, $city, $district, $town);
        var_dump($res);
        exit();
    }


    public function combinePic($pic1_path, $head_pic_path, $nickname, $qr_path)
    {
        /*
         * 上部分图
         */
        $ext1 = pathinfo($pic1_path);
        $pic1 = imagecreatefromstring(file_get_contents($pic1_path));
        $pic1_width = imagesx($pic1);
        $pic1_height = imagesy($pic1);
        // 用户头像
//        $head_pic_path = $this->img_YJ($head_pic_path);     // 圆角处理
        $head_pic = imagecreatefromstring(file_get_contents($head_pic_path));
        $head_width = imagesx($head_pic);       // 头像原本宽
        $head_height = imagesy($head_pic);      // 头像原本高
        $head_pic1_width = 150;                  // 嵌入头像的宽
        $head_pic1_height = 150;                 // 嵌入头像的高
        $from_x = ($pic1_width - $head_pic1_width) / 2;   // 组合之后头像左上角所在坐标点x
        $from_y = 60;                                     // 组合之后头像左上角所在坐标点y
        imagecopyresampled($pic1, $head_pic, $from_x, $from_y, 0, 0, $head_pic1_width, $head_pic1_height, $head_width, $head_height);
        // 用户昵称
        $font = "public/upload/share/goods/simhei.ttf";
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
        $pic2_path = 'public/upload/share/goods/pic2.png';
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
        $logo_path = 'public/upload/share/goods/logo.png';
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
        exit();
    }


    function img_YJ($imgPath)
    {
        $ext = pathinfo($imgPath);
        $src_img = null;
        switch ($ext['extension']) {
            case 'jpg':
                $src_img = imagecreatefromjpeg($imgPath);
                break;
            case 'png':
                $src_img = imagecreatefrompng($imgPath);
                break;
        }
        $wh = getimagesize($imgPath);
        $w = $wh[0];
        $h = $wh[1];
        $w = min($w, $h);
        $h = $w;
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        // 拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $bg);
        $r = $w / 2;    // 圆半径
        $y_x = $r;      // 圆心X坐标
        $y_y = $r;      // 圆心Y坐标
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
            }
        }
        // 输出图片
        switch ($ext['extension']) {
            case 'jpg':
                $imgPath = substr($imgPath, 0, strrpos($imgPath, '.')) . '.jpg';
//                imagejpeg($img, $imgPath);    // jpeg格式图片无法设置透明度
                imagepng($img, $imgPath);
                break;
            case 'png':
                $imgPath = substr($imgPath, 0, strrpos($imgPath, '.')) . '.png';
                imagepng($img, $imgPath);
                break;
        }
        return $imgPath;
    }


    function img_YJ_v2()
    {
        $imgPath = 'public/upload/132.jpg';
        $radius = 0;
        $ext = pathinfo($imgPath);
        $src_img = null;
        switch ($ext['extension']) {
            case 'jpg':
                $src_img = imagecreatefromjpeg($imgPath);
                break;
            case 'png':
                $src_img = imagecreatefrompng($imgPath);
                break;
        }
        $wh = getimagesize($imgPath);
        $w = $wh[0];
        $h = $wh[1];
        $radius = $radius == 0 ? (min($w, $h) / 2) : $radius;
        $img = imagecreatetruecolor($w, $h);
        //这一句一定要有
        imagesavealpha($img, true);
        //拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $bg);
        $r = $radius; //圆 角半径
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                if (($x >= $radius && $x <= ($w - $radius)) || ($y >= $radius && $y <= ($h - $radius))) {
                    //不在四角的范围内,直接画
                    imagesetpixel($img, $x, $y, $rgbColor);
                } else {
                    //在四角的范围内选择画
                    //上左
                    $y_x = $r; //圆心X坐标
                    $y_y = $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //上右
                    $y_x = $w - $r; //圆心X坐标
                    $y_y = $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //下左
                    $y_x = $r; //圆心X坐标
                    $y_y = $h - $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //下右
                    $y_x = $w - $r; //圆心X坐标
                    $y_y = $h - $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                }
            }
        }
        // 输出图片
        switch ($ext['extension']) {
            case 'jpg':
                $imgPath = substr($imgPath, 0, strrpos($imgPath, '.')) . '.png';
//                imagejpeg($img, $imgPath);    // jpeg格式图片无法设置透明度
                imagepng($img, $imgPath);
                break;
            case 'png':
                $imgPath = substr($imgPath, 0, strrpos($imgPath, '.')) . '.png';
                imagepng($img, $imgPath);
                break;
        }
        return $imgPath;
    }
}
