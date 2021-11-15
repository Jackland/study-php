<?php
/**
 * 该文件为页面访问安全检查配置
 * 使用时复制该文件，然后去除 .example
 * 然后再修改相关配置即可
 */

return [
    // 全局配置参数，功能见 ControllerStartupSafeChecker::isEnable
    // 全局开关，单个控制的开关在后面
    'enable' => false,
    // 当请求是 ajax 时是否检查，false 为不检查
    'enableWhenAjax' => false,
    // 验证码的 url 地址
    'captchaUrl' => 'http://yzc.test/index.php?route=safe/captcha',
    // 路由白名单，白名单内的路由不做校验
    'whiteListRoutes' => [
        'safe/*', // 验证码的路由，必须
        'api/*',
        'account/logout',
    ],
    // ip白名单，白名单内的 ip 不做校验
    'whiteListIps' => [
        '::1',
        '127.0.0.1',
        '10.*',
        '172.*',
        '192.168.*',
    ],
    // 流程 debug 日志开关，线上无需启用
    'checkerDebug' => false,

    // 与验证码页面交互时的 AES 密钥
    // 生成：base64_encode(openssl_random_pseudo_bytes(32))
    'AES_KEY' => 'ZULnwdUJGWoX0OPJFdgYfM1zEJNaSSP6+etzAX1lVPE=',
    // 生成：base64_encode(openssl_random_pseudo_bytes(16));
    'AES_IV' => 'GXo1x3fsrl6k0uAODL5HBg==',

    // 未登录时，ip首次访问检查路由时需要验证
    App\Components\PageViewSafe\IpRouteChecker::class => [
        // 开关
        'enable' => true,
        // 触发后是否跳验证码页面
        'goCaptchaWhenTrigger' => false,
        // 当 goCaptchaWhenTrigger 为 true 时，这些 ip 不跳
        'goCaptchaWhiteListIps' => [
        ],
        // 当 goCaptchaWhenTrigger 为 false 时，这些 ip 任然会跳
        'goCaptchaBlackListIps' => [
        ],
        // 检查的路由
        'checkRoutes' => [
            'product/*',
        ],
        // 检查通过后缓存时间，单位秒
        'cacheTime' => 15 * 24 * 3600,
    ],

    // 用户登录次数达到限制后，跳转验证码
    App\Components\PageViewSafe\LoginCountChecker::class => [
        // 开关
        'enable' => true,
        // 触发后是否跳验证码页面
        'goCaptchaWhenTrigger' => false,
        // 当 goCaptchaWhenTrigger 为 true 时，这些 ip 不跳
        'goCaptchaWhiteListIps' => [
        ],
        // 当 goCaptchaWhenTrigger 为 false 时，这些 ip 任然会跳
        'goCaptchaBlackListIps' => [
        ],
        // 当 $goCaptchaWhenTrigger 为 true 时，这些 customerId 不跳，优先判断 goCaptchaWhiteListIps
        'goCaptchaWhiteListCustomerIds' => [
        ],
        // 当 $goCaptchaWhenTrigger 为 false 时，这些 customerId 任然会跳，优先判断 goCaptchaBlackListIps
        'goCaptchaBlackListCustomerIds' => [
        ],
        // 不计登录次数的 ip
        'whiteListIps' => [
        ],
        // 登录次数限制，配置为5，则登录成功第6次会跳验证码
        'limitCount' => [
            'global' => 5, // 针对所有人
            //1 => 2, // 针对 1 这个用户配置数量
        ],
        // 统计登录次数的缓存时间
        'counterCacheTime' => 12 * 3600,
        // 需要验证时缓存时间
        'shouldVerifyCacheTime' => 3600,
    ],

    // 已登录客户，ip切换之后需要验证
    App\Components\PageViewSafe\LoginIpChangeChecker::class => [
        // 开关
        'enable' => true,
        // 触发后是否跳验证码页面
        'goCaptchaWhenTrigger' => false,
        // 当 goCaptchaWhenTrigger 为 true 时，这些 ip 不跳
        'goCaptchaWhiteListIps' => [
        ],
        // 当 goCaptchaWhenTrigger 为 false 时，这些 ip 任然会跳
        'goCaptchaBlackListIps' => [
        ],
        // 当 $goCaptchaWhenTrigger 为 true 时，这些 customerId 不跳，优先判断 goCaptchaWhiteListIps
        'goCaptchaWhiteListCustomerIds' => [
        ],
        // 当 $goCaptchaWhenTrigger 为 false 时，这些 customerId 任然会跳，优先判断 goCaptchaBlackListIps
        'goCaptchaBlackListCustomerIds' => [
        ],
        // 检查通过后缓存时间，单位秒
        'cacheTime' => 180,
        // ip切换容忍数
        'ipCountLimit' => [
            'global' => 1, // 针对所有人
            //1 => 2, // 针对 1 这个用户配置数量
        ],
    ],

    // ip访问频率超过时需要验证
    App\Components\PageViewSafe\IpRateLimitChecker::class => [
        // 开关
        'enable' => true,
        // 触发后是否跳验证码页面
        'goCaptchaWhenTrigger' => false,
        // 当 goCaptchaWhenTrigger 为 true 时，这些 ip 不跳
        'goCaptchaWhiteListIps' => [
        ],
        // 当 goCaptchaWhenTrigger 为 false 时，这些 ip 任然会跳
        'goCaptchaBlackListIps' => [
        ],
        'rules' => [
            // 规则说明：
            // key 为 匹配路由，可以是 product/* 的形式，匹配方式见 Support::matchWildcard()
            // 值为二维数组，表示意思，每[time]分钟访问超过[limit]次数之后报警，mode 见 IpRateLimitChecker::MODE_ 说明
            'product/product' => [
                ['time' => 1, 'limit' => 30],
                ['time' => 60, 'limit' => 400],
                ['time' => 180, 'limit' => 600],
            ],
            'product/*' => [
                ['time' => 1, 'limit' => 40],
                ['time' => 60, 'limit' => 500],
                ['time' => 180, 'limit' => 800],
            ],
            '*' => [
                ['time' => 1, 'limit' => 200, 'mode' => 'all'],
            ],
        ],
    ],
];
