<?php

namespace App\Logging;

use App\Logging\Traits\AlarmTrait;
use Framework\Helper\Json;
use Framework\Log\Processor\VarDumperProcessor;
use Framework\Log\Processor\WebServerProcessor;

/**
 * @method static void app($msg, $type = 'info', $context = [])
 * @method static void error($msg, $type = 'info', $context = [])
 * @method static void orm($msg, $type = 'info', $context = [])
 * @method static void order($msg, $type = 'info', $context = [])
 * @method static void rabbitMQ($msg, $type = 'info', $context = [])
 * @method static void salesOrder($msg, $type = 'info', $context = [])
 * @method static void weChatBusinessRobot($msg, $type = 'info', $context = [])
 * @method static void imageCloud($msg, $type = 'info', $context = [])
 * @method static void storageFee($msg, $type = 'info', $context = [])
 * @method static void apiSalesOrder($msg, $type = 'info', $context = [])
 * @method static void feeOrder($msg, $type = 'info', $context = [])
 * @method static void importProducts($msg, $type = 'info', $context = [])
 * @method static void modifyPrices($msg, $type = 'info', $context = [])
 * @method static void search($msg, $type = 'info', $context = [])
 * @method static void marketing($msg, $type = 'info', $context = [])
 * @method static void buyerSellerRecommend($msg, $type = 'info', $context = [])
 * @method static void syncProducts($msg, $type = 'info', $context = [])
 * @method static void addEditProduct($msg, $type = 'info', $context = [])
 * @method static void safeChecker($msg, $type = 'info', $context = [])
 * @method static void syncCustomer($msg, $type = 'info', $context = [])
 * @method static void sellerOperating($msg, $type = 'info', $context = [])
 * @method static void autoPurchase($msg, $type = 'info', $context = [])
 * @method static void receiptOrder($msg, $type = 'info', $context = [])
 * @method static void packZip($msg, $type = 'info', $context = [])
 * @method static void applyClaim($msg, $type = 'info', $context = [])
 * @method static void remoteAPI($msg, $type = 'info', $context = [])
 * @method static void sms($msg, $type = 'info', $context = [])
 * @method static void channelProducts($msg, $type = 'info', $context = [])
 * @method static void cloudWholesaleFulfillment($msg, $type = 'info', $context = [])
 * @method static void productFreight($msg, $type = 'info', $context = [])
 * @method static void onsiteSellerTypeChange($msg, $type = 'info', $context = [])
 * @method static void timeLimitDiscount($msg, $type = 'info', $context = [])
 * @method static void adminWhiteAuth($msg, $type = 'info', $context = [])
 * @method static void airwallex($msg, $type = 'info', $context = [])
 */
class Logger
{
    use AlarmTrait;

    // 特殊 context
    /**
     * @see WebServerProcessor
     */
    const CONTEXT_WEB_SERVER_VARS = WebServerProcessor::KEY;
    /**
     * @see VarDumperProcessor
     */
    const CONTEXT_VAR_DUMPER = VarDumperProcessor::KEY;

    public static function __callStatic($name, $arguments)
    {
        $level = $arguments[1] ?? 'info';
        $context = $arguments[2] ?? [];
        logger($name)->log($level, static::formatMessage($arguments[0]), (array)$context);
    }

    private static function formatMessage($message)
    {
        if (is_array($message)) {
            $message = Json::encode($message);
        }

        return $message;
    }
}
