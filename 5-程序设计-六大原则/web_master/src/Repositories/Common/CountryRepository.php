<?php

namespace App\Repositories\Common;

use App\Components\Traits\RequestCachedDataTrait;
use App\Models\Customer\Country;
use App\Models\Customer\Currency;
use Cart\Customer as CurrentCustomer;
use Illuminate\Database\Eloquent\Collection;

class CountryRepository
{
    use RequestCachedDataTrait;

    /**
     * 获取显示的国家
     * @return Country[]|Collection
     */
    public function getShowCountriesIndexByCode()
    {
        return Country::query()
            ->where('show_flag', 1)
            ->orderBy('sort')
            ->get();
    }

    /**
     * 根据 code 获取
     * @param string $code 如：USA
     * @return Country|null
     */
    public function getByCode($code)
    {
        return Country::query()
            ->where('iso_code_3', $code)
            ->first();
    }

    /**
     * 根据登录的用户获取当前支持显示的国家
     * @param CurrentCustomer|null $customer
     * @return Country[]|Collection
     */
    public function getShowCountriesIndexByCodeWithCustomer(?CurrentCustomer $customer)
    {
        $cacheKey = [__CLASS__, __FUNCTION__, $customer ? $customer->getId() : null, 'v1'];
        $data = $this->getRequestCachedData($cacheKey);
        if ($data !== null) {
            return $data;
        }

        if ($customer && $customer->isLogged()) {
            $country = Country::find($customer->getCountryId());
            $countries = new Collection([$country]);
        } else {
            $countries = $this->getShowCountriesIndexByCode();
        }

        $this->setRequestCachedData($cacheKey, $countries);

        return $countries;
    }

    /**
     * 根据国家获取货币
     * @param Collection $countries
     * @return Currency[]|Collection
     */
    public function getCurrenciesByCountries($countries)
    {
        $ids = $countries->pluck('currency_id')->unique()->all();

        $cacheKey = [__CLASS__, __FUNCTION__, $ids, 'v1'];
        $data = $this->getRequestCachedData($cacheKey);
        if ($data !== null) {
            return $data;
        }

        $data = Currency::query()
            ->whereIn('currency_id', $ids)
            ->get();

        $this->setRequestCachedData($cacheKey, $data);

        return $data;
    }
}
