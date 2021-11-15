<?php

namespace App\Enums\Margin;

use Framework\Enum\BaseEnum;

/**
 * tb_sys_margin_agreement_pay_record -> type
 *
 * Class MarginAgreementPayRecordType
 * @package App\Enums\Margin
 */
class MarginAgreementPayRecordType extends BaseEnum
{
    const LINE_OF_CREDIT = 1; // 授信额度
    const ACCOUNT_RECEIVABLE = 3; // 应收款
    const GUARANTEE = 4; // 抵押物
}
