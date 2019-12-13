<?php

namespace app\home\controller\api;


use think\Request;

class Suggestion
{
    /**
     * 获取反馈类型
     * @return \think\response\Json
     */
    public function getCate()
    {
        $cateList = M('suggestion_cate')->field('id, name')->order('sort')->select();
        return json(['status' => 1, 'result' => $cateList]);
    }

    /**
     * 提交建议
     * @param Request $request
     * @return \think\response\Json
     */
    public function add(Request $request)
    {
        $post = $request->post();
        // 验证参数
        $validate = validate('Suggestion');
        if (!$validate->check($post)) {
            return json(['status' => 0, 'msg' => $validate->getError()]);
        }
        $phone = $post['phone'];
        $cateId = $post['cate_id'];
        $content = $post['content'];
        // 防止频繁请求
        $res = cache($phone);
        if ($res) return json(['status' => 0, 'msg' => '请勿频繁提交']);
        cache($phone, 1, 5);
        // 记录数据
        $data = [
            'phone' => $phone,
            'cate_id' => $cateId,
            'content' => $content,
            'create_time' => time()
        ];
        M('suggestion')->add($data);
        return json(['status' => 1, 'msg' => '提交成功']);
    }
}