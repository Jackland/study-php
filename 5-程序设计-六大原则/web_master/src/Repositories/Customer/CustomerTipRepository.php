<?php

namespace App\Repositories\Customer;

use App\Models\Customer\CustomerTip;

class CustomerTipRepository
{
    /**
     * 用户根据type_key查找是否存在记录
     * @param int $customerId
     * @param string $typeKey
     * @return bool
     */
    public function checkCustomerTipExistsByTypeKey(int $customerId, string $typeKey)
    {
        return CustomerTip::query()->where([
            'customer_id' => $customerId,
            'type_key' => $typeKey,
        ])->exists();
    }
}

