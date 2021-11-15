<?php

namespace App\Models\Product\Channel;

use App\Enums\Product\Channel\ModuleType;

class NewStoresChannel extends BaseChannel
{

    /**
     * @inheritDoc
     */
    public function getChannelData(array $param = []): array
    {
        $productsInfo = [];
        $searchInfo = [];
        $this->setIsStoreChannel(true);
        $storeInfo = $this->getProductsInfoBySellerIds(ModuleType::NEW_STORE, $param);

        return [
            'storeInfo' => $storeInfo,
            'productsInfo' => $productsInfo,
            'searchInfo' => $searchInfo,
        ];
    }
}
