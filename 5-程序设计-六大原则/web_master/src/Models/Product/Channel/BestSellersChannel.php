<?php

namespace App\Models\Product\Channel;

use App\Enums\Product\Channel\ModuleType;
use App\Logging\Logger;
use Illuminate\Support\Collection;

class BestSellersChannel extends BaseChannel
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
            $searchInfo = $this->getProductsInfoBySearch(ModuleType::BEST_SELLERS,$param);
        }else{
            // feature stores
            $storeInfo = $this->getProductsInfoBySellerIds(ModuleType::FEATURED_STORES,$param);
            // sales growth rate
            $start_time = microtime(true);
            $productsInfo = $this->getProductsInfoByIds(ModuleType::SALES_GROWTH_RATE,$param);
            $end_tme = microtime(true);
            logger::channelProducts("getProductsInfoByIds function 耗时：".($end_tme - $start_time));
        }

        return [
            'storeInfo' => $storeInfo,
            'productsInfo' => $productsInfo,
            'searchInfo' => $searchInfo,
        ];
    }
}
