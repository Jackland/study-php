<?php

namespace App\Repositories\Product\ProductInfo\Traits\BaseInfoExt;

use App\Helper\CurrencyHelper;
use App\Repositories\Product\ProductPriceRepository;

trait PriceInfoTrait
{
    /**
     * 普通价格
     * @return float
     */
    public function getPrice(): float
    {
        $price = $this->product->price;
        if ($this->isCustomerSet()) {
            $price = (float)app(ProductPriceRepository::class)->getProductActualPriceByBuyer($this->getSellerId(), $this->getCustomer(), $price);
        }
        return $price;
    }

    /**
     * 精细化价格，为0表示无精细化价格
     * @return float
     */
    public function getDelicacyPrice(): float
    {
        $price = $this->delicacyPrice;
        if ($this->isCustomerSet()) {
            $price = (float)app(ProductPriceRepository::class)->getProductActualPriceByBuyer($this->getSellerId(), $this->getCustomer(), $price);
        }
        return $price;
    }

    /**
     * 未来价格，为0表示无未来价
     * @return float
     */
    public function getFuturePrice(): float
    {
        $this->loadRelations('sellerPriceNoEffect');
        if (!$this->product->sellerPriceNoEffect) {
            return 0;
        }
        $price = $this->product->sellerPriceNoEffect->new_price;
        if ($this->isCustomerSet()) {
            $price = (float)app(ProductPriceRepository::class)->getProductActualPriceByBuyer($this->getSellerId(), $this->getCustomer(), $price);
        }
        return $price;
    }

    /**
     * 未来精细化价格，为0表示无精细化未来价格
     * @return float
     */
    public function getFutureDelicacyPrice(): float
    {
        $price = $this->delicacyFuturePrice;
        if ($this->isCustomerSet()) {
            $price = (float)app(ProductPriceRepository::class)->getProductActualPriceByBuyer($this->getSellerId(), $this->getCustomer(), $price);
        }
        return $price;
    }

    /**
     * 价格是否可见
     * @return bool
     */
    public function getPriceVisible(): bool
    {
        return $this->delicacyPriceVisible;
    }

    /**
     * 获取展示的价格，有精细化则显示精细化，否则展示原价
     * @param null|array $priceRange 设置价格区间
     * @param array $config 其他配置
     * @return string
     */
    public function getShowPrice(?array $priceRange = null, array $config = []): string
    {
        $config = array_merge([
            'invisibleValue' => '**',
            'symbolSmall' => false, // 符号是否要小一号
            'rangePriceDivide' => ' - ', // 价格区间的连接符
            'forceInvisible' => false, // 强制不显示价格，比如复杂交易若未建立联系时不显示协议价格
        ], $config);

        $formatConfig = [];
        if ($config['symbolSmall']) {
            $formatConfig['symbol_options'] = ['tag' => 'small'];
        }

        if (!$this->getPriceVisible() || $config['forceInvisible'] === true) {
            $formatConfig['number_is_string'] = true;
            return CurrencyHelper::formatPrice($config['invisibleValue'], $formatConfig);
        }

        if (is_array($priceRange) && count($priceRange) == 2) {
            list($min, $max) = $priceRange;
            $min = CurrencyHelper::formatPrice($min, $formatConfig);
            $max = CurrencyHelper::formatPrice($max, $formatConfig);
            if ($min === $max) {
                return $min;
            }
            return $min . $config['rangePriceDivide'] . $max;
        }

        if ($this->getDelicacyPrice() > 0) {
            return CurrencyHelper::formatPrice($this->getDelicacyPrice(), $formatConfig);
        }

        return CurrencyHelper::formatPrice($this->getPrice(), $formatConfig);
    }
}
