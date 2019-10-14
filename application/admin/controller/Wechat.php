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

use app\common\logic\wechat\WechatUtil;
use app\common\logic\WechatLogic;
use app\common\model\WxMaterial;
use app\common\model\WxNews;
use app\common\model\WxReply;
use app\common\model\WxTplMsg;
use think\AjaxPage;
use think\Db;
use think\Page;

class Wechat extends Base
{
    private $wx_user;

    public function __construct()
    {
        parent::__construct();
        $this->wx_user = Db::name('wx_user')->find();
    }

    public function index()
    {
        $wx_user = $this->wx_user;
        header('Location:'.U('Wechat/setting', ['id' => $wx_user['id']]));
        exit;
    }

    public function setting()
    {
        $id = I('get.id');
        if (!empty($id)) {
            $wechat = Db::name('wx_user')->where(['id' => $id])->find();
            if (!$wechat) {
                $this->error('公众号不存在');
                exit;
            }
            if (IS_POST) {
                $post_data = input('post.');
                $post_data['web_expires'] = 0;
                $row = Db::name('wx_user')->where(['id' => $id])->update($post_data);
                $row && exit($this->success('修改成功'));
                exit($this->error('修改失败'));
            }
            $apiurl = 'http://'.$_SERVER['HTTP_HOST'].'/index.php?m=Home&c=Weixin&a=index';

            $this->assign('wechat', $wechat);
            $this->assign('apiurl', $apiurl);
        } else {
            //不存在ID则添加
            $exist = $this->wx_user;
            if ($exist[0]['id'] > 0) {
                $this->error('只能添加一个公众号噢');
                exit;
            }
            if (IS_POST) {
                $data = input('post.');
                $data['token'] = get_rand_str(6, 1, 0);
                $data['create_time'] = time();
                $row = Db::name('wx_user')->insertGetId($data);
                if ($row) {
                    $this->success('添加成功', U('Admin/Wechat/setting', ['id' => $row]));
                } else {
                    $this->error('操作失败');
                }
                exit;
            }
        }

        return $this->fetch();
    }

    public function MaterialList()
    {
        $wechatObj = new WechatUtil($this->wx_user);
        $data = $wechatObj->getMaterialList('news', 0, 10);
        if (!$data) {
            dump($wechatObj->getError());
            exit;
        }
        dump($data);
        exit;
    }

    /**
     * 同步图文素材管理.
     */
    public function tongbuNews()
    {
        $wechatObj = new WechatUtil($this->wx_user);
        $num = 20;
        $offset = 0;
        $data = $wechatObj->getMaterialList('news', $offset, $num);

        /*        $data = array(
                    'item' => array(
                        array(
                            'media_id' => 'mYykOpUoFHHckVk5MRMWGqUJNCFzAYGhmaY41PIbyLg',
                            'content' => array(
                                'news_item' => array(
                                    array(
                                        'title' => 'test-jackchow',
                                        'author' => 'jackchow',
                                        'digest' => 'test-jackchow',
                                        'content' => 'asjdhgjkahsnjghjashdgjashgjkahsdjklghajsdhgjklashgjkshdgjklsahdgjkshag',
                                        'content_source_url' => 'https://mp.weixin.qq.com/s?__biz=MzI4MzA1MzU1NA==&tempkey=OTY3X3BEdnVKRUJCVnNPNElqV0N3WmJyeDNDcE5JcXlxNlhsazl2RVFVRExrTjdzSEw3bGxpRHJVSVJJVG00STVrYWlfdTctQVFQUzhVbFJYcEFWWXVIMjdjVm03Ukx3UHNwcmM4c0hCdFVzanc5aUhwd0ZnWDRKQk1DWFY3SUtnVjdHT2RYamliQklPNzlLSmNJeEpranZ2aGdJNWZJbVlfeVNBNDVfNkF%2Bfg%3D%3D&scene=18#rd',
                                        'thumb_media_id' => 'mYykOpUoFHHckVk5MRMWGhRw1_Il9EjqTrj-Gh8fhhY',
                                        'show_cover_pic' => 1,
                                        'url' => 'http://mp.weixin.qq.com/s?__biz=MzAwNzcwMjAwMw==&mid=307820419&idx=1&sn=9da4dfad32f99e4be390da26b9f2e8d4&chksm=0cd6431f3ba1ca09b8842e3b6fae2e4e5329f01b2ec1c09588ee61219333dde6bee5ec788725#rd',
                                        'thumb_url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0?wx_fmt=jpeg',
                                        'need_open_comment' => 0,
                                        'only_fans_can_comment' => 0,
                                    )
                                ),
                                'create_time' => 1533093198,
                                'update_time' => 1533093199,
                            ),
                            'update_time' => 1533093199
                        ),
                        array(
                            'media_id' => 'mYykOpUoFHHckVk5MRMWGr1qqRMEPoObIZuhupWbaBM',
                            'content' => array(
                                'news_item' => array(
                                    array(
                                        'title' => 'test',
                                        'author' => 'jack',
                                        'digest' => 'test',
                                        'content' => 'asjdhgjkahsnjghjashdgjashgjkahsdjklghajsdhgjklashgjkshdgjklsahdgjkshag',
                                        'content_source_url' => 'https://mp.weixin.qq.com/s?__biz=MzI4MzA1MzU1NA==&tempkey=OTY3X3BEdnVKRUJCVnNPNElqV0N3WmJyeDNDcE5JcXlxNlhsazl2RVFVRExrTjdzSEw3bGxpRHJVSVJJVG00STVrYWlfdTctQVFQUzhVbFJYcEFWWXVIMjdjVm03Ukx3UHNwcmM4c0hCdFVzanc5aUhwd0ZnWDRKQk1DWFY3SUtnVjdHT2RYamliQklPNzlLSmNJeEpranZ2aGdJNWZJbVlfeVNBNDVfNkF%2Bfg%3D%3D&scene=18#rd',
                                        'thumb_media_id' => 'mYykOpUoFHHckVk5MRMWGhRw1_Il9EjqTrj-Gh8fhhY',
                                        'show_cover_pic' => 1,
                                        'url' => 'http://mp.weixin.qq.com/s?__biz=MzAwNzcwMjAwMw==&mid=307820418&idx=1&sn=7713bfa159a24453a7811f4a32465124&chksm=0cd6431e3ba1ca0876818802c804e38372768da2150d86873aab00eb48c810e817b9588b2ff1#rd',
                                        'thumb_url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0?wx_fmt=jpeg',
                                        'need_open_comment' => 0,
                                        'only_fans_can_comment' => 0,
                                    )
                                ),
                                'create_time' => 1533088175,
                                'update_time' => 1533088175,
                            ),
                            'update_time' => 1533088175
                        )
                    ),
                    'total_count' => 2,
                    'item_count' => 2
                );*/
        if (!$data) {
            $this->error($wechatObj->getError());
        } else {
            $insert_arr = [];
            $insert_m_arr = [];

            /*$data = array(
                'item' => array(
                    array(
                        'media_id' => 'mYykOpUoFHHckVk5MRMWGqUJNCFzAYGhmaY41PIbyLg',
                        'content' => array(
                            'news_item' => array(
                                array(
                                    'title' => 'test-jackchow',
                                    'author' => 'jackchow',
                                    'digest' => 'test-jackchow',
                                    'content' => 'asjdhgjkahsnjghjashdgjashgjkahsdjklghajsdhgjklashgjkshdgjklsahdgjkshag',
                                    'content_source_url' => 'https://mp.weixin.qq.com/s?__biz=MzI4MzA1MzU1NA==&tempkey=OTY3X3BEdnVKRUJCVnNPNElqV0N3WmJyeDNDcE5JcXlxNlhsazl2RVFVRExrTjdzSEw3bGxpRHJVSVJJVG00STVrYWlfdTctQVFQUzhVbFJYcEFWWXVIMjdjVm03Ukx3UHNwcmM4c0hCdFVzanc5aUhwd0ZnWDRKQk1DWFY3SUtnVjdHT2RYamliQklPNzlLSmNJeEpranZ2aGdJNWZJbVlfeVNBNDVfNkF%2Bfg%3D%3D&scene=18#rd',
                                    'thumb_media_id' => 'mYykOpUoFHHckVk5MRMWGhRw1_Il9EjqTrj-Gh8fhhY',
                                    'show_cover_pic' => 1,
                                    'url' => 'http://mp.weixin.qq.com/s?__biz=MzAwNzcwMjAwMw==&mid=307820419&idx=1&sn=9da4dfad32f99e4be390da26b9f2e8d4&chksm=0cd6431f3ba1ca09b8842e3b6fae2e4e5329f01b2ec1c09588ee61219333dde6bee5ec788725#rd',
                                    'thumb_url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0?wx_fmt=jpeg',
                                    'need_open_comment' => 0,
                                    'only_fans_can_comment' => 0,
                                )
                            ),
                            'create_time' => 1533093198,
                            'update_time' => 1533093199,
                        ),
                        'update_time' => 1533093199
                    ),
                    array(
                        'media_id' => 'mYykOpUoFHHckVk5MRMWGr1qqRMEPoObIZuhupWbaBM',
                        'content' => array(
                            'news_item' => array(
                                array(
                                    'title' => 'test',
                                    'author' => 'jack',
                                    'digest' => 'test',
                                    'content' => 'asjdhgjkahsnjghjashdgjashgjkahsdjklghajsdhgjklashgjkshdgjklsahdgjkshag',
                                    'content_source_url' => 'https://mp.weixin.qq.com/s?__biz=MzI4MzA1MzU1NA==&tempkey=OTY3X3BEdnVKRUJCVnNPNElqV0N3WmJyeDNDcE5JcXlxNlhsazl2RVFVRExrTjdzSEw3bGxpRHJVSVJJVG00STVrYWlfdTctQVFQUzhVbFJYcEFWWXVIMjdjVm03Ukx3UHNwcmM4c0hCdFVzanc5aUhwd0ZnWDRKQk1DWFY3SUtnVjdHT2RYamliQklPNzlLSmNJeEpranZ2aGdJNWZJbVlfeVNBNDVfNkF%2Bfg%3D%3D&scene=18#rd',
                                    'thumb_media_id' => 'mYykOpUoFHHckVk5MRMWGhRw1_Il9EjqTrj-Gh8fhhY',
                                    'show_cover_pic' => 1,
                                    'url' => 'http://mp.weixin.qq.com/s?__biz=MzAwNzcwMjAwMw==&mid=307820418&idx=1&sn=7713bfa159a24453a7811f4a32465124&chksm=0cd6431e3ba1ca0876818802c804e38372768da2150d86873aab00eb48c810e817b9588b2ff1#rd',
                                    'thumb_url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0?wx_fmt=jpeg',
                                    'need_open_comment' => 0,
                                    'only_fans_can_comment' => 0,
                                )
                            ),
                            'create_time' => 1533088175,
                            'update_time' => 1533088175,
                        ),
                        'update_time' => 1533088175
                    )
                ),
                'total_count' => 2,
                'item_count' => 2
            );*/
            $list = $data['item'];
            $last_data = M('wx_material')->order('update_time desc')->find();
            foreach ($list as $k => $v) {
                if (!M('wx_material')->where('media_id', $v['media_id'])->find()) {
                    $insert_m_arr[$k]['media_id'] = $v['media_id'];
                    $insert_m_arr[$k]['type'] = 'news';
                    // $insert_m_arr[$k]['data'] = json_encode($v['content']);
                    $insert_m_arr[$k]['data'] = '';
                    $insert_m_arr[$k]['update_time'] = $v['update_time'];
                    $material_id = M('wx_material')->add($insert_m_arr[$k]);
                }
                if ($material_id && !M('wx_news')->where('thumb_media_id', $v['content']['news_item'][0]['thumb_media_id'])->find()) {
                    $insert_arr[$k]['material_id'] = $material_id;
                    $insert_arr[$k]['update_time'] = $v['update_time'];
                    $insert_arr[$k]['title'] = $v['content']['news_item'][0]['title'];
                    $insert_arr[$k]['author'] = $v['content']['news_item'][0]['author'];
                    // $insert_arr[$k]['content'] = $v['content']['news_item'][0]['content'];
                    $insert_arr[$k]['content'] = '';
                    $insert_arr[$k]['digest'] = $v['content']['news_item'][0]['digest'];
                    $insert_arr[$k]['thumb_url'] = $v['content']['news_item'][0]['thumb_url'];
                    $insert_arr[$k]['thumb_media_id'] = $v['content']['news_item'][0]['thumb_media_id'];
                    $insert_arr[$k]['content_source_url'] = $v['content']['news_item'][0]['content_source_url'];
                    $insert_arr[$k]['show_cover_pic'] = $v['content']['news_item'][0]['show_cover_pic'];
                    M('wx_news')->add($insert_arr[$k]);
                }
            }

            $this->success('同步完成！');
        }
    }

    public function uploadMaterial()
    {
        $path = APP_PATH.'../public/images/456.jpg';

        $wechatObj = new WechatUtil($this->wx_user);
        $data = $wechatObj->uploadMaterial($path, 'thumb');
        if (!$data) {
            dump($wechatObj->getError());
            exit;
        }
        dump($data);
        exit;
    }

    // array(2) {
    //   ["media_id"] => string(43) "mYykOpUoFHHckVk5MRMWGhNiTTYav7lQhdu4d0cvT1o"
    //   ["url"] => string(135) "http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0?wx_fmt=jpeg"
    // }

//     array(2) {
    //   ["media_id"] => string(43) "mYykOpUoFHHckVk5MRMWGh2c5m1RP4WkT76Ot-KvmbM"
    //   ["url"] => string(135) "http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0?wx_fmt=jpeg"
    // }

    //array(2) {
    //   ["media_id"] => string(43) "mYykOpUoFHHckVk5MRMWGhRw1_Il9EjqTrj-Gh8fhhY"
    //   ["url"] => string(135) "http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0?wx_fmt=jpeg"
    // }

    public function uploadNewsImage()
    {
        $path = APP_PATH.'../public/images/456.jpg';

        $wechatObj = new WechatUtil($this->wx_user);
        $data = $wechatObj->uploadNewsImage($path);
        if (!$data) {
            dump($wechatObj->getError());
            exit;
        }
        dump($data);
        exit;
    }

    //http://mmbiz.qpic.cn/mmbiz_jpg/mzWVLmblAPdz3PlRNKHgia7xl9sfpxSPKkySU0s4sYB8TqzBicWjqqcdTnnVlXLTiczHAe9ick6KOe49kjsu11IRgw/0

    public function uploadNews()
    {
        $wechatObj = new WechatUtil($this->wx_user);
        $article = [
            [
                'title' => 'test-jackchow',
                'thumb_media_id' => 'mYykOpUoFHHckVk5MRMWGhRw1_Il9EjqTrj-Gh8fhhY', //封面图片素材id
                'author' => 'jackchow',
                'digest' => 'test-jackchow', //图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空。如果本字段为没有填写，则默认抓取正文前64个字。
                'show_cover_pic' => 1, //是否显示封面，0为false，即不显示，1为true，即显示
                'content' => 'asjdhgjkahsnjghjashdgjashgjkahsdjklghajsdhgjklashgjkshdgjklsahdgjkshag',
                'content_source_url' => 'https://mp.weixin.qq.com/s?__biz=MzI4MzA1MzU1NA==&tempkey=OTY3X3BEdnVKRUJCVnNPNElqV0N3WmJyeDNDcE5JcXlxNlhsazl2RVFVRExrTjdzSEw3bGxpRHJVSVJJVG00STVrYWlfdTctQVFQUzhVbFJYcEFWWXVIMjdjVm03Ukx3UHNwcmM4c0hCdFVzanc5aUhwd0ZnWDRKQk1DWFY3SUtnVjdHT2RYamliQklPNzlLSmNJeEpranZ2aGdJNWZJbVlfeVNBNDVfNkF%2Bfg%3D%3D&scene=18#rd', //图文消息的原文地址，即点击“阅读原文”后的URL
            ],
        ];

        $data = $wechatObj->uploadNews($article);
        if (!$data) {
            dump($wechatObj->getError());
            exit;
        }
        dump($data);
        exit;
    }

    //mYykOpUoFHHckVk5MRMWGqUJNCFzAYGhmaY41PIbyLg
    public function menu()
    {
        $wechat = $this->wx_user;
        if (empty($wechat)) {
            $this->error('请先在公众号配置添加公众号，才能进行微信菜单管理', U('Admin/Wechat/index'));
        }
        if (IS_POST) {
            $post_menu = input('post.menu/a');
            //dump(json_encode($post_menu));//{"34":{"name":"\u666e\u7f8e\u591a","pid":"0","'sub_button'":{"35":{"name":"\u516c\u53f8\u7b80\u4ecb","type":"view_limited","value":"123"},"36":{"name":"\u516c\u53f8\u8363\u8a89","type":"view","value":"www.baidu.com"}}},"35":{"name":"\u4ea7\u54c1\u4fe1\u606f","pid":"0","'sub_button'":{"37":{"name":"\u4ea7\u54c1\u4fe1\u606f","type":"view","value":"www.baidu.com"},"38":{"name":"\u5386\u53f2\u63a8\u9001","type":"view","value":"www.baidu.com"}}},"36":{"name":"\u9760\u5703\u5546\u57ce","pid":"0","'sub_button'":{"39":{"name":"\u9000\u6362\u8d27","type":"view_limited","value":"456"},"40":{"name":"\u666e\u7f8e\u591a\u7f51","type":"view","value":"www.baidu.com"}}}}
            // dump($post_menu);
            // exit;
            //查询数据库是否存在
            $menu_list = Db::name('wx_menu')->where(['token' => $wechat['token']])->getField('id', true);
            foreach ($post_menu as $k => $v) {
                $v['token'] = $wechat['token'];
                if (in_array($k, $menu_list)) {
                    //更新
                    Db::name('wx_menu')->where(['id' => $k])->save($v);
                } else {
                    //插入
                    Db::name('wx_menu')->where(['id' => $k])->add($v);
                }
            }
            $this->success('操作成功,进入发布步骤', U('Admin/Wechat/pub_menu'));
            exit;
        }
        //获取最大ID
        //$max_id = Db::name('wx_menu')->where(array('token'=>$wechat['token']))->field('max(id) as id')->find();
        $max_id = DB::query("SHOW TABLE STATUS WHERE NAME = '__PREFIX__wx_menu'");
        $max_id = $max_id[0]['auto_increment'] ? $max_id[0]['auto_increment'] : $max_id[0]['Auto_increment'];

        //获取父级菜单
        $p_menus = Db::name('wx_menu')->where(['token' => $wechat['token'], 'pid' => 0])->order('id ASC')->select();
        $p_menus = convert_arr_key($p_menus, 'id');
        foreach ($p_menus as $k => $v) {
            $has_child = Db::name('wx_menu')->where(['token' => $wechat['token'], 'pid' => $v['id']])->find() ? 1 : 0;
            $p_menus[$k]['has_child'] = $has_child;
        }

        //获取二级菜单
        $c_menus = Db::name('wx_menu')->where(['token' => $wechat['token'], 'pid' => ['gt', 0]])->order('id ASC')->select();
        $c_menus = convert_arr_key($c_menus, 'id');
        $this->assign('p_lists', $p_menus);
        $this->assign('c_lists', $c_menus);
        $this->assign('max_id', $max_id ? $max_id - 1 : 0);

        return $this->fetch();
    }

    /*
     * 删除菜单
     */
    public function del_menu()
    {
        $id = I('get.id');
        if (!$id) {
            exit('fail');
        }
        $row = Db::name('wx_menu')->where(['id' => $id])->delete();
        $row && Db::name('wx_menu')->where(['pid' => $id])->delete(); //删除子类
        if ($row) {
            exit('success');
        }
        exit('fail');
    }

    /*
     * 生成微信菜单
     */
    public function pub_menu()
    {
//        $menu = array();
//        $menu['button'][] = array(
//            'name'=>'历史信息',
//            'type'=>'view',
//            'url'=>'https://mp.weixin.qq.com/mp/profile_ext?action=home&__biz=MjM5NTE4NzY5NA==&scene=123#wechat_redirect'
//        );
//        $menu['button'][] = array(
//            'name'=>'测试',
//            'sub_button'=>array(
//                array(
//                    "type"=> "scancode_waitmsg",
//                    "name"=> "系统拍照发图",
//                    "key"=> "rselfmenu_1_0",
//                    "sub_button"=> array()
//                )
//            )
//        );

        //获取父级菜单
        $p_menus = Db::name('wx_menu')->where(['pid' => 0])->order('id ASC')->select();
        $p_menus = convert_arr_key($p_menus, 'id');
        if (!count($p_menus) > 0) {
            $this->error('没有菜单可发布', U('Wechat/menu'));
        }

        $post = $this->convert_menu($p_menus);
        $wechatObj = new WechatUtil($this->wx_user);

        if (false === $wechatObj->createMenu($post)) {
            $this->error($wechatObj->getError());
        }

        $this->success('菜单已成功生成', U('Wechat/menu'));
    }

    //菜单转换
    private function convert_menu($p_menus)
    {
//        $key_map = array(
//            'scancode_waitmsg'=>'rselfmenu_0_0',
//            'scancode_push'=>'rselfmenu_0_1',
//            'pic_sysphoto'=>'rselfmenu_1_0',
//            'pic_photo_or_album'=>'rselfmenu_1_1',
//            'pic_weixin'=>'rselfmenu_1_2',
//            'location_select'=>'rselfmenu_2_0',
//        );
        $new_arr = [];
        $count = 0;
        foreach ($p_menus as $k => $v) {
            $new_arr[$count]['name'] = $v['name'];

            //获取子菜单
            $c_menus = Db::name('wx_menu')->where(['pid' => $k])->order('id ASC')->select();

            if ($c_menus) {
                foreach ($c_menus as $kk => $vv) {
                    $add = [];
                    $add['name'] = $vv['name'];
                    $add['type'] = $vv['type'];
                    // click类型
                    if ('click' == $add['type']) {
                        $add['key'] = $vv['value'];
                    } elseif ('view' == $add['type']) {
                        $add['url'] = str_replace('&amp;', '&', $vv['value']);
                    } elseif ('view_limited' == $add['type']) {
                        $add['media_id'] = $vv['value'];
                    } elseif ('media_id' == $add['type']) {
                        $add['media_id'] = $vv['value'];
                    } else {
                        $add['key'] = $vv['value'];
                    }
                    $add['sub_button'] = [];
                    if ($add['name']) {
                        $new_arr[$count]['sub_button'][] = $add;
                    }
                }
            } else {
                $new_arr[$count]['type'] = $v['type'];
                // click类型
                if ('click' == $new_arr[$count]['type']) {
                    $new_arr[$count]['key'] = $v['value'];
                } elseif ('view' == $new_arr[$count]['type']) {
                    //跳转URL类型
                    $new_arr[$count]['url'] = $v['value'];
                } else {
                    //其他事件类型
                    $new_arr[$count]['key'] = $v['value'];
                }
            }
            ++$count;
        }

        return ['button' => $new_arr];
    }

    /**
     * 自动回复的菜单.
     */
    private function auto_reply_menu()
    {
        return [
            WxReply::TYPE_KEYWORD => ['menu' => '关键词自动回复', 'url' => url('auto_reply', ['type' => WxReply::TYPE_KEYWORD])],
            WxReply::TYPE_DEFAULT => ['menu' => '消息自动回复', 'url' => url('auto_reply_edit', ['type' => WxReply::TYPE_DEFAULT])],
            WxReply::TYPE_FOLLOW => ['menu' => '关注时自动回复', 'url' => url('auto_reply_edit', ['type' => WxReply::TYPE_FOLLOW])],
        ];
    }

    /**
     * 自动回复展示.
     */
    public function auto_reply()
    {
        $type = input('type', WxReply::TYPE_KEYWORD);
        $types = $this->auto_reply_menu();
        if (!key_exists($type, $types)) {
            $this->error("标签 $type 不存在");
        }
        $this->assign('type', $type);
        $this->assign('types', $types);

        if (WxReply::TYPE_KEYWORD == $type) {
            $p = input('p');
            $num = 10;
            $condition = ['type' => $type];
            $replies = WxReply::where($condition)->with('wxKeywords')->order('id', 'asc')->page($p, $num)->select();
            $count = WxReply::where($condition)->count();
            $page = new Page($count, $num);
            $this->assign('page', $page);
            $this->assign('replies', $replies);

            return $this->fetch('auto_replies');
        }
        $this->redirect('auto_reply_edit', ['type' => $type]);
    }

    /**
     * 自动回复编辑页面.
     */
    public function auto_reply_edit()
    {
        $id = input('id/d');
        $type = input('type', WxReply::TYPE_KEYWORD);
        $types = $this->auto_reply_menu();
        if (!key_exists($type, $types)) {
            $this->error("标签 $type 不存在");
        }
        $this->assign('type', $type);
        $this->assign('types', $types);

        if (WxReply::TYPE_KEYWORD == $type) {
            if ($id && !$reply = WxReply::get(['id' => $id, 'type' => $type])) {
                $this->error('该自动回复不存在');
            }
        } else {
            $reply = WxReply::get(['type' => $type]);
        }

        if (!empty($reply)) {
            if (WxReply::MSG_NEWS == $reply->msg_type) {
                $news = WxMaterial::get($reply->material_id, 'wxNews');
                $this->assign('news', $news);
            }
            $this->assign('reply', $reply);
        }

        return $this->fetch();
    }

    /**
     * 新增自动回复.
     */
    public function add_auto_reply()
    {
        $type = input('msg_type');
        $data = input('post.');

        $logic = new WechatLogic($this->wx_user);
        $return = $logic->addAutoReply($type, $data);
        $this->ajaxReturn($return);
    }

    /**
     * 更新自动回复.
     */
    public function update_auto_reply()
    {
        $type = input('msg_type');
        $id = input('id/d', 0);
        $data = input('post.');

        $logic = new WechatLogic($this->wx_user);
        $return = $logic->updateAutoReply($type, $id, $data);
        $this->ajaxReturn($return);
    }

    /**
     * 删除自动回复.
     */
    public function delete_auto_reply()
    {
        $id = input('id/d', 0);

        $logic = new WechatLogic($this->wx_user);
        $return = $logic->deleteAutoReply($id);
        $this->ajaxReturn($return);
    }

    /**
     * 粉丝详细列表.
     */
    public function fans_list()
    {
        $keyword = input('keyword');
        $p = input('p/d');
        $num = 10;
        $logic = new WechatLogic();
        $return = $logic->getFanList($p, $num, $keyword);
        if (1 != $return['status']) {
            $this->error($return['msg'], null, '', 100);
        }

        $texts = WxMaterial::all(['type' => WxMaterial::TYPE_TEXT]);
        $page = new Page($return['result']['total'], $num);

        $this->assign('page', $page);
        $this->assign('texts', $texts);
        $this->assign('user_list', $return['result']['list']);

        return $this->fetch();
    }

    public function fan_info()
    {
        $openid = I('get.id');
        $wechatObj = new WechatUtil($this->wx_user);
        $list = $wechatObj->getFanInfo($openid);
        if (false === $list) {
            $this->error($wechatObj->getError());
        }

        $list['tags'] = $wechatObj->getFanTagNames($list['tagid_list']);
        if (false === $list['tags']) {
            $this->error($wechatObj->getError());
        }

        $this->assign('list', $list);

        return $this->fetch();
    }

    /**
     * 处理发送的消息.
     */
    public function send_text_msg()
    {
        $msg = I('post.msg'); //内容
        $to_all = I('post.to_all', 0); //个体or全体
        $openids = I('post.openids'); //个体id

        $wechatObj = new WechatUtil($this->wx_user);
        if ($to_all) {
            $result = $wechatObj->sendMsgToAll(0, 'text', $msg);
        } else {
            $result = $wechatObj->sendMsg($openids, 'text', $msg);
        }

        if (false === $result) {
            return $this->ajaxReturn(['status' => 0, 'msg' => $wechatObj->getError()]);
        }

        return $this->ajaxReturn(['status' => 1, 'msg' => '已发送！']);
    }

    /**
     * 素材管理.
     */
    public function materials()
    {
        $where = [];
        if ('' != I('title')) {
            $title = I('title');
            $data = M('wx_news')->field('material_id')->where('title', 'LIKE', "%$title%")->select();
            $material_ids = [];
            foreach ($data as $k => $v) {
                $material_ids[] = $v['material_id'];
            }
            if ($material_ids) {
                $where['id'] = ['IN', $material_ids];
            }
        }

        $tab = input('tab', 'news');
        $tabs = [
            'news' => '图文素材',
            'text' => '文本素材',
        ];
        if (!key_exists($tab, $tabs)) {
            $this->error("标签 $tab 不存在");
        }

        $p = input('p', 0);
        $num = 10;
        $where['type'] = ['eq', $tab];
        if ('news' == $tab) {
            $materials = WxMaterial::where($where)->with('wxNews')->order('update_time', 'desc')->page($p, $num)->select();
        } else {
            $materials = WxMaterial::where($where)->order('update_time', 'desc')->page($p, $num)->select();
        }

        $count = WxMaterial::where($where)->count();
        $page = new Page($count, $num);

        $this->assign('page', $page);
        $this->assign('title', I('title'));
        $this->assign('list', $materials);
        $this->assign('tab', $tab);
        $this->assign('tabs', $tabs);

        return $this->fetch('materials_'.$tab);
    }

    /**
     * 异步请求图文消息.
     */
    public function ajax_news()
    {
        $p = input('p', 0);
        $num = 9;
        $materials = WxMaterial::where(['type' => WxMaterial::TYPE_NEWS])->with('wxNews')->order('update_time', 'desc')->page($p, $num)->select();
        $count = WxMaterial::where(['type' => WxMaterial::TYPE_NEWS])->count();
        $page = new AjaxPage($count, $num);

        $this->assign('page', $page);
        $this->assign('list', $materials);

        return $this->fetch();
    }

    /**
     * 单图文素材编辑.
     */
    public function news_edit()
    {
        $material_id = input('material_id/d');
        $news_id = input('news_id/d');

        if ($news_id) {
            if (!$news = WxNews::get(['id' => $news_id, 'material_id' => $material_id])) {
                $this->error('该图文素材不存在');
            }
            $this->assign('info', $news);
        }

        return $this->fetch();
    }

    /**
     * 删除素材.
     */
    public function delete_news()
    {
        $material_id = input('material_id/d');

        $logic = new WechatLogic($this->wx_user);
        $return = $logic->deleteNews($material_id);

        return $this->ajaxReturn($return);
    }

    /**
     * 删除多图文中的单图文.
     */
    public function delete_single_news()
    {
        $news_id = input('news_id/d');

        $logic = new WechatLogic($this->wx_user);
        $return = $logic->deleteSingleNews($news_id);

        return $this->ajaxReturn($return);
    }

    /**
     * 新增或更新单图文素材.
     */
    public function handle_news()
    {
        $material_id = input('material_id/d'); //为0新增多素材，否则更新多素材
        $news_id = input('news_id/d', 0); //为0新增单素材，否则更新单素材，此时material_id不为0
        $data = input('post.');

        $result = $this->validate($data, 'WechatNews', [], true);
        if (true !== $result) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'result' => $result]);
        }

        $logic = new WechatLogic();
        $return = $logic->createOrUpdateNews($material_id, $news_id, $data);

        return $this->ajaxReturn($return);
    }

    /**
     * 发送图文素材消息.
     */
    public function send_news_msg()
    {
        $material_id = input('material_id');
        $to_all = input('to_all', 0); //个体or全体
        $openids = input('openids'); //个体id

        $logic = new WechatLogic($this->wx_user);
        $return = $logic->sendNewsMsg($material_id, $openids, $to_all);

        return $this->ajaxReturn($return);
    }

    /**
     * 编辑文本素材.
     */
    public function text_edit()
    {
        $material_id = input('material_id/d');
        if ($material_id) {
            if (!$text = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_TEXT])) {
                $this->error('该文本素材不存在');
            }
            $this->assign('info', $text);
        }

        return $this->fetch();
    }

    /**
     * 新增或更新文本素材.
     */
    public function handle_text()
    {
        $material_id = input('material_id/d'); //为0新增素材，否则更新素材
        $data = input('post.');

        $logic = new WechatLogic();
        $return = $logic->createOrUpdateText($material_id, $data);

        return $this->ajaxReturn($return);
    }

    /**
     * 删除文本素材.
     */
    public function delete_text()
    {
        $material_id = input('material_id/d');

        $logic = new WechatLogic($this->wx_user);
        $return = $logic->deleteText($material_id);

        return $this->ajaxReturn($return);
    }

    /**
     * 模板消息.
     */
    public function template_msg()
    {
        $logic = new WechatLogic();
        $tpls = $logic->getDefaultTemplateMsg();

        $template_sns = get_arr_column($tpls, 'template_sn');
        $user_tpls = WxTplMsg::all(['template_sn' => ['in', $template_sns]]);
        $user_tpls = convert_arr_key($user_tpls, 'template_sn');

        $this->assign('tpls', $tpls);
        $this->assign('user_tpls', $user_tpls);

        return $this->fetch();
    }

    /**
     * 设置模板消息.
     */
    public function set_template_msg()
    {
        $template_sn = input('template_sn');
        $is_use = input('is_use/d');
        $remark = input('remark');

        $data = [];
        !is_null($is_use) && $data['is_use'] = $is_use;
        !is_null($remark) && $data['remark'] = $remark;

        $logic = new WechatLogic();
        $return = $logic->setTemplateMsg($template_sn, $data);
        $this->ajaxReturn($return);
    }

    /**
     * 重置模板消息.
     */
    public function reset_template_msg()
    {
        $template_sn = input('template_sn');

        $logic = new WechatLogic();
        $return = $logic->resetTemplateMsg($template_sn);

        $this->ajaxReturn($return);
    }
}
