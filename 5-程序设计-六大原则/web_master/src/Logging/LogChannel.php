<?php

namespace App\Logging;

use Framework\Enum\BaseEnum;
use Monolog\Handler\RotatingFileHandler;
use Psr\Log\LogLevel;

class LogChannel extends BaseEnum
{
    // 注意添加的 channel 值不要和 config/_logging.php 下 channels 已经存在的 key 重复
    const APP = 'app';
    const ERROR = 'error';
    const ORM = 'orm';
    const ORDER = 'order';
    const RABBIT_MQ = 'rabbitMQ';
    const SALES_ORDER = 'salesOrder';
    const WE_CHAT_BUSINESS_ROBOT = 'weChatBusinessRobot';
    const IMAGE_CLOUD = 'imageCloud';
    const STORAGE_FEE = 'storageFee';
    const API_SALES_ORDER = 'apiSalesOrder';
    const FEE_ORDER = 'feeOrder';
    const IMPORT_PRODUCTS = 'importProducts';
    const MODIFY_PRICES = 'modifyPrices';
    const SEARCH = 'search';
    const BUYER_SELLER_RECOMMEND = 'buyerSellerRecommend';
    const SYNC_PRODUCTS = 'syncProducts';
    const ADD_EDIT_PRODUCT = 'addEditProduct';
    const MARKETING = 'marketing';
    const SAFE_CAPTCHA = 'safeCaptcha';
    const SAFE_CHECKER = 'safeChecker';
    const SAFE_CHECKER_IP_RATE_LIMIT = 'safeCheckerIpRateLimit';
    const SAFE_CHECKER_IP_ROUTE = 'safeCheckerIpRoute';
    const SAFE_CHECKER_LOGIN_IP_CHANGE = 'safeCheckerLoginIpChange';
    const SAFE_CHECKER_LOGIN_COUNT = 'safeCheckerLoginCount';
    const SYNC_CUSTOMER = 'syncCustomer';
    const SELLER_OPERATING = 'sellerOperating';
    const AUTO_PURCHASE = 'autoPurchase';
    const RECEIPT_ORDER = 'receiptOrder';
    const PACK_ZIP = 'packZip';
    const APPLY_CLAIM = 'applyClaim';
    const REMOTE_API = 'remoteAPI';
    const SMS = 'sms';
    const CHANNEL_PRODUCTS = 'channelProducts';
    const CWF = 'cloudWholesaleFulfillment';
    const PRODUCT_FERIGHT = 'productFreight';
    const ONSITE_SELLER_TYPE_CHANGE = 'onsiteSellerTypeChange';
    const ADMIN_WHITE_AUTH = 'adminWhiteAuth';
    const AIRWALLEX = 'airwallex';
    const TIME_LIMIT_DISCOUNT = 'timeLimitDiscount';


    public static function getEasyFromChannelForConfig(): array
    {
        $channelNames = static::getValues();

        $channelConfigs = [];
        foreach ($channelNames as $channelName) {
            $channelConfigs[$channelName] = static::defaultChannelHandlers($channelName);
        }

        return $channelConfigs;
    }

    public static function defaultChannelHandlers($channelName): array
    {
        $handlers = [];
        if (get_env('CHANNEL_LOG_MODE_SPLIT', true)) {
            $handlers[] = static::channelRotatingFileHandler($channelName, 'channel');
        }
        if (get_env('CHANNEL_LOG_MODE_MIX', false)) {
            if (!in_array($channelName, get_env('CHANNEL_LOG_MODE_MIX_SKIP', []))) {
                $handlers[] = static::channelRotatingFileHandler('channelMixed', 'channelMixed');
            }
        }
        return $handlers;
    }

    protected static function channelRotatingFileHandler($channelName, $formatter): array
    {
        return [
            'handler' => function () use ($channelName) {
                $filename = aliases("@runtime/logs/{$channelName}/{$channelName}.log");
                return new RotatingFileHandler($filename);
            },
            'level' => OC_DEBUG ? LogLevel::DEBUG : LogLevel::INFO,
            'formatter' => $formatter,
            'processors' => 'channel',
        ];
    }
}
