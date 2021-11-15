<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Models\Margin\MarginTemplate;

/**
 * 现货
 * @property-read float $template_price 保证金货值价格，已经废弃，使用 getTemplatePrice() 代替
 */
class MarginInfo extends AbsComplexTransactionInfo implements Interfaces\TemplatePriceBuyerBasedInterface
{
    use Traits\Info\TemplatePriceBuyerBasedTrait;

    private $template;

    /**
     * 模板
     * @var int
     */
    public $day;
    /**
     * 最高售卖数量
     * @var int
     */
    public $max_num;
    /**
     * 最低售卖数量
     * @var int
     */
    public $min_num;

    public function __construct(MarginTemplate $template)
    {
        $this->template = $template;

        $this->id = $template->product_id;
        $this->max_num = $template->max_num;
        $this->min_num = $template->min_num;
        $this->day = $template->day;
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
     * 保证金货值价格
     * @return float
     */
    public function getTemplatePrice(): float
    {
        return $this->solveTemplatePriceByBuyer($this->template->price);
    }
}
