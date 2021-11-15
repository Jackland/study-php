<?php

namespace App\Models\Product\Channel;

use App\Enums\Product\Channel\ModuleType;

class FeaturedSellersChannel extends BaseChannel
{

    /**
     * @inheritDoc
     */
    function getChannelData(array $param = []): array
    {
        $productsInfo = [];
        $searchInfo = [];
        $this->setIsStoreChannel(true);
        $storeInfo = $this->getProductsInfoBySellerIds(ModuleType::FEATURED_STORES, $param);
        return [
            'storeInfo' => $storeInfo,
            'productsInfo' => $productsInfo,
            'searchInfo' => $searchInfo,
        ];
    }
}
