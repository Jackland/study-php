<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

abstract class AbsComplexTransactionInfo
{
    use Traits\CustomerSupportTrait;
    use Traits\WithBuyerPriceInfoTrait;

    /**
     * 产品ID
     * @var int
     */
    public $id;
}
