<?php

namespace App\Catalog\Enums\FeeOrder;

use Framework\Enum\BaseEnum;

/**
 * 费用单支付页来源页面
 *
 * Class DiscrepancyInvoiceType
 * @package App\Enums\Stock
 */
class FeeOrderSourcePage extends BaseEnum
{
    const SALES_ORDER = 'sales_order';//销售订单
    const FEE_ORDER = 'fee_order';//费用单
    const PURCHASE_ORDER = 'purchase_order';//采购单
}
