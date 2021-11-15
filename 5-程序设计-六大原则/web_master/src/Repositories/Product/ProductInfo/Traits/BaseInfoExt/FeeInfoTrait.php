<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfoExt;

use App\Enums\Common\CountryEnum;
use App\Enums\Country\Country;
use App\Helper\CountryHelper;
use App\Repositories\Freight\EuropeFreightRepository;
use Framework\Exception\NotSupportException;
use ModelExtensionModuleEuropeFreight;

/**
 * 费用相关的：运费、打包费等
 */
trait FeeInfoTrait
{
    /**
     * 获取总运费的明细
     * @param array $config
     * @return array
     */
    public function getFulfillmentInfo(array $config = []): array
    {
        $config = array_merge([
            'noCloud' => false, // 强制不需要云送仓
        ], $config);

        if (!$this->isCustomerSet()) {
            return [];
        }
        $data = [
            'will_call' => [], // 上门取货
            'drop_ship' => [], // 一件代发
            'cloud' => [], // 云送仓
        ];
        if (!$this->isCustomerSellerSelf()) {
            // 非 seller 自己，seller 自己可以用所有形式
            if ($this->isCustomerIsWillCallBuyer()) {
                // 上门取货buyer
                unset($data['drop_ship'], $data['cloud']);
            } else {
                unset($data['will_call']);
                if ($this->getCustomerCountryId() !== CountryEnum::AMERICA) {
                    // 仅美国有云送仓
                    unset($data['cloud']);
                }
            }
        }
        if ($config['noCloud']) {
            unset($data['cloud']);
        }
        // 开始处理运费数据
        if (isset($data['will_call'])) {
            $info = [
                'shipping_fee' => $this->getFreight(),
                'package_fee' => $this->getPackageFeeWillCall(),
            ];
            $info['total'] = $info['shipping_fee'] + $info['package_fee'];
            $data['will_call'] = $info;
        }
        if (isset($data['drop_ship'])) {
            $info = [
                'shipping_fee' => $this->getFreight(),
                'package_fee' => $this->getPackageFeeDropShip(),
            ];
            $info['total'] = $info['shipping_fee'] + $info['package_fee'];
            $data['drop_ship'] = $info;
        }
        if (isset($data['cloud'])) {
            $data['cloud'] = [
                'total' => 0,
            ];
            // 参考：getNoComboFreightAndPackageFee()
            throw new NotSupportException('云送仓的业务数据比较复杂，后续补充');
        }

        return $data;
    }

    /**
     * 获取总运费区间
     * @return array [$min, $max]
     */
    public function getFulfillmentRange(array $config = []): array
    {
        $info = collect($this->getFulfillmentInfo($config));
        return [$info->min('total'), $info->max('total')];
    }

    /**
     * 获取基础运费
     * 如果设置了 customer，会自动判断类型返回运费
     * @return float
     */
    public function getFreight(): float
    {
        if ($this->isCustomerSet()) {
            if ($this->isCustomerIsWillCallBuyer()) {
                // 上门取货 buyer 运费为 0
                return 0;
            }
        }
        return $this->product->freight;
    }

    /**
     * 一件代发打包费
     * @return float
     */
    public function getPackageFeeDropShip(): float
    {
        $this->loadRelations('packageFeeDropShip');
        if (!$this->product->packageFeeDropShip) {
            return 0;
        }
        return $this->product->packageFeeDropShip->fee ?: 0;
    }

    /**
     * 上门取货打包费
     * @return float
     */
    public function getPackageFeeWillCall(): float
    {
        $this->loadRelations('packageFeeWillCall');
        if (!$this->product->packageFeeWillCall) {
            return 0;
        }
        return $this->product->packageFeeWillCall->fee ?: 0;
    }

    /**
     * 获取打包费，根据 buyer 类型自动判断
     * @return float
     */
    public function getPackageFee(): float
    {
        if ($this->isCustomerIsWillCallBuyer()) {
            return $this->getPackageFeeWillCall();
        }
        return $this->getPackageFeeDropShip();
    }

    /**
     * 获取商品到达所有欧洲国家的补运费信息
     * @return array [{country_en,country_code,freight}]
     * @throws \Exception
     */
    public function getEuropeFreight(): array
    {
        $countryId = $this->getCountryId();
        if (!in_array($countryId, Country::getEuropeCountries())) {
            return [];
        }
        $europeFreightRepo = app(EuropeFreightRepository::class);
        // 获取补运费配置
        $rule = $europeFreightRepo->getInternationalConfig($countryId);
        if (!$rule) {
            return [];
        }
        // 获取所有国家
        $allCountry = $europeFreightRepo->getAllInternational($countryId);
        if ($allCountry->isEmpty()) {
            return [];
        }
        // 构造请求数据
        $requestParams = [
            'rule' => [
                $countryId => $rule
            ],
            'extra_rule' => $europeFreightRepo->getInternationExtraConfig($countryId),
            'data' => []
        ];
        $sizeInfo = $this->getSizeInfo(['unit' => 'cm']);
        $general = [];
        $combo = false;
        if (!empty($sizeInfo['general'])) {
            $general =  json_decode(json_encode([
                'product_id' => $this->id,
                'weight_kg' => $sizeInfo['general']['weight'],
                'length_cm' => $sizeInfo['general']['length'],
                'width_cm' => $sizeInfo['general']['width'],
                'height_cm' => $sizeInfo['general']['height'],
            ]));
        } elseif (!empty($sizeInfo['combo'])) {
            foreach ($sizeInfo['combo'] as $comboItem) {
                $combo[] = json_decode(json_encode([
                    'product_id' => $comboItem['product_id'],
                    'qty' => $comboItem['qty'],
                    'weight_kg' => $comboItem['weight'],
                    'length_cm' => $comboItem['length'],
                    'width_cm' => $comboItem['width'],
                    'height_cm' => $comboItem['height'],
                ]));
            }
        }
        $requestParamsDataTemp = [
            'from' => CountryHelper::getCountryCodeById($countryId),
            'product_id' => $this->id,
            'line_id' => '',
            'info' => $general,
            'combo_info' => $combo,
        ];
        foreach ($allCountry as $countryInfo) {
            $__requestParamsDataTemp = $requestParamsDataTemp;
            $__requestParamsDataTemp['to_info'][] = $countryInfo;
            $requestParams['data'][] = $__requestParamsDataTemp;
        }

        /** @var ModelExtensionModuleEuropeFreight $europeFreightModel */
        $europeFreightModel = load()->model('extension/module/europe_freight');
        $freightArr = $europeFreightModel->verifyProductRule($requestParams)['end'];
        $result = [];
        foreach ($allCountry as $key => $countryInfo) {
            if ($freightArr[$key]['code'] == ModelExtensionModuleEuropeFreight::CODE_SUCCESS) {
                $result[] = [
                    'country_en' => $countryInfo->country_en,
                    'country_code' => $countryInfo->country_code,
                    'freight' => $freightArr[$key]['freight']];
            }
        }
        array_multisort(array_column($result, 'country_en'), SORT_ASC, $result);

        return $result;
    }
}
