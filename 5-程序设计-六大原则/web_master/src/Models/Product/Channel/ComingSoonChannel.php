<?php


namespace App\Models\Product\Channel;


use App\Enums\Product\Channel\ModuleType;
use Illuminate\Support\Collection;

class ComingSoonChannel extends BaseChannel
{

    /**
     * @inheritDoc
     */
    function getChannelData(array $param = []): array
    {
        $co = new Collection($param);
        $storeInfo = [];
        $productsInfo = [];
        $searchInfo = [];
        if ($co->get('search_flag')) {
            // coming soon search
            $searchInfo = $this->getProductsInfoBySearch(ModuleType::COMING_SOON_SEARCH, $param);
        } else {
            // first arrivals
            $storeInfo = $this->getProductsInfoByIds(ModuleType::COMING_SOON, $param);
            // future goods
            $productsInfo = $this->getProductsInfoByIds(ModuleType::FUTURE_GOODS, $param);
        }

        return [
            'storeInfo' => $storeInfo,
            'productsInfo' => $productsInfo,
            'searchInfo' => $searchInfo,
        ];
    }
}
