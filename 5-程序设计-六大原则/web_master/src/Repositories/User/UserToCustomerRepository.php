<?php

namespace App\Repositories\User;

use App\Models\User\UserToCustomer;

class UserToCustomerRepository
{
    /**
     * 根据国别获取 customer_id
     * @param int $countryId
     * @return array
     */
    public function getCustomerIdByCountry($countryId)
    {
        return UserToCustomer::query()
            ->select('account_manager_id')
            ->where('country_id', $countryId)
            ->distinct()
            ->pluck('account_manager_id')->toArray();
    }
}
