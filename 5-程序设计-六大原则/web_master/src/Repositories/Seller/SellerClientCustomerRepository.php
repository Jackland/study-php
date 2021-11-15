<?php

namespace App\Repositories\Seller;

use App\Models\Seller\SellerClientCustomerMap;

class SellerClientCustomerRepository
{
    /**
     * 是否有创建店铺限时活动权限
     * @param int|null $sellerId
     * @return bool
     */
    public function checkPermission($sellerId = null)
    {
        if (is_null($sellerId)) {
            $sellerId = customer()->getId();
        }

        $accountApplyInfo = SellerClientCustomerMap::query()
            ->alias('a')
            ->leftJoin('tb_seller_account_apply as b', 'a.apply_id', '=', 'b.id')
            ->where('a.seller_id', (int)$sellerId)
            ->selectRaw('b.can_create_time_limit')
            ->first();

        if (empty($accountApplyInfo) || $accountApplyInfo->can_create_time_limit == 1) {
            return true;
        }
        return false;
    }

}
