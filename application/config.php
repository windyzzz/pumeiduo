<?php

/*
 * This file is part of the J project.
 *
 * (c) J <775893055@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------

    // 应用命名空间
    'app_namespace' => 'app',
    // 应用调试模式
    'app_debug' => true,
    // 应用Trace
    'app_trace' => false,
    // 应用模式状态
    'app_status' => '',
    // 是否支持多模块
    'app_multi_module' => true,
    // 入口自动绑定模块
    'auto_bind_module' => false,
    // 注册的根命名空间
    'root_namespace' => [],
    // 扩展函数文件
    'extra_file_list' => [THINK_PATH . 'helper' . EXT, APP_PATH . 'function.php'],
    // 默认输出类型
    'default_return_type' => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return' => 'html',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler' => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler' => 'callback',
    // 默认时区
    'default_timezone' => 'PRC',
    // 是否开启多语言
    'lang_switch_on' => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter' => 'htmlspecialchars',
    // 默认语言
    'default_lang' => 'zh-cn',
    // 应用类库后缀
    'class_suffix' => false,
    // 控制器类后缀
    'controller_suffix' => false,

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module' => 'home',
    // 禁止访问模块
    'deny_module_list' => ['common'],
    // 默认控制器名
    'default_controller' => 'Index',
    // 默认操作名
    'default_action' => 'index',
    // 默认验证器
    'default_validate' => '',
    // 默认的空控制器名
    'empty_controller' => 'Error',
    // 操作方法后缀
    'action_suffix' => '',
    // 自动搜索控制器
    'controller_auto_search' => false,

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo' => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch' => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr' => '/',
    // URL伪静态后缀
    'url_html_suffix' => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param' => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type' => 0,
    // 是否开启路由
    'url_route_on' => true,
    // 路由使用完整匹配
    'route_complete_match' => false,
    // 路由配置文件（支持配置多个）
    'route_config_file' => ['route'],
    // 是否强制使用路由
    'url_route_must' => false,
    // 域名部署
    'url_domain_deploy' => false,
    // 域名根，如thinkphp.cn
    'url_domain_root' => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert' => false,
    // 默认的访问控制器层
    'url_controller_layer' => 'controller',
    // 表单请求类型伪装变量
    'var_method' => '_method',
    // 表单ajax伪装变量
    'var_ajax' => '_ajax',
    // 表单pjax伪装变量
    'var_pjax' => '_pjax',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache' => false,
    // 请求缓存有效期
    'request_cache_expire' => null,
    // 全局请求缓存排除规则
    'request_cache_except' => [],

    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------

    'template' => [
        // 模板引擎类型 支持 php think 支持扩展
        'type' => 'Think',
        // 模板路径
        'view_path' => '',
        // 模板后缀
        'view_suffix' => 'html',
        // 模板文件名分隔符
        'view_depr' => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin' => '{',
        // 模板引擎普通标签结束标记
        'tpl_end' => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end' => '}',
    ],

    // 视图输出字符串内容替换
    'view_replace_str' => [],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl' => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl' => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件
    'exception_tmpl' => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',
    // errorpage 错误页面
    'error_tmpl' => THINK_PATH . 'tpl' . DS . 'think_error.tpl',

    // 错误显示信息,非调试模式有效
    'error_message' => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg' => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle' => function () {
        return json([
            'status' => '0',
            'msg' => '服务器异常',
        ]);
    },
//    'exception_handle' => '',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------

    'log' => [
        // 日志记录方式，内置 file socket 支持扩展
        'type' => 'File',
        // 日志保存目录
        'path' => LOG_PATH,
        // 日志记录级别
        'level' => ['error', 'log','sql'],
        // 日志开关  1 开启 0 关闭
        'switch' => 1,
    ],

    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace' => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------

    'cache' => [
        // 驱动方式
        'type' => 'File',
        // 缓存保存目录
        'path' => CACHE_PATH,
        // 缓存前缀
        'prefix' => 'gfdgdasdghjakshgksajd',
        // 缓存有效期 0表示永久缓存
        'expire' => 1,
    ],

    /*
        'cache'                  => [
            // 驱动方式
            'type'   => 'redis',
            'host'       => '192.168.0.201', // 指定redis的地址
        ],
    */
    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session' => [
        'id' => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix' => 'think',
        // 驱动方式 支持redis memcache memcached
        'type' => '',
        // 是否自动开启 SESSION
        'auto_start' => true,
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie' => [
        // cookie 名称前缀
        'prefix' => '',
        // cookie 保存时间
        'expire' => 0,
        // cookie 保存路径
        'path' => '/',
        // cookie 有效域名
        'domain' => '',
        //  cookie 启用安全传输
        'secure' => false,
        // httponly设置
        'httponly' => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],

    //分页配置
    'paginate' => [
        'type' => 'bootstrap',
        'var_page' => 'page',
        'list_rows' => 15,
    ],

    // URL
    'SERVER_URL' => \think\Env::get('SERVER.URL'),

    // 密码加密串
    'AUTH_CODE' => \think\Env::get('AUTH.CODE'), //安装完毕之后不要改变，否则所有密码都会出错
    'AUTH_CODE_SHOP' => \think\Env::get('AUTH.CODE.SHOP'),

    //redis储存数据的时间
    'REDIS_TIME' => 86400,
    'REDIS_DAY' => 7,

    'ORDER_STATUS' => [
        0 => '待确认',
        1 => '已确认',
        2 => '已收货',
        3 => '已取消',
        4 => '已完成', //评价完
        5 => '已作废',
        6 => '售后状态',
    ],

    'GROUP_STATUS' => [
        1 => '未成团',
        2 => '已成团',
    ],

    'SHIPPING_STATUS' => [
        0 => '未发货',
        1 => '已发货',
        2 => '部分发货',
    ],

    'PAY_STATUS' => [
        0 => '未支付',
        1 => '已支付',
        2 => '部分支付',
        3 => '已退款',
        4 => '拒绝退款',
    ],

    'SEX' => [
        0 => '保密',
        1 => '男',
        2 => '女',
    ],

    'COUPON_TYPE' => [
        0 => '下单赠送',
        1 => '指定发放',
        2 => '免费领取',
        3 => '线下发放',
    ],

    'PROM_TYPE' => [
        0 => '默认',
        1 => '抢购',
        2 => '团购',
        3 => '优惠',
    ],

    'TEAM_FOUND_STATUS' => [
        '0' => '待开团',
        '1' => '已开团',
        '2' => '拼团成功',
        '3' => '拼团失败',
    ],

    'TEAM_FOLLOW_STATUS' => [
        '0' => '待拼单',
        '1' => '拼单成功',
        '2' => '成团成功',
        '3' => '成团失败',
    ],

    'TEAM_TYPE' => [0 => '分享团', 1 => '佣金团', 2 => '抽奖团'],
    'FREIGHT_TYPE' => [0 => '件数', 1 => '重量', 2 => '体积'],

    // 订单用户端显示状态
    'WAITPAY' => ' AND pay_status = 0 AND order_status = 0 AND pay_code != "cod" ', //订单查询状态 待支付
    'WAITSEND' => ' AND (pay_status = 1 OR pay_code = "cod") AND shipping_status != 1 AND order_status in(0, 1) ', //订单查询状态 待发货
    'WAITRECEIVE' => ' AND shipping_status = 1 AND order_status = 1 ', //订单查询状态 待收货
    'WAITCCOMMENT' => ' AND order_status = 2 ', // 待评价 确认收货     //'FINISHED'=>'  AND order_status=1 ', //订单查询状态 已完成
    'AFTER-SALES' => ' AND order_status = 6', // 已完成
    'FINISH' => ' AND (order_status = 2 OR order_status = 4 OR order_status = 6)', // 已完成
    'CANCEL' => ' AND order_status = 3 ', // 已取消
    'CANCELLED' => 'AND order_status = 5 ', //已作废
    'PAYED' => ' AND (order_status = 2 OR (order_status = 1 AND pay_status = 1) ) ', //虚拟订单状态:已付款

    'ORDER_STATUS_DESC' => [
        'WAITPAY' => '待支付',
        'WAITSEND' => '待发货',
        'WAITSEND1' => '商家已确认，等待发货',
        'PORTIONSEND' => '部分发货',
        'WAITRECEIVE' => '待收货',
        'WAITCCOMMENT' => '待评价',
        'CANCEL' => '已取消',
        'FINISH' => '已完成',
        'CANCELLED' => '已作废',
        'AFTER-SALES' => '售后状态',
    ],

    'REFUND_STATUS' => [
        -2 => '已取消', //会员取消
        -1 => '审核失败', //不同意
        0 => '待审核', //卖家审核
        1 => '审核通过', //同意
        2 => '买家发货', //买家发货
        3 => '已收货', //服务单完成
        4 => '换货完成',
        5 => '退款完成',
        6 => '用户删除',
    ],

    // 物流状态
    'DELIVERY_STATUS' => [
        -1 => '未发货',
        0 => '快递收件(揽件',
        1 => '在途中',
        2 => '正在派件',
        3 => '已签收',
        4 => '派送失败',
        5 => '疑难件',
        6 => '退件签收',
    ],

    // 售后类型
    'RETURN_TYPE' => [
        0 => '仅退款',
        1 => '退货退款',
        2 => '换货',
    ],

    // 售后退款原因
    'RETURN_REASON' => [
        '0' => ['商品质量问题', '商品过期', '货物破损已拒签', '未收到商品'],
        '1' => ['7天无理由退换货', '商品质量问题', '规格与商品描述不符', '发错货/漏发', '商品过期', '货物破损已拒签'],
        '2' => ['7天无理由退换货', '商品质量问题', '规格与商品描述不符', '发错货/漏发', '商品过期', '货物破损已拒签']
    ],

    //短信使用场景
    'SEND_SCENE' => [
//        '1' => ['用户注册', '验证码${code}，用户注册新账号, 请勿告诉他人，感谢您的支持!', 'regis_sms_enable'],
//        '2' => ['用户找回密码', '验证码${code}，用于密码找回，如非本人操作，请及时检查账户安全', 'forget_pwd_sms_enable'],
//        '3' => ['客户下单', '您有新订单，收货人：${consignee}，联系方式：${phone}，请您及时查收.', 'order_add_sms_enable'],
//        '4' => ['客户支付', '客户下的单(订单ID:${orderId})已经支付，请及时发货.', 'order_pay_sms_enable'],
//        '5' => ['商家发货', '尊敬的${userName}用户，您的订单已发货，收货人${consignee}，请您及时查收', 'order_shipping_sms_enable'],
//        '6' => ['身份验证', '尊敬的用户，您的验证码为${code}, 请勿告诉他人.', 'bind_mobile_sms_enable'],
//        '7' => ['购买虚拟商品通知', '尊敬的用户，您购买的虚拟商品${goodsName}兑换码已生成,请注意查收.', 'virtual_goods_sms_enable'],
//        '8' => ['授权登录绑定手机号', '验证码${code}，授权登录绑定手机号, 请勿告诉他人，感谢您的支持!', 'regis_sms_enable'],
        '1' => ['通用', '您的验证码是${code}，该验证码3分钟内有效，请在页面中提交完成验证', 'regis_sms_enable'],
        '9' => ['代理商同步账号', '您好！恭喜您加入圃美多乐活优选，您的代理商用户名${user_name}，对应乐活优选商城ID：${user_id1}；请直接输入商城ID：${user_id2} 登录，登录密码是注册代理商时的手机号码后四位，请及时登录修改您的密码。', 'regis_sms_enable'],
        '10' => ['用户下载APP提示', '尊敬的圃美多会员您好！圃美多乐活优选APP上线了，截止3月31日前往https://mall.pumeiduo.com/#/pmd_Download下载并登录可领取随机红包喔！回T退订', 'regis_sms_enable']
    ],

    'APP_TOKEN_TIME' => 60 * 60 * 24, //App保持token时间 , 此处为1天

    /*
     *  订单用户端显示按钮
        去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
        取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
        确认收货  AND shipping_status=1 AND order_status=0
        评价      AND order_status=1
        查看物流  if(!empty(物流单号))
        退货按钮（联系客服）  所有退换货操作， 都需要人工介入   不支持在线退换货
     */

    /*分页每页显示数*/
    'PAGESIZE' => 10,

    'WX_PAY2' => 1,

    /**假设这个访问地址是 www.tpshop.cn/home/goods/goodsInfo/id/1.html
     *就保存名字为 home_goods_goodsinfo_1.html
     *配置成这样, 指定 模块 控制器 方法名 参数名
     */
    'HTML_CACHE_ARR' => [
        ['mca' => 'home_Goods_goodsInfo', 'p' => ['id']],
        ['mca' => 'home_Index_index'],  // 缓存首页静态页面
        ['mca' => 'home_Goods_ajaxComment', 'p' => ['goods_id', 'commentType', 'p']],  // 缓存评论静态页面 http://www.tpshop2.0.com/index.php?m=Home&c=Goods&a=ajaxComment&goods_id=142&commentType=1&p=1
        ['mca' => 'home_Goods_ajax_consult', 'p' => ['goods_id', 'consult_type', 'p']],  // 缓存咨询静态页面 http://www.tpshop2.0.com/index.php?m=Home&c=Goods&a=ajax_consult&goods_id=142&consult_type=0&p=2
    ],

    /*订单操作*/
    'CONVERT_ACTION' => [
        'pay' => '付款',
        'pay_cancel' => '取消付款',
        'confirm' => '确认订单',
        'cancel' => '取消确认',
        'invalid' => '作废订单',
        'remove' => '删除订单',
        'delivery' => '确认发货',
        'delivery_confirm' => '确认收货',
        'edit_address' => '修改订单地址'
    ],

    'WITHDRAW_STATUS' => [
        '-2' => '删除作废',
        '-1' => '审核失败',
        '0' => '申请中',
        '1' => '审核通过',
        '2' => '付款成功',
        '3' => '付款失败',
    ],

    'RECHARGE_STATUS' => [
        '0' => '待支付',
        '1' => '支付成功',
        '2' => '交易关闭',
    ],

    'erasable_type' => ['.gif', '.jpg', '.jpeg', '.bmp', '.png', '.mp4', '.3gp', '.flv', '.avi', '.wmv'],
    'COUPON_USER_TYPE' => ['全店通用', '指定商品可用', '指定分类商品可用', '', '折扣券', '兑换券'],

    'image_upload_limit_size' => 1024 * 1024 * 5, //上传图片大小限制

    /*任务类别*/
    'TASK_CATE' => [
        '1' => '日常任务',
        '2' => '推荐任务',
        '3' => '销售任务',
        '4' => '会员任务',
    ],

    'ACCOUNT_TYPE' => [
        '0' => '其他',
        '1' => '佣金结算',
        // '2'=> '积分消费',
        '3' => '下单消费',
        '4' => '积分收入',
        '5' => '积分收入 -- 下单送积分',
        '6' => '积分收入 -- 注册积分',
        '7' => '积分收入 -- 邀请积分',
        '8' => '积分收入 -- 签到积分',
        '21' => '积分收入-- 完善积分',
        // '9'=> '积分收入 -- 其他',
        '10' => '订单取消',
        // '11'=> '电商转入积分',
        '12' => '积分互转',
        '13' => '电子币互转',
        '14' => '任务获得',
        '15' => '任务获得 -- 电子币',
        '16' => '任务获得 -- 积分',
        // '17'=> '电商转入商城',
        '18' => '活动奖励',
        '19' => '商品售后',
        '20' => '账户提现',

    ],

    'ACCOUNT_TYPE_RELATION' => [
        '4' => [5, 6, 7, 8, 9],
        '14' => [15, 16],
    ],

    /*
     * HTNS物流公司配送状态
     */
    'HTNS_STATUS' => [
        '000' => '接收订单',
        '001' => '取消订单',
        '010' => '仓库搬入',
        '020' => '出库指示',
        '022' => 'PICK 生成',
        '030' => '快递单发行',
        '035' => '出口报关',
        '040' => '搬出完毕',
        '060' => '起运地机场出发',
        '070' => '目的地机场到达',
        '090' => '进口报关',
        '100' => '仓库搬出(目的地)',
        '110' => '入库当地物流公司仓库',
        '120' => '快递公司移交(搬出)',
        '990' => '返送',
        '991' => '配送失败',
        '999' => '配送完毕',
    ],

    'SUPPLIER_GOODS_SPEC' => [
        'color' => '颜色',
        'spec' => '尺码',
        '型号' => '型号',
        'size' => '尺寸'
    ],

    /*
     * OSS
     */
    'OSS_ACCESSKEY_ID' => \think\Env::get('OSS.ACCESSKEY.ID'),
    'OSS_ACCESSKEY_SECRET' => \think\Env::get('OSS.ACCESSKEY.SECRET'),
    'OSS_BUCKET' => \think\Env::get('OSS.BUCKET'),
    'OSS_ENDPOINT' => \think\Env::get('OSS.ENDPOINT'),
    'OSS_CALLBACK_URL' => \think\Env::get('OSS.CALLBACK.URL'),
    'OSS_CHILD_ACCESSKEY_ID' => \think\Env::get('OSS.CHLID.ACCESSKEY.ID'),
    'OSS_CHILD_ACCESSKEY_SECRET' => \think\Env::get('OSS.CHILD.ACCESSKEY.SECRET'),
    'OSS_CHILD_ROLE_ARN' => \think\Env::get('OSS.CHILD.ROLE.ARN'),

    /*
     * 小程序
     */
    'APPLET_TYPE' => \think\Env::get('APPLET.TYPE'),
    'APPLET_ID' => \think\Env::get('APPLET.ID'),
    'APPLET_PATH' => \think\Env::get('APPLET.PATH'),
];
