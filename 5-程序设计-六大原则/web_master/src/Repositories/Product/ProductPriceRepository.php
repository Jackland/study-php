<?php

namespace App\Repositories\Product;

use App\Enums\Country\Country;
use App\Models\Customer\Customer;
use App\Repositories\Seller\SellerProductRatioRepository;
use kriss\bcmath\BC;
use kriss\bcmath\BCS;

class ProductPriceRepository
{
    const DE_SERVICE_STORE_ID = 838; // 服务店铺DE-SERVICE(51452839)

    /**
     * 某个用户的vat属性计算出(不)含税价和服务费， 提供给当前用户使用
     * 展示unit price时调用
     * @param int $sellerId
     * @param $price
     * @param null $serviceFee
     * @return array list(总价, 不包含服务费的(不)含税价, 服务费)
     */
    public function getProductTaxExemptionPrice(int $sellerId, $price, $serviceFee = null): array
    {
        if (!customer()->isLogged()) {
            return [$price, $price, 0];
        }

        // seller查看价格直接返回
        if (customer()->isPartner()) {
            return [$price, $price, 0];
        }

        // 非欧洲国家不需计算
        if (!in_array(customer()->getCountryId(), Country::getEuropeCountries())) {
            return [$price, $price, 0];
        }

        if ($price == 0) {
            return [$price, $price, 0];
        }

        if (is_null($serviceFee)) {
            $serviceFee = $price - app(SellerProductRatioRepository::class)
                    ->calculationSellerDisplayPrice($sellerId, $price, customer()->getCountryId());
        }

        $unitPrice = $price - $serviceFee;

        // 德国本土不做改变
        if (!customer()->isEuVatBuyer()) {
            return [$price, $unitPrice, $serviceFee];
        }

        // 服务店铺DE-SERVICE(51452839)的商品不做改变,
        if ($sellerId == self::DE_SERVICE_STORE_ID) {
            return [$price, $unitPrice, $serviceFee];
        }

        // 计算免税价
        $taxExemptionPrice = $this->generateTaxExemptionPrice($unitPrice);

        return [BC::create(['scale' => 2])->add($taxExemptionPrice, $serviceFee), $taxExemptionPrice, $serviceFee];
    }

    /**
     * 通过buyer获取实际价格
     * 不需展示unit price时，只需展示价格总和时调用
     * @param int $sellerId
     * @param Customer|int $buyer
     * @param $price
     * @return mixed|void
     */
    public function getProductActualPriceByBuyer(int $sellerId, $buyer, $price)
    {
        if ($price == 0) {
            return 0;
        }

        // 服务店铺DE-SERVICE(51452839)的商品不做改变,
        if ($sellerId == self::DE_SERVICE_STORE_ID) {
            return $price;
        }

        if (is_int($buyer)) {
            $buyer = customer()->getId() == $buyer ? customer()->getModel() : Customer::query()->find($buyer);
        }
        if (empty($buyer) || !$buyer instanceof Customer || empty($buyer->customer_id)) {
            return $price;
        }

        // 非德国欧盟不做处理
        if (!$buyer->is_eu_vat_buyer) {
            return $price;
        }

        $serviceFee = $price - app(SellerProductRatioRepository::class)
                ->calculationSellerDisplayPrice($sellerId, $price, $buyer->country_id);

        $unitPrice = $price - $serviceFee;
        $taxExemptionPrice = $this->generateTaxExemptionPrice($unitPrice);

        return BC::create(['scale' => 2])->add($taxExemptionPrice, $serviceFee);
    }

    /**
     * 生成免税价 (四舍五入保留2位小数)
     * @param $unitPrice
     * @return float|string
     */
    private function generateTaxExemptionPrice($unitPrice)
    {
        $vatRate = configDB('DEU_VAT_rate', '0.19');

        return BCS::create($unitPrice, ['scale' => 2])->div(1 + $vatRate)->getResult();
    }
}
