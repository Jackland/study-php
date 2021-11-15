<?php

namespace App\Enums\Margin;

use Framework\Enum\BaseEnum;

class MarginContractSettingModule extends BaseEnum
{
    const MODULE_DAYS = 'Contract Days'; // 合约天数配置名
    const MODULE_DEPOSIT_PERCENTAGE = 'Deposit Percentage'; // 合约比例配置名
    const MODULE_MIN_QUANTITY = 'Minimum Selling Quantity'; // 合约最小数量配置名
    const MODULE_MAX_QUANTITY = 'Maximum Selling Quantity'; // 合约最小数量配置名
    const MODULE_STORAGE_FEE_SELLER = 'Storage Fee（Seller）'; // 合约是否向Seller收取仓租配置名
    const MODULE_STORAGE_FEE_BUYER = 'Storage Fee（Buyer）'; // 合约是否向Buyer收取仓租配置名
}
