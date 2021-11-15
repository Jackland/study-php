<?php

namespace App\Repositories\Bd;

use App\Models\Bd\AccountManager;


class AccountManagerRepository
{
    /**
     * [isAmericanBd description] 校验是否是美国的招商bd
     * @param int $customerId
     * @param int $countryId
     *
     * @return bool
     */
    public function isAmericanBd(int $customerId,int $countryId = AMERICAN_COUNTRY_ID)
    {
        return AccountManager::query()->alias('a')
            ->leftJoinRelations('account as lm')
            ->where([
                'a.buyerId' => $customerId,
                'lm.country_id' => $countryId,
            ])
            ->exists();
    }
}