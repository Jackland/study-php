<?php

use App\Enums\Country\Country;
use App\Enums\Platform\PlatformMapping;
use App\Models\Platform\Platform;

/**
 * Class ModelAccountPlatform
 */
class ModelAccountPlatform extends Model
{
    /**
     * @param string $from
     * @return array
     */
    public function keyList($from = ''): array
    {
        $results = [];
        //1 Wayfair
        //2 Amazon
        //3 Walmart
        $platformIds = [];
        switch ($this->customer->getCountryId()) {
            case AMERICAN_COUNTRY_ID://美
                if ($this->customer->isCollectionFromDomicile()) {
                    if($from == 'warehouse'){
                        $platformIds = [
                            PlatformMapping::WAYFAIR,
                            PlatformMapping::AMAZON,
                            PlatformMapping::WALMART,
                            PlatformMapping::OVERSTOCK,
                            PlatformMapping::HOMEDEPOT,
                        ];
                    } else {
                        $platformIds = [
                            PlatformMapping::WAYFAIR,
                            PlatformMapping::AMAZON,
                            PlatformMapping::WALMART,
                            PlatformMapping::OVERSTOCK,
                            PlatformMapping::HOMEDEPOT,
                        ];
                    }
                } else {
                    $platformIds = [
                        PlatformMapping::AMAZON,
                        PlatformMapping::EBAY,
                    ];
                }
                break;
            case Country::BRITAIN://英
                if ($this->customer->isCollectionFromDomicile()) {
                    $platformIds = [
                        PlatformMapping::WAYFAIR,
                        PlatformMapping::AMAZON,
                    ];
                }
                break;
            case Country::GERMANY://德
                if ($this->customer->isCollectionFromDomicile()) {
                    $platformIds = [
                        PlatformMapping::WAYFAIR,
                    ];
                }
                break;
            case JAPAN_COUNTRY_ID://日
                break;
            default:
                break;
        }

        if($platformIds){
            $results = Platform::query()
                ->where('is_deleted',0)
                ->whereIn('platform_id',$platformIds)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('platform_id')
                ->toArray();
        }
        return $results;
    }
}
