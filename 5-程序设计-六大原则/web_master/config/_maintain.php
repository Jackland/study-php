<?php
/**
 * 该文件定义在上线停服期间的配置属性
 */

return [
    // 是否处于维护状态
    'is_down' => defined('IS_DOWN') && IS_DOWN,
    // 维护token
    'down_token' => defined('DOWN_TOKEN') ? DOWN_TOKEN : '',
    // 维护结束时间
    'down_end_time' => defined('DOWN_END_TIME') ? DOWN_END_TIME : '',
    // 维护预计时间 字符串
    'down_estimate_time' =>
        defined('DOWN_ESTIMATE_TIME')
            ? DOWN_ESTIMATE_TIME
            : '19:00-21:00',
    // catalog白名单  支持匹配 或者 完整路由
    'catalog_whitelist' => [
        'api/*', // api接口全部允许通过
    ],
    // admin白名单 支持匹配 或者 完整路由
    'admin_whitelist' => [],
];
