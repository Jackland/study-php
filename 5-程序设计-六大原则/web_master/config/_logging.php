<?php

use App\Logging\Handlers\WeChatBusinessRobotHandler;
use App\Logging\LogChannel;
use App\Logging\Processor\WebUserProcessor;
use Framework\Log\Processor\VarDumperProcessor;
use Framework\Log\Processor\WebServerProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Psr\Log\LogLevel;

return [
    'defaultChannel' => 'app',
    'addDebugBar' => true,
    /**
     * 全局日志格式化，名字 => 格式
     * 名字用于 channels 中的 formatter
     * 格式可以是 callback，当为 string 时使用 FormatterInterface，默认为 LineFormatter
     * @see https://seldaek.github.io/monolog/doc/02-handlers-formatters-processors.html#formatters
     * @see \Framework\Log\LogManager::prepareMonoLogFormatterFromGlobal()
     */
    'formatters' => [
        'default' => "[%datetime%][%extra.uid%]: %channel%.%level_name%: %message% %context% %extra%\n",
        'channel' => "[%datetime%][%extra.uid%][%level_name%][%context.ip%][%context.customer_id%]: %message%\n%context.webServerVars%%context.varDumper%",
        'alarm' => "[%datetime%][%extra.uid%][%context.ip%][%context.customer_id%][%context.title%]: %message%\n",
        'channelMixed' => "[%datetime%][%extra.uid%][%channel%][%level_name%][%context.ip%][%context.customer_id%]: %message%\n%context.webServerVars%%context.varDumper%",
    ],
    /**
     * 全局处理器，名字 => 格式
     * 名字用于 channels 中的 processors
     * 格式为 callback，为一组处理器
     * @see https://seldaek.github.io/monolog/doc/02-handlers-formatters-processors.html#processors
     * @see \Framework\Log\LogManager::prepareMonoLogProcessorsFromGlobal()
     */
    'processors' => [
        'default' => function () {
            return [
                new WebServerProcessor(), // 处理 context.webServerVars
                new VarDumperProcessor(), // 处理 context.varDumper
                new PsrLogMessageProcessor(), // 处理 context 替换 {key}
                new UidProcessor(), // 生成uid
            ];
        },
        'channel' => function () {
            return [
                new WebUserProcessor(), // 自动载入 userId 和 ip
                new WebServerProcessor(LogLevel::ERROR, []), // 处理 context.webServerVars
                new VarDumperProcessor(), // 处理 context.varDumper
                new PsrLogMessageProcessor(), // 处理 context 替换 {key}
                new UidProcessor(), // 生成uid
            ];
        },
    ],
    /**
     * 定义所有通道
     * 名字 => handlers（为多个）
     * handlers 为数组，每个必须包含一个 key 为 'handler'，值为 callback，支持的 handler 见以下链接
     * level/formatter/processors 可选
     * @see https://seldaek.github.io/monolog/doc/02-handlers-formatters-processors.html#handlers
     * @see \Framework\Log\LogManager::prepareMonoLogHandlers()
     */
    'channels' => array_merge(
        LogChannel::getEasyFromChannelForConfig(),
        [
            // 运维告警
            'alarm' => array_merge(
                LogChannel::defaultChannelHandlers('alarm'),
                [
                    [
                        'handler' => function () {
                            return new WeChatBusinessRobotHandler(get_env('WE_CHAT_BUSINESS_ROBOT_KEY', ''));
                        },
                        'level' => LogLevel::DEBUG,
                        'formatter' => 'alarm',
                    ],
                ]
            ),
        ]
    ),
];
