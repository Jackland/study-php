<?php

namespace App\Repositories\Dictionary;

use App\Models\Dictionary\Dictionary;
use App\Enums\Safeguard\SafeguardClaimConfig;

class DictionaryRepository
{

    /**
     * 获取销售平台(目前保险模块调用，其它功能可复用)
     * @return array
     */
    public function getSalePlatform()
    {
        $cacheKey = [__CLASS__, __FUNCTION__, 'salesPlatForm'];
        $cache = cache();
        if ($cache->has($cacheKey)) {
            return $cache->get($cacheKey);
        }
        $salePlatForms = Dictionary::query()->where('DicCategory', SafeguardClaimConfig::SALE_PLATFORM)
            ->select(['DicKey', 'DicValue', 'Description'])
            ->orderBy('DicKey')
            ->get()
            ->toArray();
        if ($salePlatForms) {
            $cache->set($cacheKey, $salePlatForms, SafeguardClaimConfig::PLATFORM_OR_REASON_TTL);
        }
        return $salePlatForms;
    }

}
