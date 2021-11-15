<?php

namespace App\Repositories\Order;

use App\Models\Track\CountryState;
use Psr\SimpleCache\CacheInterface;

class CountryStateRepository
{
    /**
     * 获取美国一件代发支持的州
     *
     * @return array
     */
    public function getUsaSupportState()
    {
        $key = 'usa_support_state_list';
        $cache = app()->get(CacheInterface::class);
        $stateArr = $cache->get($key);
        if (! $stateArr) {
            $stateList = CountryState::where('country_id', AMERICAN_COUNTRY_ID)->select(['abbr', 'county_e'])->orderBy('abbr')->get()->toArray();
            $stateArr = array_column($stateList, 'abbr', 'county_e');
            $cache->set($key, $stateArr, 300);
        }

        return $stateArr;
    }
}
