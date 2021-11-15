<?php

namespace App\Helpers;

use Log;

class LoggerHelper
{
    /**
     * Order Invoice 生成日志
     *
     * @param $message
     * @param string $type
     */
    public static function logOrderInvoice($message, $type = 'info')
    {
        LoggerHelper::wrapWithDaily('orderInvoice/invoice.log', function () use ($message, $type) {
            Log::$type(json_encode($message, JSON_UNESCAPED_UNICODE));
        });
    }

    public static function logEmail($message, $type = 'info')
    {
        LoggerHelper::wrapWithDaily('email/email.log', function () use ($message, $type) {
            Log::$type(json_encode($message, JSON_UNESCAPED_UNICODE));
        });
    }


    public static function logPackZip($message, $type = 'info')
    {
        LoggerHelper::wrapWithDaily('packZip/zip.log', function () use ($message, $type) {
            Log::$type(json_encode($message, JSON_UNESCAPED_UNICODE));
        });
    }

    public static function logCalcPurchaseOrder($message, $type = 'info')
    {
        LoggerHelper::wrapWithDaily('product/purchase_order.log', function () use ($message, $type) {
            Log::$type(json_encode($message, JSON_UNESCAPED_UNICODE));
        });
    }

    /**
     * 系统消息日志记录
     *
     * @param $message
     * @param string $type
     */
    public static function logSystemMessage($message, $type = 'error')
    {
        LoggerHelper::wrapWithDaily('message/system.log', function () use ($message, $type) {
            Log::$type(json_encode($message, JSON_UNESCAPED_UNICODE));
        });
    }

    /**
     * 发送消息日志记录
     *
     * @param $message
     * @param string $type
     */
    public static function logSendMessage($message, string $type = 'error')
    {
        LoggerHelper::wrapWithDaily('message/send.log', function () use ($message, $type) {
            Log::$type(json_encode($message, JSON_UNESCAPED_UNICODE));
        });
    }

    /**
     * 文件操作日志记录
     *
     * @param $message
     * @param string $type
     */
    public static function logFile($message, $type = 'error')
    {
        LoggerHelper::wrapWithDaily('file/file.log', function () use ($message, $type) {
            Log::$type(json_encode($message, JSON_UNESCAPED_UNICODE));
        });
    }

    /**
     * 处理需要按日记录的日志
     * 由于laravel5.5版本没有 channel 的概念，使用 Log::useDailyFiles 会 push 新的一个 handler
     * 导致日志会在多个地方记录
     * 如果在队列中使用，更加会连续记录N多条相同日志，且影响后续的其他日志记录
     * @param $logFilename
     * @param $logFn
     */
    public static function wrapWithDaily($logFilename, $logFn)
    {
        $monolog = Log::getMonolog();
        $oldHandler = $monolog->popHandler();
        Log::useDailyFiles(storage_path('logs/' . $logFilename));

        call_user_func($logFn);

        $monolog->popHandler();
        $monolog->pushHandler($oldHandler);
    }
}