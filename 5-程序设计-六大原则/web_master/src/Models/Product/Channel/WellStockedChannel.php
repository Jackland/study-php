<?php

namespace App\Models\Product\Channel;

use App\Enums\Product\Channel\ModuleType;

class WellStockedChannel extends BaseChannel
{

    /**
     * @inheritDoc
     */
    function getChannelData(array $param = []): array
    {
        $co = collect($param);
        $productsInfo = [];
        $searchInfo = [];
        $storeInfo = [];
        if ($co->get('search_flag')) {
            $searchInfo = $this->getProductsInfoBySearch(ModuleType::WELL_STOCKED_SEARCH, $param);
        } else {
            $storeInfo = $this->getProductsInfoBySellerIds(ModuleType::WELL_STOCKED, $param);
        }
        return [
            'storeInfo' => $storeInfo,
            'productsInfo' => $productsInfo,
            'searchInfo' => $searchInfo,
        ];
    }
}
