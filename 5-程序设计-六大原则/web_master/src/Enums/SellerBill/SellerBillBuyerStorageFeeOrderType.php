<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 结算状态
 * tb_seller_bill_buyer_storage --> fee_order_type
 *
 * Class SettlementStatus
 * @package App\Enums\SellerBill
 */
class SellerBillBuyerStorageFeeOrderType extends BaseEnum
{
    const SALES_ORDER = '1'; // 销售单
    const RMA = '2'; // RMA
}