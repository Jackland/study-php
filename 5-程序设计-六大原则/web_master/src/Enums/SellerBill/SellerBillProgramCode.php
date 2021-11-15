<?php

namespace App\Enums\SellerBill;

use Framework\Enum\BaseEnum;

/**
 * 结算状态
 * tb_seller_bill --> program_code
 *
 * Class SettlementStatus
 * @package App\Enums\SellerBill
 */
class SellerBillProgramCode extends BaseEnum
{
    const V1 = 'V1';
    const V2 = 'V2';
    const V3 = 'V3';
}