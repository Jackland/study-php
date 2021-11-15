<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Models\Rebate\RebateAgreementTemplate;
use App\Models\Rebate\RebateAgreementTemplateItem;
use kriss\bcmath\BC;

/**
 * 返点
 * @property-read float $template_price 模版价格，已经废弃，使用 getTemplatePrice() 代替
 */
class RebateInfo extends AbsComplexTransactionInfo implements Interfaces\TemplatePriceBuyerBasedInterface
{
    use Traits\Info\TemplatePriceBuyerBasedTrait;

    private $item;
    private $template;

    /**
     * 合同天数
     * @var int
     */
    public $day;
    /**
     * 合同限定数量
     * @var int
     */
    public $qty;

    public function __construct(RebateAgreementTemplateItem $item, RebateAgreementTemplate $template)
    {
        $this->item = $item;
        $this->template = $template;

        $this->id = $item->product_id;
        $this->day = $template->day;
        $this->qty = $template->qty;
    }

    public function __get($name)
    {
        // 兼容被 deprecated 的属性
        if ($name === 'template_price') {
            return $this->getTemplatePrice();
        }
        throw new \InvalidArgumentException("{$name} not exist");
    }

    /**
     * @inheritDoc
     */
    public function getTemplateSellerId(): int
    {
        return $this->template->seller_id;
    }

    /**
     * @inheritDoc
     */
    public function solveTemplatePriceRangeByBuyer(array $prices): array
    {
        // 返点的模版价格区间已经计算过免税价格了，因此不能重复计算
        return $prices;
    }

    /**
     * 返点的产品原价
     * @return float
     */
    public function getOriginPrice(): float
    {
        return $this->solveTemplatePriceByBuyer($this->item->price);
    }

    /**
     * 返点的模版价格（减去返点后的价格）
     * @return float
     */
    public function getTemplatePrice(): float
    {
        // 返点的免税金额=原价计算免税后-返点金额
        // $item->rest_price 为 原价-返点金额
        $price = (float)BC::create(['scale' => 2])->sub($this->getOriginPrice(), $this->item->rebate_amount);
        if ($price <= 0) {
            return 0;
        }
        return $price;
    }

    /**
     * 折扣
     * @return float
     */
    public function getDiscount(): float
    {
        $originPrice = $this->getOriginPrice();
        if ($originPrice <= 0) {
            return 0;
        }
        return $this->getTemplatePrice() / $originPrice;
    }
}
