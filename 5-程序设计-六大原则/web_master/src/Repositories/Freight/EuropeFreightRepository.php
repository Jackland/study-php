<?php

namespace App\Repositories\Freight;

use App\Enums\Common\YesNoEnum;
use App\Helper\CountryHelper;
use App\Models\Freight\InternationalOrder;
use App\Models\Freight\InternationalOrderConfig;
use App\Models\Freight\InternationalOrderExtraConfig;
use App\Models\Product\Product;
use ModelExtensionModuleEuropeFreight;

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
        $productInfo = Product::alias('p')
            ->join('oc_customerpartner_to_product as ctp', 'p.product_id', '=', 'ctp.product_id')
            ->join('oc_customer as c', 'ctp.customer_id', '=', 'c.customer_id')
            ->select('p.weight_kg', 'p.length_cm', 'p.width_cm', 'p.height_cm', 'p.product_id', 'p.freight', 'p.combo_flag', 'c.country_id')
            ->where('p.product_id', $productId)
            ->first();
        if (!$productInfo || !in_array($productInfo->country_id, EUROPE_COUNTRY_ID)) {
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
            /** @var ModelExtensionModuleEuropeFreight $europeFreightModel */
            $europeFreightModel = load()->model('extension/module/europe_freight');
            if ($productInfo->combo_flag) {
                $combo_info = $europeFreightModel->getComboInfo($productInfo->product_id);
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

        $freightArr = $europeFreightModel->verifyProductRule($data)['end'];
        $result = [];
        foreach ($allCountry as $key => $countryInfo) {
            if ($freightArr[$key]['code'] == ModelExtensionModuleEuropeFreight::CODE_SUCCESS) {
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
     * 获取国际单附加配置
     *
     * @param int $countryId 国家ID
     * @return mixed
     */
    public function getInternationExtraConfig($countryId)
    {
        return InternationalOrderExtraConfig::where('country_id', $countryId)
            ->get();
    }

    /**
     * 获取国际单可发往国家信息
     *
     * @param int $countryId 国家ID
     * @return mixed
     */
    public function getAllInternational($countryId)
    {
        return InternationalOrder::where('country_id', $countryId)
            ->get();
    }
}
