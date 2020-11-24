<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 管理员操作记录.
 *
 * @param $log_info string 记录信息
 */
function adminLog($log_info)
{
    $add['log_time'] = time();
    $add['admin_id'] = session('admin_id');
    $add['log_info'] = $log_info;
    $add['log_ip'] = request()->ip();
    $add['log_url'] = request()->baseUrl();
    M('admin_log')->add($add);
}

/**
 * 平台支出记录.
 *
 * @param $log_id 支出业务关联id
 * @param $money 支出金额
 * @param $type 支出类别
 * @param $user_id or $store_id 涉及申请用户ID或商家ID
 */
function expenseLog($data)
{
    $data['addtime'] = time();
    $data['admin_id'] = session('admin_id');
    M('expense_log')->add($data);
}

function getAdminInfo($admin_id)
{
    return D('admin')->where('admin_id', $admin_id)->find();
}

function tpversion()
{
    //在线升级系统
    if (!empty($_SESSION['isset_push'])) {
        return false;
    }
    $_SESSION['isset_push'] = 1;
    error_reporting(0); //关闭所有错误报告
    $app_path = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
    $version_txt_path = $app_path . '/application/admin/conf/version.php';
    $curent_version = file_get_contents($version_txt_path);

    $vaules = [
        'domain' => $_SERVER['HTTP_HOST'],
        'last_domain' => $_SERVER['HTTP_HOST'],
        'key_num' => $curent_version,
        'install_time' => INSTALL_DATE,
        'cpu' => '0001',
        'mac' => '0002',
        'serial_number' => SERIALNUMBER,
    ];
    $url = 'http://service.tp-shop.cn/index.php?m=Home&c=Index&a=user_push&' . http_build_query($vaules);
    stream_context_set_default(['http' => ['timeout' => 3]]);
    file_get_contents($url);
}

/**
 * 面包屑导航  用于后台管理
 * 根据当前的控制器名称 和 action 方法.
 */
function navigate_admin()
{
    $navigate = include APP_PATH . 'admin/conf/navigate.php';
    $location = strtolower('Admin/' . CONTROLLER_NAME);
    $arr = [
        '后台首页' => 'javascript:void();',
        $navigate[$location]['name'] => 'javascript:void();',
        $navigate[$location]['action'][ACTION_NAME] => 'javascript:void();',
    ];

    return $arr;
}

/**
 * 导出excel.
 *
 * @param $strTable    表格内容
 * @param $filename 文件名
 */
function downloadExcel($strTable, $filename)
{
    set_time_limit(0);    // 防止超时
    ini_set("memory_limit", "128M");  // 防止内存溢出
    header('Content-type: application/vnd.ms-excel');
    header('Content-Type: application/force-download');
    header('Content-Disposition: attachment; filename=' . $filename . '_' . date('Y-m-d') . '.xls');
    header('Expires:0');
    header('Pragma:public');
    echo '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . $strTable . '</html>';
}

/**
 * 导出Excel数据表格
 * @param $expTitle
 * @param $expCellName
 * @param $expTableData
 * @param string $type 类型：商品goods 订单order
 * @param bool $showPic 是否显示图片
 * @param string $output 输出类型
 * @return bool
 * @throws PHPExcel_Exception
 * @throws PHPExcel_Reader_Exception
 * @throws PHPExcel_Writer_Exception
 */
function exportExcel($expTitle, $expCellName, $expTableData, $type, $showPic = false, $output = 'download')
{
    set_time_limit(0);    // 防止超时
    ini_set("memory_limit", "128M");  // 防止内存溢出
    $cellNum = count($expCellName);
    $dataNum = count($expTableData);
    /*引入phpexcel核心类文件*/
    include_once "plugins/PHPExcel.php";
    /*实例化excel类*/
    $objPHPExcel = new \PHPExcel();
    /*设置文本对齐方式*/
//    $objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
//    $objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
    /*设置单元格格式*/
    for ($i = 0; $i < $cellNum; $i++) {
        $objPHPExcel->getActiveSheet()->getColumnDimension($cellName[$i])->setWidth($expCellName[$i][2]);
        $objPHPExcel->setActiveSheetIndex(0)->setCellValueExplicit($cellName[$i] . '1', $expCellName[$i][1], \PHPExcel_Cell_DataType::TYPE_STRING);
    }
    /*设置数据*/
    for ($i = 0; $i < $dataNum; $i++) {
        for ($j = 0; $j < $cellNum; $j++) {
            switch ($type) {
                case 'goods':
                    /*商品数据导出*/
                    switch ($j) {
                        case 16:
                            /*缩略图*/
                            if (!empty($expTableData[$i]['original_img'])) {
                                switch ($_SERVER['SERVER_ADDR']) {
                                    case '61.238.101.139':
                                        $imgPath = '/var/www/html/pmd' . $expTableData[$i]['original_img'];
                                        break;
                                    case '61.238.101.138':
                                        $imgPath = '/var/www/html/pmd' . $expTableData[$i]['original_img'];
                                        break;
                                    default:
                                        $imgPath = 'E:/programme/pumeiduo/pumeiduo_server' . $expTableData[$i]['original_img'];
                                }
                                if (file_exists($imgPath) && $showPic) {
                                    /*设置表格宽度*/
                                    $objPHPExcel->getActiveSheet()->getColumnDimension($cellName[$j])->setWidth(20);
                                    /*设置表格高度*/
                                    $objPHPExcel->getActiveSheet()->getRowDimension($i + 2)->setRowHeight(100);
                                    /*实例化插入图片类*/
                                    $objDrawing = new PHPExcel_Worksheet_Drawing();
                                    /*设置图片路径 切记：只能是本地图片*/
                                    $objDrawing->setPath($imgPath);
                                    /*设置图片高度*/
                                    $objDrawing->setHeight(100);
                                    /*设置图片要插入的单元格*/
                                    $objDrawing->setCoordinates($cellName[$j] . ($i + 2));
                                    /*设置图片所在单元格的格式*/
                                    $objDrawing->setOffsetX(20);
                                    $objDrawing->setOffsetY(15);
                                    $objDrawing->setRotation(0);
                                    $objDrawing->getShadow()->setVisible(true);
                                    $objDrawing->getShadow()->setDirection(0);
                                    $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
                                } else {
                                    $objPHPExcel->getActiveSheet(0)->setCellValueExplicit($cellName[$j] . ($i + 2), 'https://mall.pumeiduo.com' . $expTableData[$i]['original_img'], ($expCellName[$j][3] == 0 ? \PHPExcel_Cell_DataType::TYPE_NUMERIC : \PHPExcel_Cell_DataType::TYPE_STRING));
                                }
                            } else {
                                $objPHPExcel->getActiveSheet(0)->setCellValueExplicit($cellName[$j] . ($i + 2), $expTableData[$i]['original_img'], ($expCellName[$j][3] == 0 ? \PHPExcel_Cell_DataType::TYPE_NUMERIC : \PHPExcel_Cell_DataType::TYPE_STRING));
                            }
                            break;
                        default:
                            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit($cellName[$j] . ($i + 2), $expTableData[$i][$expCellName[$j][0]], ($expCellName[$j][3] == 0 ? \PHPExcel_Cell_DataType::TYPE_NUMERIC : \PHPExcel_Cell_DataType::TYPE_STRING));
                    }
                    break;
                default:
                    $objPHPExcel->getActiveSheet(0)->setCellValueExplicit($cellName[$j] . ($i + 2), $expTableData[$i][$expCellName[$j][0]], ($expCellName[$j][3] == 0 ? \PHPExcel_Cell_DataType::TYPE_NUMERIC : \PHPExcel_Cell_DataType::TYPE_STRING));
            }
        }
    }

    $fileName = $expTitle . date('_YmdHis');
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    switch ($output) {
        case 'download':
            $xlsTitle = iconv('utf-8', 'gb2312', $expTitle);
            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
            header("Content-Disposition:attachment;filename=$fileName.xls");
            $objWriter->save('php://output');
            break;
        case 'local':
            $objWriter->save(PUBLIC_PATH . 'download_excel/' . $fileName . '.xls');
            break;
    }
    return true;
}

/**
 * 导出Excel数据表格
 * @param  array $dataList 要导出的数组格式的数据
 * @param  array $headList 导出的Excel数据第一列表头
 * @param  string $fileName 输出Excel表格文件名
 * @param  string $exportUrl 直接输出到浏览器（php://output）or输出到指定路径文件下（服务器目录地址/文件名.csv）
 * @return bool|false|string
 */
function toCsvExcel($dataList, $headList, $fileName, $exportUrl = 'php://output')
{
    set_time_limit(0);    // 防止超时
    ini_set("memory_limit", "128M");  // 防止内存溢出
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $fileName . '_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0');
    //打开PHP文件句柄,php://output 表示直接输出到浏览器,$exportUrl表示输出到指定路径文件下
    $fp = fopen($exportUrl, 'a');

    //输出Excel列名信息
    foreach ($headList as $key => $value) {
        //CSV的Excel支持GBK编码，一定要转换，否则乱码
        $headList[$key] = iconv('utf-8', 'gbk', $value);
    }

    //将数据通过fputcsv写到文件句柄
    fputcsv($fp, $headList);

    //计数器
    $num = 0;

    //每隔$limit行，刷新一下输出buffer，不要太大，也不要太小
    $limit = 100000;

    //逐行取出数据，不浪费内存
    $count = count($dataList);
    for ($i = 0; $i < $count; $i++) {
        $num++;
        //刷新一下输出buffer，防止由于数据过多造成问题
        if ($limit == $num) {
            ob_flush();
            flush();
            $num = 0;
        }
        $row = $dataList[$i];
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $rowValue = '';
                foreach ($value as $v) {
                    $rowValue .= iconv('utf-8', 'gbk', $v) . "\n";
                }
                $row[$key] = trim($rowValue, "\n");
            } else {
                $row[$key] = iconv('utf-8', 'gbk', $value);
            }
        }
        fputcsv($fp, $row);
    }
    return $fileName;
}

/**
 * 格式化字节大小.
 *
 * @param number $size 字节数
 * @param string $delimiter 数字和单位分隔符
 *
 * @return string 格式化后的带单位的大小
 */
function format_bytes($size, $delimiter = '')
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    for ($i = 0; $size >= 1024 && $i < 5; ++$i) {
        $size /= 1024;
    }

    return round($size, 2) . $delimiter . $units[$i];
}

/**
 * 根据id获取地区名字.
 *
 * @param $regionId id
 */
function getRegionName($regionId)
{
    $data = M('region2')->where(['id' => $regionId])->field('name')->find();

    return $data['name'];
}

function getMenuList($act_list)
{
    //根据角色权限过滤菜单
    $menu_list = getAllMenu();
    if ('all' != $act_list) {
        $right = M('system_menu')->where('id', 'in', $act_list)->cache(true)->getField('right', true);
        $role_right = '';
        foreach ($right as $val) {
            $role_right .= $val . ',';
        }
        $role_right = explode(',', $role_right);
        foreach ($menu_list as $k => $mrr) {
            foreach ($mrr['sub_menu'] as $j => $v) {
                if (!in_array($v['control'] . '@' . $v['act'], $role_right)) {
                    unset($menu_list[$k]['sub_menu'][$j]); //过滤菜单
                }
            }
        }
    }

    return $menu_list;
}

function getAllMenu()
{
    return [
        'system' => ['name' => '系统设置', 'icon' => 'fa-cog', 'sub_menu' => [
            ['name' => '网站设置', 'act' => 'index', 'control' => 'System'],
            ['name' => '友情链接', 'act' => 'linkList', 'control' => 'Article'],
            ['name' => '自定义导航', 'act' => 'navigationList', 'control' => 'System'],
            ['name' => '区域管理', 'act' => 'region', 'control' => 'Tools'],
            ['name' => '短信模板', 'act' => 'index', 'control' => 'SmsTemplate'],
        ]],
        'access' => ['name' => '权限管理', 'icon' => 'fa-gears', 'sub_menu' => [
            ['name' => '权限资源列表', 'act' => 'right_list', 'control' => 'System'],
            ['name' => '管理员列表', 'act' => 'index', 'control' => 'Admin'],
            ['name' => '角色管理', 'act' => 'role', 'control' => 'Admin'],
            ['name' => '供应商管理', 'act' => 'supplier', 'control' => 'Admin'],
            ['name' => '管理员日志', 'act' => 'log', 'control' => 'Admin'],
        ]],
        'member' => ['name' => '会员管理', 'icon' => 'fa-user', 'sub_menu' => [
            ['name' => '会员列表', 'act' => 'index', 'control' => 'User'],
            ['name' => '会员等级', 'act' => 'levelList', 'control' => 'User'],
            ['name' => '充值记录', 'act' => 'recharge', 'control' => 'User'],
            ['name' => '提现申请', 'act' => 'withdrawals', 'control' => 'User'],
            ['name' => '汇款记录', 'act' => 'remittance', 'control' => 'User'],
            //array('name'=>'会员整合','act'=>'integrate','control'=>'User'),
        ]],
        'goods' => ['name' => '商品管理', 'icon' => 'fa-book', 'sub_menu' => [
            ['name' => '商品分类', 'act' => 'categoryList', 'control' => 'Goods'],
            ['name' => '商品列表', 'act' => 'goodsList', 'control' => 'Goods'],
            ['name' => '商品模型', 'act' => 'goodsTypeList', 'control' => 'Goods'],
            ['name' => '商品规格', 'act' => 'specList', 'control' => 'Goods'],
            ['name' => '商品属性', 'act' => 'goodsAttributeList', 'control' => 'Goods'],
            ['name' => '品牌列表', 'act' => 'brandList', 'control' => 'Goods'],
            ['name' => '商品评论', 'act' => 'index', 'control' => 'Comment'],
            ['name' => '商品咨询', 'act' => 'ask_list', 'control' => 'Comment'],
        ]],
        'order' => ['name' => '订单管理', 'icon' => 'fa-money', 'sub_menu' => [
            ['name' => '订单列表', 'act' => 'index', 'control' => 'Order'],
            ['name' => '发货单', 'act' => 'delivery_list', 'control' => 'Order'],
            //array('name' => '快递单', 'act'=>'express_list', 'control'=>'Order'),
            ['name' => '退货单', 'act' => 'return_list', 'control' => 'Order'],
            ['name' => '添加订单', 'act' => 'add_order', 'control' => 'Order'],
            ['name' => '订单日志', 'act' => 'order_log', 'control' => 'Order'],
        ]],
        'promotion' => ['name' => '促销管理', 'icon' => 'fa-bell', 'sub_menu' => [
            ['name' => '抢购管理', 'act' => 'flash_sale', 'control' => 'Promotion'],
            ['name' => '团购管理', 'act' => 'group_buy_list', 'control' => 'Promotion'],
            ['name' => '商品促销', 'act' => 'prom_goods_list', 'control' => 'Promotion'],
            ['name' => '订单促销', 'act' => 'prom_order_list', 'control' => 'Promotion'],
            ['name' => '代金券管理', 'act' => 'index', 'control' => 'Coupon'],
            ['name' => '预售管理', 'act' => 'pre_sell_list', 'control' => 'Promotion'],
        ]],
        'Ad' => ['name' => '广告管理', 'icon' => 'fa-flag', 'sub_menu' => [
            ['name' => '广告列表', 'act' => 'adList', 'control' => 'Ad'],
            ['name' => '广告位置', 'act' => 'positionList', 'control' => 'Ad'],
        ]],
        'content' => ['name' => '内容管理', 'icon' => 'fa-comments', 'sub_menu' => [
            ['name' => '文章列表', 'act' => 'articleList', 'control' => 'Article'],
            ['name' => '文章分类', 'act' => 'categoryList', 'control' => 'Article'],
            //array('name' => '帮助管理', 'act'=>'help_list', 'control'=>'Article'),
            //array('name' => '公告管理', 'act'=>'notice_list', 'control'=>'Article'),
            ['name' => '专题列表', 'act' => 'topicList', 'control' => 'Topic'],
        ]],
        'weixin' => ['name' => '微信管理', 'icon' => 'fa-weixin', 'sub_menu' => [
            ['name' => '公众号管理', 'act' => 'index', 'control' => 'Wechat'],
            ['name' => '微信菜单管理', 'act' => 'menu', 'control' => 'Wechat'],
            ['name' => '文本回复', 'act' => 'text', 'control' => 'Wechat'],
            ['name' => '图文回复', 'act' => 'img', 'control' => 'Wechat'],
            //array('name' => '组合回复', 'act'=>'nes', 'control'=>'Wechat'),
            //array('name' => '消息推送', 'act'=>'news', 'control'=>'Wechat'),
        ]],
        'theme' => ['name' => '模板管理', 'icon' => 'fa-adjust', 'sub_menu' => [
            ['name' => 'PC端模板', 'act' => 'templateList?t=pc', 'control' => 'Template'],
            ['name' => '手机端模板', 'act' => 'templateList?t=mobile', 'control' => 'Template'],
        ]],

        'distribut' => ['name' => '分销管理', 'icon' => 'fa-cubes', 'sub_menu' => [
            ['name' => '分销商品列表', 'act' => 'goods_list', 'control' => 'Distribut'],
            ['name' => '分销商列表', 'act' => 'distributor_list', 'control' => 'Distribut'],
            ['name' => '分销关系', 'act' => 'tree', 'control' => 'Distribut'],
            ['name' => '分销设置', 'act' => 'set', 'control' => 'Distribut'],
            ['name' => '分成日志', 'act' => 'rebate_log', 'control' => 'Distribut'],
        ]],

        'tools' => ['name' => '插件工具', 'icon' => 'fa-plug', 'sub_menu' => [
            ['name' => '插件列表', 'act' => 'index', 'control' => 'Plugin'],
            ['name' => '数据备份', 'act' => 'index', 'control' => 'Tools'],
            ['name' => '数据还原', 'act' => 'restore', 'control' => 'Tools'],
        ]],
        'count' => ['name' => '统计报表', 'icon' => 'fa-signal', 'sub_menu' => [
            ['name' => '销售概况', 'act' => 'index', 'control' => 'Report'],
            ['name' => '销售排行', 'act' => 'saleTop', 'control' => 'Report'],
            ['name' => '会员排行', 'act' => 'userTop', 'control' => 'Report'],
            ['name' => '销售明细', 'act' => 'saleList', 'control' => 'Report'],
            ['name' => '会员统计', 'act' => 'user', 'control' => 'Report'],
            ['name' => '财务统计', 'act' => 'finance', 'control' => 'Report'],
        ]],
        'pickup' => ['name' => '自提点管理', 'icon' => 'fa-anchor', 'sub_menu' => [
            ['name' => '自提点列表', 'act' => 'index', 'control' => 'Pickup'],
            ['name' => '添加自提点', 'act' => 'add', 'control' => 'Pickup'],
        ]],
    ];
}

function getMenuArr()
{
    $menuArr = include APP_PATH . 'admin/conf/menu.php';
    $act_list = session('act_list');
    if ('all' != $act_list && !empty($act_list)) {
        $right = M('system_menu')->where("id in ($act_list)")->cache(true)->getField('right', true);
        $role_right = '';
        foreach ($right as $val) {
            $role_right .= $val . ',';
        }
        foreach ($menuArr as $k => $val) {
            foreach ($val['child'] as $j => $v) {
                foreach ($v['child'] as $s => $son) {
                    if (false === strpos($role_right, $son['op'] . '@' . $son['act'])) {
                        unset($menuArr[$k]['child'][$j]['child'][$s]); //过滤菜单
                    }
                }
            }
        }
        foreach ($menuArr as $mk => $mr) {
            foreach ($mr['child'] as $nk => $nrr) {
                if (empty($nrr['child'])) {
                    unset($menuArr[$mk]['child'][$nk]);
                }
            }
        }
    }
    foreach ($menuArr as $mk => $mr) {
        foreach ($mr['child'] as $nk => $nrr) {
            if (empty($nrr['child'])) {
                unset($menuArr[$mk]['child'][$nk]);
                continue;
            }
            foreach ($nrr['child'] as $ck => $child) {
                if (empty($child['type'])) {
                    $menuArr[$mk]['child'][$nk]['child'][$ck]['type'] = '1';
                }
            }
        }
    }
    return $menuArr;
}

function respose($res)
{
    exit(json_encode($res));
}

function exchangeTime($time)
{
    return date('Y-m-d H:i:s', $time);
}

function exchangeDate($time)
{
    return date('Y-m-d', $time);
}

function rebate_type($type)
{
    $rebate_type = [
        '分销提成',
        '商店提成',
    ];

    return $rebate_type[$type];
}

function trade_type($type)
{
    $trade_type = [
        '1' => '仓库自发',
        '2' => '一件代发',
        '3' => '供应链发货'
    ];

    return $trade_type[$type];
}

function commission_type($type)
{
    $commission_type = [
        '未发放',
        '已发放',
    ];

    return $commission_type[$type];
}

function sale_type($type)
{
    $trade_type = [
        '1' => '普通商品',
        '2' => '超值套组',
        '3' => '特惠团购',
    ];

    return $trade_type[$type];
}

function task_cate($type)
{
    $task_cate = [
        '1' => '日常任务',
        '2' => '推荐任务',
        '3' => '销售任务',
        '4' => '会员任务',
    ];

    return $task_cate[$type];
}

function task_type($type)
{
//    $task_type = [
//        '1' => '红包活动',
//        '2' => '邀请任务',
//        '3' => '销售任务',
//    ];
    $task_type = unserialize(M('task_config')->value('config_value'));

    return $task_type[$type];
}

function getDistributList()
{
    $list = M('distribut_level')->field('level_id,level_name')->select();
    $data = [
        'level_id' => 0,
        'level_name' => '全体',
    ];
    array_unshift($list, $data);

    return $list;
}

function getDistributOption($distribut_list)
{
    $html = '';
    foreach ($distribut_list as $key => $value) {
        $html .= "<option value='{$value['level_id']}'>" . $value['level_name'] . '</option>';
    }

    return $html;
}

function subtext($text, $length)
{
    if (mb_strlen($text, 'utf8') > $length) {
        return mb_substr($text, 0, $length, 'utf8') . '...';
    }

    return $text;
}