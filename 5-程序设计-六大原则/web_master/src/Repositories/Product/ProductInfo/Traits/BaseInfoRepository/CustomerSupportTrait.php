<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfoRepository;

use App\Models\Customer\Customer;
use App\Repositories\Product\ProductInfo\BaseInfo;

/**
 * BaseInfoRepository 支持给 BaseInfo 提供 customer 对象
 */
trait CustomerSupportTrait
{
    /**
     * 支持 customer
     * @param array|BaseInfo[] $infos
     */
    protected function supportCustomer(array $infos, ?int $customerId)
    {
        if (!$customerId) {
            return;
        }
        if ($customer = Customer::find($customerId)) {
            foreach ($infos as $info) {
                $info->setCustomer($customer);
            }
        }
    }
}
