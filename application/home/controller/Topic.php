<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace app\home\controller;

use think\Db;

class Topic extends Base
{
    /*
     * 专题列表
     */
    public function topicList()
    {
        $topicList = M('topic')->where('topic_state=2')->select();
        $this->assign('topicList', $topicList);

        return $this->fetch();
    }

    /*
     * 专题详情
     */
    public function detail()
    {
        $topic_id = I('topic_id/d', 1);
        $topic = Db::name('topic')->where('topic_id', $topic_id)->find();
        $this->assign('topic', $topic);

        return $this->fetch();
    }

    public function info()
    {
        $topic_id = I('topic_id/d', 1);
        $topic = Db::name('topic')->where('topic_id', $topic_id)->find();
        echo htmlspecialchars_decode($topic['topic_content']);
        exit;
    }
}
