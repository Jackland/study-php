<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction\Traits\InfoRepository;

use App\Models\Customer\Customer;
use App\Repositories\Product\ProductInfo\ComplexTransaction\AbsComplexTransactionInfo;

/**
 * AbsComplexTransactionRepository 支持给 Info 提供 customer 对象
 */
trait CustomerSupportTrait
{
    /**
     * 支持 customer
     * @param array|AbsComplexTransactionInfo[] $infos
     */
    protected function supportInfoCustomer(array $infos, ?Customer $customer)
    {
        if (!$customer) {
            return;
        }
        foreach ($infos as $info) {
            $info->setCustomer($customer);
        }
    }
}
