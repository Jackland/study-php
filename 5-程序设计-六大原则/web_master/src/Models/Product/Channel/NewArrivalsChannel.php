<?php


namespace App\Models\Product\Channel;

use App\Enums\Product\Channel\ModuleType;
use Illuminate\Support\Collection;

class NewArrivalsChannel extends BaseChannel
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
        if ($co->get('search_flag')) {
            // new arrival
            $searchInfo = $this->getProductsInfoBySearch(ModuleType::NEW_ARRIVALS, $param);
        } else {
            // new stored add
            $storeInfo = $this->getProductsInfoBySellerIds(ModuleType::NEW_STORE, $param);
            // hot new
            $productsInfo = $this->getProductsInfoByIds(ModuleType::HOT_NEW, $param);
        }

        return [
            'storeInfo' => $storeInfo,
            'productsInfo' => $productsInfo,
            'searchInfo' => $searchInfo,
        ];
    }
}
