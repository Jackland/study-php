<?php

namespace App\Repositories\Freight;


use App\Enums\Common\YesNoEnum;
use App\Helpers\CountryHelper;
use App\Models\Freight\InternationalOrder;
use App\Models\Freight\InternationalOrderConfig;
use App\Models\Freight\InternationalOrderExtraConfig;
use App\Models\Product\Product;
use App\Services\Product\EuropeFreightService;
use DB;

/**
 * 欧洲补运费
 *
 * Class EuropeFreightRepository
 * @package App\Repositories\Freight
 */
class EuropeFreightRepository
{
    /**
     * 获取欧洲产品-到达所有国家的补运费信息
     *
     * @param int $productId 商品ID
     * @return array
     */
    public function getAllCountryFreight($productId)
    {
        $freightArr = [];
        if (empty($productId)) {
            return $freightArr;
        }

        // 获取商品计算信息 - 下架删除商品也可以计算
        $productInfo = DB::table('oc_product as p')
            ->join('oc_customerpartner_to_product as ctp', 'p.product_id', '=', 'ctp.product_id')
            ->join('oc_customer as c', 'ctp.customer_id', '=', 'c.customer_id')
            ->select('p.weight_kg', 'p.length_cm', 'p.width_cm', 'p.height_cm', 'p.product_id', 'p.freight', 'p.combo_flag', 'c.country_id')
            ->where('p.product_id', $productId)
            ->first();
        if (!$productInfo || !in_array($productInfo->country_id, [81, 222])) {
            return $freightArr;
        }

        $allCountry = $this->getAllInternational($productInfo->country_id);
        if ($allCountry->isEmpty()) {
            return $freightArr;
        }

        // 获取补运费配置
        $rule = $this->getInternationalConfig($productInfo->country_id);
        if (!$rule) {
            return $freightArr;
        }

        try {
            if ($productInfo->combo_flag) {
                $combo_info = $this->getComboInfo($productInfo->product_id);
            } else {
                $combo_info = false;
            }
        } catch (\Exception $e) {
            return [];
        }

        $data['rule'][$productInfo->country_id] = $rule;
        $data['extra_rule'] = $this->getInternationExtraConfig($productInfo->country_id);
        $data['data'] = [];
        $countryCode = CountryHelper::getCountryCodeById($productInfo->country_id);
        $temp['from'] = $countryCode;
        $temp['product_id'] = $productId;
        $temp['line_id'] = '';
        $temp['info'] = $productInfo;
        $temp['combo_info'] = $combo_info;
        foreach ($allCountry as $countryInfo) {
            $temp['to_info'] = [];
            $temp['to_info'][] = $countryInfo;

            $data['data'][] = $temp;
        }

        $freightArr = app(EuropeFreightService::class)->verifyProductRule($data)['end'];
        $result = [];
        foreach ($allCountry as $key => $countryInfo) {
            if ($freightArr[$key]['code'] == 200) {
                $templ['country_en'] = $countryInfo->country_en;
                $templ['country_code'] = $countryInfo->country_code;
                $templ['freight'] = $freightArr[$key]['freight'];
                $result[] = $templ;
            }
        }
        array_multisort(array_column($result, 'country_en'), SORT_ASC, $result);

        return $result;
    }

    /**
     * [getComboInfo description] 获取product_id 产品信息
     * @param int $product_id
     * @return array
     */
    public function getComboInfo($product_id)
    {
        return DB::table('tb_sys_product_set_info as s')
            ->where('p.product_id', $product_id)
            ->leftJoin('oc_product as p', 'p.product_id', '=', 's.product_id')
            ->leftJoin('oc_product as pc', 'pc.product_id', '=', 's.set_product_id')
            ->whereNotNull('s.set_product_id')
            ->select('s.set_product_id', 's.qty', 'pc.sku', 'pc.weight_kg', 'pc.length_cm', 'pc.width_cm', 'pc.height_cm', 'pc.freight')
            ->orderBy('pc.sku', 'asc')
            ->get()
            ->keyBy('set_product_id')
            ->toArray();
    }

    /**
     * 获取国际单配置
     *
     * @param int $countryId 国家ID
     * @return mixed
     */
    public function getInternationalConfig($countryId)
    {
        return InternationalOrderConfig::where('country_id', $countryId)
            ->where('status', YesNoEnum::YES)
            ->first();
    }

    /**
     * 获取国际单可发往国家信息
     *
     * @param int $countryId 国家ID
     * @return mixed
     */
    private function getAllInternational($countryId)
    {
        return InternationalOrder::where('country_id', $countryId)
            ->get();
    }

    /**
     * 获取国际单附加配置
     *
     * @param int $countryId 国家ID
     * @return mixed
     */
    private function getInternationExtraConfig($countryId)
    {
        return InternationalOrderExtraConfig::where('country_id', $countryId)
            ->get();
    }
}