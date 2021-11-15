<?php

namespace App\Services\Customer;

use App\Models\Customer\CustomerTip;
use Carbon\Carbon;

class CustomerTipService
{
    /**
     * 记录用户tip
     * @param int $customerId
     * @param string $typeKey
     * @return bool
     */
    public function insertCustomerTip(int $customerId, string $typeKey)
    {
        $time = Carbon::now();
        return CustomerTip::query()->insert([
            'customer_id' => $customerId,
            'type_key' => $typeKey,
            'create_time' => $time,
            'update_time' => $time,
        ]);
    }
}
