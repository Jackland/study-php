<?php

namespace App\Repositories\Warehouse;

use App\Enums\Warehouse\SellerInventoryAdjustStatus;
use App\Enums\Warehouse\SellerInventoryAdjustType;
use App\Models\Warehouse\SellerInventoryAdjust;

class SellerInventoryAdjustRepository
{
    /**
     * @param int $id inventory_id
     * @param int $customerId 用户ID
     * @return SellerInventoryAdjust|null
     */
    public function getInventoryAdjustById($id, $customerId)
    {
        return SellerInventoryAdjust::query()
            ->with('adjustDetail')
            ->where('customer_id', $customerId)
            ->find($id);
    }
}
