<?php

namespace app\home\controller\api;


use think\Loader;

class Test
{

    public function combinePic()
    {
        /*
         * 上部分图
         */
//        $pic1_path = 'public/upload/share/goods/pic1.png';
//        $ext1 = pathinfo($pic1_path);
//        $pic1 = imagecreatefromstring(file_get_contents($pic1_path));
//        $pic1_width = imagesx($pic1);
//        $pic1_height = imagesy($pic1);
//        // 用户头像
//        $head_pic_path = 'public/upload/share/goods/12.jpg';
////        $head_pic_path = $this->img_YJ($head_pic_path);     // 圆角处理
//        $head_pic = imagecreatefromstring(file_get_contents($head_pic_path));
//        $head_width = imagesx($head_pic);       // 头像原本宽
//        $head_height = imagesy($head_pic);      // 头像原本高
//        $head_pic1_width = 112;                  // 嵌入头像的宽
//        $head_pic1_height = 112;                 // 嵌入头像的高
//        $from_x = ($pic1_width - $head_pic1_width) / 2;   // 组合之后头像左上角所在坐标点x
//        $from_y = 40;                                     // 组合之后头像左上角所在坐标点y
//        imagecopyresampled($pic1, $head_pic, $from_x, $from_y, 0, 0, $head_pic1_width, $head_pic1_height, $head_width, $head_height);
//        // 用户昵称
//        $font = "public/upload/share/goods/simhei.ttf";
//        $nickname = '会员 今晚打老虎';
//        $color = imagecolorallocate($pic1, 255, 255, 255);
//        $fontPos = imagettfbbox(14, 0, $font, $nickname);       // 字体四角位置信息
//        $font_x = ($pic1_width - $fontPos[2]) / 2;              // 嵌入字体x轴的位置
//        $font_y = 190;                                          // 嵌入字体y轴的位置
//        imagettftext($pic1, 14, 0, $font_x, $font_y, $color, $font, $nickname);
//        // 输出图片
//        switch ($ext1['extension']) {
//            case 'jpg':
//                imagejpeg($pic1, $pic1_path);
//                break;
//            case 'png':
//                imagepng($pic1, $pic1_path);
//                break;
//        }
//        imagedestroy($pic1);
//        $pic1 = imagecreatefromstring(file_get_contents($pic1_path));
//        // 背景图
//        $bg_width = 540;    // 背景图片宽度
//        $bg_height = 770;   // 背景图片高度
//        $background = imagecreatetruecolor($bg_width, $bg_height);  // 背景图片
//        $color = imagecolorallocate($background, 202, 201, 201);    // 为真彩色画布创建白色背景，再设置为透明
//        imagefill($background, 0, 0, $color);
//        imageColorTransparent($background, $color);
//        imagecopyresampled($background, $pic1, 0, 0, 0, 0, $pic1_width, $pic1_height, $pic1_width, $pic1_height);
//        // 输出图片
//        switch ($ext1['extension']) {
//            case 'jpg':
//                imagejpeg($background, $pic1_path);
//                break;
//            case 'png':
//                imagepng($background, $pic1_path);
//                break;
//        }
//        imagedestroy($background);
//        imagedestroy($pic1);
        /*
         * 下部分图
         */
        $pic2_path = 'public/upload/share/goods/pic2.png';
        $ext2 = pathinfo($pic2_path);
        $pic2 = imagecreatefromstring(file_get_contents($pic2_path));
        $pic2_width = imagesx($pic2);
        $pic2_height = imagesy($pic2);
        // 二维码
        $qr_path = 'public/upload/share/goods/goods_1_166.png';
        $qr = imagecreatefromstring(file_get_contents($qr_path));
        $qr_width = imagesx($qr);       // 二维码原本宽
        $qr_height = imagesy($qr);      // 二维码原本高
        $qr_pic2_width = 112;                  // 嵌入二维码的宽
        $qr_pic2_height = 112;                 // 嵌入二维码的高
        $from_x = 24;                   // 组合之后二维码左上角所在坐标点x
        $from_y = 10;                   // 组合之后二维码左上角所在坐标点y
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
        imagedestroy($pic2);
        imagedestroy($qr);
        exit();
    }

    public function splicingPic()
    {
        $pic_list = array(
            'public/upload/share/goods/11.jpg',
            'public/images/qrcode/goods/goods_1_166.png',
        );
        $pic_list = array_slice($pic_list, 0, 2);

        $bg_w = 270; // 背景图片宽度
        $bg_h = 458; // 背景图片高度
        $background = imagecreatetruecolor($bg_w, $bg_h); // 背景图片
        $color = imagecolorallocate($background, 202, 201, 201); // 为真彩色画布创建白色背景，再设置为透明
        imagefill($background, 0, 0, $color);
        imageColorTransparent($background, $color);

        $pic_count = 1;
        $lineArr = array(); // 需要换行的位置
        $space_x = 3;
        $space_y = 3;
        $line_x = 0;
        switch ($pic_count) {
            case 1: // 正中间
                $start_x = intval($bg_w / 4); // 开始位置X
                $start_y = intval($bg_h / 4); // 开始位置Y
                $pic_w = intval($bg_w / 2); // 宽度
                $pic_h = intval($bg_h / 2); // 高度
                break;
            case 2: // 中间位置并排
                $start_x = 2;
                $start_y = intval($bg_h / 4) + 3;
                $pic_w = intval($bg_w / 2) - 5;
                $pic_h = intval($bg_h / 2) - 5;
                $space_x = 5;
                break;
            case 3:
                $start_x = 40; // 开始位置X
                $start_y = 5; // 开始位置Y
                $pic_w = intval($bg_w / 2) - 5; // 宽度
                $pic_h = intval($bg_h / 2) - 5; // 高度
                $lineArr = array(2);
                $line_x = 4;
                break;
            case 4:
                $start_x = 4; // 开始位置X
                $start_y = 5; // 开始位置Y
                $pic_w = intval($bg_w / 2) - 5; // 宽度
                $pic_h = intval($bg_h / 2) - 5; // 高度
                $lineArr = array(3);
                $line_x = 4;
                break;
            case 5:
                $start_x = 30; // 开始位置X
                $start_y = 30; // 开始位置Y
                $pic_w = intval($bg_w / 3) - 5; // 宽度
                $pic_h = intval($bg_h / 3) - 5; // 高度
                $lineArr = array(3);
                $line_x = 5;
                break;
            case 6:
                $start_x = 5; // 开始位置X
                $start_y = 30; // 开始位置Y
                $pic_w = intval($bg_w / 3) - 5; // 宽度
                $pic_h = intval($bg_h / 3) - 5; // 高度
                $lineArr = array(4);
                $line_x = 5;
                break;
            case 7:
                $start_x = 53; // 开始位置X
                $start_y = 5; // 开始位置Y
                $pic_w = intval($bg_w / 3) - 5; // 宽度
                $pic_h = intval($bg_h / 3) - 5; // 高度
                $lineArr = array(2, 5);
                $line_x = 5;
                break;
            case 8:
                $start_x = 30; // 开始位置X
                $start_y = 5; // 开始位置Y
                $pic_w = intval($bg_w / 3) - 5; // 宽度
                $pic_h = intval($bg_h / 3) - 5; // 高度
                $lineArr = array(3, 6);
                $line_x = 5;
                break;
            case 9:
                $start_x = 5; // 开始位置X
                $start_y = 5; // 开始位置Y
                $pic_w = intval($bg_w / 3) - 5; // 宽度
                $pic_h = intval($bg_h / 3) - 5; // 高度
                $lineArr = array(4, 7);
                $line_x = 5;
                break;
        }
        foreach ($pic_list as $k => $pic_path) {
            $kk = $k + 1;
            if (in_array($kk, $lineArr)) {
                $start_x = $line_x;
                $start_y = $start_y + $pic_h + $space_y;
            }
            $pathInfo = pathinfo($pic_path);
            switch (strtolower($pathInfo['extension'])) {
                case 'jpg':
                case 'jpeg':
                    $imagecreatefromjpeg = 'imagecreatefromjpeg';
                    break;
                case 'png':
                    $imagecreatefromjpeg = 'imagecreatefrompng';
                    break;
                case 'gif':
                default:
                    $imagecreatefromjpeg = 'imagecreatefromstring';
                    $pic_path = file_get_contents($pic_path);
                    break;
            }
            $resource = $imagecreatefromjpeg($pic_path);
            // $start_x,$start_y copy图片在背景中的位置
            // 0,0 被copy图片的位置
            // $pic_w,$pic_h copy后的高度和宽度
            imagecopyresized($background, $resource, $start_x, $start_y, 0, 0, $pic_w, $pic_h, imagesx($resource), imagesy($resource)); // 最后两个参数为原始图片宽度和高度，倒数两个参数为copy时的图片宽度和高度
            $start_x = $start_x + $pic_w + $space_x;
        }

        // 输出图片
        imagepng($background, $pic_list[0]);
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
}