<?php

namespace App\Enums\CreditLine;

use Framework\Enum\BaseEnum;

/**
 * 信用额度收付款平台类型 -- 级别
 * tb_sys_credit_line_platform_type --> type_level
 *
 * Class SysCreditLintPlatformTypeLevel
 * @package App\Enums\CreditLine
 */
class SysCreditLintPlatformTypeLevel extends BaseEnum
{
    const FIRST = 1; // 一级
    const SECOND = 2; // 二级
}
