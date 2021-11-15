<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

/**
 * 议价
 */
class ProductQuoteInfo extends AbsComplexTransactionInfo
{
    public function __construct($productId)
    {
        $this->id = $productId;
    }
}
