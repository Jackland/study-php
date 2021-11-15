<?php

namespace App\Repositories\Seller;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\Seller\SellerProductRatio;
use App\Models\Seller\SellerProductRatioLog;
use App\Models\Setting\PriceSpecificSplit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;

class SellerProductRatioRepository
{
    use RequestCachedDataTrait;

    private $defaultDateTime = [
        Country::GERMANY => '2021-03-10 15:00:00',// 德国
        Country::BRITAIN => '2021-03-10 16:00:00',// 英国
    ];

    /**
     * 获取当前seller 费率模型,只会查询oc_seller_product_ratio表，如果要获取带默认值的，请使用getSellerProductRatio
     *
     * @param int $sellerId
     * @return SellerProductRatio||Model|null
     */
    public function getProductRatioBySellerId(int $sellerId)
    {
        return SellerProductRatio::query()->where('seller_id', $sellerId)->first();
    }

    /**
     * 获取seller 费率，如果不存在会用new一个SellerProductRatio模型
     * 该方法在同一请求内会缓存，并且如果没查询到也会缓存null并且返回
     *
     * @param int $sellerId
     * @param int $countryId 可以不传，不传会用seller id去获取，如果外面有尽量传，减少一次查询
     * @return SellerProductRatio|Model||null
     */
    public function getSellerProductRatio(int $sellerId, int $countryId = 0)
    {
        if (!$sellerId) {
            return null;
        }
        $cacheKey = [__CLASS__, __FUNCTION__, $sellerId];
        $sellerProductRatio = $this->getRequestCachedData($cacheKey);
        $notCacheValue = YesNoEnum::NO;
        if ($sellerProductRatio instanceof SellerProductRatio) {
            // 如果命中或者设置了0，将不再查询，直接返回null
            return $sellerProductRatio;
        } elseif ($sellerProductRatio === $notCacheValue) {
            return null;
        }
        $sellerProductRatio = $this->getProductRatioBySellerId($sellerId);
        if (!$sellerProductRatio) {
            // 如果查不到去获取默认值
            if (!$countryId) {
                // 如果没country 获取指定seller的
                $countryId = Customer::find($sellerId, ['country_id'])->country_id;
            }
            $sellerProductRatio = $notCacheValue;// 设置一个默认值，标记查不到
            $productRatio = $this->getCountryDefaultRatio($countryId);
            if (!is_null($productRatio)) {
                // 不为null都算
                $sellerProductRatio = new SellerProductRatio([
                    'seller_id' => $sellerId,
                    'product_ratio' => $productRatio,
                    'effective_time' => $this->defaultDateTime[$countryId] ?? '',
                    // 下面的数据只是为了保持数据一致性，没有啥实际作用
                    'product_ratio_next' => null,
                    'effective_time_next' => null,
                    'create_time' => Carbon::now(),
                    'update_time' => Carbon::now(),
                ]);
            }
        }
        $this->setRequestCachedData($cacheKey, $sellerProductRatio);

        return $sellerProductRatio;
    }

    /**
     * 根据费率拆分金额，重写getDisplayPrice方法
     * @param int $sellerId
     * @param float $price
     * @param int $countryId
     * @return float 返回数据进行了四舍五入
     */
    public function calculationSellerDisplayPrice(int $sellerId, float $price, int $countryId)
    {
        $sellerProductRatio = $this->getSellerProductRatio($sellerId, $countryId);
        if ($sellerProductRatio
            && $sellerProductRatio instanceof SellerProductRatio
            && !is_null($sellerProductRatio->product_ratio)) {
            // 存在且费率不为空，0也可以
            return BCS::create($price, ['scale' => 2])->mul($sellerProductRatio->product_ratio)->getResult();
        }
        return $price;
    }

    /**
     * 获取指定seller的修改记录
     *
     * @param int $sellerId
     * @param int $countryId seller 的country id，用于转换生效时间
     * @return Collection
     */
    public function getLogsBySeller(int $sellerId, int $countryId): Collection
    {
        $logs = SellerProductRatioLog::query()->alias('log')
            ->leftJoinRelations('sellerProductRatio as spr')
            ->where('spr.seller_id', $sellerId)
            ->orderByDesc('log.create_time')
            ->get(['log.*']);
        $return = collect();
        foreach ($logs as $log) {
            $newConfig = json_decode($log->new_config, true);
            if ($newConfig) {
                $return->push(array_merge($newConfig, ['create_time' => $log->create_time->toDateTimeString()]));
            }
        }
        $defaultRatio = $this->getCountryDefaultRatio($countryId);
        if (!is_null($defaultRatio)) {
            // 创造一条假的logo
            $return->push([
                'product_ratio' => $defaultRatio,
                'service_ratio' => BCS::create(1, ['scale' => 3])->sub($defaultRatio)->getResult(),
                'effective_time' => $this->getCountryDefaultEffectiveTime($countryId),
                'create_time' => $this->getCountryDefaultEffectiveTime($countryId)
            ]);
        }
        return $return;
    }

    /**
     * 获取默认费率，不对外提供了，如果要获取seller费率请使用上面的getSellerProductRatio
     *
     * @param int $countryId
     *
     * @return mixed|null
     */
    public function getCountryDefaultRatio(int $countryId)
    {
        if ($countryId && in_array($countryId, Country::getEuropeCountries())) {
            $priceRatio = PriceSpecificSplit::query()->where('country_id', $countryId)->first();
            if ($priceRatio && $priceRatio->factor) {
                return BCS::create($priceRatio->factor, ['scale' => 3])->getResult();
            }
        }
        return null;
    }

    /**
     * 获取国家的默认费率生效时间
     *
     * @param int $countryId
     *
     * @return string
     */
    public function getCountryDefaultEffectiveTime(int $countryId): string
    {
        return $this->defaultDateTime[$countryId] ?? '';
    }
}
