<?php

namespace App\Repositories\Seller\SellerInfo;

use App\Models\CustomerPartner\CustomerPartnerToCustomer;

class BaseInfoRepository
{
    private $sellerIds;

    public function __construct(array $ids)
    {
        $this->sellerIds = $ids;
    }

    /**
     * @return array|BaseInfo[]
     */
    public function getInfos(): array
    {
        $query = CustomerPartnerToCustomer::query()
            ->with(['customer'])
            ->whereIn('customer_id', $this->sellerIds);

        $sellers = $query->get();
        $infos = [];
        foreach ($sellers as $seller) {
            $infos[$seller->customer_id] = new BaseInfo($seller->customer, $seller);
        }
        return $infos;
    }
}
