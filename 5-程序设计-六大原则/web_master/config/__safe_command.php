<?php
/**
 * 出于安全考虑，prod 环境下需要限制 command 脚本的运行
 * 参数使用 @see CommandStartingListener
 */
return [
    // 所有命令是否可以执行，仅开发环境允许
    'all_can_exec' => OC_ENV === 'dev',
    // 允许执行的 command 白名单
    'white_list' => [
        // 'command_name' => 1
        'pdf:dompdf-load-fonts' => 1,
    ],
    // 临时执行参数
    'tmp_exec' => [
        'allow_option' => '--tmp-exec-for-one-time', // 设置允许临时执行的 option
        'count_down_option' => '--tmp-exec-count-down', // 设置倒计时的 option
        'count_down_time' => 10, // 倒计时默认值
    ],
];
