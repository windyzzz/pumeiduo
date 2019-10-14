<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\admin\controller;

class Import extends Base
{
    public function index()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    //上传的csv文件及图片文件 返回数组结果
    public function upload_data()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    public function add_data()
    {
        header('Content-type: text/html; charset=utf-8');
        exit('该功能暂未开放');
    }

    /**
     * csv文件转码为utf8.
     *
     * @param  string 文件路径
     *
     * @return resource 打开文件后的资源类型
     */
    private function fopen_utf8($filename)
    {
        $encoding = '';
        $handle = fopen($filename, 'r');
        $bom = fread($handle, 2);
        //fclose($handle);
        rewind($handle);

        if ($bom === chr(0xff).chr(0xfe) || $bom === chr(0xfe).chr(0xff)) {
            // UTF16 Byte Order Mark present
            $encoding = 'UTF-16';
        } else {
            $file_sample = fread($handle, 1000) + 'e'; //read first 1000 bytes
            // + e is a workaround for mb_string bug
            rewind($handle);
            $encoding = mb_detect_encoding($file_sample, 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP');
        }
        if ($encoding) {
            stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8');
        }

        return $handle;
    }

    //csv文件读取为数组形式返回
    private function str_getcsv($string, $delimiter = ',', $enclosure = '"')
    {
        $fp = fopen('php://temp/', 'r+');
        fputs($fp, $string);
        rewind($fp);
        while ($t = fgetcsv($fp, strlen($string), $delimiter, $enclosure)) {
            $r[] = $t;
        }
        if (1 == count($r)) {
            return current($r);
        }

        return $r;
    }
}
