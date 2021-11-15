<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Models\Futures\FuturesContract;

/**
 * 期货
 * @property-read float $last_unit_price 转现货单价，已经废弃，使用 getLastUnitPrice() 代替
 * @property-read float $margin_unit_price 期货尾款单价，已经废弃，使用 getMarginUnitPrice() 代替
 */
class FuturesInfo extends AbsComplexTransactionInfo implements Interfaces\TemplatePriceBuyerBasedInterface
{
    use Traits\Info\TemplatePriceBuyerBasedTrait;

    private $contract;

    /**
     * @var \Illuminate\Support\Carbon|null
     */
    public $delivery_date;
    /**
     * @var int 最低购买数量,最小协议数量
     */
    public $min_num;
    /**
     * @var int 合约数量
     */
    public $num;
    /**
     * @var int 合约的交割方式 （1.支付期货协议尾款交割；2.转现货保证金进行交割；3.转现货保证金和支付尾款混合交割模式）
     */
    public $delivery_type;

    public function __construct(FuturesContract $contract)
    {
        $this->contract = $contract;

        $this->id = $contract->product_id;
        $this->delivery_date = $contract->delivery_date;
        $this->min_num = $contract->min_num;
        $this->num = $contract->num;
        $this->delivery_type = $contract->delivery_type;
    }

    public function __get($name)
    {
        // 兼容被 deprecated 的属性
        if ($name === 'last_unit_price') {
            return $this->getLastUnitPrice();
        }
        if ($name === 'margin_unit_price') {
            return $this->getMarginUnitPrice();
        }
        throw new \InvalidArgumentException("{$name} not exist");
    }

    /**
     * @inheritDoc
     */
    public function getTemplateSellerId(): int
    {
        return $this->contract->seller_id;
    }

    /**
     * 转现货单价
     * @return float
     */
    public function getLastUnitPrice(): float
    {
        return $this->solveTemplatePriceByBuyer($this->contract->last_unit_price);
    }

    /**
     * 期货尾款单价
     * @return float
     */
    public function getMarginUnitPrice(): float
    {
        return $this->solveTemplatePriceByBuyer($this->contract->margin_unit_price);
    }

    /**
     * 获取合约价格中的最低价
     * @return float
     */
    public function getContractPrice(): float
    {
        if ($this->delivery_type == 1) {
            return $this->getLastUnitPrice();
        }

        if ($this->delivery_type == 2) {
            return $this->getMarginUnitPrice();
        }

        return min($this->getMarginUnitPrice(), $this->getLastUnitPrice());
    }
}
