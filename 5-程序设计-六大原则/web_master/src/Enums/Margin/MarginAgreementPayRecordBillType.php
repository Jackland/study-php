<?php

namespace App\Enums\Margin;

use Framework\Enum\BaseEnum;

/**
 * tb_sys_margin_agreement_pay_record -> bill_type
 *
 * Class MarginAgreementPayRecordType
 * @package App\Enums\Margin
 */
class MarginAgreementPayRecordBillType extends BaseEnum
{
    const EXPEND = 1; // 支出
    const INCOME = 2; // 收入
}