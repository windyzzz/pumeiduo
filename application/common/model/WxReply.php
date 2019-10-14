<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\common\model;

use think\Model;

class WxReply extends Model
{
    //回复类型
    const TYPE_KEYWORD = 'keyword';
    const TYPE_FOLLOW = 'follow';
    const TYPE_DEFAULT = 'default';

    //回复消息类型
    const MSG_TEXT = 'text';
    const MSG_NEWS = 'news';

    public function wxKeywords()
    {
        return $this->hasMany('WxKeyword', 'pid', 'id');
    }

    public function getKeywordsAttr($value, $data)
    {
        if (self::TYPE_KEYWORD !== $data['type']) {
            return '';
        }
        $keywords = get_arr_column($this->wx_keywords, 'keyword');

        return implode(',', $keywords);
    }

    public static function getAllMsgType()
    {
        return [
            self::MSG_TEXT => '文本',
            self::MSG_NEWS => '图文',
        ];
    }

    public function getMsgTypeNameAttr($value, $data)
    {
        $types = self::getAllMsgType();
        if (key_exists($data['msg_type'], $types)) {
            return $types[$data['msg_type']];
        }

        return '未知类型';
    }

    public static function getAllType()
    {
        return [
            self::TYPE_KEYWORD => '关键字回复',
            self::TYPE_FOLLOW => '关注时回复',
            self::TYPE_DEFAULT => '默认回复',
        ];
    }
}
