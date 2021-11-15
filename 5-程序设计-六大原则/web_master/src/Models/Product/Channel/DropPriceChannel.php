<?php

namespace App\Models\Product\Channel;

use App\Enums\Product\Channel\ModuleType;
use Illuminate\Support\Collection;

class DropPriceChannel extends BaseChannel
{
    /**
     * @inheritDoc
     */
    public function getChannelData(array $param = []): array
    {
        $co = new Collection($param);
        $storeInfo = [];
        $productsInfo = [];
        $searchInfo = [];
        if($co->get('search_flag')){
            // best sellers
            $searchInfo = $this->getProductsInfoBySearch(ModuleType::DROP_PRICE,$param);
        }else{
            // sales growth rate
            $productsInfo = $this->getProductsInfoByIds(ModuleType::RECENT_PRICE_DROPS,$param);
        }

        return [
            'storeInfo' => $storeInfo,
            'productsInfo' => $productsInfo,
            'searchInfo' => $searchInfo,
        ];
    }
}
